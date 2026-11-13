<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Download QRTeam Track</title>
    <link href="{{ asset('bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <style>
        body { background: #f4f6fb; min-height: 100vh; }
        .app-icon { width: 84px; height: 84px; border-radius: 22px; background: linear-gradient(135deg, #1e88e5, #1565c0);
                    display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 800; font-size: 1.6rem;
                    box-shadow: 0 8px 20px rgba(21, 101, 192, .35); }
        .qr svg { width: 200px; height: 200px; }
        .steps li { margin-bottom: .35rem; }
    </style>
</head>
<body>
<main class="container py-4 py-md-5" style="max-width: 860px;">
    <div class="row g-4 align-items-stretch">
        <div class="col-md-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div class="app-icon">QRT</div>
                        <div>
                            <h1 class="h3 fw-bold mb-1">QRTeam Track</h1>
                            <div class="text-secondary">Barangay Quick Response Team app for Android</div>
                        </div>
                    </div>

                    @if($release)
                        <div class="mb-3 small text-secondary">
                            Version <strong class="text-dark">{{ $release->version_name }}</strong>
                            &middot; {{ $release->sizeLabel() }}
                            &middot; updated {{ $release->created_at->format('M j, Y') }}
                        </div>

                        <a href="{{ url("/api/app/releases/{$release->id}/download") }}"
                           class="btn btn-primary btn-lg w-100 fw-bold rounded-3 mb-4">
                            Download the app
                        </a>

                        <h2 class="h6 fw-bold">How to install</h2>
                        <ol class="steps small text-secondary ps-3 mb-4">
                            <li>Tap <strong>Download the app</strong> and wait for it to finish.</li>
                            <li>Open the downloaded file <strong>QRTeamTrack-{{ $release->version_name }}.apk</strong>.</li>
                            <li>If Android asks, allow <strong>Install unknown apps</strong> for your browser, then go back.</li>
                            <li>Tap <strong>Install</strong>, then <strong>Open</strong>. New versions arrive inside the app.</li>
                        </ol>

                        @if(count($release->changeList()))
                            <h2 class="h6 fw-bold">What's new</h2>
                            <ul class="small text-secondary ps-3 mb-0">
                                @foreach($release->changeList() as $change)
                                    <li>{{ $change }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @else
                        <div class="alert alert-warning mb-0">
                            The app isn't available for download yet. Please check back later.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                    <h2 class="h6 fw-bold mb-1">Install on another phone</h2>
                    <p class="small text-secondary mb-3">Scan this code with the phone's camera.</p>
                    <div class="qr mx-auto mb-3 p-2 bg-white rounded-3 border">{!! $qr !!}</div>
                    <div class="small text-break"><a href="{{ $pageUrl }}">{{ $pageUrl }}</a></div>
                    <p class="small text-secondary mt-3 mb-0">Android phones only.</p>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
