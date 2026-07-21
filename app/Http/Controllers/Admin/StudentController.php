<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Http\Requests\Admin\StoreStudentRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Classe;
use App\Services\AccountCreationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{

    public function __construct(
        protected AccountCreationService $accountCreation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $students = Student::with(['promotion', 'user', 'notes'])
            ->when($request->search, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                });
            })
            ->when($request->classe_id, fn($q, $classId) => $q->where('classe_id', $classId))
            ->when($request->school_year, function ($q, $year) {
                $q->whereHas('promotion', fn($sub) => $sub->where('prom_year', $year));
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
        $data  = $request->validated();
        $admin = $request->user()->admin;

        $student = DB::transaction(function () use ($data, $admin) {
            Classe::where('id', $data['classe_id'])->lockForUpdate()->first();
            $nextNumber = Student::where('classe_id', $data['classe_id'])->count() + 1;

            $user = $this->accountCreation->createUserWithCredentials(
                email: $data['email'],
                role: 'student',
            );

            return Student::create([
                ...$data,
                'user_id'   => $user->id,
                'admin_id'  => $admin->id,
                'number'    => $nextNumber,
            ]);
        });

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
