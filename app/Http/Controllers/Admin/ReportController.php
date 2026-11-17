<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request, AdminReportService $report): Response
    {
        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $from = Carbon::parse($request->query('from', now('UTC')->subDays(6)->toDateString()), 'UTC')->startOfDay();
        $to = Carbon::parse($request->query('to', now('UTC')->toDateString()), 'UTC')->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        abort_if($from->diffInDays($to) > 91, 422, 'Pixelforge usage only supports ranges up to 92 days.');

        return Inertia::render('Admin/Report', [
            'report' => $report->build($from, $to),
        ]);
    }
}
