<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold leading-tight text-slate-900 dark:text-white">Ajukan Peminjaman</h2>
                <p class="text-sm text-slate-500 dark:text-slate-400">Pilih barang, tentukan jadwal, dan periksa ketersediaan sebelum mengirim.</p>
            </div>
            <a href="{{ route('loans.index') }}" class="inline-flex min-h-10 items-center rounded-lg px-3 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 dark:text-blue-300 dark:hover:bg-slate-800">← Riwayat peminjaman</a>
        </div>
    </x-slot>

    <livewire:loan.loan-request />
</x-app-layout>
