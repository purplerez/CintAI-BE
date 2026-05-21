<?php

namespace App\Http\Controllers\Coding;

use App\Http\Controllers\Controller;
use App\Jobs\GradeSubmissionJob;
use App\Models\CodingProblem;
use App\Models\Submission;
use App\Services\DockerSandboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodingController extends Controller
{
    public function __construct(private DockerSandboxService $sandbox) {}

    // ── Problems ──────────────────────────────────────────────
    public function index(Request $request): JsonResponse
    {
        $user   = $request->user();
        $isGuru = $user->hasAnyRole(['guru', 'admin']);

        $problems = CodingProblem::with(['creator:id,name', 'schoolClass:id,name,code'])
            ->when($request->topic,      fn($q, $v) => $q->where('topic', $v))
            ->when($request->difficulty, fn($q, $v) => $q->where('difficulty', $v))
            ->when($request->class_id,   fn($q, $v) => $q->where('class_id', $v))
            ->when(!$isGuru, fn($q) => $q->where('is_published', true))
            ->when(!$isGuru, function ($q) use ($user) {
                // Siswa ONLY sees problems from their enrolled classes
                $classIds = $user->enrollments()
                    ->where('status', 'active')
                    ->pluck('class_id');
                $q->whereIn('class_id', $classIds);
            })
            ->withCount('submissions')
            ->orderByDesc('created_at')
            ->paginate(15);

        return response()->json($problems);
    }

    public function show(Request $request, CodingProblem $problem): JsonResponse
    {
        if ($request->user() && $request->user()->hasAnyRole(['guru', 'admin'])) {
            $problem->load(['testCases', 'creator']);
        } else {
            $problem->load(['sampleTestCases', 'creator']);
        }
        return response()->json($problem);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', CodingProblem::class);
        $data = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'required|string',
            'starter_code'        => 'nullable|string',
            'difficulty'          => 'required|in:easy,medium,hard',
            'topic'               => 'nullable|string',
            'class_id'            => 'required|exists:classes,id',  // must belong to a class
            'max_score'           => 'integer|min:1|max:100',
            'time_limit_seconds'  => 'integer|min:1|max:10',
            'memory_limit_mb'     => 'integer|min:16|max:256',
            'detect_hardcode'     => 'boolean',
        ]);
        $data['created_by']  = $request->user()->id;
        $data['is_published'] = true;
        $problem = CodingProblem::create($data);
        return response()->json($problem, 201);
    }

    // ── Save Draft (auto-save per student per problem) ────────
    public function saveProgress(Request $request, CodingProblem $problem): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:50000']);
        // Store in a simple key-value cache (file-based) or DB as needed
        // For now, return OK — real save is in cache/localStorage on frontend
        cache()->put(
            "draft:{$request->user()->id}:{$problem->id}",
            $request->code,
            now()->addDays(7)
        );
        return response()->json(['saved' => true]);
    }

    // ── Guru: all submissions for a problem ───────────────────
    public function problemSubmissions(Request $request, CodingProblem $problem): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $submissions = $problem->submissions()
            ->with('student:id,name,email,student_number')
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('student_id')
            ->map(fn($subs) => $subs->first()); // Best submission per student
        return response()->json($submissions->values());
    }

    public function update(Request $request, CodingProblem $problem): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'title'              => 'sometimes|string|max:255',
            'description'        => 'sometimes|string',
            'difficulty'         => 'sometimes|in:easy,medium,hard',
            'topic'              => 'sometimes|string|nullable',
            'is_published'       => 'sometimes|boolean',
            'detect_hardcode'    => 'sometimes|boolean',
            'time_limit_seconds' => 'sometimes|integer|min:1|max:10',
            'memory_limit_mb'    => 'sometimes|integer|min:16|max:256',
            'max_score'          => 'sometimes|integer|min:1|max:100',
            'starter_code'       => 'sometimes|string|nullable',
            'class_id'           => 'sometimes|nullable|exists:classes,id',
        ]);
        $problem->update($data);
        return response()->json($problem);
    }

    public function destroy(Request $request, CodingProblem $problem): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $problem->delete();
        return response()->json(['message' => 'Soal berhasil dihapus.']);
    }

    // ── Test Cases ──────────────────────────────────────────────
    public function storeTestCase(Request $request, CodingProblem $problem): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'input'           => 'nullable|string',
            'expected_output' => 'required|string',
            'is_sample'       => 'boolean',
            'is_hidden'       => 'boolean',
            'weight'          => 'integer|min:1',
        ]);
        // Default weight = 1 if not specified
        $data['weight'] = $data['weight'] ?? 1;
        $tc = $problem->testCases()->create($data);
        return response()->json($tc, 201);
    }

    public function destroyTestCase(Request $request, CodingProblem $problem, \App\Models\TestCase $testCase): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        if ($testCase->problem_id !== $problem->id) {
            return response()->json(['message' => 'Test case tidak valid.'], 400);
        }
        $testCase->delete();
        return response()->json(['message' => 'Test case berhasil dihapus.']);
    }

    // ── Run Code (non-graded) ──────────────────────────────────
    public function run(Request $request): JsonResponse
    {
        $request->validate([
            'code'  => 'required|string|max:50000',
            'input' => 'nullable|string|max:1000',
        ]);

        $compile = $this->sandbox->compile($request->code);
        if (!$compile['success']) {
            return response()->json([
                'status' => 'compile_error',
                'error'  => $compile['error'],
            ]);
        }

        $result = $this->sandbox->run($compile['binary_path'], $request->input);
        return response()->json([
            'status'  => $result['status'],
            'output'  => $result['output'],
            'time_ms' => $result['time_ms'],
        ]);
    }

    // ── Submit Code (graded) ──────────────────────────────────
    public function submit(Request $request, CodingProblem $problem): JsonResponse
    {
        $request->validate(['code' => 'required|string|max:50000']);

        $submission = Submission::create([
            'student_id' => $request->user()->id,
            'problem_id' => $problem->id,
            'code'       => $request->code,
            'language'   => 'cpp',
            'status'     => 'pending',
            'total_cases'=> $problem->testCases()->count(),
        ]);

        GradeSubmissionJob::dispatch($submission);

        return response()->json([
            'submission_id' => $submission->id,
            'status'        => 'queued',
            'message'       => 'Kode sedang diproses. Periksa status submission.',
        ], 202);
    }

    // ── Submission Status ─────────────────────────────────────
    public function submissionStatus(Submission $submission): JsonResponse
    {
        return response()->json($submission->only([
            'id', 'status', 'score', 'passed_cases', 'total_cases',
            'execution_time_ms', 'test_case_results', 'compile_error', 'created_at',
        ]));
    }

    // ── Get Submission with Code (Guru/Admin) ──────────────────
    public function getSubmissionWithCode(Request $request, Submission $submission): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $submission->load([
            'student:id,name,email,student_number',
            'problem:id,title,description,topic,difficulty',
        ]);

        return response()->json([
            'id'                   => $submission->id,
            'code'                 => $submission->code,
            'language'             => $submission->language,
            'status'               => $submission->status,
            'score'                => $submission->score,
            'passed_cases'         => $submission->passed_cases,
            'total_cases'          => $submission->total_cases,
            'execution_time_ms'    => $submission->execution_time_ms,
            'memory_used_kb'       => $submission->memory_used_kb,
            'test_case_results'    => $submission->test_case_results,
            'compile_error'        => $submission->compile_error,
            'created_at'           => $submission->created_at,
            'student'              => $submission->student,
            'problem'              => $submission->problem,
        ]);
    }

    // ── My Submissions ────────────────────────────────────────
    public function mySubmissions(Request $request): JsonResponse
    {
        $submissions = Submission::with('problem:id,title,topic,difficulty')
            ->where('student_id', $request->user()->id)
            ->when($request->problem_id, fn($q, $v) => $q->where('problem_id', $v))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($submissions);
    }
}
