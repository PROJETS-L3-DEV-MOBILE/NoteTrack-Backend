<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StudentFilterRequest;
use App\Http\Requests\Student\StudentResultsFilterRequest;
use App\Services\StudentHomeService;
use Illuminate\Http\JsonResponse;

class StudentHomeController extends Controller
{
    public function __construct(
        protected StudentHomeService $studentHome,
    ) {}

    /**
     * GET /student/home/stats
     */
    public function stats(StudentFilterRequest $request): JsonResponse
    {
        $student = $request->user()->student;

        return response()->json(
            $this->studentHome->stats($student, $request->validated())
        );
    }

    /**
     * GET /student/home/statistics
     */
    public function statistics(StudentFilterRequest $request): JsonResponse
    {
        $student = $request->user()->student;

        return response()->json(
            $this->studentHome->statistics($student, $request->validated())
        );
    }

    /**
     * GET /student/results
     */
    public function results(StudentResultsFilterRequest $request): JsonResponse
    {
        $student = $request->user()->student;

        return response()->json(
            $this->studentHome->results($student, $request->validated())->values()
        );
    }
}
