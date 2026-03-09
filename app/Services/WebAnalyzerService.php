<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use ZipArchive;

class WebAnalyzerService
{
    public function __construct(private AIReviewService $aiReviewService) {}

    /**
     * Analyze an uploaded web project ZIP file.
     */
    public function analyzeZip(string $zipStoragePath, string $brief = ''): array
    {
        $fullPath = Storage::path($zipStoragePath);
        $extractDir = sys_get_temp_dir() . '/web_analyze_' . uniqid();

        mkdir($extractDir, 0755, true);

        $zip = new ZipArchive();
        if ($zip->open($fullPath) !== true) {
            return ['error' => 'Tidak dapat membuka file ZIP.', 'score' => 0];
        }

        $zip->extractTo($extractDir);
        $zip->close();

        // 1. Detect if this is a Dynamic Project
        if ($this->isDynamicProject($extractDir)) {
            $result = $this->analyzeDynamicProject($extractDir, $brief);
            $this->cleanupDir($extractDir);
            return $result;
        }

        $result = [
            'passed_checks' => 0,
            'total_checks'  => 8,
            'score'         => 0,
            'details'       => [],
        ];

        // 1. Find index.html
        $indexPath = $this->findFile($extractDir, 'index.html');
        $hasIndex  = $indexPath !== null;
        $result['details']['has_index_html'] = $hasIndex;
        if ($hasIndex) $result['passed_checks']++;

        if ($hasIndex) {
            $html = file_get_contents($indexPath);

            // 2. Check responsive meta tag
            $hasViewport = (bool) preg_match('/<meta[^>]+name=["\']viewport["\'][^>]*>/i', $html);
            $result['details']['has_viewport_meta'] = $hasViewport;
            if ($hasViewport) $result['passed_checks']++;

            // 3. Check semantic HTML5 tags
            $semanticTags = ['<header', '<nav', '<main', '<section', '<article', '<footer', '<aside'];
            $foundTags    = [];
            foreach ($semanticTags as $tag) {
                if (stripos($html, $tag) !== false) {
                    $foundTags[] = ltrim($tag, '<');
                }
            }
            $hasSemanticTags = count($foundTags) >= 3;
            $result['details']['semantic_tags'] = $foundTags;
            $result['details']['has_semantic_tags'] = $hasSemanticTags;
            if ($hasSemanticTags) $result['passed_checks']++;

            // 4. Check title tag
            $hasTitle = (bool) preg_match('/<title>.+<\/title>/i', $html);
            $result['details']['has_title'] = $hasTitle;
            if ($hasTitle) $result['passed_checks']++;

            // 5. Check alt attributes on images
            preg_match_all('/<img[^>]*>/i', $html, $images);
            $imgCount = count($images[0]);
            $altCount = 0;
            foreach ($images[0] as $img) {
                if (preg_match('/alt=["\'][^"\']*["\']/i', $img)) $altCount++;
            }
            $hasAltAttrs = $imgCount === 0 || $altCount === $imgCount;
            $result['details']['img_alt_compliance'] = $hasAltAttrs;
            if ($hasAltAttrs) $result['passed_checks']++;
        }

        // 6. Check CSS file exists
        $cssFiles = $this->findFiles($extractDir, '*.css');
        $hasCss   = !empty($cssFiles);
        $result['details']['has_css'] = $hasCss;
        if ($hasCss) $result['passed_checks']++;

        // 7. Check media query in CSS
        $hasMediaQuery = false;
        foreach ($cssFiles as $cssFile) {
            if (str_contains(file_get_contents($cssFile), '@media')) {
                $hasMediaQuery = true;
                break;
            }
        }
        $result['details']['has_media_query'] = $hasMediaQuery;
        if ($hasMediaQuery) $result['passed_checks']++;

        // 8. Check folder structure (at least 1 subfolder)
        $folders = glob($extractDir . '/*', GLOB_ONLYDIR);
        $hasFolderStructure = count($folders) >= 1;
        $result['details']['has_folder_structure'] = $hasFolderStructure;
        if ($hasFolderStructure) $result['passed_checks']++;

        // Calculate score
        $result['score'] = round(($result['passed_checks'] / $result['total_checks']) * 100, 2);

        // Cleanup
        $this->cleanupDir($extractDir);

        return $result;
    }

    private function isDynamicProject(string $dir): bool
    {
        $indicators = ['composer.json', 'package.json', 'artisan'];
        foreach ($indicators as $file) {
            if ($this->findFile($dir, $file)) return true;
        }

        $phpFiles = $this->findFiles($dir, '*.php');
        if (count($phpFiles) > 0) return true;

        return false;
    }

    private function analyzeDynamicProject(string $dir, string $brief): array
    {
        $importantFiles = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        $count = 0;
        foreach ($iterator as $file) {
            if ($file->isFile() && !str_contains($file->getPathname(), '/vendor/') && !str_contains($file->getPathname(), '/node_modules/')) {
                $ext = $file->getExtension();
                if (in_array(strtolower($ext), ['php', 'js', 'jsx', 'ts', 'tsx', 'vue', 'blade.php'])) {
                    $importantFiles[] = $file->getPathname();
                    $count++;
                    if ($count >= 15) break; 
                }
            }
        }

        $codeBundle = "";
        foreach ($importantFiles as $f) {
            $name = str_replace($dir . '/', '', $f);
            $content = substr(file_get_contents($f), 0, 4000); 
            $codeBundle .= "// --- File: {$name} ---\n{$content}\n\n";
        }

        if (empty(trim($codeBundle))) {
             return ['is_dynamic' => true, 'score' => 0, 'details' => ['error' => 'File source code dinamis utama tidak ditemukan']];
        }

        $aiResult = $this->aiReviewService->reviewWebProject($codeBundle, $brief);

        if ($aiResult['success']) {
            $fb = $aiResult['feedback'];
            return [
                'is_dynamic'    => true,
                'score'         => $fb['score'] ?? 0,
                'details'       => [
                    'quality_notes'      => $fb['quality_notes'] ?? '',
                    'security_focus'     => $fb['security_focus'] ?? '',
                    'architecture_notes' => $fb['architecture_notes'] ?? '',
                    'general_feedback'   => $fb['general_feedback'] ?? ''
                ]
            ];
        }

        return [
            'is_dynamic' => true,
            'score'      => 0,
            'details'    => ['error' => 'AI Review Gagal: ' . ($aiResult['error'] ?? 'Unknown')]
        ];
    }

    private function findFile(string $dir, string $filename): ?string
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->getFilename() === $filename) {
                return $file->getPathname();
            }
        }
        return null;
    }

    private function findFiles(string $dir, string $pattern): array
    {
        return glob($dir . '/**/' . $pattern) ?: glob($dir . '/' . $pattern) ?: [];
    }

    private function cleanupDir(string $dir): void
    {
        if (is_dir($dir)) {
            exec('rm -rf ' . escapeshellarg($dir));
        }
    }
}
