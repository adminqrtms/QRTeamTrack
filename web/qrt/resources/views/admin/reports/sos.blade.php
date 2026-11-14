@extends('layouts.app')

@php use App\Support\IncidentStatus; @endphp

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0"><span class="badge bg-danger me-2">SOS</span>#SOS-{{ $alarm->id }}</h2>
            <p class="text-muted mb-0">Emergency sent from the mobile app on {{ $alarm->created_at->format('M d, Y h:i A') }}</p>
        </div>
        <a href="{{ route('admin.reports.index', ['type' => 'sos']) }}" class="btn btn-outline-secondary btn-sm">&larr; Back to Reports</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">SOS Information</h5>
                    {!! IncidentStatus::badge($alarm->status, $alarm->resolution_method) !!}
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase fw-bold">Station</div>
                            <div>{{ $alarm->location->location_name ?? 'N/A' }}</div>
                        </div>
                        <div class="col-md-6">
                            <div class="small text-muted text-uppercase fw-bold">GPS location</div>
                            <a href="https://www.google.com/maps?q={{ $alarm->latitude }},{{ $alarm->longitude }}" target="_blank" rel="noopener">
                                {{ $alarm->latitude }}, {{ $alarm->longitude }}
                            </a>
                        </div>
                        <div class="col-12">
                            <div class="small text-muted text-uppercase fw-bold">Action taken</div>
                            <div>{{ $alarm->action_taken ?: 'No notes from the responders yet.' }}</div>
                        </div>
                    </div>

                    <h6 class="fw-bold mt-4 mb-2">Timeline</h6>
                    <ul class="list-unstyled small mb-0">
                        <li class="mb-1">🔴 SOS sent &middot; {{ $alarm->created_at->format('M d, Y h:i A') }}</li>
                        @if($alarm->responded_at)
                            <li class="mb-1">🚑 First responder on the way &middot; {{ $alarm->responded_at->format('M d, Y h:i A') }}</li>
                        @endif
                        @if($alarm->resolution_requested_at)
                            <li class="mb-1">⏳ Marked resolved by {{ $alarm->resolutionRequester->name ?? 'a responder' }} &middot; {{ $alarm->resolution_requested_at->format('M d, Y h:i A') }}</li>
                        @endif
                        @if($alarm->resolved_at)
                            <li class="mb-1">✅ Resolved ({{ $alarm->resolution_method === 'auto' ? 'automatically, no reply in 24 h' : 'confirmed by the resident' }}) &middot; {{ $alarm->resolved_at->format('M d, Y h:i A') }}</li>
                        @endif
                    </ul>
                    @if($alarm->isAwaitingConfirmation())
                        <div class="alert alert-warning small mt-3 mb-0">
                            Waiting for the resident to confirm; it resolves automatically on
                            {{ $alarm->resolution_requested_at->copy()->addHours(\App\Services\IncidentWorkflow::AUTO_RESOLVE_HOURS)->format('M j, g:i A') }} if there is no reply.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Sent By</h6>
                    <div class="fw-bold text-dark">{{ $alarm->user->name ?? 'Unknown' }}</div>
                    <div class="small text-muted">{{ $alarm->user->phone_number ?? 'No phone listed' }}</div>
                    <div class="small text-muted">{{ $alarm->user->address ?? '' }}</div>
                    @if($alarm->user)
                        <a href="{{ route('admin.residents.show', $alarm->user->id) }}" class="small text-decoration-none">Resident profile</a>
                    @endif
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="text-muted small text-uppercase fw-bold mb-3">Response Team</h6>
                    @forelse($alarm->responders as $member)
                        <div class="d-flex align-items-center {{ $loop->last ? '' : 'mb-3' }}">
                            <img src="{{ $member->avatar ? asset('storage/' . $member->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($member->name) }}"
                                 class="rounded-circle border me-3" width="44" height="44" style="object-fit: cover;" alt="">
                            <div>
                                <div class="fw-bold text-dark">{{ $member->name }}</div>
                                <div class="small text-muted">Joined {{ \Carbon\Carbon::parse($member->pivot->joined_at)->format('M j, g:i A') }}
                                    &middot; <a href="{{ route('admin.personnels.attendance', $member->id) }}" class="text-decoration-none">Log</a></div>
                            </div>
                        </div>
                    @empty
                        <p class="small text-muted mb-0">No responder yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
