<?php

namespace App\Http\Controllers;

use App\Enums\Decision;
use App\Enums\LoanStatus;
use App\Exceptions\AvailabilityException;
use App\Models\Loan;
use App\Services\BastService;
use App\Services\LoanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $user = $request->user();

        if ($user->managesInventory()) {
            return true;
        }

        if ($user->isLaboran()) {
            if ($user->major_id === null) {
                return false;
            }

            $items = $loan->loanItems()->with('item.room')->get();

            return $items->isNotEmpty() && $items->every(
                fn ($li) => (int) $li->item?->room?->major_id === (int) $user->major_id
            );
        }

        return false;
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
            'lines.*.item_id' => ['required', 'exists:items,id'],
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

        return redirect()->route('loans.show', $loan)
            ->with('success', "Request peminjaman #{$loan->id} terkirim.");
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

        return view('loans.show', [
            'loan' => $loan,
            'nextLevel' => $loan->nextApprovalLevel(),
            'canAct' => $loan->isPending() && $canManage,
            'canManage' => $canManage,
            'canCancel' => $loan->borrower_id === $request->user()->id
                && in_array($loan->status, [LoanStatus::PENDING, LoanStatus::APPROVED], true)
                && $loan->handed_over_at === null,
            'hasShortage' => $loan->loanItems->contains(fn ($li) => $li->hasShortage()),
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
        abort_unless($loan->isPending(), 422, 'Peminjaman tidak dalam status menunggu persetujuan.');
        abort_unless($this->canActOn($loan, $request), 403);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
            'signature' => ['nullable', 'string'],
        ]);

        $signaturePath = $this->storeSignature($data['signature'] ?? null, $loan);

        // TTD digital wajib untuk approval level 2 (barang bernilai tinggi).
        if ($loan->nextApprovalLevel() === 2 && ! $signaturePath) {
            return back()->withErrors(['signature' => 'Tanda tangan digital wajib untuk persetujuan barang bernilai tinggi.']);
        }

        try {
            $service->decide($loan, $request->user(), Decision::APPROVED, $data['note'] ?? null, $signaturePath);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['decision' => $e->getMessage()]);
        }

        return back()->with('success', 'Peminjaman disetujui.');
    }

    public function reject(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($loan->isPending(), 422);
        abort_unless($this->canActOn($loan, $request), 403);

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

    public function returnForm(Request $request, Loan $loan): View
    {
        abort_unless($this->canActOn($loan, $request), 403);
        abort_unless(in_array($loan->status, [LoanStatus::APPROVED, LoanStatus::DIPINJAM], true), 422, 'Peminjaman tidak dalam status dapat dikembalikan.');

        $loan->load(['loanItems.item.room', 'borrower']);

        return view('loans.return_form', ['loan' => $loan]);
    }

    /** Fallback non-Livewire untuk pengembalian tervalidasi. */
    public function processReturn(Request $request, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($this->canActOn($loan, $request), 403);
        abort_unless(in_array($loan->status, [LoanStatus::APPROVED, LoanStatus::DIPINJAM], true), 422);

        $data = $request->validate([
            'rows' => ['required', 'array'],
            'rows.*.loan_item_id' => ['required', 'integer'],
            'rows.*.qty_returned' => ['required', 'integer', 'min:0'],
            'rows.*.condition_in' => ['required', 'in:BAIK,RUSAK_RINGAN,RUSAK_BERAT'],
            'rows.*.returned_room_id' => ['required', 'exists:rooms,id'],
            'rows.*.damage_photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $service->processReturn($loan, $request->user(), $data['rows']);

        return redirect()->route('loans.show', $loan)
            ->with('success', 'Pengembalian diproses.');
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

        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;

        try {
            $service->handover($loan, $request->user(), $note);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['handover' => $e->getMessage()]);
        }

        return back()->with('success', 'Barang diserahterimakan — status peminjaman kini Sedang Dipinjam.');
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
