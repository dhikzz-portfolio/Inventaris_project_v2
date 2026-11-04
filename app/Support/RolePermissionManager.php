<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\PermissionChangeLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Menyimpan dan membaca konfigurasi permission per role.
 *
 * Konfigurasi aktual hidup pada tabel role_has_permissions (Spatie). Metadata
 * `permissions_initialized_at` dan `permissions_customized_at` pada tabel roles
 * membedakan baseline default dengan konfigurasi yang diubah Super Admin,
 * sehingga seeder idempoten tidak menimpa perubahan manual.
 */
final class RolePermissionManager
{
    public function ensureRole(string|Role $role): SpatieRole
    {
        $value = $role instanceof Role ? $role->value : strtoupper($role);

        /** @var SpatieRole $spatieRole */
        $spatieRole = SpatieRole::query()->firstOrCreate(
            ['name' => $value, 'guard_name' => 'web'],
            ['description' => PermissionCatalog::descriptionFor($value)],
        );

        return $spatieRole;
    }

    /**
     * Pastikan lima role dan baseline default tersedia.
     *
     * Role yang sudah dikustomisasi Super Admin tidak disentuh. Role yang
     * masih memakai baseline disinkronkan ulang agar perubahan baseline pada
     * kode ikut berlaku tanpa menimpa konfigurasi custom. Super Admin selalu
     * dikembalikan ke baseline untuk mencegah sistem kehilangan akses.
     */
    public function bootstrap(): void
    {
        $this->ensurePermissions();

        foreach (Role::cases() as $role) {
            $spatieRole = $this->ensureRole($role);
            $isCustomized = $spatieRole->permissions_customized_at !== null;

            if ($isCustomized && $role !== Role::SUPER_ADMIN) {
                continue;
            }

            $spatieRole->syncPermissions(PermissionCatalog::baseline($role));
            $spatieRole->forceFill([
                'description' => PermissionCatalog::descriptionFor($role),
                'permissions_initialized_at' => $spatieRole->permissions_initialized_at ?? now(),
                'permissions_customized_at' => $role === Role::SUPER_ADMIN ? null : $spatieRole->permissions_customized_at,
            ])->save();
        }

        $this->forgetCache();
    }

    /** @return list<string> */
    public function current(string|Role $role): array
    {
        $spatieRole = $this->ensureRole($role);

        return $spatieRole->permissions()->pluck('name')->sort()->values()->all();
    }

    /** @return array<string, list<string>> */
    public function currentFeatureMap(string|Role $role): array
    {
        return PermissionCatalog::toFeatureMap($this->current($role));
    }

    public function isCustomized(string|Role $role): bool
    {
        $roleValue = $role instanceof Role ? $role->value : strtoupper($role);
        $spatieRole = $this->ensureRole($roleValue);

        if ($spatieRole->permissions_customized_at !== null) {
            return true;
        }

        $current = $this->current($roleValue);
        $baseline = PermissionCatalog::baseline($roleValue);

        return $current !== $baseline;
    }

    /**
     * Simpan konfigurasi permission baru secara transaksional beserta audit.
     *
     * @param  array<string, array<int, string>>  $selection  fitur => tindakan
     */
    public function update(string|Role $role, array $selection, User $actor, ?string $reason = null): void
    {
        $roleValue = $role instanceof Role ? $role->value : strtoupper($role);
        $permissions = PermissionCatalog::normalize($selection);
        $this->ensurePermissions();
        $violations = PermissionCatalog::securityViolations($roleValue, $permissions);

        if ($violations !== []) {
            throw ValidationException::withMessages([
                'permissions' => implode(' ', $violations),
            ]);
        }

        $spatieRole = $this->ensureRole($roleValue);
        $before = $this->current($roleValue);

        if ($before === $permissions) {
            return;
        }

        DB::transaction(function () use ($spatieRole, $permissions, $before, $actor, $reason, $roleValue): void {
            $spatieRole->syncPermissions($permissions);
            $spatieRole->forceFill([
                'permissions_customized_at' => $permissions === PermissionCatalog::baseline($roleValue) ? null : now(),
            ])->save();

            PermissionChangeLog::create([
                'role_name' => $roleValue,
                'actor_id' => $actor->id,
                'reason' => $reason,
                'before' => $before,
                'after' => $permissions,
            ]);
        });

        $this->forgetCache();
    }

    public function reset(string|Role $role, User $actor, ?string $reason = null): void
    {
        $roleValue = $role instanceof Role ? $role->value : strtoupper($role);

        if ($roleValue === Role::SUPER_ADMIN->value) {
            throw ValidationException::withMessages([
                'permissions' => 'Super Admin bersifat terkunci dan selalu memakai baseline.',
            ]);
        }

        $spatieRole = $this->ensureRole($roleValue);
        $before = $this->current($roleValue);
        $baseline = PermissionCatalog::baseline($roleValue);

        if ($before === $baseline) {
            return;
        }

        DB::transaction(function () use ($spatieRole, $baseline, $before, $actor, $reason, $roleValue): void {
            $spatieRole->syncPermissions($baseline);
            $spatieRole->forceFill(['permissions_customized_at' => null])->save();

            PermissionChangeLog::create([
                'role_name' => $roleValue,
                'actor_id' => $actor->id,
                'reason' => $reason ?: 'Reset ke permission default.',
                'before' => $before,
                'after' => $baseline,
            ]);
        });

        $this->forgetCache();
    }

    public function forgetCache(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Pastikan seluruh permission katalog memiliki baris di tabel permissions. */
    private function ensurePermissions(): void
    {
        foreach (PermissionCatalog::allPermissionNames() as $name) {
            SpatiePermission::findOrCreate($name, 'web');
        }
    }
}
