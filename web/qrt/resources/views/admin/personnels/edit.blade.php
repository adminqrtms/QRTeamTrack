@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card border-0 shadow-sm mx-auto rounded-3 overflow-hidden" style="max-width: 600px;">
        <div class="card-header bg-white border-bottom py-3">
            <h2 class="h5 mb-0 fw-bold text-dark">Edit Personnel Profile</h2>
        </div>
        <div class="card-body p-4">
            <form action="{{ route('admin.personnels.update', $personnel->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="text-center mb-4">
                    <div class="position-relative d-inline-block">
                        <img src="{{ $personnel->avatar ? asset('storage/' . $personnel->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($personnel->name) . '&background=random' }}" 
                             alt="Avatar" class="rounded-circle border shadow-sm" style="width: 120px; height: 120px; object-fit: cover;">
                        <span class="position-absolute bottom-0 end-0 badge rounded-pill bg-primary border border-2 border-white p-2">
                            <i class="fas fa-camera fa-xs"></i>
                        </span>
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label for="name" class="form-label fw-bold small text-muted text-uppercase">Full Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $personnel->name) }}" class="form-control @error('name') is-invalid @enderror border-0 bg-light p-2 shadow-none" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="email" class="form-label fw-bold small text-muted text-uppercase">Email Address</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $personnel->email) }}" class="form-control @error('email') is-invalid @enderror border-0 bg-light p-2 shadow-none" required>
                        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="phone_number" class="form-label fw-bold small text-muted text-uppercase">Phone Number</label>
                        <input type="text" name="phone_number" id="phone_number" value="{{ old('phone_number', $personnel->phone_number) }}" class="form-control @error('phone_number') is-invalid @enderror border-0 bg-light p-2 shadow-none">
                        @error('phone_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12">
                        <label for="address" class="form-label fw-bold small text-muted text-uppercase">Address</label>
                        <textarea name="address" id="address" class="form-control @error('address') is-invalid @enderror border-0 bg-light p-2 shadow-none" rows="2">{{ old('address', $personnel->address) }}</textarea>
                        @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="hourly_rate" class="form-label fw-bold small text-muted text-uppercase">Hourly Rate (₱)</label>
                        <div class="input-group">
                            <span class="input-group-text border-0 bg-light px-3">₱</span>
                            <input type="number" step="0.01" name="hourly_rate" id="hourly_rate" value="{{ old('hourly_rate', $personnel->hourly_rate) }}" class="form-control @error('hourly_rate') is-invalid @enderror border-0 bg-light p-2 shadow-none">
                        </div>
                        @error('hourly_rate') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="avatar" class="form-label fw-bold small text-muted text-uppercase">Update Photo</label>
                        <input type="file" name="avatar" id="avatar" class="form-control @error('avatar') is-invalid @enderror border-0 bg-light p-2 shadow-none">
                        @error('avatar') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <hr class="my-4 opacity-10">
                <h6 class="fw-bold mb-3 small text-uppercase text-primary">Security Settings</h6>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="password" class="form-label fw-bold small text-muted text-uppercase">New Password</label>
                        <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror border-0 bg-light p-2 shadow-none" placeholder="••••••••">
                        <small class="text-muted smaller">Leave blank to keep current</small>
                        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6">
                        <label for="password_confirmation" class="form-label fw-bold small text-muted text-uppercase">Confirm Password</label>
                        <input type="password" name="password_confirmation" id="password_confirmation" class="form-control border-0 bg-light p-2 shadow-none" placeholder="••••••••">
                    </div>
                </div>

                <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3">
                    <a href="{{ route('admin.personnels.index') }}" class="btn btn-light px-4 fw-bold text-muted me-md-2">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
