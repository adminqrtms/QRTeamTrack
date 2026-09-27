@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card shadow-sm mx-auto" style="max-width: 600px;">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0 fw-bold">Assign Duty Schedule</h2>
        </div>

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mx-4 mt-3 mb-0 shadow-sm" role="alert">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <ul class="mb-0 list-unstyled">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card-body p-4">
            <form action="{{ route('admin.schedules.store') }}" method="POST">
                @csrf

                <div class="mb-4">
                    <label for="personnel_id" class="form-label fw-bold small">Select Personnel</label>
                    <select name="personnel_id" id="personnel_id" class="form-select @error('personnel_id') is-invalid @enderror" required>
                        <option value="">Choose Personnel...</option>
                        @foreach($personnel as $p)
                            <option value="{{ $p->id }}" {{ old('personnel_id') == $p->id ? 'selected' : '' }}>
                                {{ $p->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('personnel_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label for="location_id" class="form-label fw-bold small">Duty Location</label>
                    <select name="location_id" id="location_id" class="form-select @error('location_id') is-invalid @enderror" required>
                        <option value="">Choose Location...</option>
                        @foreach($locations as $l)
                            <option value="{{ $l->id }}" {{ old('location_id') == $l->id ? 'selected' : '' }}>
                                {{ $l->location_name }} ({{ $l->barangay }}) — Currently Assigned: {{ $l->schedules_count }}/10
                            </option>
                        @endforeach
                    </select>
                    <div class="form-text small text-muted">
                        <i class="bi bi-info-circle me-1"></i> Note: You can assign up to 10 personnel to the same location for the same time period.
                    </div>
                    @error('location_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="schedule_date_start" class="form-label fw-bold small">Start Date</label>
                        <input type="date" name="schedule_date_start" id="schedule_date_start" class="form-control @error('schedule_date_start') is-invalid @enderror" value="{{ old('schedule_date_start') }}" required>
                        @error('schedule_date_start') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="schedule_date_end" class="form-label fw-bold small">End Date</label>
                        <input type="date" name="schedule_date_end" id="schedule_date_end" class="form-control @error('schedule_date_end') is-invalid @enderror" value="{{ old('schedule_date_end') }}" required>
                        @error('schedule_date_end') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label for="start_time" class="form-label fw-bold small">Shift Start Time</label>
                        <input type="time" name="start_time" id="start_time" class="form-control @error('start_time') is-invalid @enderror" value="{{ old('start_time') }}" required>
                        @error('start_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6">
                        <label for="end_time" class="form-label fw-bold small">Shift End Time</label>
                        <input type="time" name="end_time" id="end_time" class="form-control @error('end_time') is-invalid @enderror" value="{{ old('end_time') }}" required>
                        @error('end_time') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-4">
                    <label for="status" class="form-label fw-bold small">Initial Status</label>
                    <select name="status" id="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="{{ route('admin.schedules.index') }}" class="btn btn-light border">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        Save Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
