<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolYear;
use Illuminate\Http\Request;

class SchoolYearController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $schoolYears = SchoolYear::all();
        return response()->json($schoolYears);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate(['label' => 'required|string|max:255|unique:school_years,label']);
        SchoolYear::create(['label' => $validated['label']]);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $schoolYear = SchoolYear::findOrFail($id);
        return response()->json($schoolYear);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, int $id)
    {
        $validated = $request->validate(['label' => 'required|string|max:255|unique:school_years,label']);

        $toUpdate = SchoolYear::findOrFail($id);
        $toUpdate->update(['label' => $validated['label']]);

        return response()->json($toUpdate);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(int $id)
    {
        $toDestroy = SchoolYear::findOrFail($id);
        $toDestroy->destroy();

        return response()->json($toDestroy);
    }
}
