<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DockerSandboxService
{
    private string $sandboxImage = 'educode/sandbox:latest';
    private string $tmpDir;
    private bool   $dockerAvailable;

    public function __construct()
    {
        $this->tmpDir = storage_path('app/sandbox');
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0775, true);
        }
        $this->dockerAvailable = $this->checkDocker();
    }

    private function checkDocker(): bool
    {
        exec('docker info 2>/dev/null', $out, $code);
        if ($code !== 0) return false;
        exec("docker image inspect {$this->sandboxImage} 2>/dev/null", $out2, $code2);
        return $code2 === 0;
    }

    /**
     * Compile C++ code. Returns ['success', 'binary_path', 'work_dir'] or ['success'=>false, 'error'].
     * Caller MUST call cleanupWorkDir(work_dir) when done with all runs.
     */
    public function compile(string $code): array
    {
        $workDir = $this->tmpDir . '/' . Str::uuid();
        if (!mkdir($workDir, 0775, true) && !is_dir($workDir)) {
            return ['success' => false, 'error' => 'Failed to create working directory.'];
        }

        $sourceFile = $workDir . '/main.cpp';
        $binaryFile = $workDir . '/program';

        file_put_contents($sourceFile, $code);

        if ($this->dockerAvailable) {
            $command = sprintf(
                'docker run --rm --network none --memory=64m --cpus=0.5 ' .
                '-v %s:/sandbox %s ' .
                'bash -c "g++ -O2 -std=c++17 -o /sandbox/program /sandbox/main.cpp 2>&1"',
                escapeshellarg($workDir),
                escapeshellarg($this->sandboxImage)
            );
        } else {
            // Native fallback: use system g++ (available via Xcode CLI tools)
            $command = sprintf(
                'g++ -O2 -std=c++17 -o %s %s 2>&1',
                escapeshellarg($binaryFile),
                escapeshellarg($sourceFile)
            );
        }

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->cleanupWorkDir($workDir);
            return [
                'success' => false,
                'error'   => implode("\n", $output),
            ];
        }

        return [
            'success'     => true,
            'binary_path' => $binaryFile,
            'work_dir'    => $workDir,
        ];
    }

    /**
     * Run the compiled binary with given input and constraints.
     * NOTE: Does NOT clean up workDir — caller must call cleanupWorkDir() after all runs.
     */
    public function run(string $binaryPath, ?string $input, int $timeoutSeconds = 3, int $memoryMb = 64): array
    {
        $workDir = dirname($binaryPath);

        // Safety check
        if (!is_dir($workDir)) {
            Log::error('DockerSandboxService::run() - workDir missing', ['workDir' => $workDir]);
            return ['output' => '', 'status' => 'runtime_error', 'time_ms' => 0, 'exit_code' => 1];
        }

        $inputFile = $workDir . '/input.txt';
        $hasInput  = ($input !== null && $input !== '');

        if ($hasInput) {
            file_put_contents($inputFile, $input);
        }

        $startTime = microtime(true);

        if ($this->dockerAvailable) {
            $inputRedirect = $hasInput ? '< /sandbox/input.txt' : '';
            $command = sprintf(
                'docker run --rm --network none --memory=%dm --cpus=0.5 ' .
                '-v %s:/sandbox %s ' .
                'bash -c "timeout %d /sandbox/program %s 2>&1"',
                $memoryMb,
                escapeshellarg($workDir),
                escapeshellarg($this->sandboxImage),
                $timeoutSeconds,
                $inputRedirect
            );
        } else {
            // Native fallback
            $inputRedirect = $hasInput ? '< ' . escapeshellarg($inputFile) : '';
            $command = sprintf(
                'timeout %d %s %s 2>&1',
                $timeoutSeconds,
                escapeshellarg($binaryPath),
                $inputRedirect
            );
        }

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        $elapsed = round((microtime(true) - $startTime) * 1000);

        // NOTE: Cleanup is NOT done here — GradingService calls cleanupWorkDir() after all test cases
        $status = match(true) {
            $returnCode === 124 => 'timeout',
            $returnCode === 137 => 'memory_limit',
            $returnCode !== 0   => 'runtime_error',
            default             => 'ok',
        };

        return [
            'output'    => implode("\n", $output),
            'status'    => $status,
            'time_ms'   => $elapsed,
            'exit_code' => $returnCode,
        ];
    }

    /**
     * Manually clean up a work directory after all runs are complete.
     */
    public function cleanupWorkDir(string $dir): void
    {
        if (is_dir($dir)) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }
}
