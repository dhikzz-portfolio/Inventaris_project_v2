@php
    $user = auth()->user();
    $canManage = $user?->managesInventory();
    $canApprove = $user?->canApproveLoans();
    $isWakasek = $user?->isApproverLevel2();
@endphp

<x-menu-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="dashboard" label="Beranda" />

{{-- Peminjam memilih barang langsung di halaman peminjaman. --}}
@unless ($user?->isPeminjam())
    <x-menu-link :href="route('catalogs.index')" :active="request()->routeIs('catalogs.*') || request()->routeIs('units.*')" icon="inventory_2" label="Daftar Barang" />
@endunless

{{-- Peminjaman --}}
<x-menu-link :href="route('loans.create')" :active="request()->routeIs('loans.create')" icon="add_shopping_cart" :label="$user?->isPeminjam() ? 'Pinjam Barang' : 'Ajukan Pinjaman'" />
<x-menu-link :href="route('loans.index')" :active="request()->routeIs('loans.index') || request()->routeIs('loans.show')" icon="swap_horiz" :label="$user?->isPeminjam() ? 'Pengajuan Saya' : 'Daftar Peminjaman'" />

@if ($canApprove)
    <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Pinjaman &amp; Pengembalian</div>
    <x-menu-link :href="route('loans.pending')" :active="request()->routeIs('loans.pending')" icon="how_to_reg" label="Permintaan Pinjam" />
    <x-menu-link :href="route('loans.returns')" :active="request()->routeIs('loans.returns')" icon="assignment_return" label="Pengembalian" />
@endif

@if ($canManage || $canApprove)
    <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Barang Sekolah</div>
    {{-- Mutasi memerlukan hak kelola; Laboran hanya dapat mencetak label. --}}
    @if ($canManage)
        <x-menu-link :href="route('movements.index')" :active="request()->routeIs('movements.*')" icon="swap_horiz" label="Pindah Barang" />
    @endif
    <x-menu-link :href="route('labels.index')" :active="request()->routeIs('labels.*')" icon="label" label="Cetak Label Barang" />
@endif

@if ($canManage)
    <x-menu-link :href="route('items.create')" :active="request()->routeIs('items.create') || request()->routeIs('items.store')" icon="add_box" label="Tambah Barang" />
    <x-menu-link :href="route('write-offs.index')" :active="request()->routeIs('write-offs.*')" icon="delete_forever" label="Barang Dihapus" />
    <x-menu-link :href="route('events.index')" :active="request()->routeIs('events.*')" icon="event" label="Daftar Kegiatan" />
    <x-menu-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="assessment" label="Laporan Bulanan" />
    <x-menu-link :href="route('masters.index')" :active="request()->routeIs('masters.*')" icon="category" label="Data Sekolah" />
@endif

@if ($canApprove)
    <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Fitur Lainnya</div>
    <x-menu-link :href="route('scan')" :active="request()->routeIs('scan')" icon="qr_code_scanner" label="Pindai Kode QR" />
@endif

<div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Akun Saya</div>
<x-menu-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" icon="notifications" label="Pemberitahuan"
             :badge="$user?->unreadNotifications()->count() ?? 0" />

@if ($isWakasek)
    <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Pengelolaan Sekolah</div>
    <x-menu-link :href="route('write-offs.pending')" :active="request()->routeIs('write-offs.pending')" icon="verified_user" label="Setujui Penghapusan" />
    <x-menu-link :href="route('items.acquisitions')" :active="request()->routeIs('items.acquisitions')" icon="rule" label="Setujui Barang Baru" />
    <x-menu-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="manage_accounts" label="Akun Pengguna" />
    <x-menu-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')" icon="settings" label="Pengaturan Sekolah" />
@endif
