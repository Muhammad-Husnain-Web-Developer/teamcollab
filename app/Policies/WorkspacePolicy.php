<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

class WorkspacePolicy
{
    public function view(User $user, Tenant $tenant): bool
    {
        return \DB::connection('mysql')
            ->table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function update(User $user, Tenant $tenant): bool
    {
        return $tenant->owner_id === $user->id || $this->isAdmin($user, $tenant);
    }

    public function delete(User $user, Tenant $tenant): bool
    {
        return $tenant->owner_id === $user->id;
    }

    private function isAdmin(User $user, Tenant $tenant): bool
    {
        $role = \DB::connection('mysql')
            ->table('tenant_users')
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->value('role');

        return in_array($role, ['owner', 'admin']);
    }
}
