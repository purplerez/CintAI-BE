<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeWebProjectJob;
use App\Models\ProjectAssignment;
use App\Models\ProjectSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $assignments = ProjectAssignment::with(['creator:id,name', 'schoolClass:id,name'])
            ->when($request->user()->hasRole('siswa'), function ($q) use ($request) {
                $userId = $request->user()->id;
                $classIds = $request->user()->enrollments()->where('status', 'active')->pluck('class_id');
                $q->whereIn('class_id', $classIds)
                  ->where('is_published', true)
                  ->with(['submissions' => function ($sq) use ($userId) {
                      $sq->where('student_id', $userId);
                  }]);
            })
            ->when($request->user()->hasRole('guru'), fn($q) => $q->where('created_by', $request->user()->id))
            ->orderByDesc('deadline')
            ->paginate(15);

        return response()->json($assignments);
    }

    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'class_id'     => 'required|exists:classes,id',
            'title'        => 'required|string|max:255',
            'client_brief' => 'nullable|string',
            'requirements' => 'nullable|string',
            'checklist'    => 'nullable|array',
            'deadline'     => 'nullable|date|after:now',
            'max_score'    => 'integer|min:1|max:100',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['is_published'] = true;
        $assignment = ProjectAssignment::create($data);
        return response()->json($assignment, 201);
    }

    public function show(ProjectAssignment $project): JsonResponse
    {
        $project->load(['submissions.student:id,name', 'schoolClass:id,name', 'creator:id,name']);
        return response()->json($project);
    }

    public function update(Request $request, ProjectAssignment $project): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $project->update($request->validate([
            'title'        => 'sometimes|string',
            'client_brief' => 'sometimes|string',
            'deadline'     => 'sometimes|date',
            'is_published' => 'sometimes|boolean',
        ]));
        return response()->json($project);
    }

    // ── Student Submission ─────────────────────────────────────
    public function submitProject(Request $request, ProjectAssignment $project): JsonResponse
    {
        $request->validate([
            'repository_url' => 'nullable|url',
            'zip_file'       => 'nullable|file|mimes:zip|max:20480',
        ]);

        $path = null;
        if ($request->hasFile('zip_file')) {
            $path = $request->file('zip_file')->store('project-submissions', 'local');
        }

        $submission = ProjectSubmission::updateOrCreate(
            ['assignment_id' => $project->id, 'student_id' => $request->user()->id],
            [
                'repository_url' => $request->repository_url,
                'zip_file_path'  => $path,
                'status'         => 'pending',
                'submitted_at'   => now(),
            ]
        );

        if ($path) {
            AnalyzeWebProjectJob::dispatch($submission);
        }

        return response()->json([
            'submission'=> $submission,
            'message'   => $path ? 'File uploaded. Analisis sedang berjalan.' : 'Submission disimpan.',
        ], 201);
    }

    public function submissionStatus(ProjectSubmission $submission): JsonResponse
    {
        return response()->json($submission);
    }

    // ── Guru: grade a submission ───────────────────────────────
    public function grade(Request $request, ProjectSubmission $submission): JsonResponse
    {
        if (!$request->user()->hasAnyRole(['guru', 'admin'])) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }
        $data = $request->validate([
            'score'    => 'required|numeric|min:0|max:100',
            'feedback' => 'nullable|string',
        ]);
        $submission->update(array_merge($data, ['status' => 'graded']));
        return response()->json($submission);
    }
}
