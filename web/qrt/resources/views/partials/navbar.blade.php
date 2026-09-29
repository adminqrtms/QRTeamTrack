<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand" href="{{ url('/') }}">QRTMS</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#tempNavbar" aria-controls="tempNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="tempNavbar">
            <ul class="navbar-nav me-auto">
                @auth
                    <li class="nav-item">
                        <a class="nav-link" href="{{ url('/') }}">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.personnels.index') }}">Personnel</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.residents.*') ? 'active' : '' }}" href="{{ route('admin.residents.index') }}">Residents</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.locations.index') }}">Locations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.schedules.index') }}">Schedules</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.reports.index') }}">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}" href="{{ route('admin.audit-logs.index') }}">Audit Logs</a>
                    </li>
                @endauth
            </ul>

            <ul class="navbar-nav ms-auto">
                @guest
                    <li class="nav-item">
                        <a href="{{ route('login') }}" class="nav-link text-secondary px-3">Login</a>
                    </li>
                @else
                    <span class="text-dark me-3 d-flex align-items-center">Hello, {{ Auth::user()->name }}</span>
                    <form action="{{ route('logout') }}" method="POST" class="d-inline">
                        @csrf
                        <button type="submit" class="btn btn-link text-danger text-decoration-none fw-bold p-0">Logout</button>
                    </form>
                @endguest
            </ul>
        </div>
    </div>
</nav>
