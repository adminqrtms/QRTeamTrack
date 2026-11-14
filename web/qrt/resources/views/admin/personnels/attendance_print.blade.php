<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Personnel Log – {{ $personnel->name }} – {{ $period->label }}</title>
    <style>
        @page { size: A4; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; margin: 0; background: #f2f2f2; }
        .sheet { background: #fff; max-width: 820px; margin: 16px auto; padding: 24px 28px; box-shadow: 0 2px 10px rgba(0,0,0,.08); }
        .toolbar { max-width: 820px; margin: 16px auto 0; display: flex; justify-content: flex-end; gap: 8px; }
        .toolbar button { font-size: 13px; padding: 8px 16px; border-radius: 6px; border: 1px solid #0d6efd; background: #0d6efd; color: #fff; cursor: pointer; }
        .toolbar button.secondary { background: #fff; color: #0d6efd; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        h1 { font-size: 17px; margin: 0 0 2px; }
        h2 { font-size: 13px; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: .03em; }
        .muted { color: #555; }
        .info { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px 16px; margin-bottom: 4px; }
        .info b { display: inline-block; min-width: 82px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #eee; font-size: 10px; text-transform: uppercase; }
        td.num, th.num { text-align: right; white-space: nowrap; }
        tfoot td { font-weight: bold; background: #f6f6f6; }
        .totals { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin: 10px 0 4px; }
        .totals div { border: 1px solid #999; padding: 6px 8px; }
        .totals strong { display: block; font-size: 14px; }
        .note { font-size: 10px; color: #555; margin-top: 4px; }
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 28px; margin-top: 40px; }
        .signatures div { border-top: 1px solid #111; padding-top: 4px; text-align: center; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { box-shadow: none; margin: 0; padding: 0; max-width: none; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button class="secondary" onclick="window.close()">Close</button>
    <button onclick="window.print()">Print</button>
</div>

<div class="sheet">
    <header>
        <div>
            <h1>QRTeam Track – Personnel Duty Log</h1>
            <div class="muted">Barangay Quick Response Team</div>
        </div>
        <div class="muted" style="text-align:right">
            Period: <strong style="color:#111">{{ $period->label }}</strong><br>
            Printed {{ now()->format('M j, Y g:i A') }}
        </div>
    </header>

    <div class="info">
        <div><b>Name:</b> {{ $personnel->name }}</div>
        <div><b>Phone:</b> {{ $personnel->phone_number ?? '—' }}</div>
        <div><b>Hourly rate:</b> ₱{{ number_format((float) $personnel->hourly_rate, 2) }}</div>
        <div style="grid-column: span 3"><b>Address:</b> {{ $personnel->address ?? '—' }}</div>
    </div>

    <div class="totals">
        <div>Duty days<strong>{{ $totals->days }}</strong></div>
        <div>Paid hours<strong>{{ number_format($totals->paid_hours, 2) }}</strong></div>
        <div>Salary<strong>₱{{ number_format($totals->pay, 2) }}</strong></div>
        <div>Responses / Solved<strong>{{ $responses->count() }} / {{ $solved->count() }}</strong></div>
    </div>

    <h2>Duty time in / time out</h2>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Assigned shift</th>
                <th>Timed in</th>
                <th>Timed out</th>
                <th>Station</th>
                <th class="num">Clocked hrs</th>
                <th class="num">Paid hrs</th>
                <th class="num">Pay</th>
            </tr>
        </thead>
        <tbody>
            @forelse($duties as $d)
                <tr>
                    <td>{{ $d->date->format('M j, Y (D)') }}</td>
                    <td>{{ $d->has_schedule ? $d->shift_start->format('h:i A') . ' – ' . $d->shift_end->format('h:i A') : 'No assigned shift' }}</td>
                    <td>{{ $d->time_in->format('h:i A') }}{{ $d->status === 'Late' ? ' (Late)' : '' }}</td>
                    <td>{{ $d->time_out ? $d->time_out->format('h:i A') : 'On duty' }}</td>
                    <td>{{ $d->location }}</td>
                    <td class="num">{{ $d->actual_hours !== null ? number_format($d->actual_hours, 2) : '—' }}</td>
                    <td class="num">{{ $d->paid_hours !== null ? number_format($d->paid_hours, 2) : '—' }}</td>
                    <td class="num">{{ $d->pay !== null ? '₱' . number_format($d->pay, 2) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center">No duty records for this period.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="5">Total ({{ $totals->sessions }} session(s), {{ $totals->late }} late)</td>
                <td class="num">{{ number_format($totals->actual_hours, 2) }}</td>
                <td class="num">{{ number_format($totals->paid_hours, 2) }}</td>
                <td class="num">₱{{ number_format($totals->pay, 2) }}</td>
            </tr>
        </tfoot>
    </table>
    <div class="note">Paid hours count only the time inside the assigned shift. Salary = paid hours × hourly rate.</div>

    <h2>Incident responses ({{ $responses->count() }})</h2>
    <table>
        <thead>
            <tr><th>Responded</th><th>Incident</th><th>Resident</th><th>Team</th><th>Status</th></tr>
        </thead>
        <tbody>
            @forelse($responses as $i)
                <tr>
                    <td>{{ $i->joined_at->format('M j, Y h:i A') }}</td>
                    <td>{{ $i->type }}: {{ $i->label }}</td>
                    <td>{{ $i->resident }}</td>
                    <td>{{ $i->teammates->isEmpty() ? 'Alone' : 'with ' . $i->teammates->join(', ', ' and ') }}</td>
                    <td>
                        {{ \App\Support\IncidentStatus::label($i->status) }}
                        @if($i->is_solved) ({{ $i->resolution_method === 'auto' ? 'auto, no reply in 24 h' : 'confirmed by resident' }}) @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center">No incident responses for this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Solved cases ({{ $solved->count() }})</h2>
    <table>
        <thead>
            <tr><th>Resolved</th><th>Incident</th><th>Resident</th><th>How</th></tr>
        </thead>
        <tbody>
            @forelse($solved as $i)
                <tr>
                    <td>{{ $i->resolved_at?->format('M j, Y h:i A') }}</td>
                    <td>{{ $i->type }}: {{ $i->label }}</td>
                    <td>{{ $i->resident }}</td>
                    <td>{{ $i->resolution_method === 'auto' ? 'Auto-resolved (no reply in 24 h)' : 'Confirmed by resident' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center">No solved cases for this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="signatures">
        <div>Prepared by</div>
        <div>{{ $personnel->name }}<br><span class="muted">Personnel</span></div>
        <div>Approved by</div>
    </div>
</div>
</body>
</html>
