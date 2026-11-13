@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="mb-4">
        <h2 class="h3 fw-bold text-dark mb-1">App Updates</h2>
        <p class="text-secondary mb-0">Versions of the mobile app. Installed apps offer the newest one when they open.</p>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-3 mb-4 border-start border-4 border-info">
        <div class="card-body">
            <h6 class="fw-bold mb-2"><i class="fas fa-upload me-1 text-info"></i> How to publish a new version</h6>
            <ol class="small text-secondary mb-0 ps-3">
                <li>On this PC, run <code>build-apk.bat</code> in the QRTeamTrack folder.</li>
                <li>When it asks <strong>Publish this as an update?</strong>, type <code>y</code>, then type what changed (one change per line).</li>
                <li>Phones see <strong>"Update available"</strong> the next time they open the app, then tap <strong>Update now</strong>.</li>
            </ol>
        </div>
    </div>

    @forelse($releases as $release)
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h5 class="fw-bold mb-1">
                            Version {{ $release->version_name }}
                            @if($loop->first)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill ms-1" style="font-size: .7rem;">Newest</span>
                            @endif
                            @if($release->required)
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill ms-1" style="font-size: .7rem;">Required</span>
                            @endif
                        </h5>
                        <div class="small text-secondary">
                            Build {{ $release->version_code }} &middot; {{ $release->sizeLabel() }} &middot;
                            published {{ $release->created_at->format('M j, Y g:i A') }}
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ url("/api/app/releases/{$release->id}/download") }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-download me-1"></i> Download APK
                        </a>
                        <form action="{{ route('admin.app-releases.destroy', $release) }}" method="POST"
                              onsubmit="return confirm('Delete version {{ $release->version_name }}? Phones that already installed it keep it.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="fas fa-trash me-1"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>

                <form action="{{ route('admin.app-releases.update', $release) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <label for="notes-{{ $release->id }}" class="form-label small fw-semibold text-secondary">What's new (one change per line)</label>
                    <textarea id="notes-{{ $release->id }}" name="notes" rows="3" class="form-control form-control-sm mb-2"
                              placeholder="e.g. Chat photos now load">{{ $release->notes }}</textarea>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="required" value="1" id="required-{{ $release->id }}" @checked($release->required)>
                            <label class="form-check-label small" for="required-{{ $release->id }}">
                                Required: users can't skip this update
                            </label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body text-center text-muted py-5">
                No versions published yet. Run <code>build-apk.bat</code> and answer <code>y</code> to publish one.
            </div>
        </div>
    @endforelse
</div>
@endsection
