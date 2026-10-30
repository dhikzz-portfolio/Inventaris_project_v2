<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Ajukan peminjaman</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih barang, tentukan jadwal, lalu kirim pengajuan Anda.</p>
            </div>
            <a href="{{ route('loans.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-brand-700 transition hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-slate-800">← Lihat riwayat</a>
        </div>
    </x-slot>

    <livewire:loan.loan-request />
</x-app-layout>
