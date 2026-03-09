<?php

namespace App\Http\Controllers\Exam;

use App\Http\Controllers\Controller;
use App\Models\CodingProblem;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $exams = Exam::with(['schoolClass:id,name', 'creator:id,name'])
            ->when($request->user()->hasRole('siswa'), function ($q) use ($request) {
                // Only show exams for classes student is enrolled in
                $classIds = $request->user()->enrollments()->where('status', 'active')->pluck('class_id');
                $q->whereIn('class_id', $classIds);
            })
            ->when($request->user()->hasRole('guru'), fn($q) => $q->where('created_by', $request->user()->id))
            ->where('status', '!=', 'draft')
            ->orderByDesc('starts_at')
            ->paginate(15);

        return response()->json($exams);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'class_id'             => 'required|exists:classes,id',
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string',
            'duration_minutes'     => 'required|integer|min:5|max:300',
            'randomize_questions'  => 'boolean',
            'allow_run'            => 'boolean',
            'fullscreen_required'  => 'boolean',
            'disable_copy_paste'   => 'boolean',
            'starts_at'            => 'nullable|date',
            'ends_at'              => 'nullable|date|after:starts_at',
        ]);
        $data['created_by'] = $request->user()->id;
        $exam = Exam::create($data);
        return response()->json($exam, 201);
    }

    public function start(Request $request, Exam $exam): JsonResponse
    {
        if ($exam->status !== 'published' && $exam->status !== 'ongoing') {
            return response()->json(['message' => 'Exam is not available.'], 403);
        }

        // Check if already attempted
        $existing = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', $request->user()->id)
            ->whereIn('status', ['submitted', 'auto_submitted', 'timed_out'])
            ->first();

        if ($existing) {
            return response()->json(['message' => 'You have already taken this exam.'], 409);
        }

        // Get or create in-progress attempt
        $attempt = ExamAttempt::firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $request->user()->id],
            ['status' => 'in_progress', 'started_at' => now()]
        );

        // Get problems for this exam's class
        $problems = CodingProblem::where('class_id', $exam->class_id)
            ->where('is_published', true)
            ->get(['id', 'title', 'description', 'difficulty', 'topic', 'starter_code']);

        if ($exam->randomize_questions) {
            $problems = $problems->shuffle();
        }

        $questionOrder = $problems->pluck('id')->toArray();
        $attempt->update(['question_order' => $questionOrder]);

        return response()->json([
            'attempt'  => $attempt,
            'exam'     => $exam->only(['id', 'title', 'duration_minutes', 'allow_run', 'fullscreen_required', 'disable_copy_paste']),
            'problems' => $problems->values(),
        ]);
    }

    public function submit(Request $request, Exam $exam): JsonResponse
    {
        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $request->validate([
            'answers'           => 'nullable|array',
            'auto_submitted'    => 'boolean',
        ]);

        $status = $request->auto_submitted ? 'auto_submitted' : 'submitted';

        // Calculate score from submissions
        $submissionIds = array_values($request->answers ?? []);
        $score = 0;
        if (!empty($submissionIds)) {
            $score = Submission::whereIn('id', $submissionIds)
                ->where('student_id', $request->user()->id)
                ->avg('score') ?? 0;
        }

        $attempt->update([
            'status'       => $status,
            'answers'      => $request->answers,
            'score'        => round($score, 2),
            'submitted_at' => now(),
        ]);

        return response()->json([
            'message' => 'Exam submitted successfully.',
            'score'   => $attempt->score,
            'status'  => $attempt->status,
        ]);
    }

    public function logActivity(Request $request, Exam $exam): JsonResponse
    {
        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', $request->user()->id)
            ->where('status', 'in_progress')
            ->firstOrFail();

        $request->validate([
            'event'      => 'required|string',
            'timestamp'  => 'required|numeric',
            'is_violated'=> 'boolean',
        ]);

        $log     = $attempt->activity_log ?? [];
        $log[]   = $request->only(['event', 'timestamp']);
        $update  = ['activity_log' => $log];

        if ($request->event === 'fullscreen_exit') {
            $update['fullscreen_violated'] = true;
            $update['violations']          = $attempt->violations + 1;
        }

        $attempt->update($update);

        return response()->json(['ok' => true]);
    }

    public function myAttempt(Request $request, Exam $exam): JsonResponse
    {
        $attempt = ExamAttempt::where('exam_id', $exam->id)
            ->where('student_id', $request->user()->id)
            ->first();

        return response()->json($attempt);
    }
}
