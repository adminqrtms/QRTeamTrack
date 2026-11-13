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

    <div class="card border-0 shadow-sm rounded-3 mb-4">
        <div class="card-body d-flex flex-column flex-md-row align-items-center gap-4">
            <div class="p-2 bg-white rounded-3 border flex-shrink-0" style="width: 216px;">{!! $downloadQr !!}</div>
            <div class="flex-grow-1 w-100">
                <h6 class="fw-bold mb-1">Download link for new users</h6>
                <p class="small text-secondary mb-2">
                    Anyone can scan this QR code or open the link on an Android phone to install the newest version.
                    Show it on screen during a demo, or send the link in a group chat.
                </p>
                <div class="input-group input-group-sm mb-2">
                    <input type="text" class="form-control" id="download-link" value="{{ $downloadUrl }}" readonly>
                    <button class="btn btn-outline-secondary" type="button" id="copy-download-link">Copy</button>
                    <a class="btn btn-outline-primary" href="{{ $downloadUrl }}" target="_blank" rel="noopener">Open page</a>
                </div>
                @if($localOnly)
                    <div class="small text-danger">
                        This link only works on this PC. Run <code>setup-ngrok.bat</code> so phones can open it from anywhere.
                    </div>
                @elseif($releases->isEmpty())
                    <div class="small text-warning-emphasis">Publish a version first (see below), or the page has nothing to download.</div>
                @endif
            </div>
        </div>
    </div>

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
@push('scripts')
<script>
document.getElementById('copy-download-link')?.addEventListener('click', function () {
    const input = document.getElementById('download-link');
    navigator.clipboard.writeText(input.value).then(() => {
        this.textContent = 'Copied!';
        setTimeout(() => (this.textContent = 'Copy'), 1500);
    }).catch(() => { input.select(); });
});
</script>
@endpush
@endsection
