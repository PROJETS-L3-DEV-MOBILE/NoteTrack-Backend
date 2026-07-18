<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Subjects\CreateUERequest;
use App\Http\Requests\Admin\Subjects\UpdateUERequest;
use App\Http\Resources\Admin\Subjects\UEResource;
use App\Models\UE;
use App\Services\SubjectService;
use Illuminate\Http\JsonResponse;

class UEController extends Controller
{
    public function __construct(
        protected SubjectService $subjects,
    ) {}

    /**
     * POST /admin/ues
     */
    public function store(CreateUERequest $request): JsonResponse
    {
        $admin = $request->user()->admin;

        $ue = $this->subjects->createUE($request->validated(), $admin);

        return response()->json(new UEResource($ue), 201);
    }

    /**
     * PUT /admin/ues/{ue}
     */
    public function update(UpdateUERequest $request, UE $ue): JsonResponse
    {
        $ue = $this->subjects->updateUE($ue, $request->validated());

        return response()->json(new UEResource($ue));
    }

    /**
     * DELETE /admin/ues/{ue}
     */
    public function destroy(UE $ue): JsonResponse
    {
        $this->subjects->deleteUE($ue);

        return response()->json(status: 204);
    }
}
