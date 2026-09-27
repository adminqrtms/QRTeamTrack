@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card shadow-sm mx-auto" style="max-width: 500px;">
        <div class="card-header bg-white py-3">
            <h2 class="h5 mb-0 fw-bold">Batch Account Generation</h2>
        </div>

        <div class="card-body p-4">
            <p class="text-muted small mb-4">
                This tool automatically generates multiple personnel accounts. 
                Names and emails will follow the QRT sequence (e.g., QRTPersonnel 1).
            </p>

            <form action="{{ route('admin.personnels.batchStore') }}" method="POST">
                @csrf

                <div class="mb-3">
                    <label for="account_count" class="form-label fw-bold small">Number of Accounts to Create</label>
                    <input type="number" name="account_count" id="account_count" class="form-control @error('account_count') is-invalid @enderror" 
                           placeholder="e.g. 10" min="1" max="100" value="{{ old('account_count') }}" required>
                    @error('account_count') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    <div class="form-text mt-1">Maximum 100 accounts per batch.</div>
                </div>

                <div class="mb-4">
                    <label for="hourly_rate" class="form-label fw-bold small">Default Hourly Rate (₱)</label>
                    <div class="input-group">
                        <span class="input-group-text">₱</span>
                        <input type="number" step="0.01" name="hourly_rate" id="hourly_rate" class="form-control @error('hourly_rate') is-invalid @enderror" 
                               value="{{ old('hourly_rate', 350) }}" required>
                    </div>
                    @error('hourly_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="p-3 bg-light rounded border mb-4">
                    <div class="small fw-bold text-uppercase text-muted mb-2">Password Pattern</div>
                    <code class="small">qrtpersonnel[month][year][6-random-numbers]</code>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="{{ route('admin.personnels.index') }}" class="btn btn-light border">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        Generate Accounts
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
