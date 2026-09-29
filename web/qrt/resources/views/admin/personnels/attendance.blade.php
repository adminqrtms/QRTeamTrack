@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark">Attendance Logs</h2>
            <p class="text-secondary mb-0">Personnel: <span class="fw-semibold">{{ $personnel->name }}</span></p>
        </div>
        <a href="{{ route('admin.personnels.index') }}" class="btn btn-outline-secondary btn-sm px-3 shadow-sm rounded-pill">
            &larr; Back to List
        </a>
    </div>

    <div class="row mb-4" data-live="attendance-summary">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-3 bg-primary text-white p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="text-uppercase fw-bold mb-0 opacity-75 small">Monthly Summary</h6>
                    <i class="fas fa-calendar-alt opacity-50"></i>
                </div>
                <div class="row">
                    <div class="col-6 border-end border-white border-opacity-25">
                        <div class="display-6 fw-bold mb-0">{{ round($monthlyHours, 1) }}</div>
                        <div class="small opacity-75">Hours Worked</div>
                    </div>
                    <div class="col-6 ps-4">
                        <div class="display-6 fw-bold mb-0">₱{{ number_format($monthlySalary, 0) }}</div>
                        <div class="small opacity-75">Est. Salary</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6 d-none d-md-block">
            <div class="card border-0 shadow-sm rounded-3 h-100 p-4 bg-white d-flex flex-row align-items-center">
                <div class="rounded-circle bg-success bg-opacity-10 p-4 me-4">
                    <i class="fas fa-user-clock text-success fa-2x"></i>
                </div>
                <div>
                    <h6 class="text-muted text-uppercase fw-bold small mb-1">Status Overview</h6>
                    <p class="text-dark mb-0 fw-medium">Tracking regular attendance for the month of <strong>{{ now()->format('F Y') }}</strong>.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary text-uppercase small fw-bold">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="py-3">Time In</th>
                        <th class="py-3">Status</th>
                        <th class="py-3">Location</th>
                        <th class="py-3">Time Out</th>
                        <th class="px-4 py-3 text-end">Hours Worked</th>
                    </tr>
                </thead>
                <tbody class="text-dark" data-live="attendance-list">
                    @forelse($attendances as $attendance)
                        <tr data-live-key="attendance-{{ $attendance->id }}">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="fw-bold text-dark">{{ $attendance->time_in->format('M d, Y') }}</span>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-10 px-3 py-2">
                                    <i class="far fa-clock me-1"></i> {{ $attendance->time_in->format('h:i A') }}
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="badge {{ $attendance->status == 'Late' ? 'bg-warning text-dark' : 'bg-info-subtle text-info border border-info-subtle' }} px-2 py-1">
                                    {{ $attendance->status }}
                                </span>
                            </td>
                            <td class="py-3">
                                <span class="text-dark small fw-medium"><i class="fas fa-map-marker-alt text-muted me-1"></i> {{ $attendance->location->location_name ?? 'N/A' }}</span>
                            </td>
                            <td class="py-3">
                                @if($attendance->time_out)
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10 px-3 py-2">
                                        <i class="far fa-clock me-1"></i> {{ $attendance->time_out->format('h:i A') }}
                                    </span>
                                @else
                                    <span class="badge bg-primary px-3 py-2"><i class="fas fa-spinner fa-spin me-1"></i> Active</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-end fw-bold">
                                {{ $attendance->hours_worked ?? '-' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted">No attendance records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4 d-flex justify-content-center">
        {{ $attendances->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
