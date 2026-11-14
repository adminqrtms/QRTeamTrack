<?php

namespace App\Support;

/** Friendly labels and badge colours for report / SOS statuses. */
class IncidentStatus
{
    private const LABELS = [
        'pending' => ['Pending', 'secondary'],
        'triggered' => ['SOS sent', 'danger'],
        'ongoing' => ['Ongoing', 'primary'],
        'in_progress' => ['Ongoing', 'primary'],
        'responding' => ['Responding', 'primary'],
        'responded' => ['Responders on site', 'primary'],
        'awaiting_confirmation' => ["Waiting for resident's confirmation", 'warning'],
        'resolved' => ['Resolved', 'success'],
        'closed' => ['Closed', 'dark'],
        'false_alarm' => ['False alarm', 'secondary'],
    ];

    public static function label(?string $status): string
    {
        return self::LABELS[$status][0] ?? ucfirst(str_replace('_', ' ', (string) $status));
    }

    public static function color(?string $status): string
    {
        return self::LABELS[$status][1] ?? 'secondary';
    }

    /** Small Bootstrap badge, e.g. in tables. */
    public static function badge(?string $status, ?string $method = null): string
    {
        $label = e(self::label($status));
        $color = self::color($status);
        $note = $status === 'resolved' && $method === 'auto' ? ' <span class="opacity-75">(auto, no reply in 24 h)</span>'
            : ($status === 'resolved' && $method === 'confirmed' ? ' <span class="opacity-75">(confirmed by resident)</span>' : '');

        return "<span class=\"badge rounded-pill bg-{$color}-subtle text-{$color}-emphasis border border-{$color}-subtle\">{$label}{$note}</span>";
    }
}
