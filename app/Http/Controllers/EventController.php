<?php

namespace App\Http\Controllers;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Services\BastService;
use App\Services\EventStatusService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(Request $request, EventStatusService $statusService): View
    {
        $status = trim((string) $request->query('status', ''));
        $today = now(config('app.timezone'))->toDateString();

        Event::query()->orderBy('id')->chunk(100, function ($events) use ($statusService): void {
            foreach ($events as $event) {
                $statusService->updateStatusIfNeeded($event);
            }
        });

        return view('events.index', [
            'events' => Event::withCount('loans')
                ->with(['creator', 'loans' => fn ($q) => $q->withCount('loanItems')])
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
        // diperuntukkan bagi guru, staf, dan pengelola inventaris.
        abort_unless($user->canRecordActivities() || $user->managesInventory(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'pic_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $event = Event::create([
            'name' => $data['name'],
            'pic_name' => $data['pic_name'],
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
            'pic_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $before = $event->status?->value;
        unset($data['status']);
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
        $event->load(['loans.borrower', 'loans.loanItems.item', 'loans.loanItems.unitAssignments.unit', 'creator', 'editor']);

        return view('events.show', [
            'event' => $event,
            'bookedItems' => collect($event->bookedItemIds()),
            'statuses' => EventStatus::cases(),
        ]);
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
