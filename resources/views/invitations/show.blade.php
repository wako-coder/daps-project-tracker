@extends('layouts.app')

@section('title', 'Invitation')

@section('content')
    <section class="card card-narrow">
        <h1>Join {{ $organization->name }}</h1>

        <p>
            You have been invited to join <strong>{{ $organization->name }}</strong>
            as <span class="badge">{{ $invitation->role->value }}</span>.
        </p>

        <form method="POST" action="{{ route('invitations.accept', $token) }}" class="actions">
            @csrf
            <button type="submit" class="button">Accept invitation</button>
            <a href="{{ route('organizations.index') }}">Not now</a>
        </form>

        <p class="muted">This invitation expires on {{ $invitation->expires_at->format('j F Y') }}.</p>
    </section>
@endsection
