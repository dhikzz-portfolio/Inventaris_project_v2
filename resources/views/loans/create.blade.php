<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Ajukan Peminjaman</h2>
                <p class="text-sm text-gray-500">Keranjang multi-item — cek kalender ketersediaan anti-bentrok sebelum kirim</p>
            </div>
            <a href="{{ route('loans.index') }}" class="text-sm text-gray-500 hover:text-gray-800">← Riwayat peminjaman</a>
        </div>
    </x-slot>

    <livewire:loan.loan-request />
</x-app-layout>
