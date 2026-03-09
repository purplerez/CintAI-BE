<?php

namespace App\Jobs;

use App\Models\ProjectSubmission;
use App\Services\WebAnalyzerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AnalyzeWebProjectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function __construct(public ProjectSubmission $submission) {}

    public function handle(WebAnalyzerService $analyzer): void
    {
        try {
            $this->submission->update(['status' => 'analyzing']);
            $this->submission->load('assignment');
            $brief = $this->submission->assignment->client_brief ?? '';

            $result = $analyzer->analyzeZip($this->submission->zip_file_path, $brief);

            $this->submission->update([
                'status'          => 'analyzed',
                'analysis_result' => $result,
                'score'           => $result['score'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('AnalyzeWebProjectJob failed', [
                'submission_id' => $this->submission->id,
                'error'         => $e->getMessage(),
            ]);
            $this->submission->update(['status' => 'pending']);
        }
    }
}
