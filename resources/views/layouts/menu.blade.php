@php
    $user = auth()->user();
    $canManage = $user?->managesInventory();
    $canApprove = $user?->canApproveLoans();
    $isWakasek = $user?->isApproverLevel2();
@endphp

<x-menu-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="dashboard" label="Dashboard" />

{{-- Katalog untuk semua role --}}
<x-menu-link :href="route('catalogs.index')" :active="request()->routeIs('catalogs.*') || request()->routeIs('units.*')" icon="inventory_2" label="Katalog Barang" />

{{-- Peminjaman --}}
<x-menu-link :href="route('loans.create')" :active="request()->routeIs('loans.create')" icon="add_shopping_cart" label="Ajukan Peminjaman" />
<x-menu-link :href="route('loans.index')" :active="request()->routeIs('loans.index') || request()->routeIs('loans.show')" icon="swap_horiz" label="Peminjaman Saya" />

@if ($canApprove)
    <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Persetujuan &amp; Pengembalian</div>
    <x-menu-link :href="route('loans.pending')" :active="request()->routeIs('loans.pending')" icon="how_to_reg" label="Persetujuan Pinjam" />
    <x-menu-link :href="route('loans.returns')" :active="request()->routeIs('loans.returns')" icon="assignment_return" label="Pengembalian" />
@endif

@if ($canManage || $canApprove)
    <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Inventaris</div>
    {{-- Mutasi memerlukan hak kelola; Laboran hanya dapat mencetak label. --}}
    @if ($canManage)
        <x-menu-link :href="route('movements.index')" :active="request()->routeIs('movements.*')" icon="swap_horiz" label="Mutasi Lokasi" />
    @endif
    <x-menu-link :href="route('labels.index')" :active="request()->routeIs('labels.*')" icon="label" label="Cetak Label" />
@endif

@if ($canManage)
    <x-menu-link :href="route('items.create')" :active="request()->routeIs('items.create') || request()->routeIs('items.store')" icon="add_box" label="Pengadaan Barang" />
    <x-menu-link :href="route('write-offs.index')" :active="request()->routeIs('write-offs.*')" icon="delete_forever" label="Inventaris Keluar" />
    <x-menu-link :href="route('events.index')" :active="request()->routeIs('events.*')" icon="event" label="Rekap Kegiatan" />
    <x-menu-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="assessment" label="Laporan Bulanan" />
    <x-menu-link :href="route('masters.index')" :active="request()->routeIs('masters.*')" icon="category" label="Master Data" />
@endif

@if ($canApprove)
    <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Alat</div>
    <x-menu-link :href="route('scan')" :active="request()->routeIs('scan')" icon="qr_code_scanner" label="Scan QR" />
@endif

<div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Akun</div>
<x-menu-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" icon="notifications" label="Notifikasi"
             :badge="$user?->unreadNotifications()->count() ?? 0" />

@if ($isWakasek)
    <div class="pt-3 pb-1 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-400 dark:text-slate-500">Administrasi</div>
    <x-menu-link :href="route('write-offs.pending')" :active="request()->routeIs('write-offs.pending')" icon="verified_user" label="Approval Penghapusan" />
    <x-menu-link :href="route('items.acquisitions')" :active="request()->routeIs('items.acquisitions')" icon="rule" label="Approval Pengadaan" />
    <x-menu-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="manage_accounts" label="Pengguna" />
    <x-menu-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')" icon="settings" label="Pengaturan Instansi" />
@endif
