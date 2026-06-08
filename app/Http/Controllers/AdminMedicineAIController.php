<?php

namespace App\Http\Controllers;

use App\Services\MedicineAI\MedicineAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * AdminMedicineAIController - Admin Medicine AI Intelligence Portal
 *
 * Advanced AI queries, comparisons, and detailed analysis
 */
class AdminMedicineAIController extends Controller
{
    private MedicineAIService $aiService;

    public function __construct()
    {
        $this->aiService = new MedicineAIService();
    }

    /**
     * Advanced AI query for medicine information
     * POST /api/admin/medicine-ai/query
     */
    public function query(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'medicine' => 'required|string|min:2|max:200',
            'query' => 'required|string|min:5|max:1000',
            'prompt_type' => 'nullable|string|in:compare,mechanism,contraindications,pediatric,geriatric,interactions,pharmacokinetics,overdose'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $medicine = $request->input('medicine');
        $query = $request->input('query');
        $promptType = $request->input('prompt_type');

        $result = $this->aiService->adminQuery($medicine, $query, $promptType);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Compare two medicines
     * POST /api/admin/medicine-ai/compare
     */
    public function compare(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'medicine1' => 'required|string|min:2|max:200',
            'medicine2' => 'required|string|min:2|max:200'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $result = $this->aiService->compareMedicines(
            $request->input('medicine1'),
            $request->input('medicine2')
        );

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Get available prompt types
     * GET /api/admin/medicine-ai/prompts
     */
    public function getPromptTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'prompts' => $this->aiService->getAdminPromptTypes()
        ]);
    }

    /**
     * Batch query for multiple medicines
     * POST /api/admin/medicine-ai/batch
     */
    public function batchQuery(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'medicines' => 'required|array|min:1|max:5',
            'medicines.*' => 'string|min:2|max:200',
            'query' => 'required|string|min:5|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $medicines = $request->input('medicines');
        $query = $request->input('query');
        $results = [];

        foreach ($medicines as $medicine) {
            $results[$medicine] = $this->aiService->adminQuery($medicine, $query);
        }

        return response()->json([
            'success' => true,
            'query' => $query,
            'results' => $results
        ]);
    }

    /**
     * Export AI analysis as structured data
     * POST /api/admin/medicine-ai/export
     */
    public function export(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'medicine' => 'required|string|min:2|max:200',
            'format' => 'nullable|string|in:json,csv,markdown'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $medicine = $request->input('medicine');
        $format = $request->input('format', 'json');

        // Get comprehensive data
        $queries = [
            'overview' => 'Provide a comprehensive overview of this medicine',
            'usage' => 'What are all the indications and uses?',
            'safety' => 'What are all warnings, contraindications, and precautions?',
            'interactions' => 'List all known drug interactions',
            'dosing' => 'What is the dosing information for different populations?'
        ];

        $analysis = [];
        foreach ($queries as $section => $query) {
            $result = $this->aiService->adminQuery($medicine, $query);
            $analysis[$section] = $result['success'] ? $result['answer'] : 'Unable to retrieve';
        }

        $exportData = [
            'medicine' => $medicine,
            'generated_at' => now()->toISOString(),
            'analysis' => $analysis,
            'sources' => ['Wikipedia', 'DailyMed', 'OpenFDA', 'PubChem', 'Internal Database'],
            'disclaimer' => 'This analysis is for informational purposes only. Not medical advice.'
        ];

        if ($format === 'markdown') {
            $markdown = $this->formatAsMarkdown($medicine, $analysis);
            return response()->json([
                'success' => true,
                'format' => 'markdown',
                'content' => $markdown
            ]);
        }

        if ($format === 'csv') {
            $csv = $this->formatAsCSV($medicine, $analysis);
            return response()->json([
                'success' => true,
                'format' => 'csv',
                'content' => $csv
            ]);
        }

        return response()->json([
            'success' => true,
            'format' => 'json',
            'data' => $exportData
        ]);
    }

    /**
     * Clear cache for a medicine
     * DELETE /api/admin/medicine-ai/cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $medicine = $request->input('medicine');

        if ($medicine) {
            $this->aiService->clearCache($medicine);
            return response()->json([
                'success' => true,
                'message' => "Cache cleared for {$medicine}"
            ]);
        }

        return response()->json([
            'success' => false,
            'error' => 'Medicine name required'
        ], 422);
    }

    /**
     * Format analysis as Markdown
     */
    private function formatAsMarkdown(string $medicine, array $analysis): string
    {
        $md = "# Medicine Analysis: {$medicine}\n\n";
        $md .= "*Generated on: " . now()->format('Y-m-d H:i:s') . "*\n\n";
        $md .= "---\n\n";

        $sections = [
            'overview' => 'Overview',
            'usage' => 'Indications & Uses',
            'safety' => 'Safety Information',
            'interactions' => 'Drug Interactions',
            'dosing' => 'Dosing Information'
        ];

        foreach ($sections as $key => $title) {
            $md .= "## {$title}\n\n";
            $md .= ($analysis[$key] ?? 'No information available') . "\n\n";
        }

        $md .= "---\n\n";
        $md .= "**Sources:** Wikipedia, DailyMed (NIH), OpenFDA, PubChem\n\n";
        $md .= "⚠️ **Disclaimer:** This information is for educational purposes only. Always consult a healthcare professional.\n";

        return $md;
    }

    /**
     * Format analysis as CSV
     */
    private function formatAsCSV(string $medicine, array $analysis): string
    {
        $lines = [
            ['Medicine', 'Section', 'Information'],
        ];

        foreach ($analysis as $section => $content) {
            // Clean content for CSV
            $cleanContent = str_replace(["\n", "\r", '"'], [' ', ' ', "'"], $content);
            $lines[] = [$medicine, ucfirst($section), $cleanContent];
        }

        $csv = '';
        foreach ($lines as $line) {
            $csv .= '"' . implode('","', $line) . "\"\n";
        }

        return $csv;
    }
}
