<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Ajukan peminjaman</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih barang, tentukan jadwal, lalu kirim pengajuan Anda.</p>
            </div>
            <a href="{{ route('loans.index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">← Lihat riwayat</a>
        </div>
    </x-slot>

    <livewire:loan.loan-request />
</x-app-layout>
