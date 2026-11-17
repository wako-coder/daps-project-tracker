<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_can_register_and_is_signed_in(): void
    {
        $this->post('/register', [
            'name' => 'Hana Girma',
            'email' => 'hana@example.com',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
        ])->assertRedirect('/orgs');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'hana@example.com']);
        $this->assertNotSame('a-long-password', User::sole()->password);
    }

    public function test_registration_rejects_a_duplicate_email_and_a_short_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/register', [
            'name' => 'Someone',
            'email' => 'taken@example.com',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_a_user_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password'])->assertRedirect('/orgs');
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_a_wrong_password_does_not_log_in(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_email_is_case_insensitive_at_registration_and_login(): void
    {
        $this->post('/register', [
            'name' => 'Hana Girma',
            'email' => '  Hana@Example.COM ',
            'password' => 'a-long-password',
            'password_confirmation' => 'a-long-password',
        ])->assertRedirect('/orgs');

        $this->assertDatabaseHas('users', ['email' => 'hana@example.com']);

        $this->post('/logout');
        $this->post('/login', ['email' => 'HANA@example.com', 'password' => 'a-long-password'])->assertRedirect('/orgs');
        $this->assertAuthenticated();
    }
}
