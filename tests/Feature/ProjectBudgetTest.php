<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectBudgetTest extends TestCase
{
    use RefreshDatabase;

    public function test_spent_counts_done_tasks_and_planned_counts_every_task(): void
    {
        $admin = User::factory()->create();
        $organization = $this->organizationWith($admin);
        $project = Project::factory()->for($organization)->create(['budget_santim' => 1_000_000_00]);

        Task::factory()->for($project)->create(['estimated_cost_santim' => 250_000_00, 'status' => TaskStatus::Done]);
        Task::factory()->for($project)->create(['estimated_cost_santim' => 100_000_50, 'status' => TaskStatus::InProgress]);
        Task::factory()->for($project)->create(['estimated_cost_santim' => 50_000_00, 'status' => TaskStatus::Todo]);

        // Another project's tasks must not be counted.
        Task::factory()->for(Project::factory()->for($organization))->create(['estimated_cost_santim' => 999_00, 'status' => TaskStatus::Done]);

        $loaded = $organization->projects()->withCostTotals()->findOrFail($project->id);

        $this->assertSame(250_000_00, $loaded->spent());
        $this->assertSame(400_000_50, $loaded->committed());
        $this->assertSame(25, $loaded->spentPercent());
        $this->assertFalse($loaded->isOverBudget());

        $this->actingAs($admin)
            ->get("/orgs/{$organization->id}/projects/{$project->id}")
            ->assertOk()
            ->assertSee('ETB 1,000,000.00')
            ->assertSee('ETB 250,000.00')
            ->assertSee('ETB 750,000.00')
            ->assertSee('ETB 400,000.50');

        $this->actingAs($admin)
            ->get("/orgs/{$organization->id}")
            ->assertOk()
            ->assertSee('ETB 250,000.00')
            ->assertSee('25% spent');
    }

    public function test_an_admin_can_create_update_and_delete_a_project(): void
    {
        $admin = User::factory()->create();
        $organization = $this->organizationWith($admin);
        $base = "/orgs/{$organization->id}/projects";

        $this->actingAs($admin);

        $this->post($base, ['name' => 'Warehouse', 'description' => 'New site', 'budget' => '750000.5'])->assertRedirect();

        $project = $organization->projects()->sole();
        $this->assertSame(750_000_50, $project->budget_santim);

        $this->put("{$base}/{$project->id}", ['name' => 'Warehouse II', 'budget' => '800000'])->assertRedirect();
        $this->assertSame('Warehouse II', $project->fresh()->name);
        $this->assertSame(800_000_00, $project->fresh()->budget_santim);

        $this->delete("{$base}/{$project->id}")->assertRedirect("/orgs/{$organization->id}");
        $this->assertModelMissing($project);
    }

    public function test_money_input_must_be_a_plain_birr_amount(): void
    {
        $admin = User::factory()->create();
        $organization = $this->organizationWith($admin);

        $this->actingAs($admin);

        foreach (['-5', '12.345', 'abc', '1e9', ''] as $bad) {
            $this->post("/orgs/{$organization->id}/projects", ['name' => 'Bad', 'budget' => $bad])
                ->assertSessionHasErrors('budget');
        }

        $this->assertSame(0, $organization->projects()->count());
    }
}
