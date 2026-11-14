@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card border-0 shadow-sm mx-auto rounded-3 overflow-hidden" style="max-width: 780px;">
        <div class="card-header bg-white border-bottom py-3">
            <h2 class="h5 mb-0 fw-bold text-dark">Edit Personnel Profile</h2>
        </div>
        <div class="card-body p-4">
            @if ($errors->any())
                <div class="alert alert-danger mb-4">
                    <div class="fw-bold small mb-1">Please fix the following:</div>
                    <ul class="mb-0 small">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('admin.personnels.update', $personnel->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="text-center mb-4">
                    <img src="{{ $personnel->avatar ? asset('storage/' . $personnel->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($personnel->name) . '&background=random' }}"
                         alt="Avatar" class="rounded-circle border shadow-sm" style="width: 110px; height: 110px; object-fit: cover;">
                    <div class="fw-bold mt-2">{{ $personnel->name }}</div>
                </div>

                @include('admin.personnels._form', ['personnel' => $personnel])

                <div class="d-grid gap-2 d-md-flex justify-content-md-end pt-3 border-top">
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
