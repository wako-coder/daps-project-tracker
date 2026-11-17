@extends('layouts.app')

@section('title', 'Organizations')

@section('content')
    <h1>Your organizations</h1>

    @if ($organizations->isEmpty())
        <p class="empty">You are not a member of any organization yet. Create one below, or ask an admin to add you by email.</p>
    @else
        <ul class="tiles">
            @foreach ($organizations as $organization)
                <li>
                    <a class="tile" href="{{ route('organizations.show', $organization) }}">
                        <strong>{{ $organization->name }}</strong>
                        <span class="badge">{{ $organization->pivot->role }}</span>
                        <span class="muted">
                            {{ $organization->projects_count }} {{ Str::plural('project', $organization->projects_count) }}
                            · {{ $organization->users_count }} {{ Str::plural('member', $organization->users_count) }}
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <section class="card">
        <h2>Create an organization</h2>
        <p class="muted">You become its first admin.</p>

        <form method="POST" action="{{ route('organizations.store') }}" class="inline-form">
            @csrf
            <label class="grow">
                Name
                <input type="text" name="name" value="{{ old('name') }}" required maxlength="255">
            </label>
            <button type="submit" class="button">Create</button>
        </form>
    </section>
@endsection
