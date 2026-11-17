<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /** Admins manage members and own projects and their budgets. */
    public function manage(User $user, Organization $organization): bool
    {
        return $user->roleIn($organization) === Role::Admin;
    }
}
