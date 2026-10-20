<?php

namespace App\Services;

use App\Models\Event;
use App\Models\InstitutionSetting;
use App\Models\Loan;
use Barryvdh\DomPDF\Facade\Pdf;

class BastService
{
    /**
     * Generate PDF Surat Peminjaman Resmi / BAST untuk satu peminjaman,
     * simpan ke storage dan simpan path-nya di loan.
     */
    public function generateForLoan(Loan $loan): ?string
    {
        $loan->load(['loanItems.item.category', 'borrower', 'event', 'approvals.approver']);
        $setting = InstitutionSetting::current();

        $filename = sprintf('BAST-%s-%s.pdf', 'PINJAM'.$loan->id, now()->format('YmdHis'));
        $path = 'bast/'.$filename;

        $pdf = Pdf::loadView('pdf.bast', [
            'loan' => $loan,
            'setting' => $setting,
            'title' => $loan->event_id ? 'BERITA ACARA SERAH TERIMA (BAST)' : 'SURAT PEMINJAMAN BARANG',
        ])->setPaper('a4');

        \Storage::disk('local')->put($path, $pdf->output());

        if ($loan->bast_path && $loan->bast_path !== $path) {
            \Storage::disk('local')->delete($loan->bast_path);
        }

        $loan->forceFill(['bast_path' => $path])->saveQuietly();

        activity('dokumen')
            ->performedOn($loan)
            ->causedBy(auth()->user())
            ->log('Membuat BAST/Surat Peminjaman PDF');

        return $path;
    }

    /**
     * Generate satu BAST gabungan untuk seluruh peminjaman dalam satu kegiatan.
     */
    public function generateForEvent(Event $event): ?string
    {
        $event->load(['loans.loanItems.item.category', 'loans.borrower', 'loans.approvals.approver']);

        $activeLoans = $event->loans->filter(
            fn (Loan $loan) => $loan->bast_path !== null || in_array($loan->status->value, ['APPROVED', 'DIPINJAM', 'SELESAI'], true)
        );

        if ($activeLoans->isEmpty()) {
            return null;
        }

        $setting = InstitutionSetting::current();
        $filename = sprintf('BAST-KEGIATAN-%s-%s.pdf', preg_replace('/[^A-Za-z0-9]/', '-', $event->name), now()->format('YmdHis'));
        $path = 'bast/'.$filename;

        $pdf = Pdf::loadView('pdf.bast_event', [
            'event' => $event,
            'loans' => $activeLoans->values(),
            'setting' => $setting,
        ])->setPaper('a4', 'landscape');

        \Storage::disk('local')->put($path, $pdf->output());

        activity('dokumen')
            ->performedOn($event)
            ->causedBy(auth()->user())
            ->log('Membuat BAST gabungan kegiatan');

        return $path;
    }
}
