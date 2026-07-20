<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Classe;
use App\Services\GradeCalculatorService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ClasseController extends Controller
{
    public function __construct(
        protected GradeCalculatorService $gradeCalculator,
    ) {}

    /**
     * Display a listing of the resource.
     * GET /api/classes
     */
    public function index(): JsonResponse
    {
        $classes = Classe::latest("created_at")
            ->withCount('students')
            ->with('students')
            ->get()
            ->map(function ($classe) {
                $classe->average = $this->classAverage($classe);
                return $classe;
            });

        return response()->json($classes, 200);
    }

    private function classAverage(Classe $classe): float
    {
        $averages = $classe->students
            ->map(fn ($student) => $this->gradeCalculator->generalAverage($student))
            ->filter(fn (?float $average) => $average !== null);

        if ($averages->isEmpty()) {
            return 0.0;
        }

        return round($averages->avg(), 2);
    }
    /**
     * Store a newly created resource in storage.
     * POST /api/classes
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label'         => 'required|string|max:255|unique:classes,label',
            'total_credits' => 'required|integer|min:0',
            'description'   => 'nullable|string|max:1000',
        ]);

        $classe = Classe::create($validated);
        return response()->json([
            "status" => "success",
            "message" => "Classe created successfully.",
            "data" => $classe
        ], 201);
    }

    /**
     * Display the specified resource.
     * GET /api/classes/{classe}
     */
    public function show(Classe $class): JsonResponse
    {
        return response()->json($class, 200);
    }

    /**
     * Update the specified resource in storage.
     * PUT /api/classes/{classe}
     */
    public function update(Request $request, Classe $class): JsonResponse
    {
        $validated = $request->validate([
            'label'         => 'nullable|string|max:255|unique:classes,label,' . $class->id,
            'total_credits' => 'nullable|integer|min:0',
            'description'   => 'nullable|string|max:1000',
        ]);

        $class->update($validated);

        return response()->json($class, 200);
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/classes/{classe}
     */
    public function destroy(Classe $class): JsonResponse
    {
        $class->delete();

        return response()->json([
            "status" => "success",
            "message" => "Classe deleted successfully."
        ], 200);
    }
}
