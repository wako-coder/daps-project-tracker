<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $admin;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->organization = $this->organizationWith($this->admin);
        $this->member = $this->addMember($this->organization, User::factory()->create());
    }

    public function test_creating_an_organization_makes_the_creator_its_admin(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/orgs', ['name' => 'Fresh Org'])->assertRedirect();

        $organization = Organization::where('name', 'Fresh Org')->sole();
        $this->assertSame(Role::Admin, $user->roleIn($organization));
    }

    public function test_a_member_cannot_manage_members_or_projects(): void
    {
        $project = Project::factory()->for($this->organization)->create();
        $newcomer = User::factory()->create();
        $base = "/orgs/{$this->organization->id}";

        $this->actingAs($this->member);

        $this->post("{$base}/members", ['email' => $newcomer->email, 'role' => 'admin'])->assertForbidden();
        $this->put("{$base}/members/{$this->member->id}", ['role' => 'admin'])->assertForbidden();
        $this->delete("{$base}/members/{$this->admin->id}")->assertForbidden();
        $this->get("{$base}/projects/create")->assertForbidden();
        $this->post("{$base}/projects", ['name' => 'Nope', 'budget' => '100'])->assertForbidden();
        $this->put("{$base}/projects/{$project->id}", ['name' => 'Nope', 'budget' => '100'])->assertForbidden();
        $this->delete("{$base}/projects/{$project->id}")->assertForbidden();

        $this->assertSame(Role::Member, $this->member->roleIn($this->organization));
        $this->assertNull($newcomer->roleIn($this->organization));
        $this->assertModelExists($project);
    }

    public function test_a_member_can_read_projects_and_work_on_tasks(): void
    {
        $project = Project::factory()->for($this->organization)->create();
        $base = "/orgs/{$this->organization->id}/projects/{$project->id}";

        $this->actingAs($this->member);

        $this->get($base)->assertOk()->assertSee($project->name);

        $this->post("{$base}/tasks", ['title' => 'Pour foundation', 'estimated_cost' => '1500.50', 'status' => 'todo'])
            ->assertRedirect($base);

        $task = $project->tasks()->sole();
        $this->assertSame(150050, $task->estimated_cost_santim);

        $this->put("{$base}/tasks/{$task->id}", ['title' => 'Pour foundation', 'estimated_cost' => '1600', 'status' => 'done'])
            ->assertRedirect($base);
        $this->assertSame(160000, $task->fresh()->estimated_cost_santim);

        $this->delete("{$base}/tasks/{$task->id}")->assertRedirect($base);
        $this->assertModelMissing($task);
    }

    public function test_an_admin_can_add_change_and_remove_members(): void
    {
        $newcomer = User::factory()->create();
        $base = "/orgs/{$this->organization->id}/members";

        $this->actingAs($this->admin);

        $this->post($base, ['email' => $newcomer->email, 'role' => 'member'])->assertSessionHasNoErrors();
        $this->assertSame(Role::Member, $newcomer->roleIn($this->organization));

        $this->put("{$base}/{$newcomer->id}", ['role' => 'admin'])->assertSessionHasNoErrors();
        $this->assertSame(Role::Admin, $newcomer->roleIn($this->organization));

        $this->delete("{$base}/{$newcomer->id}")->assertSessionHasNoErrors();
        $this->assertNull($newcomer->roleIn($this->organization));
    }

    public function test_adding_a_member_requires_a_registered_email_and_a_valid_role(): void
    {
        $newcomer = User::factory()->create();
        $base = "/orgs/{$this->organization->id}/members";

        $this->actingAs($this->admin);

        $this->post($base, ['email' => 'nobody@example.com', 'role' => 'member'])->assertSessionHasErrors('email');
        $this->post($base, ['email' => $newcomer->email, 'role' => 'owner'])->assertSessionHasErrors('role');
        $this->post($base, ['email' => $this->member->email, 'role' => 'member'])->assertSessionHasErrors('email');

        $this->assertSame(2, $this->organization->users()->count());
    }

    public function test_the_last_admin_cannot_be_demoted_or_removed(): void
    {
        $base = "/orgs/{$this->organization->id}/members/{$this->admin->id}";

        $this->actingAs($this->admin);

        $this->put($base, ['role' => 'member'])->assertSessionHasErrors('member');
        $this->delete($base)->assertSessionHasErrors('member');

        $this->assertSame(Role::Admin, $this->admin->roleIn($this->organization));
    }

    public function test_an_admin_who_removes_themselves_is_sent_back_to_their_organization_list(): void
    {
        $this->addMember($this->organization, User::factory()->create(), Role::Admin);

        $this->actingAs($this->admin)
            ->delete("/orgs/{$this->organization->id}/members/{$this->admin->id}")
            ->assertRedirect('/orgs');

        $this->assertNull($this->admin->roleIn($this->organization));
        $this->get("/orgs/{$this->organization->id}")->assertNotFound();
    }
}
