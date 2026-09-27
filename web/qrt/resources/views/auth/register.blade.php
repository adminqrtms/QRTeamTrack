@extends('layouts.app')

@section('content')
<div class="auth-wrapper d-flex align-items-center justify-content-center vh-100 py-5" 
     style="background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('/img/qrtimage.jpg'); background-size: cover; background-position: center; background-attachment: fixed;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card border-0 shadow-lg rounded-4 overflow-hidden">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <h3 class="fw-bold text-dark mb-1">Join the Team</h3>
                            <p class="text-muted small">Register your personnel account below.</p>
                        </div>

                        <form method="POST" action="{{ route('register') }}">
                            @csrf

                            <div class="mb-3">
                                <label for="name" class="form-label small fw-bold text-muted text-uppercase">Full Name</label>
                                <div class="input-group border rounded-3 overflow-hidden">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-person text-muted"></i></span>
                                    <input id="name" type="text" class="form-control border-0 bg-light p-2 shadow-none @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus placeholder="John Doe">
                                </div>
                                @error('name')
                                    <span class="invalid-feedback d-block mt-1 small" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label small fw-bold text-muted text-uppercase">Email Address</label>
                                <div class="input-group border rounded-3 overflow-hidden">
                                    <span class="input-group-text bg-light border-0"><i class="bi bi-envelope text-muted"></i></span>
                                    <input id="email" type="email" class="form-control border-0 bg-light p-2 shadow-none @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="john@example.com">
                                </div>
                                @error('email')
                                    <span class="invalid-feedback d-block mt-1 small" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="password" class="form-label small fw-bold text-muted text-uppercase">Password</label>
                                    <div class="input-group border rounded-3 overflow-hidden">
                                        <span class="input-group-text bg-light border-0"><i class="bi bi-shield-lock text-muted"></i></span>
                                        <input id="password" type="password" class="form-control border-0 bg-light p-2 shadow-none @error('password') is-invalid @enderror" name="password" required autocomplete="new-password" placeholder="••••••••">
                                    </div>
                                    @error('password')
                                        <span class="invalid-feedback d-block mt-1 small" role="alert"><strong>{{ $message }}</strong></span>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="password-confirm" class="form-label small fw-bold text-muted text-uppercase">Confirm</label>
                                    <div class="input-group border rounded-3 overflow-hidden">
                                        <span class="input-group-text bg-light border-0"><i class="bi bi-shield-check text-muted"></i></span>
                                        <input id="password-confirm" type="password" class="form-control border-0 bg-light p-2 shadow-none" name="password_confirmation" required autocomplete="new-password" placeholder="••••••••">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <p class="smaller text-muted">By signing up, you agree to follow the operational guidelines of the Quick Response Team.</p>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary py-2 fw-bold shadow-sm rounded-3">
                                    Create Account <i class="bi bi-person-plus ms-2"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer bg-light border-0 py-3 text-center">
                        <span class="text-muted small">Already registered?</span>
                        <a href="{{ route('login') }}" class="text-decoration-none small fw-bold ms-1">Login Here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
