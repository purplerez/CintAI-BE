<?php

namespace App\Services;

use App\Models\CodingProblem;
use App\Models\Submission;
use App\Models\SkillProgress;
use Illuminate\Support\Facades\Log;

class GradingService
{
    public function __construct(
        private DockerSandboxService $sandbox
    ) {}

    /**
     * Grade a submission: run all test cases against student code.
     */
    public function grade(Submission $submission): Submission
    {
        $problem   = CodingProblem::with('testCases')->findOrFail($submission->problem_id);
        $testCases = $problem->testCases()->orderBy('order')->get();

        $submission->update(['status' => 'compiling']);

        // Compile
        $compileResult = $this->sandbox->compile($submission->code);
        if (!$compileResult['success']) {
            $submission->update([
                'status'        => 'compile_error',
                'compile_error' => $compileResult['error'],
                'score'         => 0,
            ]);
            return $submission->fresh();
        }

        $submission->update(['status' => 'running']);

        $results     = [];
        $passed      = 0;
        $totalWeight = $testCases->sum('weight') ?: 1;
        $earnedWeight = 0;
        $lastRunResult = null;

        // Run all test cases — workDir stays intact across all runs
        try {
            foreach ($testCases as $testCase) {
                $runResult = $this->sandbox->run(
                    $compileResult['binary_path'],
                    $testCase->input,
                    $problem->time_limit_seconds,
                    $problem->memory_limit_mb
                );

                $lastRunResult  = $runResult;
                $actualOutput   = trim($runResult['output'] ?? '');
                $expectedOutput = trim($testCase->expected_output);
                $casePassed     = $actualOutput === $expectedOutput;

                if ($casePassed) {
                    $passed++;
                    $earnedWeight += $testCase->weight;
                }

                if (!$testCase->is_hidden) {
                    $results[] = [
                        'case_id'  => $testCase->id,
                        'input'    => $testCase->is_sample ? $testCase->input : '[hidden]',
                        'expected' => $testCase->is_sample ? $expectedOutput : '[hidden]',
                        'actual'   => $testCase->is_sample ? $actualOutput : '[hidden]',
                        'passed'   => $casePassed,
                        'time_ms'  => $runResult['time_ms'] ?? null,
                        'status'   => $runResult['status'] ?? 'ok',
                    ];
                }
            }
        } finally {
            // Always clean up work directory after all test cases regardless of errors
            $this->sandbox->cleanupWorkDir($compileResult['work_dir']);
        }

        $score = $totalWeight > 0 ? round(($earnedWeight / $totalWeight) * $problem->max_score, 2) : 0;

        // Logic/hardcode detection
        $logicAnalysis = $this->analyzeLogic($submission->code, $testCases->first()?->expected_output);
        if ($problem->detect_hardcode && ($logicAnalysis['is_hardcoded'] ?? false)) {
            $score = max(0, $score * 0.5);
        }

        $finalStatus = $passed === $testCases->count() ? 'accepted' : 'wrong_answer';
        if (isset($lastRunResult['status']) && $lastRunResult['status'] === 'timeout') {
            $finalStatus = 'time_limit';
        }

        $submission->update([
            'status'            => $finalStatus,
            'score'             => $score,
            'passed_cases'      => $passed,
            'total_cases'       => $testCases->count(),
            'execution_time_ms' => $lastRunResult['time_ms'] ?? null,
            'test_case_results' => $results,
            'logic_analysis'    => $logicAnalysis,
        ]);

        $this->updateSkillProgress($submission->student_id, $problem->topic ?? 'general', $score, $finalStatus);

        return $submission->fresh();
    }

    /**
     * Detect hardcoded output and basic logic patterns.
     */
    private function analyzeLogic(string $code, ?string $expectedOutput): array
    {
        $isHardcoded = false;
        $usesVariables = false;
        $usesLoop = false;
        $usesCondition = false;

        // Hardcode detection: if expected output appears literally in code string
        if ($expectedOutput && str_contains($code, '"' . trim($expectedOutput) . '"')) {
            // Check if there's minimal logic (just a cout with exact expected value)
            $coutPattern = '/cout\s*<<\s*"' . preg_quote(trim($expectedOutput), '/') . '"/i';
            if (preg_match($coutPattern, $code)) {
                $isHardcoded = true;
            }
        }

        // Variable usage detection
        if (preg_match('/\b(int|float|double|string|char|auto)\s+\w+\s*=/', $code)) {
            $usesVariables = true;
        }

        // Loop detection
        if (preg_match('/\b(for|while|do)\s*\(/', $code)) {
            $usesLoop = true;
        }

        // Condition detection
        if (preg_match('/\bif\s*\(/', $code)) {
            $usesCondition = true;
        }

        return [
            'is_hardcoded'    => $isHardcoded,
            'uses_variables'  => $usesVariables,
            'uses_loop'       => $usesLoop,
            'uses_condition'  => $usesCondition,
            'complexity_hint' => $this->estimateComplexity($code),
        ];
    }

    private function estimateComplexity(string $code): string
    {
        $lines = substr_count($code, "\n") + 1;
        if ($lines < 10) return 'very_simple';
        if ($lines < 25) return 'simple';
        if ($lines < 60) return 'moderate';
        return 'complex';
    }

    private function updateSkillProgress(int $studentId, string $topic, float $score, string $status): void
    {
        $progress = SkillProgress::firstOrCreate(
            ['student_id' => $studentId, 'topic' => $topic],
            ['total_attempts' => 0, 'accepted_count' => 0, 'avg_score' => 0, 'best_score' => 0, 'xp' => 0]
        );

        $newTotal = $progress->total_attempts + 1;
        $newAccepted = $progress->accepted_count + ($status === 'accepted' ? 1 : 0);
        $newAvg = round((($progress->avg_score * $progress->total_attempts) + $score) / $newTotal, 2);
        $newBest = max($progress->best_score, $score);
        $xpGain = $status === 'accepted' ? 10 : 2;

        $level = $this->calculateLevel($newAvg, $newAccepted, $newTotal);

        $progress->update([
            'total_attempts'   => $newTotal,
            'accepted_count'   => $newAccepted,
            'avg_score'        => $newAvg,
            'best_score'       => $newBest,
            'level'            => $level,
            'xp'               => $progress->xp + $xpGain,
            'last_activity_at' => now(),
        ]);
    }

    private function calculateLevel(float $avgScore, int $accepted, int $total): string
    {
        $rate = $total > 0 ? $accepted / $total : 0;
        if ($avgScore >= 90 && $rate >= 0.85) return 'expert';
        if ($avgScore >= 75 && $rate >= 0.70) return 'advanced';
        if ($avgScore >= 60 && $rate >= 0.50) return 'proficient';
        if ($avgScore >= 40 && $rate >= 0.30) return 'developing';
        return 'beginner';
    }
}
