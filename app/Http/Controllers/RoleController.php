<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\PermissionChangeLog;
use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RolePermissionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Pengelolaan role dan permission — hanya untuk Super Admin.
 *
 * Batas keamanan antar-role tetap ditegakkan di backend; halaman ini hanya
 * mengatur tindakan di dalam ruang kewenangan masing-masing role.
 */
class RoleController extends Controller
{
    public function __construct(private readonly RolePermissionManager $permissions) {}

    public function index(): View
    {
        $roles = collect(Role::cases())->map(function (Role $role): array {
            $featureMap = $this->permissions->currentFeatureMap($role);
            $visibleFeatures = collect($featureMap)
                ->filter(fn (array $actions): bool => $actions !== [])
                ->count();

            return [
                'role' => $role,
                'description' => PermissionCatalog::descriptionFor($role),
                'user_count' => User::query()->where('role', $role->value)->count(),
                'feature_count' => $visibleFeatures,
                'locked' => PermissionCatalog::isLocked($role),
                'customized' => ! PermissionCatalog::isLocked($role) && $this->permissions->isCustomized($role),
            ];
        });

        return view('roles.index', ['roles' => $roles]);
    }

    public function show(string $role): View
    {
        $roleEnum = $this->resolveRole($role);
        $featureMap = $this->permissions->currentFeatureMap($roleEnum);
        $states = [];

        foreach (PermissionCatalog::MODULES as $module) {
            foreach ($module['features'] as $feature => $meta) {
                $states[$feature] = PermissionCatalog::stateOf($feature, $featureMap);
            }
        }

        return view('roles.show', [
            'role' => $roleEnum,
            'description' => PermissionCatalog::descriptionFor($roleEnum),
            'catalog' => PermissionCatalog::MODULES,
            'selected' => $featureMap,
            'states' => $states,
            'locked' => PermissionCatalog::isLocked($roleEnum),
            'customized' => ! PermissionCatalog::isLocked($roleEnum) && $this->permissions->isCustomized($roleEnum),
            'userCount' => User::query()->where('role', $roleEnum->value)->count(),
            'history' => $this->history($roleEnum),
            'baseline' => PermissionCatalog::toFeatureMap(PermissionCatalog::baseline($roleEnum)),
        ]);
    }

    public function update(Request $request, string $role): RedirectResponse
    {
        $roleEnum = $this->resolveRole($role);

        abort_if(PermissionCatalog::isLocked($roleEnum), 403, 'Permission Super Admin bersifat terkunci.');

        $data = $request->validate([
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['array'],
            'permissions.*.*' => ['string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        [$before, $after] = $this->permissions->update(
            $roleEnum,
            $data['permissions'] ?? [],
            $request->user(),
            $data['reason'] ?? null,
        );

        activity('permission')
            ->causedBy($request->user())
            ->withProperties([
                'role' => $roleEnum->value,
                'reason' => $data['reason'] ?? null,
                'before' => $before,
                'after' => $after,
            ])
            ->log('Memperbarui permission role '.$roleEnum->label());

        activity('permission')
            ->causedBy($request->user())
            ->withProperties(['role' => $roleEnum->value, 'alasan' => $data['reason'] ?? null])
            ->log('Memperbarui permission role '.$roleEnum->label());

        return redirect()->route('roles.show', $roleEnum->value)
            ->with('success', "Permission role {$roleEnum->label()} disimpan.");
    }

    public function reset(Request $request, string $role): RedirectResponse
    {
        $roleEnum = $this->resolveRole($role);

        abort_if(PermissionCatalog::isLocked($roleEnum), 403, 'Permission Super Admin bersifat terkunci.');

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        [$before, $after] = $this->permissions->reset($roleEnum, $request->user(), $data['reason'] ?? null);

        activity('permission')
            ->causedBy($request->user())
            ->withProperties([
                'role' => $roleEnum->value,
                'reason' => $data['reason'] ?? null,
                'before' => $before,
                'after' => $after,
            ])
            ->log('Mereset permission role '.$roleEnum->label().' ke default');

        return redirect()->route('roles.show', $roleEnum->value)
            ->with('success', "Permission role {$roleEnum->label()} dikembalikan ke default.");
    }

    /** @return Collection<int, PermissionChangeLog> */
    private function history(Role $role): Collection
    {
        return PermissionChangeLog::query()
            ->with('actor')
            ->where('role_name', $role->value)
            ->latest('recorded_at')
            ->limit(30)
            ->get();
    }

    private function resolveRole(string $role): Role
    {
        return Role::tryFrom(strtoupper($role)) ?? abort(404);
    }
}
