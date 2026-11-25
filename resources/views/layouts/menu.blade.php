@php
    $user = auth()->user();
    $can = fn (string $permission): bool => (bool) $user?->can($permission);
    $canViewMaster = $user?->hasAnyPermission([
        'master_jurusan.view', 'master_gedung.view', 'master_ruangan.view', 'master_kategori.view',
    ]) ?? false;
    $pendingValidationCount = $user !== null && $can('procurement.validate')
        ? \App\Models\Item::query()
            ->where('acquisition_status', \App\Enums\AcquisitionStatus::MENUNGGU_KONFIRMASI->value)
            ->visibleTo($user)
            ->count()
        : 0;
@endphp

<x-menu-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="dashboard" label="Beranda" />

@if ($can('catalog.view'))
    <x-menu-link :href="route('catalogs.index')" :active="request()->routeIs('catalogs.*') || request()->routeIs('units.*')" icon="inventory_2" label="Katalog Barang" />
@endif

@if ($can('loan.create'))
    <x-menu-link :href="route('loans.create')" :active="request()->routeIs('loans.create')" icon="add_shopping_cart" :label="$user?->isMember() ? 'Peminjaman' : 'Ajukan Peminjaman'" />
@endif
@if ($can('loan.view'))
    <x-menu-link :href="route('loans.index')" :active="request()->routeIs('loans.index') || request()->routeIs('loans.show')" icon="swap_horiz" :label="$user?->isApproverLevel2() ? 'Daftar Peminjaman' : 'Peminjaman Saya'" />
@endif

@if ($can('inventory.view'))
    <x-menu-link :href="route('scan')" :active="request()->routeIs('scan') || request()->routeIs('scan.resolve')" icon="qr_code_scanner" label="Scan Inventaris" />
@endif
@if ($can('event.view'))
    <x-menu-link :href="route('events.index')" :active="request()->routeIs('events.*')" icon="event" label="Rekap Kegiatan" />
@endif

@if ($can('loan.approve') || $can('loan.return'))
    <div class="px-3 pb-1 pt-5 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Pinjaman &amp; Pengembalian</div>
    @if ($can('loan.approve'))
        <x-menu-link :href="route('loans.pending')" :active="request()->routeIs('loans.pending')" icon="how_to_reg" label="Permintaan Pinjam" />
    @endif
    @if ($can('loan.return'))
        <x-menu-link :href="route('loans.returns')" :active="request()->routeIs('loans.returns')" icon="assignment_return" label="Pengembalian" />
    @endif
@endif

@if ($can('movement.view') || $can('unit_labels.view'))
    <div class="px-3 pb-1 pt-5 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Barang Sekolah</div>
    @if ($can('movement.view'))
        <x-menu-link :href="route('movements.index')" :active="request()->routeIs('movements.*')" icon="swap_horiz" label="Pindah Barang" />
    @endif
    @if ($can('unit_labels.view'))
        <x-menu-link :href="route('labels.index')" :active="request()->routeIs('labels.*')" icon="label" label="Cetak Label Barang" />
    @endif
@endif

@if ($can('procurement.view') || $can('damage_report.view'))
    <div class="px-3 pb-1 pt-5 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Pengadaan &amp; Kondisi</div>
    @if ($can('procurement.validate'))
        <x-menu-link :href="route('items.acquisitions', ['status' => \App\Enums\AcquisitionStatus::MENUNGGU_KONFIRMASI->value])"
                     :active="request()->routeIs('items.acquisitions*') && request('status') === \App\Enums\AcquisitionStatus::MENUNGGU_KONFIRMASI->value"
                     icon="how_to_reg" label="Validasi Pengadaan" :badge="$pendingValidationCount" />
    @endif
    @if ($can('procurement.view'))
        <x-menu-link :href="route('items.acquisitions')"
                     :active="request()->routeIs('items.acquisitions*') && request('status') !== \App\Enums\AcquisitionStatus::MENUNGGU_KONFIRMASI->value"
                     icon="shopping_bag" :label="$can('procurement.create') ? 'Pengadaan Barang' : 'Riwayat Pengadaan'" />
    @endif
    @if ($can('damage_report.view'))
        <x-menu-link :href="route('damage-reports.index')" :active="request()->routeIs('damage-reports.*')" icon="report_problem" label="Laporan Kerusakan" />
    @endif
@endif

@if ($can('write_off.view'))
    <x-menu-link :href="route('write-offs.index')" :active="request()->routeIs('write-offs.*')" icon="delete_forever" label="Inventaris Keluar" />
@endif

@if ($can('report.view'))
    <x-menu-link :href="route('reports.index')" :active="request()->routeIs('reports.*')" icon="assessment" label="Laporan Bulanan" />
@endif

@if ($canViewMaster)
    <x-menu-link :href="route('masters.index')" :active="request()->routeIs('masters.*')" icon="category" label="Master Data" />
@endif

<div class="px-3 pb-1 pt-5 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Akun Saya</div>
<x-menu-link :href="route('notifications.index')" :active="request()->routeIs('notifications.*')" icon="notifications" label="Pemberitahuan"
             :badge="$user?->unreadNotifications()->count() ?? 0" />

@if ($can('write_off.approve') || $can('school_settings.view') || $can('roles.view') || $can('users.view') || $can('monitoring.view'))
    <div class="px-3 pb-1 pt-5 text-[10px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">Pengelolaan</div>
    @if ($can('write_off.approve'))
        <x-menu-link :href="route('write-offs.pending')" :active="request()->routeIs('write-offs.pending')" icon="verified_user" label="Setujui Penghapusan" />
    @endif
    @if ($can('school_settings.view'))
        <x-menu-link :href="route('settings.edit')" :active="request()->routeIs('settings.*')" icon="settings" label="Pengaturan Sekolah" />
    @endif
    @if ($can('users.view') && ! $user?->isAdmin())
        <x-menu-link :href="route('users.index')" :active="request()->routeIs('users.*')" icon="manage_accounts" label="Akun Pengguna" />
    @endif
    @if ($can('roles.view'))
        <x-menu-link :href="route('roles.index')" :active="request()->routeIs('roles.*')" icon="admin_panel_settings" label="Role &amp; Permission" />
    @endif
@endif
