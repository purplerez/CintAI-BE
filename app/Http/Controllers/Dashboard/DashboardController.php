<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SchoolClass;
use App\Models\SkillProgress;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    // ── Guru Dashboard ─────────────────────────────────────────
    public function guru(Request $request): JsonResponse
    {
        $teacherId = $request->user()->id;
        $classIds  = SchoolClass::where('teacher_id', $teacherId)->pluck('id');

        // Submission stats per class
        $submissionStats = Submission::whereHas('problem', fn($q) => $q->whereIn('class_id', $classIds))
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        // Submissions in last 24 hours
        $submissions24h = Submission::whereHas('problem', fn($q) => $q->whereIn('class_id', $classIds))
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        // Avg class progress (pass rate %)
        $totalSubs = Submission::whereHas('problem', fn($q) => $q->whereIn('class_id', $classIds))->count();
        $acceptedSubs = Submission::whereHas('problem', fn($q) => $q->whereIn('class_id', $classIds))
            ->where('status', 'accepted')->count();
        $avgProgress = $totalSubs > 0 ? round(($acceptedSubs / $totalSubs) * 100, 1) : 0;

        // Top students by avg score
        $ranking = Submission::whereHas('problem', fn($q) => $q->whereIn('class_id', $classIds))
            ->select('student_id', DB::raw('AVG(score) as avg_score'), DB::raw('COUNT(*) as total'))
            ->with('student:id,name')
            ->groupBy('student_id')
            ->orderByDesc('avg_score')
            ->limit(10)
            ->get();

        // Error heatmap: error count per topic
        $errorHeatmap = Submission::whereHas('problem', fn($q) => $q->whereIn('class_id', $classIds))
            ->join('coding_problems', 'submissions.problem_id', '=', 'coding_problems.id')
            ->where('submissions.status', '!=', 'accepted')
            ->select('coding_problems.topic', DB::raw('count(*) as error_count'))
            ->groupBy('coding_problems.topic')
            ->orderByDesc('error_count')
            ->limit(10)
            ->get();

        // Top failed topics with fail rate %
        $topFailed = Submission::whereHas('problem', fn($q) => $q->whereIn('class_id', $classIds))
            ->join('coding_problems', 'submissions.problem_id', '=', 'coding_problems.id')
            ->select(
                'coding_problems.topic',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN submissions.status != "accepted" THEN 1 ELSE 0 END) as failed')
            )
            ->groupBy('coding_problems.topic')
            ->having('total', '>', 0)
            ->orderByDesc('failed')
            ->limit(6)
            ->get()
            ->map(fn($t) => [
                'topic'     => $t->topic,
                'fail_rate' => round(($t->failed / $t->total) * 100),
                'total'     => $t->total,
            ]);

        // Recent activity log (last 20 submissions across all classes)
        $recentSubmissions = Submission::whereHas('problem', fn($q) => $q->whereIn('class_id', $classIds))
            ->with(['student:id,name', 'problem:id,title,topic'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // Weekly activity
        $weeklyActivity = Submission::whereHas('problem', fn($q) => $q->whereIn('class_id', $classIds))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as submissions'))
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Class progress overview
        $classProgress = SchoolClass::whereIn('id', $classIds)
            ->withCount('enrollments')
            ->get(['id', 'name', 'grade_level']);

        return response()->json([
            'submission_stats'   => $submissionStats,
            'submissions_24h'    => $submissions24h,
            'avg_progress'       => $avgProgress,
            'ranking'            => $ranking,
            'error_heatmap'      => $errorHeatmap,
            'top_failed_topics'  => $topFailed,
            'recent_submissions' => $recentSubmissions,
            'weekly_activity'    => $weeklyActivity,
            'classes'            => $classProgress,
        ]);
    }

    // ── Siswa Dashboard ────────────────────────────────────────
    public function siswa(Request $request): JsonResponse
    {
        $studentId = $request->user()->id;

        // Skill progress (skill tree data)
        $skillProgress = SkillProgress::where('student_id', $studentId)
            ->orderByDesc('xp')
            ->get();

        // Recent submissions
        $recentSubmissions = Submission::with('problem:id,title,topic,difficulty')
            ->where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Submission summary
        $summary = Submission::where('student_id', $studentId)
            ->select(
                DB::raw('COUNT(*) as total_submissions'),
                DB::raw('SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted'),
                DB::raw('AVG(score) as avg_score'),
                DB::raw('MAX(score) as best_score')
            )
            ->first();

        // Remedial recommendations: topics with low avg score
        $remedial = Submission::where('student_id', $studentId)
            ->join('coding_problems', 'submissions.problem_id', '=', 'coding_problems.id')
            ->select('coding_problems.topic', DB::raw('AVG(submissions.score) as avg_score'))
            ->groupBy('coding_problems.topic')
            ->having('avg_score', '<', 60)
            ->orderBy('avg_score')
            ->limit(5)
            ->get();

        // Activity heatmap (last 90 days)
        $activityHeatmap = ActivityLog::where('user_id', $studentId)
            ->where('created_at', '>=', now()->subDays(90))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as activities'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'skill_progress'    => $skillProgress,
            'recent_submissions'=> $recentSubmissions,
            'summary'           => $summary,
            'remedial'          => $remedial,
            'activity_heatmap'  => $activityHeatmap,
        ]);
    }
}
