@extends('layouts.app')

@section('title', $organization->name)

@section('content')
    @php($isAdmin = $role === \App\Enums\Role::Admin)

    <p class="crumbs"><a href="{{ route('organizations.index') }}">Organizations</a> / {{ $organization->name }}</p>

    <div class="heading-row">
        <h1>{{ $organization->name }} <span class="badge">you are {{ $role->value }}</span></h1>
        @if ($isAdmin)
            <a class="button" href="{{ route('projects.create', $organization) }}">New project</a>
        @endif
    </div>

    <section class="card">
        <h2>Projects</h2>

        @if ($projects->isEmpty())
            <p class="empty">No projects yet.{{ $isAdmin ? '' : ' An admin can create one.' }}</p>
        @else
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th class="num">Tasks</th>
                            <th class="num">Budget</th>
                            <th class="num">Spent</th>
                            <th>Spent vs budget</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $project)
                            <tr>
                                <td><a href="{{ route('projects.show', [$organization, $project]) }}">{{ $project->name }}</a></td>
                                <td class="num">{{ $project->tasks_count }}</td>
                                <td class="num">{{ \App\Support\Money::format($project->budget_santim) }}</td>
                                <td class="num">{{ \App\Support\Money::format($project->spent()) }}</td>
                                <td>
                                    @include('partials.budget-bar', ['project' => $project])
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <section class="card">
        <h2>Members</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        @if ($isAdmin)
                            <th></th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($members as $member)
                        <tr>
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>
                                @if ($isAdmin)
                                    <form method="POST" action="{{ route('members.update', [$organization, $member]) }}" class="inline-form">
                                        @csrf
                                        @method('PUT')
                                        <select name="role" aria-label="Role for {{ $member->name }}">
                                            @foreach ($roles as $option)
                                                <option value="{{ $option->value }}" @selected($member->pivot->role === $option->value)>{{ $option->label() }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="button button-quiet">Save</button>
                                    </form>
                                @else
                                    <span class="badge">{{ $member->pivot->role }}</span>
                                @endif
                            </td>
                            @if ($isAdmin)
                                <td class="actions">
                                    <form method="POST" action="{{ route('members.destroy', [$organization, $member]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="link-button danger">Remove</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($isAdmin)
            <h3>Add a member</h3>
            <p class="muted">They need an account first. Enter the email they registered with.</p>

            <form method="POST" action="{{ route('members.store', $organization) }}" class="inline-form">
                @csrf
                <label class="grow">
                    Email
                    <input type="email" name="email" value="{{ old('email') }}" required>
                </label>
                <label>
                    Role
                    <select name="role">
                        @foreach ($roles as $option)
                            <option value="{{ $option->value }}" @selected(old('role', 'member') === $option->value)>{{ $option->label() }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="button">Add member</button>
            </form>
        @endif
    </section>
@endsection
