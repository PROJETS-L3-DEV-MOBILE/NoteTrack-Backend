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

        $inputFrom = $validated['from_date'] ?? null;
        $inputTo   = $validated['to_date']   ?? null;

        $fromDate = $inputFrom
            ? Carbon::parse($inputFrom)->startOfDay()
            : Carbon::now()->startOfMonth()->startOfDay();

        $toDate = $inputTo
            ? Carbon::parse($inputTo)->endOfDay()
            : Carbon::now()->endOfDay();

        if ($toDate->lt($fromDate)) {
            $fromDate = (clone $toDate)->startOfMonth();
        }

        $teacher = $request->user()->teacher;

        $stats = $this->dashboardService->getStats(
            teacher: $teacher,
            fromDate: $fromDate->toIso8601String(),
            toDate: $toDate->toIso8601String()
        );

        return new TeacherDashboardResource($stats);
    }
}
