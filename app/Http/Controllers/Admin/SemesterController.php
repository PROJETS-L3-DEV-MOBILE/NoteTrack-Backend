<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use Illuminate\Http\Request;

class SemesterController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $semesters = Semester::all();
        return response()->json($semesters, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $request->validate(
            ['label' => 'required|string|max:255|unique:semesters,label',]
        );

        $createdSemester = Semester::create(['label' => $data['label']]);
        return response()->json($createdSemester, 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id)
    {
        $semester = Semester::findOrFail($id);
        return response()->json($semester, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $data = $request->validate(
            ['label' => 'required|string|max:255|unique:semesters,label',]
        );
        $semester = Semester::findOrFail($id);

        $updatedSemester = $semester->update(["label" => $data['label']]);
        return response()->json($updatedSemester, 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $destroyCount = Semester::destroy($id);

        if ($destroyCount > 0) {
            return response()->json([
                'message' => 'Trying to delete unexisting resource'
            ], 400);
        }

        return response()->json([
            'message' => 'Semester deleted successfully',
        ]);
    }
}
