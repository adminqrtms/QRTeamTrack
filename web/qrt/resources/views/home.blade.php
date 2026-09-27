@extends('partials.app')

@section('content')
<div class="hero-section">
    <div class="container text-center">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <h1 class="display-3 fw-bold mb-4">QRTMS</h1>
                <p class="lead mb-5">Quick Response Team Management System. Streamlining emergency coordination and personnel tracking for rapid deployment.</p>
                
                <div class="d-grid gap-3 d-sm-flex justify-content-sm-center">
                    @guest
                        <a href="{{ route('login') }}" class="btn btn-primary btn-lg btn-qrt shadow">
                            Access System Login
                        </a>
                        {{-- @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-outline-light btn-lg btn-qrt">
                                Register Personnel
                            </a>
                        @endif --}}
                    @else
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-success btn-lg btn-qrt shadow">
                            Go to Admin Dashboard
                        </a>
                    @endguest
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
