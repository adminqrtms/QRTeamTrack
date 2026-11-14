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
                <p class="text-muted mb-0">Comprehensive log of all reported incidents and emergencies. <span class="live-indicator text-success fw-semibold d-inline-flex align-items-center gap-1"><span class="dot"></span> Live</span></p>
            </div>
        </div>
    </div>

    @php
        $filterUrl = fn ($changes) => route('admin.reports.index', array_filter(array_merge(['type' => $type, 'status' => $status], $changes)));
        $typeLabels = [null => 'All', 'report' => 'Reports', 'sos' => 'SOS'];
        $statusLabels = [null => 'Any status', 'active' => 'Active', 'awaiting' => 'Waiting for resident', 'resolved' => 'Resolved'];
    @endphp

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-0 py-3 d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold">Reports and SOS <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $reports->total() }}</span></h5>
            <div class="d-flex flex-wrap gap-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="Type">
                    @foreach($typeLabels as $value => $label)
                        <a href="{{ $filterUrl(['type' => $value, 'page' => null]) }}"
                           class="btn {{ $type == $value ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                    @endforeach
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        {{ $statusLabels[$status] ?? 'Any status' }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        @foreach($statusLabels as $value => $label)
                            <li><a class="dropdown-item {{ $status == $value ? 'active' : '' }}" href="{{ $filterUrl(['status' => $value, 'page' => null]) }}">{{ $label }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Reference</th>
                            <th>Reporter</th>
                            <th>Status / Responders</th>
                            <th>Date Reported</th>
                            <th>Last Updated</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody data-live="reports-list">
                        @forelse($reports as $item)
                            <tr data-live-key="{{ $item->key }}">
                                <td class="ps-4">
                                    <span class="badge {{ $item->kind === 'sos' ? 'bg-danger' : 'bg-info' }} me-1">{{ $item->kind === 'sos' ? 'SOS' : 'Report' }}</span>
                                    <span class="fw-bold text-primary">{{ $item->ref }}</span>
                                    <div class="small text-dark">{{ $item->title }}</div>
                                </td>
                                <td>
                                    <span class="text-muted small">{{ $item->reporter }}</span>
                                </td>
                                <td>
                                    {!! \App\Support\IncidentStatus::badge($item->status, $item->method) !!}
                                    @if($item->responders->isNotEmpty())
                                        <div class="small text-muted mt-1">{{ $item->responders->join(', ') }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div class="small fw-bold">{{ $item->created_at->format('M d, Y') }}</div>
                                    <div class="text-muted smaller">{{ $item->created_at->format('h:i A') }}</div>
                                </td>
                                <td class="text-muted small">
                                    {{ $item->updated_at->diffForHumans() }}
                                </td>
                                <td class="text-end pe-4">
                                    <a href="{{ $item->url }}" class="btn btn-sm btn-outline-secondary">View Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-clipboard-x fs-1 d-block mb-3"></i>
                                    No reports or SOS found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($reports->hasPages())
            <div class="card-footer bg-white py-3" data-live="reports-pagination">
                {{ $reports->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
@endsection
