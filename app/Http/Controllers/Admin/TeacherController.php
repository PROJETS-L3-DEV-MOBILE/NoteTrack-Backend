<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeacherSortEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Models\Teacher;
use App\Services\AccountCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{

    public function __construct(protected AccountCreationService $accountCreationService) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $sort = TeacherSortEnum::tryFrom($request->query('sort')) ?? TeacherSortEnum::CreationDate;

        $query = Teacher::query()
            ->with(['user', 'admin'])
            ->withCount([
                'subjects',
                'classes' => fn($q) => $q->distinct()
            ])
            ->select('*')
            ->selectRaw("CONCAT(first_name, ' ', last_name) as full_name");

        match ($sort) {
            TeacherSortEnum::NameAZ => $query->orderBy('full_name', 'asc'),
            TeacherSortEnum::NameZA => $query->orderBy('full_name', 'desc'),
            TeacherSortEnum::CreationDate => $query->orderBy('created_at', 'asc'),
        };

        $perPage = (int) $request->query('per_page', 20);

        $teachers = $query->paginate($perPage)
            ->through(fn($teacher) => new TeacherResource($teacher));

        return response()->json($teachers, 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $admin = $request->user()->admin;

        $teacher = DB::transaction(function () use ($data, $admin) {
            $user = $this->accountCreationService->createUserWithCredentials(
                email: $data['email'],
                role: 'teacher',
            );

            return Teacher::create([
                ...$data,
                'user_id'  => $user->id,
                'admin_id' => $admin->id,
            ]);
        });

        return response()->json($teacher, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $teacher = Teacher::with('user')->findOrFail($id);
        $teacher->full_name = $teacher->first_name . ' ' . $teacher->last_name;
        $teacher->load('subjects');

        return response()->json($teacher, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTeacherRequest $request, string $id): JsonResponse
    {
        $validatedData = $request->validated();
        $teacher = Teacher::findOrFail($id);

        DB::transaction(function () use ($validatedData, $teacher) {
            if (isset($validatedData['email']) && $validatedData['email'] !== null) {
                $teacher->user()->update([
                    'email' => $validatedData['email']
                ]);
            }

            $teacherData = collect($validatedData)
                ->except('email')
                ->filter(fn($value) => $value !== null)
                ->toArray();

            if (!empty($teacherData)) {
                $teacher->update($teacherData);
            }
        });

        $teacher->refresh()->load('user');
        $teacher->full_name = $teacher->first_name . ' ' . $teacher->last_name;

        return response()->json([
            'message' => 'Teacher updated.',
            'teacher' => $teacher
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $teacher = Teacher::findOrFail($id);
        $teacher->delete();

        return response()->json([
            'message' => 'Teacher deleted.'
        ], 200);
    }
}
