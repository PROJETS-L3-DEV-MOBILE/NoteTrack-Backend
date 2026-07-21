<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\TeacherDashboardResource;
use Illuminate\Http\Request;
use App\Services\TeacherDashboardService;
use Carbon\Carbon;

class TeacherDashboardController extends Controller
{
    public function __construct(
        protected TeacherDashboardService $dashboardService
    ) {}

    /**
     * GET /teacher/dashboard/stats
     */
    public function stats(Request $request): TeacherDashboardResource
    {
        $validated = $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date'   => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $fromDate = $validated['from_date'] ?? Carbon::now()->startOfMonth()->toIso8601String();
        $toDate   = $validated['to_date']   ?? Carbon::now()->endOfDay()->toIso8601String();

        $teacher = $request->user()->teacher;

        $stats = $this->dashboardService->getStats(
            teacher: $teacher,
            fromDate: $fromDate,
            toDate: $toDate
        );

        return new TeacherDashboardResource($stats);
    }
}
