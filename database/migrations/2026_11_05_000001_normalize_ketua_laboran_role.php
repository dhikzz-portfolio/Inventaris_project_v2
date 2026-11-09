<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $roleTable = config('permission.table_names.roles', 'roles');
        $modelHasRolesTable = config('permission.table_names.model_has_roles', 'model_has_roles');
        $roleHasPermissionsTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';

        DB::transaction(function () use (
            $roleTable,
            $modelHasRolesTable,
            $roleHasPermissionsTable,
            $roleKey,
        ): void {
            DB::table('users')
                ->where('role', 'KETUA_LABORAN')
                ->update(['role' => 'EDITOR_VALIDATOR']);

            $legacyRole = DB::table($roleTable)->where('name', 'KETUA_LABORAN')->first();

            if ($legacyRole === null) {
                return;
            }

            $currentRole = DB::table($roleTable)->where('name', 'EDITOR_VALIDATOR')->first();

            if ($currentRole === null) {
                DB::table($roleTable)
                    ->where('id', $legacyRole->id)
                    ->update(['name' => 'EDITOR_VALIDATOR']);

                return;
            }

            $legacyAssignments = DB::table($modelHasRolesTable)
                ->where($roleKey, $legacyRole->id)
                ->get();

            foreach ($legacyAssignments as $assignment) {
                $newAssignment = (array) $assignment;
                $newAssignment[$roleKey] = $currentRole->id;

                DB::table($modelHasRolesTable)->insertOrIgnore($newAssignment);
            }

            DB::table($roleHasPermissionsTable)->where($roleKey, $legacyRole->id)->delete();
            DB::table($modelHasRolesTable)->where($roleKey, $legacyRole->id)->delete();
            DB::table($roleTable)->where('id', $legacyRole->id)->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        throw new LogicException('Role normalization cannot be safely reversed after users may have been assigned EDITOR_VALIDATOR.');
    }
};
