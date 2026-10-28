<?php

namespace App\Http\Controllers;

use App\Enums\Condition;
use App\Enums\Decision;
use App\Enums\DocumentStatus;
use App\Enums\LoanStatus;
use App\Enums\UnitStatus;
use App\Exceptions\AvailabilityException;
use App\Models\AssetUnit;
use App\Models\InstitutionSetting;
use App\Models\Loan;
use App\Models\LoanItem;
use App\Models\LoanItemUnit;
use App\Services\BastService;
use App\Services\LoanService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoanController extends Controller
{
    /** Scope visibilitas peminjaman sesuai role (dipakai juga untuk mencegah IDOR). */
    private function scopeFor(Request $request)
    {
        return Loan::query()->visibleTo($request->user());
    }

    /**
     * Boleh memproses pinjaman? Laboran hanya untuk unit dalam jurusannya,
     * dan SELURUH item harus berada dalam cakupan (bukan sebagian).
     */
    private function canActOn(Loan $loan, Request $request): bool
    {
        return $loan->canBeManagedBy($request->user());
    }

    // ================= AJUKAN (fallback non-Livewire) =================

    public function create(): View
    {
        return view('loans.create');
    }

    public function store(Request $request, LoanService $service): RedirectResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'string', 'min:3', 'max:500'],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after:start_date'],
            'event_id' => ['nullable', 'exists:events,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:item_catalogs,id'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $loan = $service->requestLoan(
                $request->user(),
                $data['lines'],
                $data['purpose'],
                $data['start_date'],
                $data['due_date'],
                $data['event_id'] ?? null
            );
        } catch (AvailabilityException $e) {
            return back()->withErrors(['lines' => $e->getMessage()])->withInput();
        }

        $message = match (true) {
            $loan->status !== LoanStatus::APPROVED => "Request peminjaman #{$loan->id} terkirim — menunggu persetujuan.",
            $request->user()->isApproverLevel2() => "Request peminjaman #{$loan->id} disetujui atas kewenangan Wakasek Sarpras — lanjutkan ke serah terima.",
            default => "Request peminjaman #{$loan->id} disetujui otomatis untuk laboratorium yang Anda tugaskan — lanjutkan ke serah terima.",
        };

        return redirect()->route('loans.show', $loan)->with('success', $message);
    }

    // ================= DAFTAR =================

    public function index(Request $request): View
    {
        $status = $request->query('status');

        return view('loans.index', [
            'loans' => $this->scopeFor($request)
                ->with(['borrower', 'event', 'loanItems.item'])
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->paginate(12),
            'status' => $status,
            'counts' => [
                'all' => $this->scopeFor($request)->count(),
                'PENDING' => $this->scopeFor($request)->where('status', 'PENDING')->count(),
                'APPROVED' => $this->scopeFor($request)->where('status', 'APPROVED')->count(),
                'DIPINJAM' => $this->scopeFor($request)->where('status', 'DIPINJAM')->count(),
                'SELESAI' => $this->scopeFor($request)->where('status', 'SELESAI')->count(),
                'DIBATALKAN' => $this->scopeFor($request)->where('status', 'DIBATALKAN')->count(),
            ],
        ]);
    }

    public function show(Request $request, Loan $loan): View
    {
        abort_unless($loan->canBeViewedBy($request->user()), 403);

        $loan->load([
            'loanItems.item.room', 'borrower.major', 'event',
            'approvals.approver', 'returner', 'handoverOfficer', 'canceller',
        ]);

        $canManage = $this->canActOn($loan, $request);
        $nextLevel = $loan->nextApprovalLevelFor($request->user());

        return view('loans.show', [
            'loan' => $loan,
            'nextLevel' => $nextLevel,
            'canAct' => $loan->isPending() && $canManage && $nextLevel !== null,
            'canManage' => $canManage,
            'canCancel' => $loan->borrower_id === $request->user()->id
                && in_array($loan->status, [LoanStatus::PENDING, LoanStatus::APPROVED], true)
                && $loan->handed_over_at === null,
            'hasShortage' => $loan->loanItems->contains(fn ($li) => $li->hasShortage()),
            'canManageDocuments' => $request->user()->canManageLoanDocuments(),
        ]);
    }

    // ================= PERSETUJUAN (Berjenjang + TTD Digital) =================

    public function pending(Request $request): View
    {
        return view('loans.pending', [
            'loans' => $this->scopeFor($request)
                ->where('status', LoanStatus::PENDING->value)
                ->with(['borrower.major', 'event', 'loanItems.item.room.major'])
                ->latest()
                ->get(),
        ]);
    }

    public function approve(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($this->canActOn($loan, $request), 403);

        if (! $loan->isPending()) {
            return back()->withErrors(['decision' => $this->statusMessage($loan)]);
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'signature' => ['nullable', 'string'],
        ]);

        // Tanda tangan digital bersifat opsional: dokumen resmi dapat dicetak
        // dan ditandatangani secara fisik/manual.
        $signaturePath = $this->storeSignature($data['signature'] ?? null, $loan);

        try {
            $service->decide($loan, $request->user(), Decision::APPROVED, $data['note'] ?? null, $signaturePath);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('success', 'Peminjaman disetujui.');
    }

    public function reject(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($this->canActOn($loan, $request), 403);

        if (! $loan->isPending()) {
            return back()->withErrors(['decision' => $this->statusMessage($loan)]);
        }

        $data = $request->validate([
            'note' => ['required', 'string', 'max:500'],
            'signature' => ['nullable', 'string'],
        ]);

        $signaturePath = $this->storeSignature($data['signature'] ?? null, $loan);

        try {
            $service->decide($loan, $request->user(), Decision::REJECTED, $data['note'], $signaturePath);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('success', 'Peminjaman ditolak.');
    }

    /** Pesan status yang jelas untuk antarmuka (bukan halaman exception). */
    private function statusMessage(Loan $loan): string
    {
        return match ($loan->status) {
            LoanStatus::PENDING => 'Peminjaman masih menunggu persetujuan yang diwajibkan.',
            LoanStatus::APPROVED => 'Peminjaman sudah disetujui dan siap diserahterimakan.',
            LoanStatus::DIPINJAM => 'Barang sudah diserahkan pada peminjaman ini.',
            LoanStatus::SELESAI => 'Peminjaman sudah selesai.',
            LoanStatus::DITOLAK => 'Peminjaman sudah ditolak.',
            LoanStatus::DIBATALKAN => 'Peminjaman sudah dibatalkan.',
            default => 'Status peminjaman tidak dapat diproses.',
        };
    }

    /** Simpan gambar TTD digital (dataURL base64) ke storage. */
    private function storeSignature(?string $dataUrl, Loan $loan): ?string
    {
        if (! $dataUrl || ! preg_match('#^data:image/(png|jpeg);base64,#', $dataUrl, $m)) {
            return null;
        }

        $binary = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
        if ($binary === false) {
            return null;
        }

        $ext = $m[1] === 'jpeg' ? 'jpg' : 'png';
        $path = sprintf('signatures/loan-%d-L%d-%s.%s', $loan->id, $loan->approvals()->max('level') + 1 ?? 1, now()->format('YmdHis'), $ext);
        \Storage::disk('local')->put($path, $binary);

        return $path;
    }

    // ================= PENGEMBALIAN =================

    public function returns(Request $request): View
    {
        return view('loans.returns', [
            'loans' => $this->scopeFor($request)
                ->whereIn('status', [LoanStatus::APPROVED->value, LoanStatus::DIPINJAM->value])
                ->with(['borrower', 'event', 'loanItems.item'])
                ->orderBy('due_date')
                ->paginate(12),
        ]);
    }

    public function returnForm(Request $request, Loan $loan): View|RedirectResponse
    {
        abort_unless($this->canActOn($loan, $request), 403);

        if (! in_array($loan->status, [LoanStatus::APPROVED, LoanStatus::DIPINJAM], true)) {
            return redirect()->route('loans.show', $loan)
                ->withErrors(['return' => $this->statusMessage($loan).' Pengembalian hanya dapat diproses untuk peminjaman yang disetujui atau sedang dipinjam.']);
        }

        $loan->load(['loanItems.item.room', 'borrower']);

        return view('loans.return_form', ['loan' => $loan]);
    }

    public function handoverForm(Request $request, Loan $loan): View|RedirectResponse
    {
        abort_unless($this->canActOn($loan, $request), 403);

        // Status yang belum memenuhi syarat menghasilkan pesan yang jelas di
        // antarmuka, bukan halaman exception mentah.
        $blocker = $loan->handoverBlocker();

        if ($blocker !== null) {
            return redirect()->route('loans.show', $loan)
                ->withErrors(['handover' => $blocker.' Serah terima hanya dapat dilakukan setelah persetujuan yang diwajibkan terpenuhi.']);
        }

        $loan->load(['loanItems.item.room.units', 'borrower']);

        return view('loans.handover_form', [
            'loan' => $loan,
            'availableUnits' => $this->availableUnitsForHandover($loan, $request),
        ]);
    }

    /**
     * Unit yang dapat diserahkan untuk setiap baris peminjaman.
     * Hanya unit dalam cakupan petugas, aktif, kondisi baik, dan belum dipinjam.
     *
     * @return array<int, array{item_id: int, item: Item, room: Room|null, qty_borrowed: int, units: Collection<int, AssetUnit>}>
     */
    private function availableUnitsForHandover(Loan $loan, Request $request): array
    {
        $user = $request->user();

        // Jangan memakai toArray(): model & koleksi unit harus tetap berupa objek
        // agar template dapat memakai $item->name dan $units->isEmpty().
        return $loan->loanItems->mapWithKeys(fn (LoanItem $li) => [
            $li->id => [
                'item_id' => $li->item_id,
                'item' => $li->item,
                'room' => $li->item?->room,
                'qty_borrowed' => (int) $li->qty_borrowed,
                'units' => AssetUnit::visibleTo($user)
                    ->where('item_catalog_id', $li->item_id)
                    ->where('status', UnitStatus::AKTIF->value)
                    ->where('condition', Condition::BAIK->value)
                    ->whereNotIn('id', function ($q) {
                        $q->select('asset_unit_id')
                            ->from((new LoanItemUnit)->getTable())
                            ->whereNull('returned_at');
                    })
                    ->orderBy('inventory_code')
                    ->get(),
            ],
        ])->all();
    }

    /** Fallback non-Livewire untuk pengembalian tervalidasi. */
    public function processReturn(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($this->canActOn($loan, $request), 403);

        if (! in_array($loan->status, [LoanStatus::APPROVED, LoanStatus::DIPINJAM], true)) {
            return back()->withErrors(['rows' => $this->statusMessage($loan).' Pengembalian hanya dapat diproses untuk peminjaman yang disetujui atau sedang dipinjam.']);
        }

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.loan_item_id' => ['required', 'integer'],
            'rows.*.qty_returned' => ['required', 'integer', 'min:0'],
            'rows.*.condition_in' => ['required', 'in:BAIK,RUSAK_RINGAN,RUSAK_BERAT'],
            'rows.*.returned_room_id' => ['required', 'exists:rooms,id'],
            'rows.*.damage_photo' => ['nullable', 'image', 'max:4096'],
            'rows.*.return_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->processReturn($loan, $request->user(), $data['rows']);

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Pengembalian diproses.');
    }

    /** Selesaikan pengembalian setelah backend memeriksa ulang seluruh unit. */
    public function finishReturn(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($this->canActOn($loan, $request), 403);

        try {
            $service->completeReturn($loan, $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['finish' => $e->getMessage()]);
        }

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Pengembalian selesai — seluruh unit telah dikonfirmasi.');
    }

    /** Wakasek menutup pinjaman dengan selisih (barang tetap berflag Hilang). */
    public function closeShortage(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($this->canActOn($loan, $request) && $request->user()->isApproverLevel2(), 403);

        try {
            $service->closeWithShortage($loan, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('success', 'Pinjaman ditutup — selisih tercatat sebagai barang Hilang beserta riwayat peminjam terakhir.');
    }

    public function handover(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($this->canActOn($loan, $request), 403);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'unit_assignments' => ['nullable', 'array'],
            'unit_assignments.*' => ['array'],
            'unit_assignments.*.loan_item_id' => ['nullable', 'integer', 'exists:loan_items,id'],
            'unit_assignments.*.item_id' => ['nullable', 'integer', 'exists:item_catalogs,id'],
            'unit_assignments.*.unit_ids' => ['present', 'array'],
            'unit_assignments.*.unit_ids.*' => ['required', 'integer', 'exists:asset_units,id'],
        ]);

        $unitAssignments = $this->normalizeUnitAssignments($loan, $data['unit_assignments'] ?? []);

        try {
            $service->handover($loan, $request->user(), $unitAssignments, $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['handover' => $e->getMessage()]);
        }

        return back()->with('success', 'Barang diserahterimakan — status peminjaman kini Sedang Dipinjam.');
    }

    // ================= STATUS DOKUMEN =================

    /** Dokumen dicetak (cetak fisik). */
    public function markDocumentPrinted(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        return $this->updateDocumentStatus($request, $loan, $service, DocumentStatus::DICETAK);
    }

    /** Dokumen sudah ditandatangani secara fisik/manual oleh pihak terkait. */
    public function markDocumentSignedPhysical(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        return $this->updateDocumentStatus($request, $loan, $service, DocumentStatus::DITANDATANGANI_FISIK);
    }

    /** Dokumen sudah ditandatangani secara digital (hanya bila ada TTD digital tersimpan). */
    public function markDocumentSignedDigital(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        return $this->updateDocumentStatus($request, $loan, $service, DocumentStatus::DITANDATANGANI_DIGITAL);
    }

    /**
     * Status dokumen terpisah dari status transaksi: mencatat cetak/tanda tangan
     * tidak pernah mengubah kelayakan transaksi peminjaman.
     */
    private function updateDocumentStatus(Request $request, Loan $loan, LoanService $service, DocumentStatus $status): RedirectResponse
    {
        abort_unless($loan->canBeViewedBy($request->user()), 403);
        abort_unless($request->user()->canManageLoanDocuments(), 403);

        try {
            $service->markDocumentStatus($loan, $request->user(), $status);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        return back()->with('success', 'Status dokumen diperbarui: '.$status->label().'.');
    }

    /**
     * Samakan bentuk payload penugasan unit menjadi identitas baris peminjaman
     * yang sudah terverifikasi. Kunci array dipakai sebagai identitas baris
     * peminjaman (loan_items.id) agar klien tidak perlu mengirim ulang data
     * katalog yang sudah diketahui server.
     *
     * @param  array<int|string, array<string, mixed>>  $assignments
     * @return array<int, array{loan_item_id: int, item_id: int, unit_ids: list<int>}>
     */
    private function normalizeUnitAssignments(Loan $loan, array $assignments): array
    {
        if ($assignments === []) {
            return [];
        }

        $loanItems = $loan->loanItems()->get();
        $byId = $loanItems->keyBy('id');
        $byItemId = $loanItems->groupBy('item_id');

        $normalized = [];
        $previouslyAssigned = [];

        foreach ($assignments as $key => $assignment) {
            $unitIds = array_values(array_unique(array_map('intval', (array) ($assignment['unit_ids'] ?? []))));
            if ($unitIds === []) {
                continue;
            }

            $loanItem = null;
            $candidate = (int) ($assignment['loan_item_id'] ?? 0);
            if ($candidate > 0) {
                $loanItem = $byId->get($candidate);
            }

            if ($loanItem === null) {
                $itemId = (int) ($assignment['item_id'] ?? 0);
                if ($itemId > 0) {
                    $loanItem = $byItemId->get($itemId)?->first();
                }
            }

            if ($loanItem === null) {
                $keyId = (int) $key;
                $loanItem = $byId->get($keyId) ?? $byItemId->get($keyId)?->first();
            }

            if ($loanItem === null) {
                throw ValidationException::withMessages([
                    'handover' => 'Baris peminjaman untuk unit yang dipilih tidak ditemukan.',
                ]);
            }

            if (count($unitIds) > (int) $loanItem->qty_borrowed) {
                throw ValidationException::withMessages([
                    'handover' => 'Jumlah unit yang dipilih melebihi jumlah yang dipinjam pada baris ini.',
                ]);
            }

            // Satu unit fisik tidak boleh dipilih pada dua baris sekaligus.
            if (array_intersect($unitIds, $previouslyAssigned) !== []) {
                throw ValidationException::withMessages([
                    'handover' => 'Unit yang sama tidak boleh dipilih lebih dari sekali.',
                ]);
            }

            $previouslyAssigned = array_merge($previouslyAssigned, $unitIds);

            $normalized[] = [
                'loan_item_id' => (int) $loanItem->id,
                'item_id' => (int) $loanItem->item_id,
                'unit_ids' => $unitIds,
            ];
        }

        return $normalized;
    }

    public function cancel(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($loan->canBeViewedBy($request->user()), 403);

        $reason = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:500']])['cancel_reason'] ?? null;

        try {
            $service->cancel($loan, $request->user(), $reason);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return back()->with('success', 'Peminjaman dibatalkan dan jadwalnya tidak lagi memblokir ketersediaan.');
    }

    /**
     * Pratinjau surat sebelum dicetak — tanpa mewajibkan unduh PDF.
     * Hak akses sama dengan detail transaksi (peminjam hanya miliknya).
     */
    public function previewLetter(Request $request, Loan $loan): View
    {
        abort_unless($loan->canBeViewedBy($request->user()), 403);

        $loan->load([
            'loanItems.item.category',
            'loanItems.item.room',
            'loanItems.unitAssignments.unit',
            'borrower.major',
            'event',
            'approvals.approver',
            'handoverOfficer',
        ]);

        $bast = app(BastService::class);

        return view('loans.letter_preview', [
            'loan' => $loan,
            'setting' => InstitutionSetting::current(),
            'title' => $bast->documentTitle($loan),
            'documentNumber' => $bast->documentNumber($loan),
        ]);
    }

    public function downloadBast(Request $request, Loan $loan)
    {
        abort_unless($loan->canBeViewedBy($request->user()), 403);

        // Selalu generate ulang agar BAST memuat tanda tangan/approval terbaru.
        $path = app(BastService::class)->generateForLoan($loan);

        $filename = $loan->event_id
            ? 'BAST-'.$loan->id.'.pdf'
            : 'Surat-Peminjaman-'.$loan->id.'.pdf';

        return \Storage::disk('local')->download($path, $filename);
    }
}
