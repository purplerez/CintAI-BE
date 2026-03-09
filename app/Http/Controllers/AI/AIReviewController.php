<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\CodingProblem;
use App\Services\AIReviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIReviewController extends Controller
{
    public function __construct(private AIReviewService $aiService) {}

    public function review(Request $request): JsonResponse
    {
        $request->validate([
            'code'       => 'required|string|max:50000',
            'problem_id' => 'nullable|exists:coding_problems,id',
        ]);

        $problemDescription = '';
        if ($request->problem_id) {
            $problem = CodingProblem::find($request->problem_id);
            $problemDescription = $problem?->description ?? '';
        }

        $result = $this->aiService->review($request->code, $problemDescription);

        return response()->json($result);
    }
}
