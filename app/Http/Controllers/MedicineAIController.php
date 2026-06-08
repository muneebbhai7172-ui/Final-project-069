<?php

namespace App\Http\Controllers;

use App\Services\MedicineAI\MedicineAIService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * MedicineAIController - Customer-facing Medicine Chatbot API
 */
class MedicineAIController extends Controller
{
    private MedicineAIService $aiService;

    public function __construct()
    {
        $this->aiService = new MedicineAIService();
    }

    /**
     * Chat with AI about a specific medicine
     * POST /api/medicine-ai/chat
     */
    public function chat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'medicine_id' => 'required|integer|exists:medicines,id',
            'question' => 'required|string|min:3|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $medicineId = $request->input('medicine_id');
        $question = $this->sanitizeQuestion($request->input('question'));

        // Rate limiting check (simple implementation)
        $rateLimitKey = 'medicine_ai_' . ($request->ip() ?? 'unknown');
        $requests = cache()->get($rateLimitKey, 0);

        if ($requests >= 20) { // 20 requests per hour
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Please try again later.'
            ], 429);
        }

        cache()->put($rateLimitKey, $requests + 1, now()->addHour());

        $result = $this->aiService->customerChat($medicineId, $question);

        return response()->json($result, $result['success'] ? 200 : 500);
    }

    /**
     * Get suggested questions for a medicine
     * GET /api/medicine-ai/suggestions
     */
    public function getSuggestions(Request $request): JsonResponse
    {
        $medicineId = $request->query('medicine_id');

        // Get base suggestions
        $suggestions = $this->aiService->getAllSuggestedQuestions();

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
            'medicine_id' => $medicineId
        ]);
    }

    /**
     * Get AI service status
     * GET /api/medicine-ai/status
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'service' => 'Medicine AI Chatbot',
            'version' => '1.0',
            'features' => [
                'chat' => true,
                'suggestions' => true,
                'sources' => ['Wikipedia', 'DailyMed', 'OpenFDA', 'PubChem']
            ],
            'disclaimer' => 'This service provides educational information only. Not medical advice.'
        ]);
    }

    /**
     * Sanitize user question
     */
    private function sanitizeQuestion(string $question): string
    {
        // Remove any HTML/script tags
        $question = strip_tags($question);

        // Remove excessive whitespace
        $question = preg_replace('/\s+/', ' ', $question);

        // Trim
        $question = trim($question);

        // Ensure it ends with a question mark if it's a question
        if (!preg_match('/[.?!]$/', $question)) {
            $question .= '?';
        }

        return $question;
    }
}
