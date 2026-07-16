<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\ResultsFilterRequest;
use App\Http\Requests\Admin\Dashboard\StatsFilterRequest;
use App\Http\Resources\Admin\Dashboard\LatestNoteResource;
use App\Http\Resources\Admin\Dashboard\NotificationResource;
use App\Http\Resources\Admin\Dashboard\RecentSubjectResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        protected DashboardService $dashboard,
    ) {}

    public function stats(StatsFilterRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $this->dashboard->stats($data['from_date'] ?? null, $data['to_date'] ?? null)
        );
    }

    public function results(ResultsFilterRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json($this->dashboard->results(
            $data['level'] ?? null,
            $data['class_id'] ?? null,
            $data['school_year'] ?? null,
        ));
    }

    public function recentActivities(Request $request): JsonResponse
    {
        $notifications = $this->dashboard->recentActivities(
            (int) $request->integer('limit', 10)
        );

        return response()->json(NotificationResource::collection($notifications));
    }

    public function latestNotes(Request $request): JsonResponse
    {
        $notes = $this->dashboard->latestNotes(
            (int) $request->integer('limit', 10)
        );

        return response()->json(LatestNoteResource::collection($notes));
    }

    public function recentSubjects(Request $request): JsonResponse
    {
        $subjects = $this->dashboard->recentSubjects(
            (int) $request->integer('limit', 10)
        );

        return response()->json(RecentSubjectResource::collection($subjects));
    }
}
