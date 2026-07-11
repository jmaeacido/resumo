<?php

namespace App\Http\Controllers;

use App\Models\ResumeReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $reports = [];

        if ($request->user()) {
            $reports = ResumeReport::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->limit(20)
                ->get(['id', 'mode', 'score', 'job_title', 'created_at'])
                ->map(fn (ResumeReport $report) => [
                    'id' => $report->id,
                    'mode' => $report->mode,
                    'score' => $report->score,
                    'job_title' => $report->job_title,
                    'created_at' => $report->created_at?->toIso8601String(),
                ]);
        }

        return Inertia::render('Dashboard', [
            'reports' => $reports,
        ]);
    }
}
