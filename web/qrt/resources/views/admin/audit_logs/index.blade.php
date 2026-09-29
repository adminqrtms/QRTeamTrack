@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-0">Audit Logs</h2>
            <p class="text-secondary mb-0">Personnel time in and time out activity recorded from the mobile app.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="row g-3 align-items-end">
                <div class="col-6 col-md-2">
                    <label for="date_from" class="form-label small fw-semibold text-secondary">Date From</label>
                    <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label for="date_to" class="form-label small fw-semibold text-secondary">Date To</label>
                    <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label for="time_from" class="form-label small fw-semibold text-secondary">Time From</label>
                    <input type="time" id="time_from" name="time_from" value="{{ request('time_from') }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label for="time_to" class="form-label small fw-semibold text-secondary">Time To</label>
                    <input type="time" id="time_to" name="time_to" value="{{ request('time_to') }}" class="form-control form-control-sm">
                </div>
                <div class="col-6 col-md-2">
                    <label for="action" class="form-label small fw-semibold text-secondary">Action</label>
                    <select id="action" name="action" class="form-select form-select-sm">
                        <option value="">All</option>
                        @foreach($actions as $value => $label)
                            <option value="{{ $value }}" @selected(request('action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="search" class="form-label small fw-semibold text-secondary">Personnel</label>
                    <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Name or email" class="form-control form-control-sm">
                </div>
                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm px-4">Filter</button>
                    <a href="{{ route('admin.audit-logs.index') }}" class="btn btn-outline-secondary btn-sm px-4">Reset</a>
                </div>
            </form>

            @if($errors->any())
                <div class="alert alert-danger small mt-3 mb-0">
                    @foreach($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-secondary text-uppercase small fw-bold">
                    <tr>
                        <th class="px-4 py-3">Date</th>
                        <th class="py-3">Time</th>
                        <th class="py-3">Personnel</th>
                        <th class="py-3">Action</th>
                        <th class="py-3">Location</th>
                        <th class="px-4 py-3">Details</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse($logs as $log)
                        <tr>
                            <td class="px-4 py-3 text-nowrap fw-bold">{{ $log->logged_at->format('M d, Y') }}</td>
                            <td class="py-3 text-nowrap">{{ $log->logged_at->format('h:i A') }}</td>
                            <td class="py-3">
                                <div class="fw-semibold">{{ $log->user->name ?? 'Deleted user' }}</div>
                                <div class="small text-muted">{{ $log->user->email ?? '' }}</div>
                            </td>
                            <td class="py-3">
                                @if($log->action === \App\Models\AuditLog::ACTION_TIME_IN)
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2">{{ $log->action_label }}</span>
                                @elseif($log->action === \App\Models\AuditLog::ACTION_TIME_OUT)
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2">{{ $log->action_label }}</span>
                                @else
                                    <span class="badge bg-secondary px-3 py-2">{{ $log->action_label }}</span>
                                @endif
                            </td>
                            <td class="py-3 small">{{ $log->location->location_name ?? 'N/A' }}</td>
                            <td class="px-4 py-3 small text-secondary">{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted">No audit logs found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $logs->links('pagination::bootstrap-5') }}
    </div>
</div>
@endsection
