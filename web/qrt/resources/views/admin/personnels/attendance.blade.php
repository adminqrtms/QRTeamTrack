@extends('layouts.app')

@php
    use App\Support\IncidentStatus;
    $query = $period->mode === 'range'
        ? ['mode' => 'range', 'from' => $period->from->toDateString(), 'to' => $period->to->toDateString()]
        : ['month' => $period->month];
@endphp

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ $personnel->avatar ? asset('storage/' . $personnel->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($personnel->name) . '&background=random' }}"
                 alt="" class="rounded-circle border" style="width: 56px; height: 56px; object-fit: cover;">
            <div>
                <h2 class="h4 fw-bold text-dark mb-0">{{ $personnel->name }}</h2>
                <div class="text-secondary small">
                    Personnel log &middot; ₱{{ number_format((float) $personnel->hourly_rate, 2) }}/hour
                    @if($personnel->phone_number) &middot; {{ $personnel->phone_number }} @endif
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('admin.personnels.attendance.print', ['id' => $personnel->id] + $query) }}" target="_blank" rel="noopener"
               class="btn btn-primary btn-sm px-3 shadow-sm">
                Print log
            </a>
            <a href="{{ route('admin.personnels.index') }}" class="btn btn-outline-secondary btn-sm px-3 shadow-sm">
                &larr; Back to list
            </a>
        </div>
    </div>

    {{-- Period: a month, or a custom date range (e.g. half-month payroll) --}}
    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end" id="period-form">
                <div class="col-auto">
                    <label class="form-label small fw-semibold text-secondary mb-1">Period</label>
                    <select name="mode" id="period-mode" class="form-select form-select-sm">
                        <option value="month" @selected($period->mode === 'month')>Month</option>
                        <option value="range" @selected($period->mode === 'range')>Date range</option>
                    </select>
                </div>
                <div class="col-auto period-month">
                    <label class="form-label small fw-semibold text-secondary mb-1">Month</label>
                    <input type="month" name="month" value="{{ $period->month }}" class="form-control form-control-sm">
                </div>
                <div class="col-auto period-range">
                    <label class="form-label small fw-semibold text-secondary mb-1">From</label>
                    <input type="date" name="from" value="{{ $period->from->toDateString() }}" class="form-control form-control-sm">
                </div>
                <div class="col-auto period-range">
                    <label class="form-label small fw-semibold text-secondary mb-1">To</label>
                    <input type="date" name="to" value="{{ $period->to->toDateString() }}" class="form-control form-control-sm">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Show</button>
                </div>
                <div class="col text-md-end small text-secondary">Showing <strong class="text-dark">{{ $period->label }}</strong></div>
            </form>
        </div>
    </div>

    {{-- Summary --}}
    <div class="row g-3 mb-4" data-live="personnel-log-summary">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100 bg-primary text-white">
                <div class="card-body">
                    <div class="small opacity-75 text-uppercase fw-bold">Salary (paid hours)</div>
                    <div class="h3 fw-bold mb-0">₱{{ number_format($totals->pay, 2) }}</div>
                    <div class="small opacity-75">{{ number_format($totals->paid_hours, 2) }} hrs × ₱{{ number_format((float) $personnel->hourly_rate, 2) }}</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <div class="small text-secondary text-uppercase fw-bold">Duty days</div>
                    <div class="h3 fw-bold mb-0">{{ $totals->days }}</div>
                    <div class="small text-secondary">{{ $totals->sessions }} session(s) &middot; {{ $totals->late }} late</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <div class="small text-secondary text-uppercase fw-bold">Incident responses</div>
                    <div class="h3 fw-bold mb-0">{{ $responses->count() }}</div>
                    <div class="small text-secondary">reports and SOS joined</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-3 h-100">
                <div class="card-body">
                    <div class="small text-secondary text-uppercase fw-bold">Solved cases</div>
                    <div class="h3 fw-bold mb-0 text-success">{{ $solved->count() }}</div>
                    <div class="small text-secondary">confirmed resolved</div>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-0" role="tablist">
        <li class="nav-item"><button class="nav-link active fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-duty" type="button">Duty time in/out</button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-responses" type="button">Incident responses <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $responses->count() }}</span></button></li>
        <li class="nav-item"><button class="nav-link fw-semibold" data-bs-toggle="tab" data-bs-target="#tab-solved" type="button">Solved cases <span class="badge bg-success-subtle text-success-emphasis">{{ $solved->count() }}</span></button></li>
    </ul>

    <div class="tab-content card border-0 border-top-0 shadow-sm rounded-bottom-3">
        {{-- Duty: assigned shift vs. actual time in/out --}}
        <div class="tab-pane fade show active" id="tab-duty" data-live="personnel-log-duty">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-secondary text-uppercase small fw-bold">
                        <tr>
                            <th class="px-4 py-3">Date</th>
                            <th class="py-3">Assigned shift</th>
                            <th class="py-3">Timed in – Timed out</th>
                            <th class="py-3">Station</th>
                            <th class="py-3 text-end">Paid hours</th>
                            <th class="px-4 py-3 text-end">Pay</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($duties as $d)
                            <tr>
                                <td class="px-4 fw-semibold">{{ $d->date->format('D, M j, Y') }}</td>
                                <td>
                                    @if($d->has_schedule)
                                        {{ $d->shift_start->format('h:i A') }} – {{ $d->shift_end->format('h:i A') }}
                                    @else
                                        <span class="text-danger small">No assigned shift</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold">{{ $d->time_in->format('h:i A') }}</span>
                                    @if($d->status === 'Late')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Late</span>
                                    @endif
                                    –
                                    @if($d->time_out)
                                        <span class="fw-semibold">{{ $d->time_out->format('h:i A') }}</span>
                                    @else
                                        <span class="badge bg-success">On duty</span>
                                    @endif
                                </td>
                                <td class="small text-secondary">{{ $d->location }}</td>
                                <td class="text-end">
                                    @if($d->paid_hours !== null)
                                        <span class="fw-semibold">{{ number_format($d->paid_hours, 2) }}</span>
                                        @if($d->actual_hours != $d->paid_hours)
                                            <div class="small text-muted">clocked {{ number_format($d->actual_hours, 2) }}</div>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="px-4 text-end fw-semibold">{{ $d->pay !== null ? '₱' . number_format($d->pay, 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-5">No duty records for {{ $period->label }}.</td></tr>
                        @endforelse
                    </tbody>
                    @if($duties->isNotEmpty())
                        <tfoot class="bg-light fw-bold">
                            <tr>
                                <td class="px-4" colspan="4">Total</td>
                                <td class="text-end">{{ number_format($totals->paid_hours, 2) }}</td>
                                <td class="px-4 text-end">₱{{ number_format($totals->pay, 2) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
            <div class="small text-muted px-4 py-2 border-top">
                Paid hours count only the time inside the assigned shift (coming early or leaving late is not paid).
            </div>
        </div>

        {{-- Incident responses --}}
        <div class="tab-pane fade" id="tab-responses">
            @include('admin.personnels._incidents', ['incidents' => $responses, 'empty' => "No incident responses for {$period->label}."])
        </div>

        {{-- Solved cases --}}
        <div class="tab-pane fade" id="tab-solved">
            @include('admin.personnels._incidents', ['incidents' => $solved, 'empty' => "No solved cases for {$period->label}."])
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const mode = document.getElementById('period-mode');
        const sync = () => {
            const range = mode.value === 'range';
            document.querySelectorAll('.period-range').forEach(el => el.classList.toggle('d-none', !range));
            document.querySelectorAll('.period-month').forEach(el => el.classList.toggle('d-none', range));
        };
        mode.addEventListener('change', sync);
        sync();
        // Only send the fields of the chosen mode
        document.getElementById('period-form').addEventListener('submit', () => {
            document.querySelectorAll('#period-form .d-none input').forEach(i => i.disabled = true);
        });
    })();
</script>
@endpush
@endsection
