<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $promotions = Promotion::all()->load('schoolYear');
        return response()->json($promotions, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'label' => ['required', 'string', 'max:255', 'unique:promotions,label'],
            'school_year_id' => ['required', 'integer', 'exists:school_years,id'],
        ]);

        $promotion = Promotion::create($validatedData);

        return response()->json($promotion, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $promotion = Promotion::findOrFail($id);
        return response()->json($promotion->load('schoolYear'), 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $validatedData = $request->validate([
            'label' => ['nullable', 'string', 'max:255', 'unique:promotions,label,' . $id],
            'school_year_id' => ['nullable', 'integer', 'exists:school_years,id'],
        ]);

        $promotion = Promotion::findOrFail($id);

        $dataToUpdate = collect($validatedData)
            ->filter(fn($value) => $value !== null)
            ->toArray();

        if (!empty($dataToUpdate)) {
            $promotion->update($dataToUpdate);
        }

        return response()->json([
            'message' => 'Promotion updated successfully.',
            'promotion' => $promotion
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $promotion = Promotion::findOrFail($id);

        $promotion->delete();

        return response()->json([
            'message' => 'Promotion deleted successfully.'
        ], 200);
    }
}
