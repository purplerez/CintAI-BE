<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodingProblem;
use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GradebookController extends Controller
{
    /**
     * GET /api/classes/{class}/gradebook
     * Returns a cross-tab: students × problems with best submission score each.
     */
    public function index(Request $request, SchoolClass $class): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Problems in this class (ordered by created_at)
        $problems = CodingProblem::where('class_id', $class->id)
            ->where('is_published', true)
            ->orderBy('created_at')
            ->get(['id', 'title', 'max_score', 'difficulty', 'topic']);

        // Students enrolled in this class
        $students = Enrollment::where('class_id', $class->id)
            ->where('status', 'active')
            ->with('student:id,name,email,student_number')
            ->get()
            ->pluck('student')
            ->filter()
            ->sortBy('name')
            ->values();

        if ($problems->isEmpty() || $students->isEmpty()) {
            return response()->json([
                'class'    => $class->only(['id', 'name', 'code', 'grade_level', 'academic_year']),
                'problems' => $problems,
                'students' => [],
                'summary'  => ['total_students' => $students->count(), 'total_problems' => $problems->count()],
            ]);
        }

        $problemIds  = $problems->pluck('id');
        $studentIds  = $students->pluck('id');

        // Best submission per (student, problem) — highest score
        $submissions = Submission::whereIn('student_id', $studentIds)
            ->whereIn('problem_id', $problemIds)
            ->select('student_id', 'problem_id',
                DB::raw('MAX(score) as best_score'),
                DB::raw('MAX(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as has_accepted'),
                DB::raw('COUNT(*) as attempts'),
                DB::raw('MIN(status) as status') // fallback status
            )
            ->groupBy('student_id', 'problem_id')
            ->get()
            ->keyBy(fn($s) => "{$s->student_id}_{$s->problem_id}");

        // Build student rows
        $rows = $students->map(function ($student) use ($problems, $submissions) {
            $scores         = [];
            $totalScore     = 0;
            $maxPossible    = 0;
            $submittedCount = 0;
            $acceptedCount  = 0;

            foreach ($problems as $problem) {
                $key = "{$student->id}_{$problem->id}";
                $sub = $submissions->get($key);

                if ($sub) {
                    $submittedCount++;
                    if ($sub->has_accepted) $acceptedCount++;
                    $totalScore  += $sub->best_score;
                }
                $maxPossible += $problem->max_score;

                $scores[$problem->id] = $sub ? [
                    'score'    => round($sub->best_score, 1),
                    'attempts' => $sub->attempts,
                    'accepted' => (bool) $sub->has_accepted,
                ] : null;
            }

            $percentage = $maxPossible > 0 ? round(($totalScore / $maxPossible) * 100, 1) : 0;

            return [
                'id'              => $student->id,
                'name'            => $student->name,
                'email'           => $student->email,
                'student_number'  => $student->student_number,
                'scores'          => $scores,
                'total_score'     => round($totalScore, 1),
                'max_possible'    => $maxPossible,
                'percentage'      => $percentage,
                'submitted_count' => $submittedCount,
                'accepted_count'  => $acceptedCount,
                'not_submitted'   => count($problems) - $submittedCount,
            ];
        });

        // Class summary
        $submitted = $rows->sum('submitted_count');
        $total     = $students->count() * $problems->count();
        $summary = [
            'total_students'     => $students->count(),
            'total_problems'     => $problems->count(),
            'completion_rate'    => $total > 0 ? round(($submitted / $total) * 100, 1) : 0,
            'avg_class_score'    => $rows->avg('percentage'),
            'fully_completed'    => $rows->filter(fn($r) => $r['not_submitted'] === 0)->count(),
        ];

        return response()->json([
            'class'    => $class->only(['id', 'name', 'code', 'grade_level', 'academic_year']),
            'problems' => $problems,
            'students' => $rows->values(),
            'summary'  => $summary,
        ]);
    }
}
