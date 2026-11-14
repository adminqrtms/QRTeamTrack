@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="card shadow-sm mx-auto" style="max-width: 780px;">
        <div class="card-header bg-light py-3">
            <h2 class="h5 mb-0 fw-bold">Add New Personnel</h2>
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

            <form action="{{ route('admin.personnels.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('admin.personnels._form', ['personnel' => null])

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="{{ route('admin.personnels.index') }}" class="text-decoration-none small fw-bold">
                        Cancel
                    </a>
                    <button type="submit" class="btn btn-primary px-4 fw-bold">
                        Create Personnel Account
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
