@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<style>
    .personnel-popup { min-width: 180px; }

    .popup-avatar {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 50%;
    }

    .leaflet-popup-content-wrapper {
        border-radius: 12px;
        padding: 5px;
    }

    .avatar-marker {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        border: 3px solid white;
        overflow: hidden;
        box-shadow: 0 4px 10px rgba(0,0,0,0.25);
        background: #fff;
    }

    .avatar-marker img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
    }
</style>
@endpush

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark">QRT Personnel GPS Monitor</h2>
            <p class="text-secondary mb-0">Real-time location tracking of active personnel
                <span class="live-indicator text-success fw-semibold d-inline-flex align-items-center gap-1 ms-1"><span class="dot"></span> Live</span>
            </p>
        </div>
        <a href="{{ route('admin.personnels.index') }}" class="btn btn-outline-secondary px-4 fw-bold shadow-sm">
            &larr; Back to List
        </a>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-2">
            <!-- Map Container -->
            <div id="map" style="height: 650px; border-radius: 8px; z-index: 1; background: #f8f9fa;"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<!-- Leaflet JS -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const defaultLat = 7.2186412178918795;
    const defaultLng = 124.23833719169606;

    const map = L.map('map').setView([defaultLat, defaultLng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    setTimeout(() => {
        map.invalidateSize();
    }, 300);

    const markers = {}; // personnel id => Leaflet marker
    let hasFitted = false;

    function avatarUrlFor(person) {
        return person.avatar
            ? `/storage/${person.avatar}`
            : `https://ui-avatars.com/api/?name=${encodeURIComponent(person.name)}&background=random`;
    }

    function popupFor(person, avatarUrl) {
        return `
            <div class="personnel-popup d-flex align-items-center gap-3">
                <img src="${avatarUrl}" class="popup-avatar border shadow-sm">
                <div>
                    <div class="fw-bold text-dark">${person.name}</div>
                    <div class="text-muted small">${person.phone_number || 'No phone'}</div>
                    <div class="small text-secondary">
                        Last Seen: ${person.last_seen_time}
                    </div>
                    <div class="mt-1">
                        <span class="badge ${person.is_active ? 'bg-success' : 'bg-danger'} rounded-pill">
                            ${person.is_active ? 'Active' : 'Inactive'}
                        </span>
                    </div>
                </div>
            </div>
        `;
    }

    // Add, move or remove markers so the map matches the latest personnel list.
    function renderPersonnel(personnels) {
        const seen = new Set();
        const markerBounds = [];

        personnels.forEach(person => {
            if (person.last_latitude === null || person.last_longitude === null) return;

            const lat = parseFloat(person.last_latitude);
            const lng = parseFloat(person.last_longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const coords = [lat, lng];
            const avatarUrl = avatarUrlFor(person);
            seen.add(person.id);
            markerBounds.push(coords);

            if (markers[person.id]) {
                markers[person.id].setLatLng(coords);
                markers[person.id].setPopupContent(popupFor(person, avatarUrl));
                return;
            }

            // Custom avatar icon
            const avatarIcon = L.divIcon({
                className: '',
                html: `
                    <div class="avatar-marker">
                        <img src="${avatarUrl}" alt="${person.name}">
                    </div>
                `,
                iconSize: [50, 50],
                iconAnchor: [25, 25],
                popupAnchor: [0, -25]
            });

            const marker = L.marker(coords, { icon: avatarIcon })
                .addTo(map)
                .bindPopup(popupFor(person, avatarUrl));

            // Show name above marker
            marker.bindTooltip(person.name, {
                permanent: true,
                direction: 'top',
                offset: [0, -28]
            });

            markers[person.id] = marker;
        });

        Object.keys(markers).forEach(id => {
            if (!seen.has(Number(id))) {
                map.removeLayer(markers[id]);
                delete markers[id];
            }
        });

        // Only auto-fit once so the admin's own zoom/pan is not reset every refresh.
        if (!hasFitted && markerBounds.length > 0) {
            map.fitBounds(markerBounds, { padding: [50, 50] });
            hasFitted = true;
        }
    }

    renderPersonnel(@json($personnels));

    setInterval(async () => {
        if (document.hidden) return;
        try {
            const res = await fetch(window.location.href, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store',
                credentials: 'same-origin',
            });
            if (res.ok && !res.redirected) renderPersonnel(await res.json());
        } catch (e) {
            // Network hiccup: try again on the next tick.
        }
    }, 5000);
});
</script>
@endpush
