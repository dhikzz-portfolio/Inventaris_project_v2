<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use App\Services\BastService;
use App\Services\EventStatusService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request, EventStatusService $statusService): View
    {
        $user = $request->user();
        $status = trim((string) $request->query('status', ''));
        $today = now(config('app.timezone'))->toDateString();

        Event::query()->orderBy('id')->chunk(100, function ($events) use ($statusService): void {
            foreach ($events as $event) {
                $statusService->updateStatusIfNeeded($event);
            }
        });

        return view('events.index', [
            'events' => Event::withCount('loans')
                ->with(['creator', 'picUser', 'loans' => fn ($q) => $q->withCount('loanItems')])
                // Member hanya melihat rekap kegiatan miliknya sendiri.
                ->when($user->isMember(), fn ($query) => $query->where(function ($scope) use ($user): void {
                    $scope->where('created_by', $user->id)
                        ->orWhereHas('loans', fn ($loan) => $loan
                            ->where('borrower_id', $user->id)
                            ->orWhere('requested_by', $user->id));
                }))
                ->when(in_array($status, EventStatus::values(), true), function ($query) use ($status, $today): void {
                    match ($status) {
                        EventStatus::BELUM_BERJALAN->value => $query->whereDate('start_date', '>', $today),
                        EventStatus::SEDANG_BERJALAN->value => $query->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today),
                        EventStatus::SUDAH_BERJALAN->value => $query->whereDate('end_date', '<', $today),
                    };
                })
                ->latest('start_date')
                ->paginate(12)
                ->withQueryString(),
            'statuses' => EventStatus::cases(),
            'activeStatus' => $status,
            'picUsers' => $this->picUsers(),
        ]);
    }

    /**
     * Guru, staf, dan pengelola inventaris boleh mencatat kegiatan sekolah.
     * Pencatatan kegiatan tidak memberikan akses pengelolaan inventaris.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Siswa/peminjam biasa hanya melihat rekap; pencatatan kegiatan
        // diperuntukkan bagi guru, staf, Operator, dan pengelola inventaris.
        abort_unless($user->canRecordActivities() || $user->managesInventory() || $user->isOperator(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pic_mode' => ['nullable', Rule::in(['user', 'custom'])],
            'pic_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $pic = $this->resolvePic($data, $request);

        $event = Event::create([
            'name' => $data['name'],
            'pic_name' => $pic['pic_name'],
            'pic_user_id' => $pic['pic_user_id'],
            'description' => $data['description'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => app(EventStatusService::class)->calculateStatus(new Event(['start_date' => $data['start_date'], 'end_date' => $data['end_date']]))->value,
            'created_by' => $request->user()->id,
        ]);

        activity('kegiatan')->performedOn($event)->causedBy($request->user())->log('Membuat kegiatan baru');

        return redirect()->route('events.show', $event)
            ->with('success', 'Kegiatan dibuat. Status dihitung otomatis berdasarkan tanggal pelaksanaan.');
    }

    /**
     * Perbarui kegiatan. Guru/staf hanya boleh mengubah kegiatan miliknya;
     * pengelola inventaris boleh memantau seluruh kegiatan. Perubahan status
     * kegiatan tidak mengubah status persetujuan peminjaman terkait.
     */
    public function update(Request $request, Event $event): RedirectResponse
    {
        abort_unless($event->isManageableBy($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pic_mode' => ['nullable', Rule::in(['user', 'custom'])],
            'pic_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $pic = $this->resolvePic($data, $request);
        $before = $event->status?->value;
        unset($data['status'], $data['pic_mode']);
        $data['pic_name'] = $pic['pic_name'];
        $data['pic_user_id'] = $pic['pic_user_id'];
        $event->fill($data + ['updated_by' => $request->user()->id]);
        $event->status = app(EventStatusService::class)->calculateStatus($event);
        $event->save();

        activity('kegiatan')
            ->performedOn($event)
            ->causedBy($request->user())
            ->withProperties(['status_sebelum' => $before, 'status_sesudah' => $event->status?->value])
            ->log('Memperbarui kegiatan');

        return back()->with('success', 'Kegiatan diperbarui. Status dihitung otomatis berdasarkan tanggal pelaksanaan.');
    }

    public function show(Event $event, EventStatusService $statusService): View
    {
        $statusService->updateStatusIfNeeded($event);
        $event->load(['loans.borrower', 'loans.loanItems.item', 'loans.loanItems.unitAssignments.unit', 'creator', 'editor', 'picUser']);

        return view('events.show', [
            'event' => $event,
            'bookedItems' => collect($event->bookedItemIds()),
            'statuses' => EventStatus::cases(),
            'picUsers' => $this->picUsers(),
        ]);
    }

    /**
     * Hapus kegiatan. Pembuat atau Admin boleh menghapus, dan kegiatan
     * yang sudah memiliki transaksi peminjaman selalu ditolak meskipun request
     * dikirim langsung ke API/route.
     */
    public function destroy(Request $request, Event $event): RedirectResponse
    {
        $user = $request->user();
        $isOwner = (int) $event->created_by === (int) $user->id;

        abort_unless($isOwner || $user->isAdmin(), 403,
            'Hanya pembuat kegiatan atau Admin yang dapat menghapus kegiatan ini.');

        if ($event->hasLoans()) {
            return back()->with('error', 'Kegiatan tidak dapat dihapus karena telah memiliki riwayat transaksi peminjaman.');
        }

        // Keputusan akhir tetap di backend melalui policy (bukan hanya UI).
        Gate::authorize('delete', $event);

        $name = $event->name;

        activity('kegiatan')
            ->performedOn($event)
            ->causedBy($request->user())
            ->log('Menghapus kegiatan '.$name);

        $event->delete();

        return redirect()->route('events.index')
            ->with('success', "Kegiatan \"{$name}\" dihapus.");
    }

    /**
     * Tentukan penanggung jawab: akun pengguna terdaftar atau nama khusus.
     * Tidak ada akun pengguna palsu yang dibuat untuk nama organisasi/panitia.
     *
     * @param  array<string, mixed>  $data
     * @return array{pic_name: string, pic_user_id: int|null}
     */
    private function resolvePic(array $data, Request $request): array
    {
        $mode = (string) ($data['pic_mode'] ?? ($request->filled('pic_user_id') ? 'user' : 'custom'));

        if ($mode === 'user') {
            $user = User::query()->find($data['pic_user_id'] ?? null);

            if ($user === null) {
                abort(422, 'Pilih akun pengguna penanggung jawab atau gunakan nama khusus.');
            }

            return ['pic_name' => $user->displayName(), 'pic_user_id' => (int) $user->id];
        }

        $name = trim((string) ($data['pic_name'] ?? ''));

        if ($name === '') {
            abort(422, 'Nama penanggung jawab wajib diisi.');
        }

        return ['pic_name' => $name, 'pic_user_id' => null];
    }

    /** @return Collection<int, User> */
    private function picUsers()
    {
        return User::query()->orderBy('name')->get(['id', 'name', 'degree', 'role']);
    }

    public function downloadBast(Request $request, Event $event, BastService $bast)
    {
        $path = $bast->generateForEvent($event);

        if (! $path) {
            return back()->with('error', 'Belum ada peminjaman aktif pada kegiatan ini untuk dibuatkan BAST.');
        }

        return \Storage::disk('local')->download($path, 'BAST-KEGIATAN-'.$event->id.'.pdf');
    }
}
