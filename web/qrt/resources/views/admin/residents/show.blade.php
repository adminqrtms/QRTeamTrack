@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('admin.residents.index') }}">Residents</a></li>
                    <li class="breadcrumb-item active">{{ $resident->name }}</li>
                </ol>
            </nav>
            <h2 class="fw-bold mb-0">Resident Profile</h2>
        </div>
        <a href="{{ route('admin.residents.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>

    <div class="row g-4">
        <!-- Profile Information -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-4">
                    <div class="mb-3">
                        @if($resident->avatar)
                            <img src="{{ asset('storage/' . $resident->avatar) }}" class="rounded-circle img-thumbnail" style="width: 120px; height: 120px; object-fit: cover;">
                        @else
                            <i class="bi bi-person-circle text-secondary" style="font-size: 5rem;"></i>
                        @endif
                    </div>
                    <h4 class="fw-bold">{{ $resident->name }}</h4>
                    <span class="badge bg-info text-dark text-uppercase px-3">Resident</span>
                    <hr class="my-4">
                    <div class="text-start">
                        <p class="mb-2"><strong>Email:</strong> <span class="text-muted">{{ $resident->email }}</span></p>
                        <p class="mb-2"><strong>Phone:</strong> <span class="text-muted">{{ $resident->phone_number ?? 'N/A' }}</span></p>
                        <p class="mb-2"><strong>Address:</strong> <span class="text-muted">{{ $resident->address ?? 'N/A' }}</span></p>
                        <p class="mb-0"><strong>Barangay:</strong> <span class="text-muted">{{ $resident->location->location_name ?? 'Not Assigned' }}</span></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity Details -->
        <div class="col-md-8">
            <!-- Alarm History -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bell-fill text-danger me-2"></i>Recent Alarms</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Status</th>
                                    <th>Location (Lat/Lng)</th>
                                    <th>Triggered At</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($resident->alarms()->latest()->take(5)->get() as $alarm)
                                    <tr>
                                        <td class="ps-4">
                                            <span class="badge rounded-pill bg-{{ $alarm->status == 'triggered' ? 'danger' : ($alarm->status == 'resolved' ? 'success' : 'warning') }}">
                                                {{ ucfirst($alarm->status) }}
                                            </span>
                                        </td>
                                        <td class="small">{{ $alarm->latitude }}, {{ $alarm->longitude }}</td>
                                        <td>{{ $alarm->created_at->format('M d, Y h:i A') }}</td>
                                        <td class="text-end pe-4">
                                            <span class="text-muted small">ID: #{{ $alarm->id }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No alarms recorded.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Incident Reports -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Submitted Reports</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Reference</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($resident->reports()->latest()->take(5)->get() as $report)
                                    <tr>
                                        <td class="ps-4 fw-bold">#INC-{{ $report->id }}</td>
                                        <td><span class="text-capitalize">{{ $report->type }}</span></td>
                                        <td>
                                            <span class="badge bg-{{ $report->status == 'resolved' ? 'success' : 'info' }}">
                                                {{ ucfirst($report->status) }}
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('admin.reports.show', $report->id) }}" class="btn btn-sm btn-outline-primary">View Report</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No reports submitted.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
