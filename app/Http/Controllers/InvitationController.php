<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class InvitationController extends Controller
{
    /**
     * The page an invitation link opens. Requires login (see routes/web.php);
     * a guest is sent to log in or register first and then returned here.
     */
    public function show(Request $request, string $token): View
    {
        $invitation = $this->invitationFor($request, $token);

        return view('invitations.show', [
            'invitation' => $invitation,
            'organization' => $invitation->organization,
            'token' => $token,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitationFor($request, $token);
        $organization = $invitation->organization;

        // Membership and removal of the invitation happen together or not at all.
        DB::transaction(function () use ($request, $invitation, $organization) {
            if ($request->user()->roleIn($organization) === null) {
                $organization->users()->attach($request->user(), ['role' => $invitation->role->value]);
            }

            $invitation->delete();
        });

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', "You joined {$organization->name}.");
    }

    /** Admin-only (see routes/web.php). $invitation is resolved through $organization->invitations(). */
    public function destroy(Organization $organization, Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        return back()->with('status', "Invitation for {$invitation->email} revoked.");
    }

    /**
     * An invitation is usable only with its secret token, before it expires,
     * and by the account whose email it was sent to. Anything else is a 404.
     */
    private function invitationFor(Request $request, string $token): Invitation
    {
        $invitation = Invitation::findByToken($token);

        abort_if($invitation === null, 404);
        abort_unless($invitation->email === $request->user()->email, 404);

        return $invitation;
    }
}
