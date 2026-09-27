@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="fw-bold">Administrator Dashboard</h2>
            <p class="text-muted">Overview of the Quick Response Team Management System.</p>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1" style="font-size: 0.8rem; opacity: 0.8;">Total Personnel</h6>
                            <h2 class="mb-0 fw-bold">{{ $personnelCount }}</h2>
                        </div>
                        <i class="bi bi-people-fill fs-1 opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 border-top border-white-50">
                    <a href="{{ route('admin.personnels.index') }}" class="text-white text-decoration-none small">View All Personnel →</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1" style="font-size: 0.8rem; opacity: 0.8;">Registered Residents</h6>
                            <h2 class="mb-0 fw-bold">{{ $residentCount }}</h2>
                        </div>
                        <i class="bi bi-person-badge-fill fs-1 opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 border-top border-white-50">
                    <a href="{{ route('admin.residents.index') }}" class="btn btn-sm w-100 text-white text-start p-0 small">Manage Residents →</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1" style="font-size: 0.8rem; opacity: 0.8;">Active Locations</h6>
                            <h2 class="mb-0 fw-bold">{{ $locationCount }}</h2>
                        </div>
                        <i class="bi bi-geo-alt-fill fs-1 opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 border-top border-white-50">
                    <a href="{{ route('admin.locations.index') }}" class="text-white text-decoration-none small">Manage Locations →</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1" style="font-size: 0.8rem; opacity: 0.8;">Active Schedules</h6>
                            <h2 class="mb-0 fw-bold">{{ $scheduleCount }}</h2>
                        </div>
                        <i class="bi bi-calendar-event-fill fs-1 opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 border-top border-dark-50">
                    <a href="{{ route('admin.schedules.index') }}" class="text-dark text-decoration-none small">View Schedule →</a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-uppercase mb-1" style="font-size: 0.8rem; opacity: 0.8;">Incidents Logged</h6>
                            <h2 class="mb-0 fw-bold">{{ $recentReports->count() }}</h2>
                        </div>
                        <i class="bi bi-exclamation-triangle-fill fs-1 opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0 border-top border-white-50">
                    <a href="{{ route('admin.reports.index') }}" class="text-white text-decoration-none small">Review Reports →</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Activity Table -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h5 class="mb-0 fw-bold">Recent Incident Reports</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-4">Reference</th>
                                    <th>Status</th>
                                    <th>Date Reported</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentReports as $report)
                                    <tr>
                                        <td class="ps-4 fw-bold text-primary">#INC-{{ $report->id }}</td>
                                        <td>
                                            <span class="badge rounded-pill bg-{{ $report->status == 'resolved' ? 'success' : 'info' }}">
                                                {{ ucfirst($report->status) }}
                                            </span>
                                        </td>
                                        <td>{{ $report->created_at->format('M d, Y h:i A') }}</td>
                                        <td class="text-end pe-4">
                                            <a href="{{ route('admin.reports.index') }}" class="btn btn-sm btn-outline-secondary">View Details</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No recent reports found.</td>
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
