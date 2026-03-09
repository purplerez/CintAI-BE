<?php

namespace App\Jobs;

use App\Models\Submission;
use App\Services\GradingService;
use App\Services\DockerSandboxService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GradeSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function __construct(public Submission $submission) {}

    public function handle(GradingService $gradingService): void
    {
        try {
            $this->submission->update(['status' => 'queued']);
            $gradingService->grade($this->submission);
        } catch (\Exception $e) {
            Log::error('GradeSubmissionJob failed', [
                'submission_id' => $this->submission->id,
                'error'         => $e->getMessage(),
            ]);
            $this->submission->update([
                'status'        => 'runtime_error',
                'compile_error' => 'Internal grading error: ' . $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->submission->update(['status' => 'runtime_error']);
        Log::error('GradeSubmissionJob permanently failed', ['error' => $exception->getMessage()]);
    }
}
