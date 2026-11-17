@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <section class="card card-narrow">
        <h1>Create an account</h1>

        <form method="POST" action="{{ route('register') }}" class="stack">
            @csrf

            <label>
                Name
                <input type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name">
            </label>

            <label>
                Email
                <input type="email" name="email" value="{{ old('email') }}" required autocomplete="username">
            </label>

            <label>
                Password <span class="muted">(at least 8 characters)</span>
                <input type="password" name="password" required autocomplete="new-password">
            </label>

            <label>
                Confirm password
                <input type="password" name="password_confirmation" required autocomplete="new-password">
            </label>

            <button type="submit" class="button">Register</button>
        </form>

        <p class="muted">Already registered? <a href="{{ route('login') }}">Log in</a></p>
    </section>
@endsection
