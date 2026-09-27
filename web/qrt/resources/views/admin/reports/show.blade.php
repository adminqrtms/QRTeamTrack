@extends('layouts.app')

@section('content')
<div class="container py-4">
    <!-- Header & Breadcrumbs -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.reports.index') }}" class="text-decoration-none">Reports</a></li>
                    <li class="breadcrumb-item active">Incident #INC-{{ $report->id }}</li>
                </ol>
            </nav>
            <h2 class="fw-bold mb-0 text-dark">Incident Details</h2>
        </div>
        <a href="{{ route('admin.reports.index') }}" class="btn btn-outline-secondary px-4 shadow-sm">
            <i class="bi bi-arrow-left me-2"></i>Back to List
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row g-4">
        <!-- Main Information Column -->
        <div class="col-lg-8">
            <!-- Basic Incident Info Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">Incident Information</h5>
                    <span class="badge rounded-pill bg-{{ $report->status == 'resolved' ? 'success' : ($report->status == 'pending' ? 'danger' : 'info') }} px-3 py-2">
                        {{ strtoupper($report->status) }}
                    </span>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase fw-bold mb-1">Incident Type</label>
                            <p class="fw-bold fs-5 text-primary mb-0">{{ $report->type ?? 'Not Categorized' }}</p>
                        </div>
                        <div class="col-md-6">
                            <label class="text-muted small text-uppercase fw-bold mb-1">Date Reported</label>
                            <p class="mb-0 fw-semibold">{{ $report->created_at->format('F d, Y') }}</p>
                            <small class="text-muted">{{ $report->created_at->format('h:i A') }} ({{ $report->created_at->diffForHumans() }})</small>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Title / Summary</label>
                        <p class="fw-semibold text-dark">{{ $report->title }}</p>
                    </div>

                    <div class="mb-4">
                        <label class="text-muted small text-uppercase fw-bold mb-1">Description</label>
                        <div class="p-3 bg-light rounded text-dark" style="white-space: pre-wrap;">{{ $report->description }}</div>
                    </div>

                    @if($report->image)
                    <div class="mb-4">
                        <label class="text-muted small text-uppercase fw-bold d-block mb-2">Attached Photo</label>
                        <a href="{{ asset('storage/' . $report->image) }}" target="_blank">
                            <img src="{{ asset('storage/' . $report->image) }}" class="img-fluid rounded border shadow-sm w-100" style="max-height: 500px; object-fit: contain;" alt="Incident Image">
                        </a>
                    </div>
                    @endif

                    @if($report->action_taken)
                    <div class="mt-4 pt-4 border-top">
                        <label class="text-muted small text-uppercase fw-bold mb-2">Action Taken</label>
                        <div class="p-3 border-start border-4 border-success bg-success-subtle rounded text-dark">
                            {{ $report->action_taken }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Geographic Location Card -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-0">
                    <h5 class="mb-0 fw-bold">Incident Location</h5>
                </div>
                <div class="card-body p-0">
                    <div class="px-4 py-3 bg-light border-bottom">
                        <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                        <span class="fw-semibold text-dark">{{ $report->location->location_name ?? 'Custom Coordinate Entry' }}</span>
                        @if($report->location)
                            <span class="text-muted small ms-1">- Barangay {{ $report->location->barangay }}</span>
                        @endif
                    </div>
                    <div id="reportMap" style="height: 400px;"></div>
                    <div class="p-3 text-muted small bg-white">
                        <strong>GPS Data:</strong> {{ $report->latitude }}, {{ $report->longitude }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Management Column -->
        <div class="col-lg-4">
            <!-- Status Management -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Manage Incident</h6>
                    <form action="{{ route('admin.reports.updateStatus', $report->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="mb-3">
                            <label class="form-label small">Update Status</label>
                            <select name="status" class="form-select shadow-sm">
                                <option value="pending" @if($report->status == 'pending') selected @endif>Pending</option>
                                <!-- <option value="in_progress" @if($report->status == 'in_progress') selected @endif>In Progress</option> -->
                                <option value="resolved" @if($report->status == 'resolved') selected @endif>Resolved</option>
                                <!-- <option value="closed" @if($report->status == 'closed') selected @endif>Closed</option> -->
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-bold py-2">Update Incident Status</button>
                    </form>
                </div>
            </div>

            <!-- Reporter Profile -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Reported By</h6>
                    <div class="d-flex align-items-center mb-3">
                        <img src="{{ $report->user->avatar ? asset('storage/' . $report->user->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($report->user->name) }}" 
                             class="rounded-circle border me-3" width="56" height="56" style="object-fit: cover;">
                        <div>
                            <div class="fw-bold text-dark">{{ $report->user->name }}</div>
                            <div class="small text-muted">Registered Resident</div>
                        </div>
                    </div>
                    <div class="small border-top pt-3">
                        <div class="mb-2 text-dark"><i class="bi bi-telephone-fill text-muted me-2"></i>{{ $report->user->phone_number ?? 'No phone listed' }}</div>
                        <div class="mb-2 text-dark"><i class="bi bi-envelope-fill text-muted me-2"></i>{{ $report->user->email }}</div>
                        <div class="text-dark"><i class="bi bi-house-door-fill text-muted me-2"></i>{{ $report->user->address ?? 'Address not provided' }}</div>
                    </div>
                </div>
            </div>

            <!-- Assigned Personnel -->
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Assigned QRT Personnel</h6>
                    @if($report->personnel)
                        <div class="d-flex align-items-center">
                            <img src="{{ $report->personnel->avatar ? asset('storage/' . $report->personnel->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($report->personnel->name) }}" 
                                 class="rounded-circle border me-3" width="48" height="48" style="object-fit: cover;">
                            <div>
                                <div class="fw-bold text-dark">{{ $report->personnel->name }}</div>
                                <a href="{{ route('admin.personnels.attendance', $report->personnel->id) }}" class="text-decoration-none smaller">View Profile</a>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-person-slash fs-1 d-block mb-2"></i>
                            <p class="small mb-0">No personnel has been assigned to this incident yet.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lat = {{ $report->latitude }};
    const lng = {{ $report->longitude }};
    
    const map = L.map('reportMap').setView([lat, lng], 16);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);

    const marker = L.marker([lat, lng]).addTo(map);
    marker.bindPopup("<div class='fw-bold'>{{ $report->type }}</div><div>{{ $report->location->location_name ?? 'Incident Site' }}</div>").openPopup();
    
    // Ensure map renders correctly if it was hidden
    setTimeout(() => { map.invalidateSize(); }, 400);
});
</script>
@endpush
@endsection
