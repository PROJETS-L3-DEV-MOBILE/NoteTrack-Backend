<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Subjects\CreateSubjectRequest;
use App\Http\Requests\Admin\Subjects\UpdateSubjectRequest;
use App\Http\Resources\Admin\Subjects\LevelResource;
use App\Http\Resources\Admin\Subjects\SubjectResource;
use App\Models\Subject;
use App\Models\UE;
use App\Services\SubjectService;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function __construct(
        protected SubjectService $subjects,
    ) {}

    /**
     * GET /admin/subjects
     */
    public function index(): JsonResponse
    {
        return response()->json(
            LevelResource::collection($this->subjects->groupedByLevel())
        );
    }

    /**
     * POST /admin/ues/{ue}/subjects
     */
    public function store(CreateSubjectRequest $request, UE $ue): JsonResponse
    {
        $admin = $request->user()->admin;

        $subject = $this->subjects->createSubject($ue, $request->validated(), $admin);

        return response()->json(new SubjectResource($subject), 201);
    }

    /**
     * PUT /admin/ues/{ue}/subjects/{subject}
     */
    public function update(UpdateSubjectRequest $request, UE $ue, Subject $subject): JsonResponse
    {
        $subject = $this->subjects->updateSubject($subject, $request->validated());

        return response()->json(new SubjectResource($subject), 200);
    }

    /**
     * DELETE /admin/ues/{ue}/subjects/{subject}
     */
    public function destroy(UE $ue, Subject $subject): JsonResponse
    {
        $this->subjects->deleteSubject($subject);

        return response()->json(status: 204);
    }
}
