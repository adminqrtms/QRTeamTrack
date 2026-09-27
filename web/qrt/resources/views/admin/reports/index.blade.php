@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="d-flex align-items-center">
            <div class="bg-danger text-white rounded p-3 me-3 shadow-sm">
                <i class="bi bi-exclamation-triangle-fill fs-3"></i>
            </div>
            <div>
                <h2 class="fw-bold mb-0">Incident Reports</h2>
                <p class="text-muted mb-0">Comprehensive log of all reported incidents and emergencies.</p>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">All Incidents</h5>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterStatus" data-bs-toggle="dropdown" aria-expanded="false">
                    Filter Status
                </button>
                <ul class="dropdown-menu shadow-sm" aria-labelledby="filterStatus">
                    <li><a class="dropdown-item" href="#">All Reports</a></li>
                    <li><a class="dropdown-item" href="#">Resolved</a></li>
                    <li><a class="dropdown-item" href="#">Pending</a></li>
                </ul>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Reporter</th>
                            <th>Status</th>
                            <th>Date Reported</th>
                            <th>Last Updated</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($reports as $report)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-bold text-primary">#INC-{{ $report->id }}</span>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $report->user->name ?? 'Unknown' }}</span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill bg-{{ $report->status == 'resolved' ? 'success' : 'info' }}">
                                        {{ ucfirst($report->status) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="small fw-bold">{{ $report->created_at->format('M d, Y') }}</div>
                                    <div class="text-muted smaller">{{ $report->created_at->format('h:i A') }}</div>
                                </td>
                                <td class="text-muted small">
                                    {{ $report->updated_at->diffForHumans() }}
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ route('admin.reports.show', $report->id) }}" class="btn btn-sm btn-outline-secondary">View Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-clipboard-x fs-1 d-block mb-3"></i>
                                    No incident reports found in the system.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if(method_exists($reports, 'links'))
            <div class="card-footer bg-white py-3">
                {{ $reports->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
