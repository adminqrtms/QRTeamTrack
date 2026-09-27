@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">{{ __('Add New Duty Location') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('admin.locations.store') }}">
                        @csrf

                        <div class="mb-3">
                            <label for="location_name" class="form-label">Location Name</label>
                            <input type="text" class="form-control @error('location_name') is-invalid @enderror" id="location_name" name="location_name" value="{{ old('location_name') }}" required placeholder="e.g. Police Outpost 1, Community Center">
                            @error('location_name')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="barangay" class="form-label">Barangay</label>
                            <input type="text" class="form-control @error('barangay') is-invalid @enderror" id="barangay" name="barangay" value="{{ old('barangay') }}" required placeholder="e.g. San Jose">
                            @error('barangay')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="number" step="any" class="form-control @error('latitude') is-invalid @enderror" id="latitude" name="latitude" value="{{ old('latitude') }}" required readonly>
                                @error('latitude')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="number" step="any" class="form-control @error('longitude') is-invalid @enderror" id="longitude" name="longitude" value="{{ old('longitude') }}" required readonly>
                                @error('longitude')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Click on the map to set coordinates</label>
                            <div id="map" style="height: 300px; border-radius: 8px;" class="border"></div>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="{{ route('admin.locations.index') }}" class="btn btn-light border">Back to List</a>
                            <button type="submit" class="btn btn-primary px-4">Save Location</button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="mt-3 small text-muted">
                Tip: Use coordinates to help the system calculate the nearest personnel during emergencies.
            </div>
        </div>
    </div>
</div>

{{-- Leaflet CSS & JS for Map Picker --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Default coordinates (e.g., center of your area or the Philippines)
        const defaultLat = 7.2186412178918795;
        const defaultLng = 124.23833719169606;

        const map = L.map('map').setView([defaultLat, defaultLng], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        let marker;

        map.on('click', function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;

            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker([lat, lng]).addTo(map);
            }

            document.getElementById('latitude').value = lat.toFixed(8);
            document.getElementById('longitude').value = lng.toFixed(8);

            // Reverse Geocoding to get Barangay and Location Name
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data.address) {
                        const addr = data.address;
                        
                        // Attempt to find Barangay (usually suburb, neighbourhood, or village in PH)
                        const barangay = addr.suburb || addr.neighbourhood || addr.village || addr.quarter || '';
                        document.getElementById('barangay').value = barangay;

                        // Attempt to find a Location Name (building, amenity, or road)
                        const locName = addr.amenity || addr.building || addr.house_number || addr.road || '';
                        if (locName) {
                            document.getElementById('location_name').value = locName;
                        }
                    }
                })
                .catch(error => console.error('Geocoding error:', error));
        });

        // Optional: Get current location of the admin
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                map.setView([pos.coords.latitude, pos.coords.longitude], 15);
            });
        }
    });
</script>
@endsection
