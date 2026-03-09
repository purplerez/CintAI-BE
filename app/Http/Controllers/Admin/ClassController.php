<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    // Guru: list their own classes
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SchoolClass::withCount('enrollments');

        if ($user->hasRole('guru')) {
            $query->where('teacher_id', $user->id);
        }

        return response()->json($query->orderByDesc('created_at')->get());
    }

    // Guru: create class
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'code'          => 'required|string|max:50|unique:classes,code',
            'grade_level'   => 'nullable|string|max:10',
            'academic_year' => 'nullable|integer|min:2020|max:2030',
            'description'   => 'nullable|string',
        ]);
        $data['teacher_id'] = $request->user()->id;
        $class = SchoolClass::create($data);
        return response()->json($class, 201);
    }

    // Guru: update class
    public function update(Request $request, SchoolClass $class): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $class->update($request->validate([
            'name'        => 'sometimes|string|max:255',
            'grade_level' => 'sometimes|string',
            'is_active'   => 'sometimes|boolean',
            'description' => 'sometimes|string|nullable',
        ]));
        return response()->json($class);
    }

    // Guru: list students
    public function students(SchoolClass $class): JsonResponse
    {
        $students = $class->enrollments()
            ->with('student:id,name,email,student_number')
            ->where('status', 'active')
            ->get()
            ->pluck('student');
        return response()->json($students);
    }

    // ── Siswa: join class by code ────────────────────────────
    public function join(Request $request): JsonResponse
    {
        $request->validate(['code' => 'required|string']);

        $class = SchoolClass::where('code', $request->code)
            ->where('is_active', true)
            ->first();

        if (!$class) {
            return response()->json(['message' => 'Kode kelas tidak ditemukan atau kelas tidak aktif.'], 404);
        }

        $existing = Enrollment::where('class_id', $class->id)
            ->where('student_id', $request->user()->id)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Kamu sudah terdaftar di kelas ini.'], 409);
        }

        Enrollment::create([
            'class_id'      => $class->id,
            'student_id'    => $request->user()->id,
            'status'        => 'active',
            'enrolled_at'   => now(),
        ]);

        return response()->json([
            'message' => "Berhasil bergabung ke kelas {$class->name}!",
            'class'   => $class->only(['id', 'name', 'code', 'grade_level', 'academic_year']),
        ], 201);
    }

    // Siswa: list my enrolled classes
    public function myClasses(Request $request): JsonResponse
    {
        $classes = $request->user()
            ->enrollments()
            ->with('schoolClass:id,name,code,grade_level,academic_year,teacher_id')
            ->where('status', 'active')
            ->get()
            ->map(fn($e) => array_merge(
                $e->schoolClass->toArray(),
                ['enrolled_at' => $e->enrolled_at]
            ));

        return response()->json($classes);
    }

    // Guru: kick / remove student
    public function removeStudent(Request $request, SchoolClass $class, int $studentId): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        Enrollment::where('class_id', $class->id)
            ->where('student_id', $studentId)
            ->delete();
        return response()->json(['message' => 'Siswa dikeluarkan dari kelas.']);
    }
}
