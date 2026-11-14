{{-- Names of everyone who responded to $incident (report or SOS). --}}
@if($incident->responders->isEmpty())
    <span class="text-muted">No responder yet</span>
@else
    @foreach($incident->responders as $r)
        <a href="{{ route('admin.personnels.attendance', $r->id) }}" class="d-block text-decoration-none fw-semibold">{{ $r->name }}</a>
    @endforeach
@endif
