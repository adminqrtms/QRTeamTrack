{{-- Fields shared by Add and Edit Personnel. $personnel is null when adding. --}}
@php
    $p = $personnel ?? null;
    $isEdit = (bool) $p;
    // New personnel default to Cotabato City; existing ones keep their saved address
    $province = old('province', $p?->province ?? ($isEdit ? '' : 'Maguindanao del Norte'));
    $city = old('city', $p?->city ?? ($isEdit ? '' : 'Cotabato City'));
    $barangay = old('barangay', $p?->barangay ?? '');
@endphp

<p class="small text-muted mb-3">Fields marked <span class="text-danger fw-bold">*</span> are required.</p>

<h6 class="fw-bold text-secondary small text-uppercase mb-2">Name</h6>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <label for="first_name" class="form-label fw-bold small required">First Name</label>
        <input type="text" name="first_name" id="first_name" value="{{ old('first_name', $p?->first_name) }}"
               class="form-control @error('first_name') is-invalid @enderror" maxlength="100" required autocomplete="given-name">
        @error('first_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="middle_name" class="form-label fw-bold small">Middle Name <span class="fw-normal text-muted">(optional)</span></label>
        <input type="text" name="middle_name" id="middle_name" value="{{ old('middle_name', $p?->middle_name) }}"
               class="form-control @error('middle_name') is-invalid @enderror" maxlength="100" autocomplete="additional-name">
        @error('middle_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="last_name" class="form-label fw-bold small required">Last Name</label>
        <input type="text" name="last_name" id="last_name" value="{{ old('last_name', $p?->last_name) }}"
               class="form-control @error('last_name') is-invalid @enderror" maxlength="100" required autocomplete="family-name">
        @error('last_name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<h6 class="fw-bold text-secondary small text-uppercase mb-2">Contact</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="email" class="form-label fw-bold small required">Email Address</label>
        <input type="email" name="email" id="email" value="{{ old('email', $p?->email) }}"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-6">
        <label for="phone_number" class="form-label fw-bold small required">Phone Number</label>
        <input type="text" name="phone_number" id="phone_number" value="{{ old('phone_number', $p?->phone_number) }}"
               class="form-control @error('phone_number') is-invalid @enderror" inputmode="numeric"
               pattern="09[0-9]{9}" placeholder="09XXXXXXXXX" required
               title="11 digits starting with 09">
        <div class="form-text small">11 digits, starting with 09.</div>
        @error('phone_number') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<h6 class="fw-bold text-secondary small text-uppercase mb-2">Address</h6>
<div class="row g-3 mb-4" id="address-fields"
     data-source="{{ asset('data/ph-addresses.json') }}"
     data-province="{{ $province }}" data-city="{{ $city }}" data-barangay="{{ $barangay }}">
    <div class="col-md-4">
        <label for="province" class="form-label fw-bold small required">Province</label>
        <select name="province" id="province" class="form-select @error('province') is-invalid @enderror" required>
            <option value="">Loading...</option>
        </select>
        @error('province') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="city" class="form-label fw-bold small required">City / Municipality</label>
        <select name="city" id="city" class="form-select @error('city') is-invalid @enderror" required disabled>
            <option value="">Choose province first</option>
        </select>
        @error('city') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-md-4">
        <label for="barangay" class="form-label fw-bold small required">Barangay</label>
        <select name="barangay" id="barangay" class="form-select @error('barangay') is-invalid @enderror" required disabled>
            <option value="">Choose city first</option>
        </select>
        @error('barangay') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <label for="street" class="form-label fw-bold small">House No. / Street / Purok <span class="fw-normal text-muted">(optional)</span></label>
        <input type="text" name="street" id="street" value="{{ old('street', $p?->street) }}"
               class="form-control @error('street') is-invalid @enderror" maxlength="255"
               placeholder="e.g. 123 Sinsuat Avenue, Purok 2">
        @if($isEdit && !$p->street && $p->address)
            <div class="form-text small">Previously saved address: {{ $p->address }}</div>
        @endif
        @error('street') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<h6 class="fw-bold text-secondary small text-uppercase mb-2">Pay and Photo</h6>
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="hourly_rate" class="form-label fw-bold small required">Hourly Rate (₱)</label>
        <div class="input-group has-validation">
            <span class="input-group-text">₱</span>
            <input type="text" inputmode="numeric" pattern="[0-9]{2,3}"
                   data-min="{{ \App\Http\Controllers\Admin\PersonnelController::MIN_HOURLY_RATE }}"
                   data-max="{{ \App\Http\Controllers\Admin\PersonnelController::MAX_HOURLY_RATE }}"
                   name="hourly_rate" id="hourly_rate"
                   value="{{ old('hourly_rate', $p?->hourly_rate !== null ? (int) $p->hourly_rate : '') }}"
                   class="form-control @error('hourly_rate') is-invalid @enderror" placeholder="50 to 100" required>
            <span class="input-group-text">/ hour</span>
            @error('hourly_rate') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="form-text small">
            Whole pesos from ₱{{ \App\Http\Controllers\Admin\PersonnelController::MIN_HOURLY_RATE }} to ₱{{ \App\Http\Controllers\Admin\PersonnelController::MAX_HOURLY_RATE }} per hour.
            Cotabato City minimum wage is ₱436/day (₱461 from Dec 1, 2026), about ₱55–58/hour for 8 hours.
        </div>
    </div>
    <div class="col-md-6">
        <label for="avatar" class="form-label fw-bold small">{{ $isEdit ? 'Update Photo' : 'Profile Picture' }} <span class="fw-normal text-muted">(optional)</span></label>
        <input type="file" name="avatar" id="avatar" accept="image/png,image/jpeg" class="form-control @error('avatar') is-invalid @enderror">
        <div class="form-text small">JPG or PNG, up to 2 MB.</div>
        @error('avatar') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
</div>

<h6 class="fw-bold text-secondary small text-uppercase mb-2">{{ $isEdit ? 'Change Password' : 'Password' }}</h6>
@if($isEdit)
    <p class="small text-muted mb-2">Leave both empty to keep the current password.</p>
@endif
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <label for="password" class="form-label fw-bold small {{ $isEdit ? '' : 'required' }}">{{ $isEdit ? 'New Password' : 'Password' }}</label>
        <input type="password" name="password" id="password" autocomplete="new-password"
               class="form-control @error('password') is-invalid @enderror" {{ $isEdit ? '' : 'required' }}>
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <ul class="password-rules small ps-0 mt-2 mb-0" id="password-rules">
            <li data-rule="length">At least 8 characters</li>
            <li data-rule="upper">At least 1 capital letter (A–Z)</li>
            <li data-rule="symbol">At least 1 symbol (e.g. ! @ # $ %)</li>
        </ul>
    </div>
    <div class="col-md-6">
        <label for="password_confirmation" class="form-label fw-bold small {{ $isEdit ? '' : 'required' }}">Confirm Password</label>
        <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password"
               class="form-control" {{ $isEdit ? '' : 'required' }}>
        <div class="small mt-2" id="password-match"></div>
    </div>
</div>

@push('scripts')
<script src="{{ asset('js/personnel-form.js') }}" defer></script>
@endpush
