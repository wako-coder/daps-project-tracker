<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\TaskRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * $task always belongs to $project, and $project to $organization: the route
 * group uses scoped bindings, so each is looked up through its parent.
 */
class TaskController extends Controller
{
    public function store(TaskRequest $request, Organization $organization, Project $project): RedirectResponse
    {
        $project->tasks()->create($request->attributesForModel());

        return redirect()
            ->route('projects.show', [$organization, $project])
            ->with('status', 'Task added.');
    }

    public function edit(Organization $organization, Project $project, Task $task): View
    {
        return view('tasks.edit', [
            'organization' => $organization,
            'project' => $project,
            'task' => $task,
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
        ]);
    }

    public function update(TaskRequest $request, Organization $organization, Project $project, Task $task): RedirectResponse
    {
        $task->update($request->attributesForModel());

        return redirect()
            ->route('projects.show', [$organization, $project])
            ->with('status', 'Task updated.');
    }

    public function destroy(Organization $organization, Project $project, Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()
            ->route('projects.show', [$organization, $project])
            ->with('status', 'Task deleted.');
    }
}
