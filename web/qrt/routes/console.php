<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Incidents the resident didn't confirm within 24 hours resolve by themselves.
// (Also done whenever a list is opened, so it works on a PC without a scheduler.)
Schedule::call(fn () => \App\Services\IncidentWorkflow::autoResolveOverdue())->hourly();
