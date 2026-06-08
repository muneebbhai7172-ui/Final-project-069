<?php

namespace App\Services\MedicineAI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * HuggingFaceService - AI Reasoning using Google Gemini API
 *
 * Uses Google Gemini for medical question answering
 */
class HuggingFaceService
{
    private string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';
    private ?string $apiKey;

    // Gemini model
    private array $models = [
        'gemini-2.5-flash',
    ];

    private const MAX_CONTEXT_LENGTH = 10000;
    private const MAX_RETRIES = 2;
    private const TIMEOUT = 30;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
    }

    /**
     * Generate AI response for a medical question
     */
    public function generateResponse(string $context, string $question, bool $advancedMode = false): array
    {
        Log::info("AI request received", ['question' => $question, 'context_length' => strlen($context), 'api_key_set' => !empty($this->apiKey)]);

        // Check if API key is configured
        if (empty($this->apiKey)) {
            Log::error("Gemini API key not configured!");
            return $this->generateFallbackResponse($context, $question);
        }

        $prompt = $this->buildPrompt($context, $question, $advancedMode);

        // Try each model until one works
        foreach ($this->models as $model) {
            Log::info("Trying model: {$model}");
            $result = $this->callModel($model, $prompt);

            if ($result['success']) {
                Log::info("AI response generated successfully", ['model' => $model]);
                return $result;
            }

            Log::warning("Model {$model} failed, trying fallback...");
        }

        // If all models fail, return a graceful fallback
        Log::warning("All AI models failed, using fallback");
        return $this->generateFallbackResponse($context, $question);
    }

    /**
     * Build the prompt for the AI model (optimized for Gemini)
     */
    private function buildPrompt(string $context, string $question, bool $advancedMode): string
    {
        // Truncate context if too long
        $context = $this->truncateContext($context);

        return <<<PROMPT
You are a medical information assistant with access to online medical databases and resources. The information below has been gathered from trusted online sources including Wikipedia, DailyMed (NIH), PubChem, and FDA databases specifically for this medicine.

Your task: Use this web-sourced information to provide comprehensive, accurate answers about the medicine.

--- ONLINE MEDICAL INFORMATION (From Wikipedia, DailyMed, PubChem, FDA) ---
{$context}
--- END OF ONLINE INFORMATION ---

User's Question: {$question}

CRITICAL FORMATTING RULES:
1. ALWAYS use SHORT bullet points (•) - maximum 1-2 lines per point
2. Break long information into multiple small bullet points
3. Use sub-bullets for additional details
4. Keep answers concise and scannable
5. Reference specific sources when providing information (e.g., "According to Wikipedia...", "DailyMed data shows...")
6. If the online data doesn't contain the answer, clearly state that and provide general medical advice to consult a healthcare provider

Example format:
• Main point here (1 line)
• Another point
  - Sub-detail
  - Another sub-detail
• Final point

Provide your answer now in SHORT bullet points based on the online medical information:
PROMPT;
    }

    /**
     * Truncate context to fit model limits
     */
    private function truncateContext(string $context): string
    {
        if (strlen($context) <= self::MAX_CONTEXT_LENGTH) {
            return $context;
        }

        // Smart truncation - keep beginning and end
        $half = (int)(self::MAX_CONTEXT_LENGTH / 2);
        $start = substr($context, 0, $half);
        $end = substr($context, -$half);

        return $start . "\n...[content truncated]...\n" . $end;
    }

    /**
     * Call the Gemini model API
     */
    private function callModel(string $model, string $prompt): array
    {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            try {
                Log::info("Calling Gemini model: {$model}, attempt: " . ($attempt + 1));

                $response = Http::timeout(self::TIMEOUT)
                    ->post($this->apiUrl . '?key=' . $this->apiKey, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt]
                                ]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature' => 0.4,
                            'maxOutputTokens' => 800,
                        ]
                    ]);

                Log::info("Gemini response status: " . $response->status());

                if ($response->successful()) {
                    $data = $response->json();
                    Log::info("Gemini response received");

                    // Handle Gemini response format
                    $text = null;
                    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                        $text = $data['candidates'][0]['content']['parts'][0]['text'];
                    }

                    if ($text && strlen(trim($text)) > 10) {
                        return [
                            'success' => true,
                            'response' => $this->cleanResponse($text),
                            'model' => $model
                        ];
                    }
                }

                // Check for rate limit or loading
                if ($response->status() === 503) {
                    Log::info("Gemini service unavailable, trying fallback...");
                    break;
                }

                // Rate limited
                if ($response->status() === 429) {
                    Log::warning("Rate limited for Gemini, trying fallback...");
                    break;
                }

                // Invalid API key
                if ($response->status() === 401 || $response->status() === 403) {
                    Log::warning("Gemini API: Invalid API key. Using fallback mode.");
                    break;
                }

                // Bad request
                if ($response->status() === 400) {
                    Log::warning("Gemini API: Bad request, using fallback...");
                    break;
                }

                Log::warning("Gemini API error for {$model}: Status=" . $response->status() . ", Body=" . $response->body());

            } catch (\Exception $e) {
                Log::error("Gemini exception for {$model}: " . $e->getMessage());
            }
        }

        return ['success' => false, 'error' => 'Model unavailable'];
    }

    /**
     * Clean the AI response
     */
    private function cleanResponse(string $response): string
    {
        // Remove any prompt echoing
        $response = preg_replace('/^(Answer:|ANSWER:)\s*/i', '', $response);

        // Clean up whitespace
        $response = preg_replace('/\s+/', ' ', $response);
        $response = trim($response);

        // Ensure it's not empty
        if (empty($response)) {
            return "I couldn't generate a specific answer based on the available information.";
        }

        return $response;
    }

    /**
     * Generate fallback response when AI is unavailable
     */
    private function generateFallbackResponse(string $context, string $question): array
    {
        Log::warning('AI models failed, using intelligent fallback');

        // Extract key information from context based on question type
        $response = $this->extractIntelligentResponse($context, $question);

        return [
            'success' => true,
            'response' => $response,
            'model' => 'context-analysis',
            'note' => 'Response generated from FDA, Wikipedia, and PubChem data'
        ];
    }

    /**
     * Extract intelligent response from context based on question type
     */
    private function extractIntelligentResponse(string $context, string $question): string
    {
        $question = strtolower($question);
        $parts = [];

        // Determine question type
        $isSideEffects = preg_match('/side effect|adverse|reaction/i', $question);
        $isDosage = preg_match('/dosage|dose|how to take|administration/i', $question);
        $isWarnings = preg_match('/warning|precaution|caution|danger/i', $question);
        $isInteractions = preg_match('/interaction|interact|combine|with other/i', $question);
        $isPregnancy = preg_match('/pregnan|breastfeed|nursing|lactation/i', $question);
        $isUses = preg_match('/use|what is|treat|indication|purpose|for what/i', $question);

        // Extract FDA Drug Label information (most reliable for clinical data)
        if (preg_match('/FDA DRUG LABEL INFORMATION:\s*(.+?)(?=\n\n[A-Z]|$)/s', $context, $fdaMatch)) {
            $fdaInfo = trim($fdaMatch[1]);

            if ($isSideEffects) {
                if (preg_match('/Adverse Reactions:\s*(.+?)(?=\n[A-Z]|$)/si', $fdaInfo, $match)) {
                    $parts[] = "**Common Side Effects (FDA):**\n";
                    $sideEffects = $this->formatAsBullets($match[1]);
                    $parts[] = $sideEffects;
                }
            }

            if ($isDosage) {
                if (preg_match('/Dosage:\s*(.+?)(?=\n[A-Z]|$)/si', $fdaInfo, $match)) {
                    $parts[] = "**Dosage Information (FDA):**\n";
                    $parts[] = $this->formatAsBullets($match[1]);
                }
            }

            if ($isWarnings) {
                if (preg_match('/Warnings.*?:\s*(.+?)(?=\n[A-Z]|$)/si', $fdaInfo, $match)) {
                    $parts[] = "**Warnings (FDA):**\n";
                    $parts[] = $this->formatAsBullets($match[1]);
                }
            }

            if ($isInteractions) {
                if (preg_match('/Drug Interactions:\s*(.+?)(?=\n[A-Z]|$)/si', $fdaInfo, $match)) {
                    $parts[] = "**Drug Interactions (FDA):**\n";
                    $parts[] = $this->formatAsBullets($match[1]);
                }
            }

            if ($isPregnancy) {
                if (preg_match('/Pregnancy.*?:\s*(.+?)(?=\n[A-Z]|$)/si', $fdaInfo, $match)) {
                    $parts[] = "**Pregnancy Information (FDA):**\n";
                    $parts[] = $this->formatAsBullets($match[1]);
                }
            }

            if ($isUses) {
                if (preg_match('/Indications:\s*(.+?)(?=\n[A-Z]|$)/si', $fdaInfo, $match)) {
                    $parts[] = "**Uses & Indications (FDA):**\n";
                    $parts[] = $this->formatAsBullets($match[1]);
                }
            }
        }

        // If no FDA data found or for general info, check Wikipedia
        if (empty($parts)) {
            if (preg_match('/WIKIPEDIA SUMMARY:\s*(.+?)(?=\n\n[A-Z]|$)/s', $context, $wikiMatch)) {
                $wikiText = trim($wikiMatch[1]);
                $parts[] = "**About this Medicine (Wikipedia):**\n";
                $parts[] = $this->formatAsBullets($wikiText);
            }
        }

        // Extract PubChem chemical information if requested or if no other data
        if (preg_match('/CHEMICAL INFORMATION \(PubChem\):\s*(.+?)(?=\n\n|$)/s', $context, $chemMatch)) {
            $chemInfo = trim($chemMatch[1]);
            $parts[] = "\n**Chemical Properties (PubChem):**";

            // Extract specific chemical data
            if (preg_match('/IUPAC Name:\s*(.+)/', $chemInfo, $iupac)) {
                $parts[] = "• IUPAC Name: " . trim($iupac[1]);
            }
            if (preg_match('/Molecular Formula:\s*(.+)/', $chemInfo, $formula)) {
                $parts[] = "• Formula: " . trim($formula[1]);
            }
            if (preg_match('/Molecular Weight:\s*(.+)/', $chemInfo, $weight)) {
                $parts[] = "• Molecular Weight: " . trim($weight[1]);
            }
        }

        // If we got some data, return it with disclaimer
        if (!empty($parts)) {
            $parts[] = "\n**Important:**";
            $parts[] = "• Always consult a healthcare professional for personalized medical advice";
            $parts[] = "• This information is from FDA, Wikipedia, and PubChem databases";

            return implode("\n", $parts);
        }

        // If we have database info only, format it
        return $this->formatDatabaseInfo($context, $question);
    }

    /**
     * Format text as bullet points
     */
    private function formatAsBullets(string $text): string
    {
        // Split into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $bullets = [];

        foreach (array_slice($sentences, 0, 8) as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 15) {
                // Truncate very long sentences
                if (strlen($sentence) > 200) {
                    $sentence = substr($sentence, 0, 200) . '...';
                }
                $bullets[] = "• " . $sentence;
            }
        }

        return implode("\n", $bullets);
    }

    /**
     * Format database information into a readable response
     */
    private function formatDatabaseInfo(string $context, string $question): string
    {
        $question = strtolower($question);
        $parts = [];

        // Extract medicine details from context
        preg_match('/Medicine Name:\s*(.+)/i', $context, $nameMatch);
        preg_match('/Category:\s*(.+)/i', $context, $categoryMatch);
        preg_match('/Manufacturer:\s*(.+)/i', $context, $manufacturerMatch);
        preg_match('/Description:\s*(.+)/i', $context, $descMatch);
        preg_match('/Price:\s*(.+)/i', $context, $priceMatch);

        $name = $nameMatch[1] ?? 'This medicine';
        $category = $categoryMatch[1] ?? null;
        $manufacturer = $manufacturerMatch[1] ?? null;
        $description = $descMatch[1] ?? null;

        // Build intelligent response based on question type

        // Handle greetings and non-medical questions
        if (preg_match('/(hi|hello|hey|how are you|good morning|good evening)/i', $question)) {
            return "Hello! I'm here to help you with questions about {$name}. You can ask me about:\n\n• What this medicine is used for\n• Side effects\n• Dosage information\n• Drug interactions\n• Pregnancy safety\n• Storage instructions\n\nWhat would you like to know about {$name}?";
        }

        if (strpos($question, 'side effect') !== false) {
            $parts[] = "**Side Effects Information for {$name}:**\n";
            $parts[] = "Based on our database, {$name} is classified as a {$category}.";
            $parts[] = "\n\n**Common side effects may include:**";
            $parts[] = "- Consult the product leaflet for specific side effects";
            $parts[] = "- If you experience any unusual symptoms, contact your healthcare provider immediately";
            $parts[] = "\n\n**Note:** Side effect information varies by individual. Always read the package insert and consult your pharmacist or doctor.";
        } elseif (strpos($question, 'dosage') !== false || strpos($question, 'dose') !== false) {
            $parts[] = "**Dosage Information for {$name}:**\n";
            $parts[] = "Category: {$category}";
            if ($description) {
                $parts[] = "Description: {$description}";
            }
            $parts[] = "\n\n**Important:** Dosage should be determined by a qualified healthcare professional based on your individual condition, age, and other medications.";
            $parts[] = "\n\nPlease consult your doctor or pharmacist for the appropriate dosage.";
        } elseif (strpos($question, 'use') !== false || strpos($question, 'what is') !== false || strpos($question, 'treat') !== false) {
            $parts[] = "**About {$name}:**\n";
            if ($category) {
                $parts[] = "**Category:** {$category}";
            }
            if ($description) {
                $parts[] = "**Description:** {$description}";
            }
            if ($manufacturer) {
                $parts[] = "**Manufacturer:** {$manufacturer}";
            }
            $parts[] = "\n\n**Uses:** Based on its classification as a {$category}, this medicine is typically used to treat conditions related to its therapeutic category.";
            $parts[] = "\n\nFor specific medical conditions this treats, please consult your healthcare provider.";
        } elseif (strpos($question, 'pregnancy') !== false || strpos($question, 'pregnant') !== false) {
            $parts[] = "**Pregnancy Safety for {$name}:**\n";
            $parts[] = "**Important:** The safety of this medication during pregnancy has not been verified in our database.";
            $parts[] = "\n\n**Recommendation:** Always consult your doctor before taking any medication during pregnancy or while breastfeeding.";
        } elseif (strpos($question, 'interaction') !== false) {
            $parts[] = "**Drug Interactions for {$name}:**\n";
            $parts[] = "Category: {$category}";
            $parts[] = "\n\n**Important:** Drug interactions depend on many factors. Always inform your doctor or pharmacist about all medications you are taking, including over-the-counter medicines and supplements.";
        } else {
            // For unrecognized questions, provide helpful guidance
            $parts[] = "I'd be happy to help you learn about {$name}.\n";
            $parts[] = "Here are some questions I can answer:";
            $parts[] = "• What is this medicine used for?";
            $parts[] = "• What are the side effects?";
            $parts[] = "• How should I take this medicine?";
            $parts[] = "• Are there any drug interactions?";
            $parts[] = "• Is it safe during pregnancy?";
            $parts[] = "\nPlease ask me a specific question about {$name}, or consult your healthcare provider for detailed medical advice.";
        }

        return implode("\n", $parts);
    }

    /**
     * Extract relevant information using keyword matching
     */
    private function extractRelevantInfo(string $context, string $question): string
    {
        $question = strtolower($question);
        $sections = [];

        // Determine what type of information is being asked
        $keywords = [
            'side effect' => ['adverse', 'side effect', 'reaction'],
            'dosage' => ['dosage', 'dose', 'administration', 'take', 'how to use'],
            'warning' => ['warning', 'precaution', 'caution', 'danger'],
            'pregnancy' => ['pregnancy', 'pregnant', 'breastfeeding', 'nursing'],
            'use' => ['indication', 'use', 'treat', 'purpose', 'what is'],
            'storage' => ['storage', 'store', 'keep'],
            'interaction' => ['interaction', 'interact', 'combine']
        ];

        $matchedCategory = 'general';
        foreach ($keywords as $category => $terms) {
            foreach ($terms as $term) {
                if (strpos($question, $term) !== false) {
                    $matchedCategory = $category;
                    break 2;
                }
            }
        }

        // Extract sentences containing relevant keywords
        $sentences = preg_split('/[.!?]+/', $context);
        $relevant = [];

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) < 20) continue;

            $sentenceLower = strtolower($sentence);

            if ($matchedCategory !== 'general') {
                foreach ($keywords[$matchedCategory] as $term) {
                    if (strpos($sentenceLower, $term) !== false) {
                        $relevant[] = $sentence;
                        break;
                    }
                }
            }
        }

        if (empty($relevant)) {
            // Return first few sentences as general info
            $relevant = array_slice($sentences, 0, 3);
        }

        $response = implode('. ', array_slice($relevant, 0, 5));

        if (empty($response)) {
            return "Based on the available information, I cannot provide a specific answer to your question. Please consult a healthcare professional for accurate medical advice.";
        }

        return $response . ".";
    }

    /**
     * Check if API key is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }
}
