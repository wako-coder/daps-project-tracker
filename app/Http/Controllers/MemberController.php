<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Support\InvitationMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin-only (see routes/web.php). {user} is resolved through
 * $organization->users(), so it is always an existing member of this organization.
 */
class MemberController extends Controller
{
    public function store(Request $request, Organization $organization, InvitationMailer $mailer): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $email = Str::lower(trim($data['email']));
        $user = User::where('email', $email)->first();

        if ($user === null) {
            return $this->invite($request, $organization, $mailer, $email, Role::from($data['role']));
        }

        if ($organization->users()->whereKey($user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'email' => 'That person is already a member.',
            ]);
        }

        $organization->users()->attach($user, ['role' => $data['role']]);

        // An earlier invitation to this address is no longer needed.
        $organization->invitations()->where('email', $email)->delete();

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

    public function destroy(Request $request, Organization $organization, User $user): RedirectResponse
    {
        $this->ensureNotLastAdmin($organization, $user);

        $organization->users()->detach($user->getKey());

        // An admin who removes themselves can no longer open this organization.
        if ($user->is($request->user())) {
            return redirect()->route('organizations.index')->with('status', "You left {$organization->name}.");
        }

        return back()->with('status', "{$user->name} removed.");
    }

    /**
     * Nobody has registered with this email yet. With invitations switched on,
     * create (or renew) an invitation and email its link; otherwise keep the
     * original behaviour and ask the admin to have them register first.
     */
    private function invite(Request $request, Organization $organization, InvitationMailer $mailer, string $email, Role $role): RedirectResponse
    {
        if (! InvitationMailer::enabled()) {
            throw ValidationException::withMessages([
                'email' => 'No account uses that email. Ask them to register first.',
            ]);
        }

        // The token is shown once, in the link. Only its hash is stored.
        $token = Str::random(48);

        $invitation = $organization->invitations()->updateOrCreate(
            ['email' => $email],
            [
                'role' => $role->value,
                'token_hash' => Invitation::hashToken($token),
                'invited_by' => $request->user()->getKey(),
                'expires_at' => now()->addDays(7),
            ],
        );

        $acceptUrl = route('invitations.show', $token);

        if ($mailer->send($invitation, $acceptUrl)) {
            return back()->with('status', "Invitation sent to {$email}. The link is valid for 7 days.");
        }

        // The invitation still exists; the admin can pass the link on themselves.
        return back()
            ->with('status', "The invitation was created but the email could not be sent. Send {$email} this link yourself (valid for 7 days):")
            ->with('invitation_link', $acceptUrl);
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
