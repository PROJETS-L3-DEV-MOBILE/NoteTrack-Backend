<?php

namespace App\Http\Controllers;

use App\Models\Classe;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ClasseController extends Controller
{
    /**
     * Display a listing of the resource.
     * GET /api/classes
     */
    public function index(): JsonResponse
    {
        $classes = Classe::latest("created_at")->get();
        return response()->json($classes, 200);
    }

    /**
     * Store a newly created resource in storage.
     * POST /api/classes
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'label'         => 'required|string|max:255',
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
     * PUT/PATCH /api/classes/{classe}
     */
    public function update(Request $request, Classe $classe): JsonResponse
    {
        $validated = $request->validate([
            'label'         => 'required|string|max:255',
            'total_credits' => 'required|integer|min:0',
            'description'   => 'nullable|string|max:1000',
        ]);

        $classe->fill($validated);
        $classe->save();

        return response()->json($classe, 200);
    }

    /**
     * Remove the specified resource from storage.
     * DELETE /api/classes/{classe}
     */
    public function destroy(Classe $classe): JsonResponse
    {
        $classe->destroy($classe->id);
        return response()->json([
            "status" => "success",
            "message" => "Classe deleted successfully."
        ]);
    }
}
