@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center bg-white">
                    <span class="fw-bold">{{ __('Manage Locations') }}</span>
                    <a href="{{ route('admin.locations.create') }}" class="btn btn-primary btn-sm">Add New Location</a>
                </div>

                <div class="card-body">
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Location Name</th>
                                    <th>Barangay</th>
                                    <th>Latitude</th>
                                    <th>Longitude</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($locations as $location)
                                    <tr>
                                        <td>{{ $location->location_name }}</td>
                                        <td>{{ $location->barangay }}</td>
                                        <td><code>{{ $location->latitude }}</code></td>
                                        <td><code>{{ $location->longitude }}</code></td>
                                        <td class="text-center">
                                            <a href="{{ route('admin.locations.edit', $location->id) }}" class="btn btn-sm btn-outline-info">Edit</a>
                                            <form action="{{ route('admin.locations.destroy', $location->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this location?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No locations registered yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-center mt-3">
                        {{ $locations->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
