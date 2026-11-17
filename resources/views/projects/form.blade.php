@extends('layouts.app')

@section('title', $project->exists ? 'Edit project' : 'New project')

@section('content')
    <p class="crumbs">
        <a href="{{ route('organizations.index') }}">Organizations</a> /
        <a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a> /
        {{ $project->exists ? $project->name : 'New project' }}
    </p>

    <section class="card card-narrow">
        <h1>{{ $project->exists ? 'Edit project' : 'New project' }}</h1>

        <form method="POST" class="stack"
              action="{{ $project->exists ? route('projects.update', [$organization, $project]) : route('projects.store', $organization) }}">
            @csrf
            @if ($project->exists)
                @method('PUT')
            @endif

            <label>
                Name
                <input type="text" name="name" value="{{ old('name', $project->name) }}" required maxlength="255" autofocus>
            </label>

            <label>
                Description <span class="muted">(optional)</span>
                <textarea name="description" rows="3" maxlength="2000">{{ old('description', $project->description) }}</textarea>
            </label>

            <label>
                Budget (ETB)
                <input type="text" name="budget" inputmode="decimal" placeholder="250000.00" required
                       value="{{ old('budget', $project->exists ? \App\Support\Money::toInput($project->budget_santim) : '') }}">
            </label>

            <div class="actions">
                <button type="submit" class="button">{{ $project->exists ? 'Save changes' : 'Create project' }}</button>
                <a href="{{ $project->exists ? route('projects.show', [$organization, $project]) : route('organizations.show', $organization) }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
