{{-- Incident table for the personnel log ($incidents from App\Services\PersonnelLog). --}}
<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="bg-light text-secondary text-uppercase small fw-bold">
            <tr>
                <th class="px-4 py-3">Incident</th>
                <th class="py-3">Resident</th>
                <th class="py-3">Responded</th>
                <th class="py-3">Team</th>
                <th class="px-4 py-3">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($incidents as $i)
                <tr>
                    <td class="px-4">
                        <span class="badge {{ $i->type === 'SOS' ? 'bg-danger' : 'bg-info' }} me-1">{{ $i->type }}</span>
                        <span class="fw-semibold">{{ $i->label }}</span>
                        <div class="small text-muted">{{ $i->location }}</div>
                    </td>
                    <td>{{ $i->resident }}</td>
                    <td class="small">{{ $i->joined_at->format('M j, Y h:i A') }}</td>
                    <td class="small">
                        @if($i->teammates->isEmpty())
                            <span class="text-muted">Alone</span>
                        @else
                            with {{ $i->teammates->join(', ', ' and ') }}
                        @endif
                    </td>
                    <td class="px-4">
                        {!! \App\Support\IncidentStatus::badge($i->status, $i->resolution_method) !!}
                        @if($i->resolved_at)
                            <div class="small text-muted">{{ $i->resolved_at->format('M j, Y h:i A') }}</div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center text-muted py-5">{{ $empty }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
