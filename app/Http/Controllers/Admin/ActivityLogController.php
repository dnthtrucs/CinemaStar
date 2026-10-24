<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ActivityLogController extends Controller
{
    public function index(Request $request, ActivityLogService $service)
    {
        $filters = $request->validate([
            'group' => ['nullable', Rule::in(array_keys(ActivityLogService::GROUPS))],
            'actor' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])],
        ]);
        $logs = $service->paginate($filters);

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'groups' => ActivityLogService::GROUPS,
            'related' => $service->relatedRecords($logs->getCollection()),
        ]);
    }
}
