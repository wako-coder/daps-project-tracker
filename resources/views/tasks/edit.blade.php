@extends('layouts.app')

@section('title', 'Edit task')

@section('content')
    <p class="crumbs">
        <a href="{{ route('organizations.index') }}">Organizations</a> /
        <a href="{{ route('organizations.show', $organization) }}">{{ $organization->name }}</a> /
        <a href="{{ route('projects.show', [$organization, $project]) }}">{{ $project->name }}</a> /
        Edit task
    </p>

    <section class="card card-narrow">
        <h1>Edit task</h1>

        <form method="POST" action="{{ route('tasks.update', [$organization, $project, $task]) }}" class="stack">
            @csrf
            @method('PUT')

            @include('tasks.fields', ['task' => $task])

            <div class="actions">
                <button type="submit" class="button">Save changes</button>
                <a href="{{ route('projects.show', [$organization, $project]) }}">Cancel</a>
            </div>
        </form>
    </section>
@endsection
