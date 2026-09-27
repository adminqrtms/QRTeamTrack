@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">Registered Residents</h2>
            <p class="text-muted small mb-0">Manage community members and view their report history.</p>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-uppercase small fw-bold">
                    <tr>
                        <th class="px-4 py-3">Resident Name</th>
                        <th class="py-3">Contact Details</th>
                        <th class="py-3 text-center">Triggered Alarms</th>
                        <th class="py-3 text-center">Submitted Reports</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($residents as $resident)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="d-flex align-items-center">
                                    <img src="{{ $resident->avatar ? asset('storage/' . $resident->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($resident->name) . '&background=random' }}" 
                                         alt="Avatar" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                    <div>
                                        <div class="fw-bold text-dark">{{ $resident->name }}</div>
                                        <div class="text-muted smaller">{{ $resident->location->location_name ?? 'No Location' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3">
                                <div class="small fw-medium text-dark">{{ $resident->email }}</div>
                                <div class="text-muted small">{{ $resident->phone_number ?? 'N/A' }}</div>
                            </td>
                            <td class="py-3 text-center">
                                <span class="badge bg-danger rounded-pill px-3">
                                    <i class="fas fa-bell me-1"></i> {{ $resident->alarms_count }}
                                </span>
                            </td>
                            <td class="py-3 text-center">
                                <span class="badge bg-primary rounded-pill px-3">
                                    <i class="fas fa-file-alt me-1"></i> {{ $resident->reports_count }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="{{ route('admin.residents.show', $resident->id) }}" class="btn btn-sm btn-outline-primary" title="View Profile">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                    <form action="{{ route('admin.residents.destroy', $resident->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this resident account?');" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Account">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-5 text-center text-muted">
                                <div class="mb-2"><i class="fas fa-users-slash fa-2x"></i></div>
                                No residents registered yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4 d-flex justify-content-center">
        {{ $residents->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
