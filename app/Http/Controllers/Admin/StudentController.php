<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationType;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Classe;
use App\Models\User;
use App\Services\AccountCreationService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{

    public function __construct(
        protected AccountCreationService $accountCreation,
        protected NotificationService $notificationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $students = Student::with(['promotion', 'user', 'notes'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $term = '%' . mb_strtolower($search, 'UTF-8') . '%';

                    $sub->whereRaw('LOWER(first_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(last_name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(matricule) LIKE ?', [$term]);
                });
            })
            ->when($request->classe_id, fn($q, $classId) => $q->where('classe_id', $classId))
            ->when($request->school_year_id, function ($q, $schoolYearId) {
                $q->whereHas('promotion', fn($sub) => $sub->where('school_year_id', $schoolYearId));
            })
            ->paginate($request->query('limit', 15));

        $studentsItems = collect($students->items());

        if ($request->filled('mention')) {
            $studentsItems = $studentsItems->filter(function ($student) use ($request) {
                return isset($student->mention->value)
                    ? $student->mention->value === $request->mention
                    : $student->mention === $request->mention;
            });
        }

        return response()->json([
            'total' => $students->total(),
            'students' => StudentResource::collection($studentsItems->values()),
        ], 200);
    }

    public function store(StoreStudentRequest $request)
    {
        $data = $request->validated();
        $currentUser = $request->user();
        $adminModel = $currentUser->admin;

        $student = DB::transaction(function () use ($data, $adminModel) {
            Classe::where('id', $data['classe_id'])->lockForUpdate()->first();
            $nextNumber = Student::where('classe_id', $data['classe_id'])->count() + 1;

            $user = $this->accountCreation->createUserWithCredentials(
                email: $data['email'],
                role: 'student',
            );

            return Student::create([
                ...$data,
                'user_id'  => $user->id,
                'admin_id' => $adminModel->id,
                'number'   => $nextNumber,
            ]);
        });

        $this->notificationService->notifyStudentCreated($request->user(), $student);

        return response()->json($student, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        $response = new StudentResource($student);

        return response()->json($response, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, string $id)
    {
        $data = collect($request->validated())
            ->filter(fn($value) => $value !== null)
            ->toArray();

        $student = Student::findOrFail($id);

        $updatedStudent = DB::transaction(function () use ($data, $student) {

            if (isset($data['email']) && $student->user) {
                $student->user->update(['email' => $data['email']]);
            }

            $studentData = collect($data)->except(['email'])->toArray();

            if (!empty($studentData)) {
                $student->update($studentData);
            }

            return $student;
        });
        return response()->json($updatedStudent->load('user'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $student = Student::findOrFail($id);
        Student::destroy($id);
        return response()->json(["message" => "Student deleted successfully"], 200);
    }
}
