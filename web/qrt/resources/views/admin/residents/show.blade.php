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
            <!-- SOS alarms -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold"><i class="bi bi-bell-fill text-danger me-2"></i>SOS Alarms <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $alarms->count() }}</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">SOS</th>
                                    <th>Sent</th>
                                    <th>Responded by</th>
                                    <th class="pe-4">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($alarms as $alarm)
                                    <tr>
                                        <td class="ps-4 fw-bold">#{{ $alarm->id }}
                                            <div class="small text-muted fw-normal">{{ $alarm->location->location_name ?? '' }}</div>
                                        </td>
                                        <td class="small">{{ $alarm->created_at->format('M d, Y h:i A') }}</td>
                                        <td class="small">@include('admin.residents._responders', ['incident' => $alarm])</td>
                                        <td class="pe-4">{!! \App\Support\IncidentStatus::badge($alarm->status, $alarm->resolution_method) !!}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No SOS sent.</td>
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
                    <h5 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Submitted Reports <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $reports->count() }}</span></h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Report</th>
                                    <th>Responded by</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reports as $report)
                                    <tr>
                                        <td class="ps-4">
                                            <span class="fw-bold">#INC-{{ $report->id }}</span> {{ $report->title }}
                                            <div class="small text-muted"><span class="text-capitalize">{{ str_replace('_', ' ', $report->type) }}</span> &middot; {{ $report->created_at->format('M d, Y h:i A') }}</div>
                                        </td>
                                        <td class="small">@include('admin.residents._responders', ['incident' => $report])</td>
                                        <td>{!! \App\Support\IncidentStatus::badge($report->status, $report->resolution_method) !!}</td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('admin.reports.show', $report->id) }}" class="btn btn-sm btn-outline-primary">View</a>
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
