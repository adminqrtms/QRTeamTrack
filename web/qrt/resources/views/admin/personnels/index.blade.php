@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">QRT Personnel Management</h2>
            <p class="text-muted small mb-0">Manage and monitor your emergency response team members.</p>
        </div>
        <div class="btn-group">
            <a href="{{ route('admin.personnels.batch_create') }}" class="btn btn-outline-secondary shadow-sm me-2">
                <i class="fas fa-users me-1"></i> Batch Create
            </a>
            <a href="{{ route('admin.personnels.monitor') }}" class="btn btn-outline-primary shadow-sm me-2">
                <i class="fas fa-map-marker-alt me-1"></i> GPS Monitor
            </a>
            <a href="{{ route('admin.personnels.create') }}" class="btn btn-success shadow-sm">
                <i class="fas fa-plus me-1"></i> Add Personnel
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('generated_accounts'))
        @php session()->keep(['generated_accounts']); @endphp
        <div class="card border-0 shadow-sm mb-4 border-start border-4 border-info">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-info"><i class="fas fa-key me-2"></i> Batch Credentials Created</h5>
                <p class="text-muted small mb-0">Please export these credentials now. For security reasons, they will not be shown again after you leave or refresh this page.</p>
                <div class="mt-3">
                    <a href="{{ route('admin.personnels.exportGeneratedAccounts') }}" class="btn btn-sm btn-success shadow-sm me-2">
                        <i class="fas fa-file-excel me-1"></i> Export to Excel (.xlsx)
                    </a>
                    <!-- <button type="button" id="copy-all-btn" class="btn btn-sm btn-outline-info shadow-sm">
                        <i class="fas fa-copy me-1"></i> Copy All Credentials
                    </button> -->
                </div>
            </div>
            <!-- <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Name</th>
                            <th>Email Address</th>
                            <th class="pe-4">Generated Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(session('generated_accounts') as $account)
                            <tr>
                                <td class="ps-4 fw-bold">{{ $account['name'] }}</td>
                                <td><code>{{ $account['email'] }}</code></td>
                                <td class="pe-4 d-flex align-items-center">
                                    <code class="text-primary fw-bold me-2" id="password-{{ $loop->index }}">{{ $account['password'] }}</code>
                                    <button type="button" class="btn btn-sm btn-outline-secondary copy-password-btn" data-password-target="password-{{ $loop->index }}" title="Copy Password">Copy
                                        <i class="far fa-copy"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div> -->
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-uppercase small fw-bold">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="py-3">Contact Info</th>
                        <th class="py-3">Rate</th>
                        <th class="py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($personnel as $p)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="d-flex align-items-center">
                                    <img src="{{ $p->avatar ? asset('storage/' . $p->avatar) : 'https://ui-avatars.com/api/?name=' . urlencode($p->name) . '&background=random' }}" 
                                         alt="Avatar" class="rounded-circle me-3 border shadow-sm" style="width: 40px; height: 40px; object-fit: cover;">
                                    <span class="fw-bold text-dark">{{ $p->name }}</span>
                                </div>
                            </td>
                            <td class="py-3">
                                <div class="small fw-medium text-dark">{{ $p->email }}</div>
                                <div class="text-muted small">{{ $p->phone_number ?? 'No phone' }}</div>
                            </td>
                            <td class="py-3">
                                <span class="fw-bold text-primary">₱{{ number_format($p->hourly_rate, 2) }}</span><small class="text-muted">/hr</small>
                            </td>
                            <td class="py-3 text-center">
                                <form action="{{ route('admin.personnels.toggleStatus', $p->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    <button type="submit" class="btn btn-sm rounded-pill px-3 fw-bold {{ $p->is_active ? 'btn-success' : 'btn-danger' }}" style="--bs-btn-padding-y: .25rem; --bs-btn-font-size: .75rem;">
                                        {{ $p->is_active ? 'Active' : 'Inactive' }}
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="{{ route('admin.personnels.attendance', $p->id) }}" class="btn btn-sm btn-outline-secondary" title="View Attendance">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    </a>
                                    <a href="{{ route('admin.personnels.edit', $p->id) }}" class="btn btn-sm btn-outline-primary" title="Edit">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                                    </a>
                                    <form action="{{ route('admin.personnels.destroy', $p->id) }}" method="POST" onsubmit="return confirm('Are you sure?');" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        {{-- <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button> --}}
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-5 text-center text-muted">No personnel found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="mt-4 d-flex justify-content-center">
        {{ $personnel->links('pagination::bootstrap-5') }}
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.copy-password-btn').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.dataset.passwordTarget;
            const passwordElement = document.getElementById(targetId);
            if (passwordElement) {
                const passwordText = passwordElement.innerText;
                navigator.clipboard.writeText(passwordText).then(() => {
                    alert('Password copied to clipboard!');
                }).catch(err => {
                    console.error('Failed to copy password: ', err);
                });
            }
        });
    });

    // Copy All Logic
    const copyAllBtn = document.getElementById('copy-all-btn');
    if (copyAllBtn) {
        copyAllBtn.addEventListener('click', function() {
            let allData = "BATCH PERSONNEL CREDENTIALS\n==========================\n\n";
            @if(session('generated_accounts'))
                @foreach(session('generated_accounts') as $account)
                    allData += "Name: {{ $account['name'] }}\nEmail: {{ $account['email'] }}\nPassword: {{ $account['password'] }}\n--------------------------\n";
                @endforeach
            @endif
            
            navigator.clipboard.writeText(allData).then(() => {
                alert('All credentials copied to clipboard!');
            }).catch(err => {
                console.error('Error copying text: ', err);
            });
        });
    }
});
</script>
@endpush
@endsection
