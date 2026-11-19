@extends('layouts.app')

@section('title', $project->name)

@section('content')
    @php($isAdmin = $role === \App\Enums\Role::Admin)
    @php($money = \App\Support\Money::class)

    <p class="crumbs">
        <a href="{{ route('organizations.index') }}">Organizations</a> /
        <a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a> /
        {{ $project->name }}
    </p>

    <div class="heading-row">
        <h1>{{ $project->name }}</h1>
        @if ($isAdmin)
            <div class="actions">
                <a class="button button-quiet" href="{{ route('projects.edit', [$organization, $project]) }}">Edit project</a>
                <form method="POST" action="{{ route('projects.destroy', [$organization, $project]) }}"
                      onsubmit="return confirm('Delete this project and all its tasks?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="button button-danger">Delete</button>
                </form>
            </div>
        @endif
    </div>

    @if ($project->description)
        <p>{{ $project->description }}</p>
    @endif

    <section class="card">
        <h2>Spent vs budget</h2>

        <dl class="stats">
            <div>
                <dt>Budget</dt>
                <dd>{{ $money::format($project->budget_santim) }}</dd>
            </div>
            <div>
                <dt>Spent <small>(done tasks)</small></dt>
                <dd>{{ $money::format($project->spent()) }}</dd>
            </div>
            <div>
                <dt>Remaining</dt>
                <dd class="{{ $project->spent() > $project->budget_santim ? 'danger' : '' }}">
                    {{ $money::format($project->budget_santim - $project->spent()) }}
                </dd>
            </div>
            <div>
                <dt>Planned <small>(all tasks)</small></dt>
                <dd class="{{ $project->isOverBudget() ? 'danger' : '' }}">{{ $money::format($project->committed()) }}</dd>
            </div>
        </dl>

        @include('partials.budget-bar', ['project' => $project])
    </section>

    <section class="card">
        <h2>Tasks</h2>

        <nav class="filter" aria-label="Filter tasks by priority">
            Priority:
            <a href="{{ route('projects.show', [$organization, $project]) }}" @class(['current' => ! $priority]) @if (! $priority) aria-current="page" @endif>All</a>
            @foreach ($priorities as $option)
                <a href="{{ route('projects.show', [$organization, $project, 'priority' => $option->value]) }}"
                   @class(['current' => $priority === $option]) @if ($priority === $option) aria-current="page" @endif>{{ $option->label() }}</a>
            @endforeach
        </nav>

        @if ($tasks->isEmpty() && $priority)
            <p class="empty">No {{ strtolower($priority->label()) }} priority tasks.</p>
        @elseif ($tasks->isEmpty())
            <p class="empty">No tasks yet. Add the first one below.</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th class="num">Estimated cost</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tasks as $task)
                            <tr>
                                <td>{{ $task->title }}</td>
                                <td><span class="badge badge-{{ $task->status->value }}">{{ $task->status->label() }}</span></td>
                                <td><span class="badge priority-{{ $task->priority->value }}">{{ $task->priority->label() }}</span></td>
                                <td class="num">{{ $money::format($task->estimated_cost_santim) }}</td>
                                <td class="actions">
                                    <a href="{{ route('tasks.edit', [$organization, $project, $task]) }}">Edit</a>
                                    <form method="POST" action="{{ route('tasks.destroy', [$organization, $project, $task]) }}"
                                          onsubmit="return confirm('Delete this task?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="link-button danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h3>Add a task</h3>

        <form method="POST" action="{{ route('tasks.store', [$organization, $project]) }}" class="inline-form">
            @csrf
            @include('tasks.fields', ['task' => null])
            <button type="submit" class="button">Add task</button>
        </form>
    </section>
@endsection
