@extends('layouts.app')

@section('content')
<div class="auth-wrapper d-flex align-items-center justify-content-center vh-100" 
     style="background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('/img/qrtimage.jpg'); background-size: cover; background-position: center;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <!-- Logo Area -->
                <div class="text-center mb-4">
                    <h1 class="display-5 fw-bold text-white mb-0">QRTMS Login</h1>
                    <p class="text-white-50 small text-uppercase tracking-widest">Administrator Portal</p>
                </div>

                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-md-6">
                        <div class="mb-4">
                            <h4 class="fw-bold text-dark mb-1">Welcome!</h4>
                            <p class="text-muted small">Please enter the Admin credentials to access the system.</p>
                        </div>

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            <div class="mb-3">
                                <label for="email" class="form-label small fw-bold text-muted text-uppercase">Email Address</label>
                                <div class="input-group border rounded-3 overflow-hidden">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-envelope text-muted"></i></span>
                                    <input id="email" type="email" class="form-control border-0 bg-light p-2 shadow-none @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" autofocus placeholder="name@example.com">
                                </div>
                                @error('email')
                                    <span class="invalid-feedback d-block mt-1 small" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <label for="password" class="form-label small fw-bold text-muted text-uppercase">Password</label>
                                    <!-- @if (Route::has('password.request'))
                                        <a class="text-decoration-none smaller fw-bold" href="{{ route('password.request') }}">Forgot?</a>
                                    @endif -->
                                </div>
                                <div class="input-group border rounded-3 overflow-hidden">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-lock text-muted"></i></span>
                                    <input id="password" type="password" class="form-control border-0 bg-light p-2 shadow-none @error('password') is-invalid @enderror" name="password" required autocomplete="current-password" placeholder="••••••••">
                                </div>
                                @error('password')
                                    <span class="invalid-feedback d-block mt-1 small" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <!-- <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                                    <label class="form-check-label small text-muted" for="remember">Keep me logged in</label>
                                </div> -->
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary py-2 fw-bold shadow-sm rounded-3">
                                    Sign In <i class="bi bi-box-arrow-in-right ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <!-- {{-- @if (Route::has('register'))
                        <div class="card-footer bg-light border-0 py-3 text-center">
                            <span class="text-muted small">New to the team?</span>
                            <a href="{{ route('register') }}" class="text-decoration-none small fw-bold ms-1">Create Account</a>
                        </div>
                    @endif --}} -->
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
