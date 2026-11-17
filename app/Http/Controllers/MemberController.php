<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin-only (see routes/web.php). {user} is resolved through
 * $organization->users(), so it is always an existing member of this organization.
 */
class MemberController extends Controller
{
    public function store(Request $request, Organization $organization): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => 'No account uses that email. Ask them to register first.',
            ]);
        }

        if ($organization->users()->whereKey($user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'email' => 'That person is already a member.',
            ]);
        }

        $organization->users()->attach($user, ['role' => $data['role']]);

        return back()->with('status', "{$user->name} added as {$data['role']}.");
    }

    public function update(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $newRole = Role::from($data['role']);

        if ($newRole !== Role::Admin) {
            $this->ensureNotLastAdmin($organization, $user);
        }

        $organization->users()->updateExistingPivot($user->getKey(), ['role' => $newRole->value]);

        return back()->with('status', "{$user->name} is now {$newRole->value}.");
    }

    public function destroy(Organization $organization, User $user): RedirectResponse
    {
        $this->ensureNotLastAdmin($organization, $user);

        $organization->users()->detach($user->getKey());

        return back()->with('status', "{$user->name} removed.");
    }

    /** An organization must always keep at least one admin. */
    private function ensureNotLastAdmin(Organization $organization, User $user): void
    {
        $isAdmin = $user->roleIn($organization) === Role::Admin;

        if ($isAdmin && $organization->adminCount() === 1) {
            throw ValidationException::withMessages([
                'member' => 'An organization needs at least one admin. Make someone else an admin first.',
            ]);
        }
    }
}
