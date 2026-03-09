<?php

namespace App\Http\Controllers\Logic;

use App\Http\Controllers\Controller;
use App\Models\LogicBuilderFlow;
use App\Services\LogicBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogicBuilderController extends Controller
{
    public function __construct(private LogicBuilderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $flows = LogicBuilderFlow::where('student_id', $request->user()->id)
            ->with('problem:id,title')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($flows);
    }

    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'      => 'nullable|string|max:255',
            'problem_id' => 'nullable|exists:coding_problems,id',
            'flow_data'  => 'required|array',
            'flow_id'    => 'nullable|exists:logic_builder_flows,id',
        ]);

        if ($request->flow_id) {
            $flow = LogicBuilderFlow::where('id', $request->flow_id)
                ->where('student_id', $request->user()->id)
                ->firstOrFail();
            $flow->update([
                'title'      => $data['title'] ?? $flow->title,
                'flow_data'  => $data['flow_data'],
                'problem_id' => $data['problem_id'] ?? $flow->problem_id,
            ]);
        } else {
            $flow = LogicBuilderFlow::create([
                'student_id' => $request->user()->id,
                'title'      => $data['title'] ?? 'Untitled Flow',
                'problem_id' => $data['problem_id'] ?? null,
                'flow_data'  => $data['flow_data'],
            ]);
        }

        return response()->json($flow);
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate(['flow_data' => 'required|array']);

        $code = $this->service->generateCode($request->flow_data);

        return response()->json(['code' => $code]);
    }

    public function simulate(Request $request): JsonResponse
    {
        $request->validate([
            'flow_data' => 'required|array',
            'input'     => 'nullable|string',
        ]);

        $result = $this->service->simulate($request->flow_data, $request->input ?? '');

        return response()->json($result);
    }

    public function destroy(Request $request, LogicBuilderFlow $flow): JsonResponse
    {
        abort_if($flow->student_id !== $request->user()->id, 403);
        $flow->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
