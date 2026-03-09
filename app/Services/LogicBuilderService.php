<?php

namespace App\Services;

class LogicBuilderService
{
    /**
     * Generate C++ source code from a React Flow JSON (nodes + edges).
     */
    public function generateCode(array $flowData): string
    {
        $nodes = collect($flowData['nodes'] ?? []);
        $edges = collect($flowData['edges'] ?? []);

        // Build adjacency map
        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge['source']][] = $edge['target'];
        }

        // Find start node
        $startNode = $nodes->firstWhere('type', 'start');
        if (!$startNode) {
            return "// Error: No start node found in flow.";
        }

        $lines = [
            '#include <iostream>',
            '#include <string>',
            'using namespace std;',
            '',
            'int main() {',
        ];

        $visited = [];
        $this->traverseNodes($startNode['id'], $nodes, $adjacency, $lines, $visited, '    ');

        $lines[] = '    return 0;';
        $lines[] = '}';

        return implode("\n", $lines);
    }

    private function traverseNodes(
        string $nodeId,
        $nodes,
        array $adjacency,
        array &$lines,
        array &$visited,
        string $indent
    ): void {
        if (in_array($nodeId, $visited)) return;
        $visited[] = $nodeId;

        $node = $nodes->firstWhere('id', $nodeId);
        if (!$node) return;

        $data = $node['data'] ?? [];
        $type = $node['type'] ?? 'default';

        switch ($type) {
            case 'start':
                $lines[] = $indent . '// === START ===';
                break;

            case 'end':
                $lines[] = $indent . '// === END ===';
                return;

            case 'declare':
                $varType = $data['varType'] ?? 'int';
                $varName = $data['varName'] ?? 'x';
                $varVal  = $data['value'] ?? '0';
                $lines[] = $indent . "{$varType} {$varName} = {$varVal};";
                break;

            case 'input':
                $varName = $data['varName'] ?? 'input';
                $varType = $data['varType'] ?? 'int';
                $lines[] = $indent . "{$varType} {$varName};";
                $lines[] = $indent . "cin >> {$varName};";
                break;

            case 'output':
                $value = $data['value'] ?? '""';
                $lines[] = $indent . "cout << {$value} << endl;";
                break;

            case 'assign':
                $varName = $data['varName'] ?? 'x';
                $expr    = $data['expression'] ?? '0';
                $lines[] = $indent . "{$varName} = {$expr};";
                break;

            case 'condition':
                $condition = $data['condition'] ?? 'true';
                $lines[] = $indent . "if ({$condition}) {";
                // true branch
                $trueEdge = collect($adjacency[$nodeId] ?? [])->first();
                if ($trueEdge) {
                    $this->traverseNodes($trueEdge, $nodes, $adjacency, $lines, $visited, $indent . '    ');
                }
                $lines[] = $indent . '}';
                break;

            case 'loop':
                $init      = $data['init'] ?? 'int i = 0';
                $condition = $data['condition'] ?? 'i < 10';
                $step      = $data['step'] ?? 'i++';
                $lines[] = $indent . "for ({$init}; {$condition}; {$step}) {";
                foreach ($adjacency[$nodeId] ?? [] as $childId) {
                    $this->traverseNodes($childId, $nodes, $adjacency, $lines, $visited, $indent . '    ');
                }
                $lines[] = $indent . '}';
                return;

            default:
                if (!empty($data['label'])) {
                    $lines[] = $indent . "// {$data['label']}";
                }
        }

        // Continue to next node
        foreach ($adjacency[$nodeId] ?? [] as $nextId) {
            $this->traverseNodes($nextId, $nodes, $adjacency, $lines, $visited, $indent);
        }
    }

    /**
     * Simulate execution step-by-step for visual feedback.
     */
    public function simulate(array $flowData, string $input = ''): array
    {
        $nodes  = collect($flowData['nodes'] ?? []);
        $edges  = collect($flowData['edges'] ?? []);
        $steps  = [];
        $memory = [];

        $adjacency = [];
        foreach ($edges as $edge) {
            $adjacency[$edge['source']][] = $edge['target'];
        }

        $startNode = $nodes->firstWhere('type', 'start');
        if (!$startNode) return ['error' => 'No start node'];

        $inputValues = array_filter(explode("\n", $input));
        $inputIdx    = 0;
        $visited     = [];

        $this->simulateNode($startNode['id'], $nodes, $adjacency, $steps, $memory, $visited, $inputValues, $inputIdx);

        return ['steps' => $steps, 'final_memory' => $memory];
    }

    private function simulateNode(
        string $nodeId,
        $nodes,
        array $adjacency,
        array &$steps,
        array &$memory,
        array &$visited,
        array $inputValues,
        int &$inputIdx
    ): void {
        if (in_array($nodeId, $visited)) return;
        $visited[] = $nodeId;

        $node = $nodes->firstWhere('id', $nodeId);
        if (!$node) return;

        $data = $node['data'] ?? [];
        $type = $node['type'] ?? 'default';

        $step = ['node_id' => $nodeId, 'type' => $type, 'memory' => $memory, 'output' => null];

        switch ($type) {
            case 'declare':
            case 'assign':
                $varName = $data['varName'] ?? 'x';
                $value   = $data['value'] ?? $data['expression'] ?? '0';
                $memory[$varName] = $value;
                $step['action'] = "Set {$varName} = {$value}";
                break;
            case 'input':
                $varName = $data['varName'] ?? 'input';
                $val = $inputValues[$inputIdx++] ?? '0';
                $memory[$varName] = $val;
                $step['action'] = "Read {$varName} = {$val}";
                break;
            case 'output':
                $value = $data['value'] ?? '';
                $resolved = $memory[$value] ?? $value;
                $step['output'] = $resolved;
                $step['action'] = "Output: {$resolved}";
                break;
        }

        $steps[] = $step;

        foreach ($adjacency[$nodeId] ?? [] as $nextId) {
            $this->simulateNode($nextId, $nodes, $adjacency, $steps, $memory, $visited, $inputValues, $inputIdx);
        }
    }
}
