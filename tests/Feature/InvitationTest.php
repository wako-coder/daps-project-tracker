<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->organization = $this->organizationWith($this->admin);

        // Nothing in this suite may reach the real Brevo API.
        Http::preventStrayRequests();
    }

    private function enableInvitations(): void
    {
        config(['services.brevo.key' => 'test-key', 'services.brevo.from_address' => 'tracker@example.com']);
    }

    /** Invite an address as the admin and return the secret token from the emailed link. */
    private function invite(string $email, string $role = 'member'): string
    {
        $token = null;

        Http::fake(['api.brevo.com/*' => function (Request $request) use (&$token) {
            preg_match('#/invitations/([A-Za-z0-9]+)#', $request['htmlContent'], $matches);
            $token = $matches[1];

            return Http::response(['messageId' => 'test'], 201);
        }]);

        $this->actingAs($this->admin)
            ->post("/orgs/{$this->organization->id}/members", ['email' => $email, 'role' => $role])
            ->assertSessionHasNoErrors();

        return $token;
    }

    public function test_with_invitations_off_an_unknown_email_is_rejected_as_before(): void
    {
        $this->actingAs($this->admin)
            ->post("/orgs/{$this->organization->id}/members", ['email' => 'new@example.com', 'role' => 'member'])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, Invitation::count());
    }

    public function test_an_admin_can_invite_an_address_that_has_no_account(): void
    {
        $this->enableInvitations();

        $token = $this->invite('New.Person@Example.com', 'admin');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request->hasHeader('api-key', 'test-key')
            && $request['to'][0]['email'] === 'new.person@example.com'
            && $request['sender']['email'] === 'tracker@example.com');

        $invitation = $this->organization->invitations()->sole();
        $this->assertSame('new.person@example.com', $invitation->email);
        $this->assertSame(Role::Admin, $invitation->role);
        $this->assertTrue($invitation->expires_at->isFuture());

        // The link's token is stored only as a hash.
        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertSame(Invitation::hashToken($token), $invitation->token_hash);
    }

    public function test_an_address_that_already_has_an_account_is_added_directly(): void
    {
        $this->enableInvitations();
        Http::fake();
        $existing = User::factory()->create();

        $this->actingAs($this->admin)
            ->post("/orgs/{$this->organization->id}/members", ['email' => $existing->email, 'role' => 'member'])
            ->assertSessionHasNoErrors();

        $this->assertSame(Role::Member, $existing->roleIn($this->organization));
        $this->assertSame(0, Invitation::count());
        Http::assertNothingSent();
    }

    public function test_the_invited_person_can_register_and_accept(): void
    {
        $this->enableInvitations();
        $token = $this->invite('new@example.com', 'member');
        $this->post('/logout');

        // A guest opening the link is sent to log in, then brought back after registering.
        $this->get("/invitations/{$token}")->assertRedirect('/login');

        $this->post('/register', [
            'name' => 'New Person',
            'email' => 'new@example.com',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
        ])->assertRedirect("/invitations/{$token}");

        $this->get("/invitations/{$token}")->assertOk()->assertSee($this->organization->name);

        // Opening the page alone does not join.
        $newUser = User::where('email', 'new@example.com')->sole();
        $this->assertNull($newUser->roleIn($this->organization));

        $this->post("/invitations/{$token}")->assertRedirect("/orgs/{$this->organization->id}");

        $this->assertSame(Role::Member, $newUser->roleIn($this->organization));
        $this->assertSame(0, Invitation::count());

        // The link works once.
        $this->post("/invitations/{$token}")->assertNotFound();
    }

    public function test_an_invitation_cannot_be_used_by_a_different_account(): void
    {
        $this->enableInvitations();
        $token = $this->invite('new@example.com');
        $someoneElse = User::factory()->create();

        $this->actingAs($someoneElse)->get("/invitations/{$token}")->assertNotFound();
        $this->actingAs($someoneElse)->post("/invitations/{$token}")->assertNotFound();

        $this->assertNull($someoneElse->roleIn($this->organization));
        $this->assertSame(1, Invitation::count());
    }

    public function test_an_expired_or_wrong_token_is_not_found(): void
    {
        $this->enableInvitations();
        $token = $this->invite('new@example.com');
        $invitee = User::factory()->create(['email' => 'new@example.com']);

        $this->actingAs($invitee)->get('/invitations/'.str_repeat('x', 48))->assertNotFound();

        Invitation::query()->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($invitee)->get("/invitations/{$token}")->assertNotFound();
        $this->actingAs($invitee)->post("/invitations/{$token}")->assertNotFound();
        $this->assertNull($invitee->roleIn($this->organization));
    }

    public function test_inviting_the_same_address_again_replaces_the_old_link(): void
    {
        $this->enableInvitations();
        $first = $this->invite('new@example.com', 'member');
        $second = $this->invite('new@example.com', 'admin');
        $invitee = User::factory()->create(['email' => 'new@example.com']);

        $this->assertSame(1, Invitation::count());
        $this->actingAs($invitee)->get("/invitations/{$first}")->assertNotFound();
        $this->actingAs($invitee)->get("/invitations/{$second}")->assertOk();
    }

    public function test_an_admin_can_revoke_an_invitation_and_a_member_cannot(): void
    {
        $this->enableInvitations();
        $token = $this->invite('new@example.com');
        $invitation = Invitation::sole();
        $member = $this->addMember($this->organization, User::factory()->create());
        $url = "/orgs/{$this->organization->id}/invitations/{$invitation->id}";

        $this->actingAs($member)->delete($url)->assertForbidden();
        $this->assertModelExists($invitation);

        $this->actingAs($this->admin)->delete($url)->assertSessionHasNoErrors();
        $this->assertModelMissing($invitation);

        $invitee = User::factory()->create(['email' => 'new@example.com']);
        $this->actingAs($invitee)->get("/invitations/{$token}")->assertNotFound();
    }

    public function test_an_invitation_from_another_organization_cannot_be_revoked_through_mine(): void
    {
        $this->enableInvitations();
        $this->invite('new@example.com');
        $invitation = Invitation::sole();

        $otherAdmin = User::factory()->create();
        $other = $this->organizationWith($otherAdmin);

        $this->actingAs($otherAdmin)
            ->delete("/orgs/{$other->id}/invitations/{$invitation->id}")
            ->assertNotFound();

        $this->assertModelExists($invitation);
    }

    public function test_if_the_email_cannot_be_sent_the_admin_is_given_the_link(): void
    {
        $this->enableInvitations();
        Http::fake(['api.brevo.com/*' => Http::response(['message' => 'unauthorized'], 401)]);

        $this->actingAs($this->admin)
            ->post("/orgs/{$this->organization->id}/members", ['email' => 'new@example.com', 'role' => 'member'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('invitation_link');

        $this->assertSame(1, Invitation::count());
    }
}
