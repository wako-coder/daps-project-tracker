<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\ProjectRequest;
use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * $project always belongs to $organization: the route group uses scoped
 * bindings, so it is looked up through $organization->projects().
 */
class ProjectController extends Controller
{
    public function show(Request $request, Organization $organization, Project $project): View
    {
        // An unknown ?priority= value shows every task rather than an error.
        $priority = TaskPriority::tryFrom((string) $request->query('priority'));

        return view('projects.show', [
            'organization' => $organization,
            // The budget figures always cover the whole project; the filter
            // only narrows the task list.
            'project' => $organization->projects()->withCostTotals()->findOrFail($project->getKey()),
            'tasks' => $project->tasks()
                ->when($priority, fn ($query) => $query->where('priority', $priority))
                ->orderBy('id')
                ->get(),
            'statuses' => TaskStatus::cases(),
            'priorities' => TaskPriority::cases(),
            'priority' => $priority,
        ]);
    }

    public function create(Organization $organization): View
    {
        return view('projects.form', ['organization' => $organization, 'project' => new Project]);
    }

    public function store(ProjectRequest $request, Organization $organization): RedirectResponse
    {
        $project = $organization->projects()->create($request->attributesForModel());

        return redirect()
            ->route('projects.show', [$organization, $project])
            ->with('status', 'Project created.');
    }

    public function edit(Organization $organization, Project $project): View
    {
        return view('projects.form', ['organization' => $organization, 'project' => $project]);
    }

    public function update(ProjectRequest $request, Organization $organization, Project $project): RedirectResponse
    {
        $project->update($request->attributesForModel());

        return redirect()
            ->route('projects.show', [$organization, $project])
            ->with('status', 'Project updated.');
    }

    public function destroy(Organization $organization, Project $project): RedirectResponse
    {
        $project->delete();

        return redirect()
            ->route('organizations.show', $organization)
            ->with('status', 'Project deleted.');
    }
}
