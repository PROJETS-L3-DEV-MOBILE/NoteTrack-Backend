<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTeacherRequest;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\AccountCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(
        protected AccountCreationService $accountCreation,
    ) {}

    public function storeTeacher(StoreTeacherRequest $request): JsonResponse
    {
        $data  = $request->validated();
        $admin = $request->user()->admin;

        $teacher = DB::transaction(function () use ($data, $admin) {
            $user = $this->accountCreation->createUserWithCredentials(
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
}
