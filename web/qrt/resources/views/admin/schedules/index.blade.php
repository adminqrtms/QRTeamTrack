@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="h3 fw-bold text-dark">Duty Schedules</h2>
        <a href="{{ route('admin.schedules.create') }}" class="btn btn-primary">
            Assign New Duty
        </a>
    </div>

    <!-- Occupancy Summary -->
    <div class="row mb-4">
        @foreach($locations as $loc)
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body py-3">
                        <h6 class="text-muted small text-uppercase fw-bold mb-1">{{ $loc->location_name }}</h6>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="h4 mb-0 fw-bold">{{ $loc->schedules_count }} <small class="text-muted h6">/ 10</small></span>
                            <span class="badge {{ $loc->schedules_count >= 10 ? 'bg-danger' : ($loc->schedules_count >= 5 ? 'bg-warning' : 'bg-success') }}">Personnel</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <ul class="mb-0 list-unstyled">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <h3 class="h5 fw-bold mb-3 px-1">Schedule List</h3>
    <div class="card shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light text-uppercase small fw-bold">
                    <tr>
                        <th class="px-4 py-3">Personnel</th>
                        <th class="py-3">Location</th>
                        <th class="py-3">Dates</th>
                        <th class="py-3">Shift Time</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($schedules as $s)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="fw-semibold">{{ $s->user->name ?? 'N/A' }}</span>
                            </td>
                            <td class="py-3">
                                <span class="text-primary fw-medium">{{ $s->location->location_name ?? 'N/A' }}</span>
                                <div class="d-flex align-items-center">
                                    <span class="small text-muted me-2">{{ $s->location->barangay ?? '' }}</span>
                                    <span class="badge bg-light text-dark border-0 small p-0" title="Current total assigned here">({{ $locations->where('id', $s->location_id)->first()->schedules_count ?? 0 }} assigned)</span>
                                </div>
                            </td>
                            <td class="py-3">
                                <div class="small">{{ $s->schedule_date_start->format('M d, Y') }}</div>
                                <div class="small text-muted">to {{ $s->schedule_date_end->format('M d, Y') }}</div>
                            </td>
                            <td class="py-3">
                                <span class="badge bg-light text-dark border">
                                    {{ $s->start_time->format('h:i A') }} - {{ $s->end_time->format('h:i A') }}
                                </span>
                            </td>
                            <td class="py-3 text-center">
                                <span class="badge rounded-pill {{ $s->status === 'active' ? 'bg-success' : 'bg-secondary' }}">
                                    {{ ucfirst($s->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <form action="{{ route('admin.schedules.destroy', $s->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this schedule?');" class="d-inline-flex">
                                    <a href="{{ route('admin.schedules.edit', $s->id) }}" class="btn btn-sm btn-outline-primary me-2">
                                        Edit
                                    </a>
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-5 text-center text-muted">No schedules found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white border-bottom-0 pt-4 px-4">
            <h3 class="h5 fw-bold mb-0">Calendar View</h3>
        </div>
        <div class="card-body p-4">
            <div id="calendar" style="min-height: 600px;"></div>
        </div>
    </div>

    <!-- Schedule Details Modal -->
    <div class="modal fade" id="scheduleDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light border-bottom-0 py-3">
                    <h5 class="modal-title fw-bold text-dark">Duty Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-4 text-center">
                        <label class="small text-muted d-block text-uppercase fw-bold mb-1">Personnel Assigned</label>
                        <h4 id="modalPersonnel" class="fw-bold text-dark mb-0"></h4>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="small text-muted d-block text-uppercase fw-bold mb-1">Location</label>
                            <div class="p-2 bg-light rounded border">
                                <span id="modalLocation" class="text-primary fw-bold d-block"></span>
                                <span id="modalBarangay" class="small text-muted"></span>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted d-block text-uppercase fw-bold mb-1">Shift Start</label>
                            <div id="modalStart" class="fw-semibold text-dark"></div>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted d-block text-uppercase fw-bold mb-1">Shift End</label>
                            <div id="modalEnd" class="fw-semibold text-dark"></div>
                        </div>
                        <div class="col-12 mt-4 text-center">
                            <span id="modalStatus" class="badge rounded-pill px-4 py-2"></span>
                        </div>
                        <div class="col-12 text-center mt-4">
                            <a href="#" id="modalEditButton" class="btn btn-sm btn-outline-primary">Edit Schedule</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl) return;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listMonth'
            },
            events: [
                @foreach($schedules as $s)
                {
                    title: '{{ $s->user->name ?? "N/A" }} - {{ $s->location->location_name ?? "N/A" }}',
                    start: '{{ $s->schedule_date_start->format("Y-m-d") }}T{{ $s->start_time->format("H:i:s") }}',
                    end: '{{ $s->schedule_date_end->format("Y-m-d") }}T{{ $s->end_time->format("H:i:s") }}',
                    backgroundColor: '{{ $s->status === "active" ? "#0d6efd" : "#6c757d" }}',
                    borderColor: '{{ $s->status === "active" ? "#0d6efd" : "#6c757d" }}',
                    extendedProps: {
                        scheduleId: {{ $s->id }},
                        personnelName: '{{ $s->user->name ?? "N/A" }}',
                        location: '{{ $s->location->location_name ?? "N/A" }}',
                        barangay: '{{ $s->location->barangay ?? "" }}',
                        status: '{{ $s->status }}',
                        formattedStart: '{{ $s->schedule_date_start->format("M d, Y") }} ({{ $s->start_time->format("h:i A") }})',
                        formattedEnd: '{{ $s->schedule_date_end->format("M d, Y") }} ({{ $s->end_time->format("h:i A") }})'
                    }
                },
                @endforeach
            ],
            eventClick: function(info) {
                const props = info.event.extendedProps;
                
                // Fill modal content
                document.getElementById('modalPersonnel').textContent = props.personnelName;
                document.getElementById('modalLocation').textContent = props.location;
                document.getElementById('modalBarangay').textContent = props.barangay;
                document.getElementById('modalStart').textContent = props.formattedStart;
                document.getElementById('modalEnd').textContent = props.formattedEnd;
                
                // Handle Status Badge
                const statusBadge = document.getElementById('modalStatus');
                statusBadge.textContent = props.status.charAt(0).toUpperCase() + props.status.slice(1);
                statusBadge.className = 'badge rounded-pill px-4 py-2 ' + (props.status === 'active' ? 'bg-success' : 'bg-secondary');

                // Set the edit button link
                document.getElementById('modalEditButton').href = `/admin/schedules/${props.scheduleId}/edit`;

                // Show Bootstrap Modal
                var myModal = new bootstrap.Modal(document.getElementById('scheduleDetailsModal'));
                myModal.show();
            },
            eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' }
        });
        calendar.render();
    });
</script>
@endsection
