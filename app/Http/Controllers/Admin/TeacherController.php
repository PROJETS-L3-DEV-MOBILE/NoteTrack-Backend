<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TeacherSortEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Http\Requests\UpdateTeacherRequest;
use App\Http\Resources\TeacherResource;
use App\Http\Resources\TeacherSubjectsResource;
use App\Models\Teacher;
use App\Services\AccountCreationService;
use App\Services\TeacherSubjectsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TeacherController extends Controller
{

    public function __construct(
        protected AccountCreationService $accountCreationService,
        protected TeacherSubjectsService $teacherSubjectsService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->query('search', '');
        $sort = TeacherSortEnum::tryFrom($request->query('sort')) ?? TeacherSortEnum::CreationDate;

        $query = Teacher::query()
            ->select('teachers.*')
            ->selectRaw("CONCAT(first_name, ' ', last_name) as full_name")
            ->with(['user', 'admin.user'])
            ->withCount('subjects')
            ->withCount([
                'subjects as classes_count' => function ($q) {
                    $q->selectRaw('COUNT(DISTINCT ues.class_id)')
                        ->join('ues', 'subjects.ue_id', '=', 'ues.id');
                }
            ]);

        // case insensitive search
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $term = '%' . mb_strtolower($search, 'UTF-8') . '%';

                $q->whereRaw('LOWER(first_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(display_name) LIKE ?', [$term]);
            });
        }

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

    public function indexRaw(): JsonResponse
    {
        $teachers = Teacher::all();

        return response()->json(
            TeacherResource::collection($teachers),
            200
        );
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
        $teacher->load([
            'subjects',
            'subjects.ue:id,label,classe_id',
            'subjects.ue.classe'
        ]);

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

    /**
     * List teacher subjects with TeacherSubjectsResource details
     * GET /teachers/subjects
     */
    public function subjects(Request $request): JsonResponse
    {
        /** @var \App\Models\Teacher $teacher */
        $teacher = $request->user()->teacher ?? $request->user();

        $groupedSubjects = $this->teacherSubjectsService->getGroupedSubjects($teacher);

        return response()->json(TeacherSubjectsResource::collection($groupedSubjects), 200);
    }
}
