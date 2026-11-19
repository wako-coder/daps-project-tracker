<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Proves that one organization's data never reaches a user of another.
 *
 * Two organizations are set up. "Entoto" is the victim; the intruder is a
 * signed-in admin of "Other", who tries to reach Entoto's data.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Organization $entoto;

    private Project $entotoProject;

    private Task $entotoTask;

    private User $entotoAdmin;

    private Organization $other;

    private User $intruder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entotoAdmin = User::factory()->create();
        $this->entoto = $this->organizationWith($this->entotoAdmin);
        $this->entotoProject = Project::factory()->for($this->entoto)->create(['name' => 'Secret Entoto Project']);
        $this->entotoTask = Task::factory()->for($this->entotoProject)->create(['title' => 'Secret Entoto Task']);

        $this->intruder = User::factory()->create();
        $this->other = $this->organizationWith($this->intruder);
    }

    /** Every route registered under /orgs/{organization}. */
    private function organizationRoutes(): array
    {
        return collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route) => str_starts_with($route->uri(), 'orgs/{organization}'))
            ->values()
            ->all();
    }

    /** The URL of a route, filled in with Entoto's real ids. */
    private function entotoUrl(RoutingRoute $route): string
    {
        $ids = [
            'organization' => $this->entoto->id,
            'project' => $this->entotoProject->id,
            'task' => $this->entotoTask->id,
            'user' => $this->entotoAdmin->id,
        ];

        return route($route->getName(), array_intersect_key($ids, array_flip($route->parameterNames())));
    }

    public function test_the_organization_list_shows_only_organizations_the_user_belongs_to(): void
    {
        $this->actingAs($this->intruder)
            ->get('/orgs')
            ->assertOk()
            ->assertSee($this->other->name)
            ->assertDontSee($this->entoto->name);
    }

    /**
     * The headline test. It walks every route under /orgs/{organization} with
     * every HTTP method it accepts, using Entoto's real ids, as the intruder.
     * New routes are covered automatically.
     */
    public function test_a_user_gets_404_on_every_route_of_an_organization_they_do_not_belong_to(): void
    {
        $routes = $this->organizationRoutes();
        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $response = $this->actingAs($this->intruder)->call($method, $this->entotoUrl($route));

                $this->assertSame(404, $response->getStatusCode(), "{$method} {$route->uri()} must be 404 for a non-member");
                $response->assertDontSee('Secret Entoto');
            }
        }

        // The write attempts above changed nothing.
        $this->assertModelExists($this->entotoProject);
        $this->assertModelExists($this->entotoTask);
        $this->assertSame(1, $this->entoto->users()->count());
        $this->assertSame(1, $this->entoto->projects()->count());
        $this->assertSame(1, $this->entotoProject->tasks()->count());
    }

    /** A guard against the easiest mistake: adding a route and forgetting the middleware. */
    public function test_every_organization_route_is_behind_auth_and_the_membership_middleware(): void
    {
        foreach ($this->organizationRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth', $middleware, "{$route->uri()} is missing auth");
            $this->assertContains('member', $middleware, "{$route->uri()} is missing the membership middleware");
            $this->assertTrue($route->enforcesScopedBindings(), "{$route->uri()} does not scope its bindings");
        }
    }

    /** Being a member of your own organization must not unlock ids from another one. */
    public function test_another_organizations_project_and_task_ids_do_not_resolve_under_my_organization(): void
    {
        $myProject = Project::factory()->for($this->other)->create();

        $this->actingAs($this->intruder);

        // Entoto's project under my organization's URL.
        $this->get("/orgs/{$this->other->id}/projects/{$this->entotoProject->id}")->assertNotFound();
        $this->put("/orgs/{$this->other->id}/projects/{$this->entotoProject->id}", ['name' => 'Hijacked', 'budget' => '1'])->assertNotFound();
        $this->delete("/orgs/{$this->other->id}/projects/{$this->entotoProject->id}")->assertNotFound();

        // Entoto's task under my own project's URL.
        $this->get("/orgs/{$this->other->id}/projects/{$myProject->id}/tasks/{$this->entotoTask->id}/edit")->assertNotFound();
        $this->put("/orgs/{$this->other->id}/projects/{$myProject->id}/tasks/{$this->entotoTask->id}", ['title' => 'Hijacked', 'estimated_cost' => '1', 'status' => 'done', 'priority' => 'high'])->assertNotFound();
        $this->delete("/orgs/{$this->other->id}/projects/{$myProject->id}/tasks/{$this->entotoTask->id}")->assertNotFound();

        // Entoto's admin is not a member of my organization, so I cannot change or remove them here.
        $this->put("/orgs/{$this->other->id}/members/{$this->entotoAdmin->id}", ['role' => 'member'])->assertNotFound();
        $this->delete("/orgs/{$this->other->id}/members/{$this->entotoAdmin->id}")->assertNotFound();

        $this->assertSame('Secret Entoto Project', $this->entotoProject->fresh()->name);
        $this->assertSame('Secret Entoto Task', $this->entotoTask->fresh()->title);
    }

    /** Ids smuggled into the request body are ignored: the tenant comes from the URL only. */
    public function test_a_task_cannot_be_planted_in_another_organizations_project_through_the_request_body(): void
    {
        $myProject = Project::factory()->for($this->other)->create();

        $this->actingAs($this->intruder)
            ->post("/orgs/{$this->other->id}/projects/{$myProject->id}/tasks", [
                'title' => 'Planted',
                'estimated_cost' => '10',
                'status' => 'todo',
                'priority' => 'medium',
                'project_id' => $this->entotoProject->id,
            ])
            ->assertRedirect();

        $this->assertSame($myProject->id, Task::where('title', 'Planted')->sole()->project_id);
        $this->assertSame(1, $this->entotoProject->tasks()->count());
    }

    public function test_a_removed_member_loses_access_immediately(): void
    {
        $member = $this->addMember($this->entoto, User::factory()->create());

        $this->actingAs($member)->get("/orgs/{$this->entoto->id}")->assertOk();

        $this->entoto->users()->detach($member);

        $this->actingAs($member)->get("/orgs/{$this->entoto->id}")->assertNotFound();
    }

    public function test_guests_are_sent_to_the_login_page(): void
    {
        $this->get('/orgs')->assertRedirect('/login');
        $this->get("/orgs/{$this->entoto->id}")->assertRedirect('/login');
        $this->get("/orgs/{$this->entoto->id}/projects/{$this->entotoProject->id}")->assertRedirect('/login');
    }
}
