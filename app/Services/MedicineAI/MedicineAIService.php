<?php

namespace App\Services\MedicineAI;

use App\Models\Medicine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MedicineAIService - Main orchestrator for Medicine AI Chatbot
 *
 * Combines database info, external APIs, and AI reasoning
 * to provide comprehensive medicine information
 */
class MedicineAIService
{
    private MedicalDataFetcher $dataFetcher;
    private HuggingFaceService $aiService;

    // Suggested questions for customer chatbot
    private array $suggestedQuestions = [
        'What is this medicine used for?',
        'What are the common side effects?',
        'Are there any drug interactions I should know about?',
        'What are the warnings and precautions?',
        'How should I take this medicine?',
        'Can this medicine be taken during pregnancy?',
        'What should I do if I miss a dose?',
        'How should this medicine be stored?'
    ];

    // Admin advanced prompts
    private array $adminPrompts = [
        'compare' => 'Compare this medicine with alternatives for the same condition',
        'mechanism' => 'Explain the mechanism of action in detail',
        'contraindications' => 'List all contraindications and absolute restrictions',
        'pediatric' => 'What are the pediatric considerations and dosing?',
        'geriatric' => 'What are the geriatric considerations?',
        'interactions' => 'Provide a comprehensive list of drug interactions',
        'pharmacokinetics' => 'Describe the pharmacokinetics (absorption, distribution, metabolism, excretion)',
        'overdose' => 'What are the signs of overdose and treatment protocols?'
    ];

    public function __construct()
    {
        $this->dataFetcher = new MedicalDataFetcher();
        $this->aiService = new HuggingFaceService();
    }

    /**
     * Handle customer chat query
     */
    public function customerChat(int $medicineId, string $question): array
    {
        try {
            // Get medicine from database
            $medicine = Medicine::find($medicineId);

            if (!$medicine) {
                return $this->errorResponse('Medicine not found');
            }

            // Build context from DB + APIs
            $context = $this->buildContext($medicine);

            // Generate AI response
            $aiResult = $this->aiService->generateResponse(
                $context['text'],
                $question,
                false // customer mode (simpler)
            );

            return $this->formatResponse(
                $medicine,
                $question,
                $aiResult['response'],
                $context['sources'],
                $aiResult['model'] ?? 'unknown'
            );

        } catch (\Exception $e) {
            Log::error('Customer chat error: ' . $e->getMessage());
            return $this->errorResponse('Unable to process your question. Please try again.');
        }
    }

    /**
     * Handle admin advanced query
     */
    public function adminQuery(string $medicineName, string $query, ?string $promptType = null): array
    {
        try {
            // Search medicine in DB or use name directly
            $medicine = Medicine::where('name', 'LIKE', "%{$medicineName}%")
                ->orWhere('description', 'LIKE', "%{$medicineName}%")
                ->first();

            // Build context
            $context = $this->buildContextByName(
                $medicine?->name ?? $medicineName,
                $medicine?->description ?? null,
                $medicine
            );

            // Use advanced prompt if specified
            $finalQuery = $query;
            if ($promptType && isset($this->adminPrompts[$promptType])) {
                $finalQuery = $this->adminPrompts[$promptType] . ": " . $query;
            }

            // Generate AI response with advanced mode
            $aiResult = $this->aiService->generateResponse(
                $context['text'],
                $finalQuery,
                true // advanced mode
            );

            return $this->formatAdminResponse(
                $medicineName,
                $query,
                $aiResult['response'],
                $context['sources'],
                $context['rawData'],
                $aiResult['model'] ?? 'unknown'
            );

        } catch (\Exception $e) {
            Log::error('Admin query error: ' . $e->getMessage());
            return $this->errorResponse('Unable to process query: ' . $e->getMessage());
        }
    }

    /**
     * Compare two medicines
     */
    public function compareMedicines(string $medicine1, string $medicine2): array
    {
        try {
            $context1 = $this->buildContextByName($medicine1, null, null);
            $context2 = $this->buildContextByName($medicine2, null, null);

            $combinedContext = "MEDICINE 1 ({$medicine1}):\n" . $context1['text'] .
                              "\n\nMEDICINE 2 ({$medicine2}):\n" . $context2['text'];

            $question = "Compare {$medicine1} and {$medicine2}. Highlight: 1) Common uses 2) Key differences 3) Side effect profiles 4) Drug interactions 5) Which might be preferred in different situations";

            $aiResult = $this->aiService->generateResponse($combinedContext, $question, true);

            $allSources = array_unique(array_merge($context1['sources'], $context2['sources']));

            return [
                'success' => true,
                'comparison' => [
                    'medicine1' => $medicine1,
                    'medicine2' => $medicine2,
                    'analysis' => $aiResult['response'],
                    'sources' => $allSources,
                    'model' => $aiResult['model'] ?? 'unknown'
                ],
                'disclaimer' => $this->getDisclaimer()
            ];

        } catch (\Exception $e) {
            Log::error('Compare medicines error: ' . $e->getMessage());
            return $this->errorResponse('Unable to compare medicines');
        }
    }

    /**
     * Build context from database medicine record + external APIs
     */
    private function buildContext(Medicine $medicine): array
    {
        $sources = [];
        $contextParts = [];

        // 1. Database info
        $dbContext = $this->buildDatabaseContext($medicine);
        if ($dbContext) {
            $contextParts[] = "LOCAL DATABASE INFORMATION:\n" . $dbContext;
            $sources[] = 'Internal Database';
        }

        // 2. External API data
        $externalData = $this->dataFetcher->fetchAllSources(
            $medicine->name,
            $medicine->description
        );

        if (!empty($externalData['wikipedia'])) {
            if (is_array($externalData['wikipedia'])) {
                $wikiText = $externalData['wikipedia']['summary'] ?? json_encode($externalData['wikipedia']);
            } else {
                $wikiText = $externalData['wikipedia'];
            }
            $contextParts[] = "WIKIPEDIA SUMMARY:\n" . $wikiText;
            $sources[] = 'Wikipedia';
        }

        // OpenFDA has the most useful clinical data (side effects, warnings, dosage)
        if (!empty($externalData['openfda'])) {
            $fdaText = $this->formatFDAData($externalData['openfda']);
            $contextParts[] = "FDA DRUG LABEL INFORMATION:\n" . $fdaText;
            $sources[] = 'FDA';
        }

        if (!empty($externalData['dailymed'])) {
            $dailymedText = $this->formatDailyMedData($externalData['dailymed']);
            $contextParts[] = "DAILYMED (NIH) INFORMATION:\n" . $dailymedText;
            $sources[] = 'DailyMed (NIH)';
        }

        if (!empty($externalData['pubchem'])) {
            $pubchemText = $this->formatPubChemData($externalData['pubchem']);
            $contextParts[] = "CHEMICAL INFORMATION (PubChem):\n" . $pubchemText;
            $sources[] = 'PubChem';
        }

        return [
            'text' => implode("\n\n", $contextParts),
            'sources' => $sources,
            'rawData' => $externalData
        ];
    }

    /**
     * Build context by medicine name (for admin queries without DB record)
     */
    private function buildContextByName(string $name, ?string $description, ?Medicine $medicine): array
    {
        $sources = [];
        $contextParts = [];

        // Database info if available
        if ($medicine) {
            $dbContext = $this->buildDatabaseContext($medicine);
            if ($dbContext) {
                $contextParts[] = "LOCAL DATABASE INFORMATION:\n" . $dbContext;
                $sources[] = 'Internal Database';
            }
        }

        // External API data - try medicine name first
        $externalData = $this->dataFetcher->fetchAllSources($name, $description);

        // If no external data found and we have category, search by category for general info
        if (empty($externalData['wikipedia']) && empty($externalData['openfda']) && $medicine && $medicine->category) {
            $categoryData = $this->dataFetcher->fetchAllSources($medicine->category, null);
            if (!empty($categoryData['wikipedia'])) {
                $externalData['wikipedia'] = $categoryData['wikipedia'];
                $externalData['sources_used'][] = 'Wikipedia (Category)';
            }
        }

        if (!empty($externalData['wikipedia'])) {
            if (is_array($externalData['wikipedia'])) {
                $wikiText = $externalData['wikipedia']['summary'] ?? json_encode($externalData['wikipedia']);
            } else {
                $wikiText = $externalData['wikipedia'];
            }
            $contextParts[] = "WIKIPEDIA SUMMARY:\n" . $wikiText;
            $sources[] = 'Wikipedia';
        }

        // OpenFDA has the most useful clinical data
        if (!empty($externalData['openfda'])) {
            $fdaText = $this->formatFDAData($externalData['openfda']);
            $contextParts[] = "FDA DRUG LABEL INFORMATION:\n" . $fdaText;
            $sources[] = 'FDA';
        }

        if (!empty($externalData['dailymed'])) {
            $dailymedText = $this->formatDailyMedData($externalData['dailymed']);
            $contextParts[] = "DAILYMED (NIH) INFORMATION:\n" . $dailymedText;
            $sources[] = 'DailyMed (NIH)';
        }

        if (!empty($externalData['pubchem'])) {
            $pubchemText = $this->formatPubChemData($externalData['pubchem']);
            $contextParts[] = "CHEMICAL INFORMATION (PubChem):\n" . $pubchemText;
            $sources[] = 'PubChem';
        }

        return [
            'text' => implode("\n\n", $contextParts),
            'sources' => $sources,
            'rawData' => $externalData
        ];
    }

    /**
     * Build context string from database medicine record
     */
    private function buildDatabaseContext(Medicine $medicine): string
    {
        $parts = [];

        $parts[] = "Medicine Name: " . $medicine->name;

        if ($medicine->manufacturer) {
            $parts[] = "Manufacturer: " . $medicine->manufacturer;
        }

        if ($medicine->category) {
            $parts[] = "Category: " . $medicine->category;
        }

        if ($medicine->description) {
            $parts[] = "Description: " . $medicine->description;
        }

        if ($medicine->price) {
            $parts[] = "Price: ₹" . $medicine->price;
        }

        if (isset($medicine->requires_prescription) && $medicine->requires_prescription) {
            $parts[] = "Note: This medicine requires a prescription";
        }

        return implode("\n", $parts);
    }

    /**
     * Format PubChem data for context
     */
    private function formatPubChemData(array $data): string
    {
        $formatted = [];

        if (!empty($data['IUPACName'])) {
            $formatted[] = "IUPAC Name: " . $data['IUPACName'];
        }

        if (!empty($data['MolecularFormula'])) {
            $formatted[] = "Molecular Formula: " . $data['MolecularFormula'];
        }

        if (!empty($data['MolecularWeight'])) {
            $formatted[] = "Molecular Weight: " . $data['MolecularWeight'];
        }

        if (!empty($data['CanonicalSMILES'])) {
            $formatted[] = "Chemical Structure: " . $data['CanonicalSMILES'];
        }

        return implode("\n", $formatted);
    }

    /**
     * Format DailyMed data for context
     */
    private function formatDailyMedData(array $data): string
    {
        $formatted = [];

        if (!empty($data['name'])) {
            $formatted[] = "Name: " . $data['name'];
        }

        $fields = [
            'indications' => 'Uses/Indications',
            'dosage' => 'Dosage',
            'warnings' => 'Warnings',
            'precautions' => 'Precautions',
            'adverse_reactions' => 'Side Effects',
            'pregnancy' => 'Pregnancy Info',
            'storage' => 'Storage',
        ];

        foreach ($fields as $key => $label) {
            if (!empty($data[$key])) {
                $value = is_string($data[$key]) ? $data[$key] : json_encode($data[$key]);
                if (strlen($value) > 500) {
                    $value = substr($value, 0, 500) . '...';
                }
                $formatted[] = "{$label}: {$value}";
            }
        }

        return implode("\n", $formatted);
    }

    /**
     * Format FDA data for context
     */
    private function formatFDAData(array $data): string
    {
        $formatted = [];

        $fields = [
            'indications_and_usage' => 'Indications',
            'warnings' => 'Warnings',
            'warnings_and_cautions' => 'Warnings & Cautions',
            'adverse_reactions' => 'Adverse Reactions',
            'drug_interactions' => 'Drug Interactions',
            'dosage_and_administration' => 'Dosage',
            'contraindications' => 'Contraindications',
            'pregnancy' => 'Pregnancy Information',
            'nursing_mothers' => 'Nursing Mothers',
            'pediatric_use' => 'Pediatric Use',
            'geriatric_use' => 'Geriatric Use',
            'overdosage' => 'Overdosage',
            'how_supplied' => 'How Supplied',
            'storage_and_handling' => 'Storage'
        ];

        foreach ($fields as $key => $label) {
            if (!empty($data[$key])) {
                $value = is_array($data[$key]) ? implode(' ', $data[$key]) : $data[$key];
                // Truncate very long sections
                if (strlen($value) > 1000) {
                    $value = substr($value, 0, 1000) . '...';
                }
                $formatted[] = "{$label}: {$value}";
            }
        }

        return implode("\n\n", $formatted);
    }

    /**
     * Format response for customer chat
     */
    private function formatResponse(Medicine $medicine, string $question, string $answer, array $sources, string $model): array
    {
        return [
            'success' => true,
            'medicine' => [
                'id' => $medicine->id,
                'name' => $medicine->name,
                'description' => $medicine->description
            ],
            'question' => $question,
            'answer' => $answer,
            'sources' => $sources,
            'suggestedQuestions' => $this->getSuggestedQuestions($question),
            'model' => $model,
            'disclaimer' => $this->getDisclaimer(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Format response for admin queries
     */
    private function formatAdminResponse(string $medicineName, string $query, string $answer, array $sources, array $rawData, string $model): array
    {
        return [
            'success' => true,
            'medicine' => $medicineName,
            'query' => $query,
            'answer' => $answer,
            'sources' => $sources,
            'rawData' => $rawData,
            'availablePrompts' => $this->adminPrompts,
            'model' => $model,
            'disclaimer' => $this->getDisclaimer(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Get suggested questions (excluding already asked)
     */
    private function getSuggestedQuestions(string $askedQuestion): array
    {
        $askedLower = strtolower($askedQuestion);

        return array_values(array_filter($this->suggestedQuestions, function($q) use ($askedLower) {
            return !str_contains($askedLower, strtolower(substr($q, 0, 20)));
        }));
    }

    /**
     * Get all suggested questions
     */
    public function getAllSuggestedQuestions(): array
    {
        return $this->suggestedQuestions;
    }

    /**
     * Get admin prompt types
     */
    public function getAdminPromptTypes(): array
    {
        return $this->adminPrompts;
    }

    /**
     * Get medical disclaimer
     */
    private function getDisclaimer(): string
    {
        return "⚠️ DISCLAIMER: This information is for educational purposes only and should not be considered medical advice. Always consult a qualified healthcare professional before starting, stopping, or changing any medication. Do not use this information for self-diagnosis or self-treatment.";
    }

    /**
     * Error response format
     */
    private function errorResponse(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
            'disclaimer' => $this->getDisclaimer()
        ];
    }

    /**
     * Clear cache for a medicine
     */
    public function clearCache(string $medicineName): void
    {
        $cacheKey = 'medicine_ai_' . md5(strtolower($medicineName));
        Cache::forget($cacheKey);
    }
}
