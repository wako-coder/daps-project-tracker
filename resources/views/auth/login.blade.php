@extends('layouts.app')

@section('title', 'Log in')

@section('content')
    <section class="card card-narrow">
        <h1>Log in</h1>

        <form method="POST" action="{{ route('login') }}" class="stack">
            @csrf

            <label>
                Email
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
            </label>

            <label>
                Password
                <input type="password" name="password" required autocomplete="current-password">
            </label>

            <button type="submit" class="button">Log in</button>
        </form>

        <p class="muted">No account yet? <a href="{{ route('register') }}">Register</a></p>
    </section>
@endsection
