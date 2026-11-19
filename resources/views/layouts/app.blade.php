<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Project Tracker') · Project Tracker</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
    <header class="topbar">
        <div class="container topbar-inner">
            <a class="brand" href="{{ route('organizations.index') }}">Project Tracker</a>

            @auth
                <nav class="topbar-nav">
                    <a href="{{ route('organizations.index') }}">Organizations</a>
                    <span class="muted">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="link-button" type="submit">Log out</button>
                    </form>
                </nav>
            @endauth
        </div>
    </header>

    <main class="container">
        @if (session('status'))
            <p class="notice notice-ok" role="status">
                {{ session('status') }}
                @if (session('invitation_link'))
                    <br><code class="copyable">{{ session('invitation_link') }}</code>
                @endif
            </p>
        @endif

        @if ($errors->any())
            <div class="notice notice-error" role="alert">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
