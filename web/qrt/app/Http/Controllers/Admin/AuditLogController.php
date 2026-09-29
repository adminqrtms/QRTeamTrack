<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List audit logs with date, time, action and personnel filters.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'time_from' => 'nullable|date_format:H:i',
            'time_to' => 'nullable|date_format:H:i',
            'action' => 'nullable|in:' . implode(',', array_keys(AuditLog::ACTIONS)),
            'search' => 'nullable|string|max:100',
        ]);

        $query = AuditLog::with(['user', 'location'])
            ->orderByDesc('logged_at')
            ->orderByDesc('id');

        if (!empty($filters['date_from'])) {
            $query->whereDate('logged_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('logged_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['time_from'])) {
            $query->whereTime('logged_at', '>=', $filters['time_from'] . ':00');
        }
        if (!empty($filters['time_to'])) {
            $query->whereTime('logged_at', '<=', $filters['time_to'] . ':59');
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(15)->withQueryString();

        return view('admin.audit_logs.index', [
            'logs' => $logs,
            'actions' => AuditLog::ACTIONS,
        ]);
    }
}
