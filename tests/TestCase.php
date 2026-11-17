<?php

namespace Tests;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Create an organization and attach the given users to it with the given roles. */
    protected function organizationWith(User ...$admins): Organization
    {
        $organization = Organization::factory()->create();

        foreach ($admins as $admin) {
            $this->addMember($organization, $admin, Role::Admin);
        }

        return $organization;
    }

    protected function addMember(Organization $organization, User $user, Role $role = Role::Member): User
    {
        $organization->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
