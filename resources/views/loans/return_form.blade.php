<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3 flex-wrap">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Form Pengembalian</h2>
                    <span class="text-[11px] font-bold px-2 py-1 rounded-full {{ $loan->status->badgeClass() }}">{{ $loan->status->label() }}</span>
                </div>
                <p class="text-sm text-gray-500">Pinjaman #{{ $loan->id }} — {{ $loan->purpose }}</p>
            </div>
            <a href="{{ route('loans.returns') }}" class="text-sm text-gray-500 hover:text-gray-800">← Daftar pengembalian</a>
        </div>
    </x-slot>

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">{{ $errors->first() }}</div>
    @endif

    <livewire:loan.return-form :loan="$loan" />
</x-app-layout>
