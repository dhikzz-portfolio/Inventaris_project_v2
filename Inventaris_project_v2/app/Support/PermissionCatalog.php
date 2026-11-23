<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Satu sumber kebenaran untuk modul, fitur, tindakan, dan baseline permission.
 *
 * Nama permission mengikuti pola `fitur.tindakan`, misalnya `catalog.view`
 * atau `procurement.validate`. Hak akses aktual disimpan oleh
 * spatie/laravel-permission; katalog ini hanya mendefinisikan struktur,
 * baseline default, dan batasan keamanan yang tidak boleh dilanggar.
 */
final class PermissionCatalog
{
    public const STATE_FULL = 'FULL';

    public const STATE_VIEW_ONLY = 'VIEW_ONLY';

    public const STATE_HIDDEN = 'HIDDEN';

    public const STATE_CUSTOM = 'CUSTOM';

    /**
     * Hierarki Role -> Modul -> Fitur -> Tindakan.
     *
     * @var array<string, array{label: string, description: string, features: array<string, array{label: string, actions: list<string>}>}>
     */
    public const MODULES = [
        'administrasi' => [
            'label' => 'Administrasi Sistem',
            'description' => 'Akun pengguna, pengaturan sekolah, konfigurasi role, dan pemantauan aktivitas.',
            'features' => [
                'users' => ['label' => 'Akun Pengguna', 'actions' => ['view', 'create', 'edit', 'delete']],
                'school_settings' => ['label' => 'Pengaturan Sekolah', 'actions' => ['view', 'edit']],
                'roles' => ['label' => 'Role & Permission', 'actions' => ['view', 'manage']],
                'monitoring' => ['label' => 'Pemantauan Aktivitas', 'actions' => ['view']],
            ],
        ],
        'master_data' => [
            'label' => 'Master Data',
            'description' => 'Data induk inventaris sekolah.',
            'features' => [
                'master_jurusan' => ['label' => 'Jurusan', 'actions' => ['view', 'create', 'edit', 'delete']],
                'master_gedung' => ['label' => 'Gedung', 'actions' => ['view', 'create', 'edit', 'delete']],
                'master_ruangan' => ['label' => 'Ruangan & Laboratorium', 'actions' => ['view', 'create', 'edit', 'delete']],
                'master_kategori' => ['label' => 'Kategori Barang', 'actions' => ['view', 'create', 'edit', 'delete']],
            ],
        ],
        'inventaris' => [
            'label' => 'Inventaris',
            'description' => 'Katalog, unit barang, label, distribusi, mutasi, dan penghapusan.',
            'features' => [
                'catalog' => ['label' => 'Katalog Barang', 'actions' => ['view', 'create', 'edit', 'delete']],
                'inventory' => ['label' => 'Data & Kondisi Inventaris', 'actions' => ['view', 'edit']],
                'unit_labels' => ['label' => 'Label & QR', 'actions' => ['view', 'print']],
                'distribution' => ['label' => 'Distribusi Barang', 'actions' => ['view']],
                'movement' => ['label' => 'Mutasi Barang', 'actions' => ['view', 'create']],
                'write_off' => ['label' => 'Penghapusan Inventaris', 'actions' => ['view', 'create', 'approve', 'reject']],
            ],
        ],
        'operasional' => [
            'label' => 'Operasional',
            'description' => 'Pengadaan, peminjaman, kerusakan, dan kegiatan.',
            'features' => [
                'procurement' => ['label' => 'Pengadaan Barang', 'actions' => ['view', 'create', 'edit', 'validate', 'approve', 'reject', 'receive']],
                'loan' => ['label' => 'Peminjaman & Pengembalian', 'actions' => ['view', 'create', 'approve', 'reject', 'handover', 'return']],
                'damage_report' => ['label' => 'Laporan Kerusakan', 'actions' => ['view', 'create', 'edit']],
                'event' => ['label' => 'Rekap Kegiatan', 'actions' => ['view', 'create', 'edit', 'delete']],
            ],
        ],
        'laporan' => [
            'label' => 'Laporan',
            'description' => 'Laporan inventaris dan ekspor dokumen.',
            'features' => [
                'report' => ['label' => 'Laporan Inventaris', 'actions' => ['view', 'export', 'print', 'generate', 'delete', 'import']],
            ],
        ],
    ];

    /**
     * Baseline permission default setiap role.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const BASELINES = [
        Role::SUPER_ADMIN->value => [
            'users' => ['view', 'create', 'edit', 'delete'],
            'school_settings' => ['view', 'edit'],
            'roles' => ['view', 'manage'],
            'monitoring' => ['view'],
        ],
        Role::ADMIN->value => [
            'master_jurusan' => ['view', 'create', 'edit', 'delete'],
            'master_gedung' => ['view', 'create', 'edit', 'delete'],
            'master_ruangan' => ['view', 'create', 'edit', 'delete'],
            'master_kategori' => ['view', 'create', 'edit', 'delete'],
            'catalog' => ['view', 'create', 'edit', 'delete'],
            'inventory' => ['view', 'edit'],
            'unit_labels' => ['view', 'print'],
            'distribution' => ['view'],
            'movement' => ['view', 'create'],
            'write_off' => ['view', 'create', 'approve', 'reject'],
            'procurement' => ['view', 'create', 'edit', 'approve', 'reject', 'receive'],
            'loan' => ['view', 'create', 'approve', 'reject', 'handover', 'return'],
            'damage_report' => ['view', 'create', 'edit'],
            'event' => ['view', 'create', 'edit', 'delete'],
            'report' => ['view', 'export', 'print', 'generate', 'delete', 'import'],
        ],
        Role::EDITOR_VALIDATOR->value => [
            'catalog' => ['view'],
            'inventory' => ['view'],
            'unit_labels' => ['view', 'print'],
            'distribution' => ['view'],
            'procurement' => ['view', 'validate'],
            'loan' => ['view', 'create', 'approve', 'reject', 'handover', 'return'],
            'damage_report' => ['view', 'edit'],
            'event' => ['view', 'create', 'edit'],
            'report' => ['view', 'export', 'print'],
        ],
        Role::OPERATOR->value => [
            'catalog' => ['view'],
            'inventory' => ['view', 'edit'],
            'unit_labels' => ['view', 'print'],
            'distribution' => ['view'],
            'movement' => ['view', 'create'],
            'procurement' => ['view', 'create', 'edit', 'receive'],
            'loan' => ['view', 'create', 'approve', 'reject', 'handover', 'return'],
            'damage_report' => ['view', 'create', 'edit'],
            'event' => ['view', 'create', 'edit', 'delete'],
            'report' => ['view', 'export', 'print'],
        ],
        Role::MEMBER->value => [
            'catalog' => ['view'],
            'loan' => ['view', 'create'],
            'event' => ['view', 'create', 'edit', 'delete'],
        ],
    ];

    /** @var array<string, string> */
    public const ROLE_DESCRIPTIONS = [
        Role::SUPER_ADMIN->value => 'Administrasi sistem: akun pengguna, pengaturan sekolah, konfigurasi role, dan pemantauan aktivitas.',
        Role::ADMIN->value => 'Administrasi operasional: seluruh master data dan operasional inventaris sekolah.',
        Role::EDITOR_VALIDATOR->value => 'Pengawasan seluruh laboratorium dan validasi pengadaan dari Operator.',
        Role::OPERATOR->value => 'Operasional laboratorium pada jurusan yang ditugaskan (kategori Laboran atau K3).',
        Role::MEMBER->value => 'Pengguna umum: peminjaman barang dan rekap kegiatan milik sendiri.',
    ];

    private const ADMIN_RESTRICTED_FEATURES = ['users', 'school_settings', 'roles', 'monitoring'];

    /** @var list<string> */
    private const EDITOR_FORBIDDEN = [
        'procurement.create', 'procurement.edit', 'procurement.approve', 'procurement.reject', 'procurement.receive',
        'master_jurusan.create', 'master_jurusan.edit', 'master_jurusan.delete',
        'master_gedung.create', 'master_gedung.edit', 'master_gedung.delete',
        'master_ruangan.create', 'master_ruangan.edit', 'master_ruangan.delete',
        'master_kategori.create', 'master_kategori.edit', 'master_kategori.delete',
        'write_off.view', 'write_off.create', 'write_off.approve', 'write_off.reject',
        'catalog.create', 'catalog.edit', 'catalog.delete',
        'inventory.edit',
        'report.generate', 'report.delete', 'report.import',
    ];

    /** @var list<string> */
    private const OPERATOR_FORBIDDEN = [
        'procurement.validate', 'procurement.approve', 'procurement.reject',
        'master_jurusan.create', 'master_jurusan.edit', 'master_jurusan.delete',
        'master_gedung.create', 'master_gedung.edit', 'master_gedung.delete',
        'master_ruangan.create', 'master_ruangan.edit', 'master_ruangan.delete',
        'master_kategori.create', 'master_kategori.edit', 'master_kategori.delete',
        'write_off.view', 'write_off.create', 'write_off.approve', 'write_off.reject',
        'catalog.create', 'catalog.edit', 'catalog.delete',
        'report.generate', 'report.delete', 'report.import',
    ];

    /** @return array<string, array{label: string, description: string, features: array<string, array{label: string, actions: list<string>}>}> */
    public static function modules(): array
    {
        return self::MODULES;
    }

    /** @return array<string, string> */
    public static function roleDescriptions(): array
    {
        return self::ROLE_DESCRIPTIONS;
    }

    public static function descriptionFor(string|Role $role): string
    {
        return self::ROLE_DESCRIPTIONS[self::roleValue($role)] ?? '';
    }

    /** @return list<string> */
    public static function featureActions(string $feature): array
    {
        foreach (self::MODULES as $module) {
            if (isset($module['features'][$feature])) {
                return $module['features'][$feature]['actions'];
            }
        }

        return [];
    }

    public static function featureLabel(string $feature): string
    {
        foreach (self::MODULES as $module) {
            if (isset($module['features'][$feature])) {
                return $module['features'][$feature]['label'];
            }
        }

        return $feature;
    }

    public static function moduleKeyForFeature(string $feature): ?string
    {
        foreach (self::MODULES as $key => $module) {
            if (isset($module['features'][$feature])) {
                return $key;
            }
        }

        return null;
    }

    /** @return list<string> */
    public static function allPermissionNames(): array
    {
        $names = [];

        foreach (self::MODULES as $module) {
            foreach ($module['features'] as $feature => $meta) {
                foreach ($meta['actions'] as $action) {
                    $names[] = $feature.'.'.$action;
                }
            }
        }

        return $names;
    }

    /**
     * Baseline role sebagai peta fitur => tindakan.
     *
     * @return array<string, list<string>>
     */
    public static function baselineByFeature(string|Role $role): array
    {
        return self::BASELINES[self::roleValue($role)] ?? [];
    }

    /** @return list<string> */
    public static function baseline(string|Role $role): array
    {
        return self::flatten(self::baselineByFeature($role));
    }

    /**
     * Ubah pilihan formulir (fitur => tindakan) menjadi daftar nama permission.
     * Tindakan selain `view` otomatis menyertakan `view` agar halaman tetap
     * dapat dibuka sebelum tindakan dijalankan.
     *
     * @param  array<string, array<int, string>>  $selection
     * @return list<string>
     */
    public static function normalize(array $selection): array
    {
        $normalized = [];

        foreach ($selection as $feature => $actions) {
            $validActions = self::featureActions($feature);

            if ($validActions === []) {
                continue;
            }

            $actions = array_values(array_intersect($validActions, array_map('strval', (array) $actions)));

            if ($actions !== [] && ! in_array('view', $actions, true) && in_array('view', $validActions, true)) {
                $actions[] = 'view';
            }

            foreach ($actions as $action) {
                $normalized[] = $feature.'.'.$action;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param  list<string>  $permissionNames
     * @return array<string, list<string>>
     */
    public static function toFeatureMap(array $permissionNames): array
    {
        $map = [];

        foreach ($permissionNames as $name) {
            [$feature, $action] = array_pad(explode('.', $name, 2), 2, null);

            if ($feature === null || $action === null) {
                continue;
            }

            $map[$feature][] = $action;
        }

        foreach ($map as $feature => $actions) {
            $map[$feature] = array_values(array_intersect(self::featureActions($feature), $actions));
        }

        return $map;
    }

    /**
     * Status tampilan fitur: Kelola, Hanya lihat, atau Disembunyikan.
     *
     * @param  array<string, list<string>>  $featureMap
     */
    public static function stateOf(string $feature, array $featureMap): string
    {
        $actions = $featureMap[$feature] ?? [];
        $available = self::featureActions($feature);

        if ($actions === []) {
            return self::STATE_HIDDEN;
        }

        if ($available !== [] && count(array_diff($available, $actions)) === 0) {
            return self::STATE_FULL;
        }

        if ($actions === ['view']) {
            return self::STATE_VIEW_ONLY;
        }

        return self::STATE_CUSTOM;
    }

    /**
     * Batasan keamanan yang tidak boleh dilanggar konfigurasi permission.
     *
     * @param  array<int, string>  $permissionNames
     * @return list<string>
     */
    public static function securityViolations(string|Role $role, array $permissionNames): array
    {
        $roleValue = self::roleValue($role);
        $violations = [];
        $permissionNames = array_values(array_unique($permissionNames));

        if ($roleValue === Role::SUPER_ADMIN->value) {
            return ['Super Admin bersifat terkunci; permission role ini tidak dapat diubah.'];
        }

        foreach ($permissionNames as $permission) {
            if (! in_array($permission, self::allPermissionNames(), true)) {
                $violations[] = "Permission tidak dikenal: {$permission}.";

                continue;
            }

            $feature = explode('.', $permission)[0];

            if (in_array($feature, self::ADMIN_RESTRICTED_FEATURES, true)) {
                $violations[] = "Fitur {$feature} hanya boleh dimiliki Super Admin.";
            }
        }

        if ($roleValue === Role::MEMBER->value) {
            $allowed = self::baseline(Role::MEMBER);

            foreach ($permissionNames as $permission) {
                if (! in_array($permission, $allowed, true)) {
                    $violations[] = "Member tidak boleh memperoleh permission {$permission}.";
                }
            }
        }

        if ($roleValue === Role::ADMIN->value) {
            foreach ($permissionNames as $permission) {
                $feature = explode('.', $permission)[0];

                if (in_array($feature, self::ADMIN_RESTRICTED_FEATURES, true)) {
                    $violations[] = "Admin tidak boleh memperoleh akses {$feature}; fitur tersebut khusus Super Admin.";
                }
            }
        }

        $forbidden = match ($roleValue) {
            Role::EDITOR_VALIDATOR->value => self::EDITOR_FORBIDDEN,
            Role::OPERATOR->value => self::OPERATOR_FORBIDDEN,
            default => [],
        };

        if ($forbidden !== []) {
            foreach ($permissionNames as $permission) {
                if (in_array($permission, $forbidden, true)) {
                    $violations[] = "{$roleValue} tidak boleh memperoleh permission {$permission} sesuai pemisahan tanggung jawab.";
                }
            }
        }

        return array_values(array_unique($violations));
    }

    public static function isLocked(string|Role $role): bool
    {
        return self::roleValue($role) === Role::SUPER_ADMIN->value;
    }

    /**
     * @param  array<string, list<string>>  $featureMap
     * @return list<string>
     */
    private static function flatten(array $featureMap): array
    {
        $names = [];

        foreach ($featureMap as $feature => $actions) {
            foreach ($actions as $action) {
                $names[] = $feature.'.'.$action;
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }

    private static function roleValue(string|Role $role): string
    {
        return $role instanceof Role ? $role->value : strtoupper($role);
    }
}
