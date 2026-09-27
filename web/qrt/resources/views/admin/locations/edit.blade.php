@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">{{ __('Edit Duty Location') }}</div>

                <div class="card-body">
                    <form method="POST" action="{{ route('admin.locations.update', $location->id) }}">
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="location_name" class="form-label">Location Name</label>
                            <input type="text" class="form-control @error('location_name') is-invalid @enderror" id="location_name" name="location_name" value="{{ old('location_name', $location->location_name) }}" required>
                            @error('location_name')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="barangay" class="form-label">Barangay</label>
                            <input type="text" class="form-control @error('barangay') is-invalid @enderror" id="barangay" name="barangay" value="{{ old('barangay', $location->barangay) }}" required>
                            @error('barangay')
                                <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="latitude" class="form-label">Latitude</label>
                                <input type="number" step="any" class="form-control @error('latitude') is-invalid @enderror" id="latitude" name="latitude" value="{{ old('latitude', $location->latitude) }}" required readonly>
                                @error('latitude')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="longitude" class="form-label">Longitude</label>
                                <input type="number" step="any" class="form-control @error('longitude') is-invalid @enderror" id="longitude" name="longitude" value="{{ old('longitude', $location->longitude) }}" required readonly>
                                @error('longitude')
                                    <span class="invalid-feedback"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Click on the map to update coordinates</label>
                            <div id="map" style="height: 300px; border-radius: 8px;" class="border"></div>
                        </div>

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="{{ route('admin.locations.index') }}" class="btn btn-light border">Back to List</a>
                            <button type="submit" class="btn btn-primary px-4">Update Location</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Leaflet CSS & JS for Map Picker --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const initialLat = {{ $location->latitude }};
        const initialLng = {{ $location->longitude }};

        // Initialize map
        const map = L.map('map').setView([initialLat, initialLng], 15);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        // Initial Marker
        let marker = L.marker([initialLat, initialLng], {draggable: true}).addTo(map);

        function updateInputs(lat, lng) {
            document.getElementById('latitude').value = lat.toFixed(8);
            document.getElementById('longitude').value = lng.toFixed(8);

            // Fetch Address details
            fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data.address) {
                        const addr = data.address;
                        
                        // In the Philippines, barangays are often mapped as 'suburb' or 'neighbourhood'
                        const barangay = addr.suburb || addr.neighbourhood || addr.village || addr.quarter || '';
                        document.getElementById('barangay').value = barangay;

                        // Update location name if a specific place/road is found
                        const locName = addr.amenity || addr.building || addr.house_number || addr.road || '';
                        if (locName) {
                            document.getElementById('location_name').value = locName;
                        }
                    }
                })
                .catch(error => console.error('Geocoding error:', error));
        }

        // Update inputs when marker is dragged
        marker.on('dragend', function(event) {
            const position = marker.getLatLng();
            updateInputs(position.lat, position.lng);
        });

        // Update marker and inputs when map is clicked
        map.on('click', function(e) {
            const lat = e.latlng.lat;
            const lng = e.latlng.lng;
            marker.setLatLng([lat, lng]);
            updateInputs(lat, lng);
        });
    });
</script>
@endsection
