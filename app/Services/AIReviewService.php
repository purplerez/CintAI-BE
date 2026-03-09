<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIReviewService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
        $this->model  = config('services.gemini.model', 'gemini-2.0-flash');
    }

    public function review(string $code, string $problemDescription = ''): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'feedback' => $this->defaultFeedback(), 'error' => 'API Key belum dikonfigurasi.'];
        }

        $prompt = $this->buildPrompt($code, $problemDescription);
        $systemInstruction = 'You are an expert C++ programming teacher for high school RPL students. Provide clear, educational, and encouraging feedback. Always respond ONLY in valid JSON format, no markdown.';

        $data = $this->callGemini($prompt, $systemInstruction);
        if (!$data['success']) {
            return ['success' => false, 'feedback' => $this->defaultFeedback(), 'error' => $data['error']];
        }

        return ['success' => true, 'feedback' => array_merge($this->defaultFeedback(), $data['data'])];
    }

    /**
     * Review Dynamic Web Projects (PHP/JS) and return structured feedback.
     */
    public function reviewWebProject(string $code, string $problemDescription = ''): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'feedback' => $this->defaultWebFeedback(), 'error' => 'API Key belum dikonfigurasi.'];
        }

        $problemSection = $problemDescription ? "Client Brief/Requirements:\n{$problemDescription}\n\n" : '';
        $prompt = <<<PROMPT
Analyze the following dynamic web project source code written by a high school student.

{$problemSection}Source Files:
```
{$code}
```

Respond with a JSON object containing EXACTLY these keys:
- score: integer 1-100 indicating overall quality based on brief fulfillment and code structure
- quality_notes: string explaining code cleanliness, readability, and structural strengths/weaknesses in Bahasa Indonesia
- security_focus: string checking for common web vulnerabilities (SQL injection, XSS, etc.) if applicable, or general safety tips in Bahasa Indonesia
- architecture_notes: string evaluating the separation of concerns (e.g. MVC) or file organization in Bahasa Indonesia
- general_feedback: short encouraging feedback in Bahasa Indonesia
PROMPT;

        $systemInstruction = 'You are an expert Web Development teacher. Evaluate the dynamic web code against the provided brief. Provide clear, constructive feedback. Always respond ONLY in valid JSON format, no markdown.';

        $data = $this->callGemini($prompt, $systemInstruction);
        if (!$data['success']) {
            return ['success' => false, 'feedback' => $this->defaultWebFeedback(), 'error' => $data['error']];
        }

        return ['success' => true, 'feedback' => array_merge($this->defaultWebFeedback(), $data['data'])];
    }

    private function callGemini(string $prompt, string $systemInstruction): array
    {
        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

            $response = Http::timeout(30)->post($url, [
                'contents' => [['parts' => [['text' => $prompt]]]],
                'generationConfig' => ['temperature' => 0.4, 'responseMimeType' => 'application/json'],
                'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            ]);

            if (!$response->successful()) {
                Log::error('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
                return ['success' => false, 'error' => 'API Error: ' . $response->status()];
            }

            $content = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '{}';
            $content = preg_replace('/^```json\s*/i', '', trim($content));
            $content = preg_replace('/\s*```$/i', '', $content);

            return ['success' => true, 'data' => json_decode($content, true) ?? []];
        } catch (\Exception $e) {
            Log::error('AIReviewService error', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function buildPrompt(string $code, string $problemDescription): string
    {
        $problemSection = $problemDescription ? "Problem Description:\n{$problemDescription}\n\n" : '';
        return <<<PROMPT
Analyze the following C++ code written by a high school RPL student.

{$problemSection}Student Code:
```cpp
{$code}
```

Respond with a JSON object containing EXACTLY these keys:
- readability_score: integer 1-10
- correctness_notes: string explaining potential bugs or logic errors
- optimization_tips: array of strings (max 3 tips)
- error_explanation: string explaining any errors in Bahasa Indonesia
- alternative_approach: string describing a better approach if applicable
- encouragement: short motivational message in Bahasa Indonesia
- concepts_used: array of strings e.g. ["loops", "arrays", "functions"]
- improvement_areas: array of strings for topics the student should study more
PROMPT;
    }

    private function defaultFeedback(): array
    {
        return [
            'readability_score'    => 5,
            'correctness_notes'    => 'Kode belum dapat dianalisis saat ini.',
            'optimization_tips'    => [],
            'error_explanation'    => '',
            'alternative_approach' => '',
            'encouragement'        => 'Tetap semangat belajar!',
            'concepts_used'        => [],
            'improvement_areas'    => [],
        ];
    }

    private function defaultWebFeedback(): array
    {
        return [
            'score'              => 0,
            'quality_notes'      => 'Analisis kode dinamis gagal atau belum selesai.',
            'security_focus'     => 'Tidak ada info keamanan.',
            'architecture_notes' => 'Tidak dapat menampilkan arsitektur proyek.',
            'general_feedback'   => 'Coba submit ulang atau periksa log error.',
        ];
    }
}
