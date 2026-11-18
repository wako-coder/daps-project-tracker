<?php

namespace Tests\Feature;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TaskPriorityTest extends TestCase
{
    use RefreshDatabase;

    private function priorityMigration(): Migration
    {
        return require database_path('migrations/2026_11_18_000001_add_priority_to_tasks_table.php');
    }

    /**
     * Rehearses the live upgrade: put the schema back to the version before
     * priorities, insert tasks the way the old code did, then run the
     * migration and check every existing task came out as medium.
     */
    public function test_the_migration_backfills_existing_tasks_to_medium_and_rolls_back_cleanly(): void
    {
        $migration = $this->priorityMigration();
        $project = Project::factory()->create();

        $migration->down();
        $this->assertFalse(Schema::hasColumn('tasks', 'priority'));

        foreach (['Survey', 'Wiring', 'Painting'] as $title) {
            DB::table('tasks')->insert([
                'project_id' => $project->id,
                'title' => $title,
                'estimated_cost_santim' => 1_000_00,
                'status' => 'todo',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $migration->up();

        $this->assertSame(['medium'], DB::table('tasks')->distinct()->pluck('priority')->all());
        $this->assertSame(3, DB::table('tasks')->count());

        // Rolling back drops only the new column; the tasks themselves survive.
        $migration->down();
        $this->assertFalse(Schema::hasColumn('tasks', 'priority'));
        $this->assertSame(3, DB::table('tasks')->count());

        $migration->up();
        $this->assertSame(['medium'], DB::table('tasks')->distinct()->pluck('priority')->all());
    }

    public function test_the_database_rejects_an_unknown_priority(): void
    {
        $task = Task::factory()->create();

        $this->expectException(QueryException::class);

        DB::table('tasks')->where('id', $task->id)->update(['priority' => 'urgent']);
    }

    public function test_a_member_can_set_and_change_a_tasks_priority(): void
    {
        $admin = User::factory()->create();
        $organization = $this->organizationWith($admin);
        $project = Project::factory()->for($organization)->create();
        $base = "/orgs/{$organization->id}/projects/{$project->id}/tasks";

        $this->actingAs($this->addMember($organization, User::factory()->create()));

        $this->post($base, ['title' => 'Wiring', 'estimated_cost' => '500', 'status' => 'todo', 'priority' => 'high'])->assertRedirect();

        $task = $project->tasks()->sole();
        $this->assertSame(TaskPriority::High, $task->priority);

        $this->put("{$base}/{$task->id}", ['title' => 'Wiring', 'estimated_cost' => '500', 'status' => 'todo', 'priority' => 'low'])->assertRedirect();
        $this->assertSame(TaskPriority::Low, $task->fresh()->priority);

        $this->post($base, ['title' => 'Bad', 'estimated_cost' => '1', 'status' => 'todo', 'priority' => 'urgent'])
            ->assertSessionHasErrors('priority');
        $this->assertSame(1, $project->tasks()->count());
    }

    public function test_the_task_list_filters_by_priority_but_the_budget_still_covers_every_task(): void
    {
        $admin = User::factory()->create();
        $organization = $this->organizationWith($admin);
        $project = Project::factory()->for($organization)->create(['budget_santim' => 10_000_00]);

        Task::factory()->for($project)->create(['title' => 'Urgent wiring', 'priority' => TaskPriority::High, 'status' => TaskStatus::Done, 'estimated_cost_santim' => 1_000_00]);
        Task::factory()->for($project)->create(['title' => 'Order paint', 'priority' => TaskPriority::Medium, 'status' => TaskStatus::Done, 'estimated_cost_santim' => 2_000_00]);
        Task::factory()->for($project)->create(['title' => 'Tidy storeroom', 'priority' => TaskPriority::Low]);

        $url = "/orgs/{$organization->id}/projects/{$project->id}";
        $this->actingAs($admin);

        $this->get("{$url}?priority=high")
            ->assertOk()
            ->assertSee('Urgent wiring')
            ->assertDontSee('Order paint')
            ->assertDontSee('Tidy storeroom')
            ->assertSee('ETB 3,000.00');

        $this->get("{$url}?priority=low")
            ->assertOk()
            ->assertSee('Tidy storeroom')
            ->assertDontSee('Urgent wiring');

        // No filter, or a value that is not a priority, shows everything.
        foreach (['', '?priority=urgent'] as $query) {
            $this->get($url.$query)
                ->assertOk()
                ->assertSee('Urgent wiring')
                ->assertSee('Order paint')
                ->assertSee('Tidy storeroom');
        }
    }

    public function test_the_filter_never_shows_another_organizations_tasks(): void
    {
        $admin = User::factory()->create();
        $organization = $this->organizationWith($admin);
        $project = Project::factory()->for($organization)->create();
        Task::factory()->for($project)->create(['title' => 'Our high task', 'priority' => TaskPriority::High]);

        $other = Project::factory()->for($this->organizationWith(User::factory()->create()))->create();
        Task::factory()->for($other)->create(['title' => 'Their high task', 'priority' => TaskPriority::High]);

        $this->actingAs($admin)
            ->get("/orgs/{$organization->id}/projects/{$project->id}?priority=high")
            ->assertOk()
            ->assertSee('Our high task')
            ->assertDontSee('Their high task');

        $this->actingAs($admin)
            ->get("/orgs/{$other->organization_id}/projects/{$other->id}?priority=high")
            ->assertNotFound();
    }
}
