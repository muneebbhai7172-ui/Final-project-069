<?php

namespace App\Services\MedicineAI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MedicalDataFetcher - Fetches medical data from multiple public APIs
 *
 * Sources:
 * - Wikipedia REST API
 * - DailyMed (NIH)
 * - OpenFDA Drug Label API
 * - PubChem
 */
class MedicalDataFetcher
{
    private const CACHE_TTL = 3600; // 1 hour cache
    private const TIMEOUT = 5; // 5 seconds timeout

    /**
     * Fetch data from all medical sources
     */
    public function fetchAllSources(string $medicineName, ?string $salt = null): array
    {
        $results = [
            'wikipedia' => null,
            'pubchem' => null,
            'openfda' => null,
            'dailymed' => null,
            'sources_used' => [],
            'errors' => []
        ];

        // Clean medicine name - extract just the drug name without dosage
        $cleanName = $this->extractDrugName($medicineName);

        // Fetch from Wikipedia (fastest source)
        $results['wikipedia'] = $this->fetchWikipedia($cleanName);
        if ($results['wikipedia']) {
            $results['sources_used'][] = 'Wikipedia';
        }

        // Fetch from OpenFDA - has actual side effects, warnings, dosage
        $results['openfda'] = $this->fetchOpenFDA($cleanName);
        if ($results['openfda']) {
            $results['sources_used'][] = 'FDA';
        }

        // Fetch from PubChem for chemical info
        $results['pubchem'] = $this->fetchPubChem($cleanName);
        if ($results['pubchem']) {
            $results['sources_used'][] = 'PubChem';
        }

        // Fetch from DailyMed if no FDA data
        if (!$results['openfda']) {
            $results['dailymed'] = $this->fetchDailyMed($cleanName);
            if ($results['dailymed']) {
                $results['sources_used'][] = 'DailyMed';
            }
        }

        return $results;
    }

    /**
     * Extract drug name from medicine name (remove dosage like "100mg", "500 mg", etc.)
     */
    private function extractDrugName(string $medicineName): string
    {
        // Remove dosage patterns like "100mg", "500 mg", "250 MG", etc.
        $cleanName = preg_replace('/\s*\d+\s*(mg|ml|g|mcg|iu|units?)\b/i', '', $medicineName);
        // Remove common suffixes like "Tablets", "Capsules", etc.
        $cleanName = preg_replace('/\s*(tablet|capsule|syrup|injection|cream|ointment|suspension)s?\b/i', '', $cleanName);
        // Clean up any extra spaces
        $cleanName = trim(preg_replace('/\s+/', ' ', $cleanName));
        return $cleanName ?: $medicineName;
    }

    /**
     * Fetch from Wikipedia REST API
     */
    public function fetchWikipedia(string $term): ?array
    {
        $cacheKey = 'wikipedia_' . md5($term);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($term) {
            try {
                // Clean and encode the term
                $encodedTerm = urlencode(str_replace(' ', '_', $term));
                $url = "https://en.wikipedia.org/api/rest_v1/page/summary/{$encodedTerm}";

                $response = Http::timeout(self::TIMEOUT)
                    ->withHeaders(['User-Agent' => 'MuneebDrugHouse/1.0'])
                    ->get($url);

                if ($response->successful()) {
                    $data = $response->json();

                    if (isset($data['extract']) && !empty($data['extract'])) {
                        return [
                            'title' => $data['title'] ?? $term,
                            'summary' => $data['extract'] ?? '',
                            'description' => $data['description'] ?? '',
                            'url' => $data['content_urls']['desktop']['page'] ?? null
                        ];
                    }
                }

                // Try alternative search
                return $this->searchWikipedia($term);

            } catch (\Exception $e) {
                Log::warning('Wikipedia API error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Search Wikipedia for the term
     */
    private function searchWikipedia(string $term): ?array
    {
        try {
            $url = "https://en.wikipedia.org/w/api.php";
            $response = Http::timeout(self::TIMEOUT)
                ->get($url, [
                    'action' => 'query',
                    'list' => 'search',
                    'srsearch' => $term . ' medicine drug',
                    'format' => 'json',
                    'srlimit' => 1
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $results = $data['query']['search'] ?? [];

                if (!empty($results)) {
                    $title = $results[0]['title'];
                    $snippet = strip_tags($results[0]['snippet']);

                    return [
                        'title' => $title,
                        'summary' => $snippet,
                        'description' => 'Search result',
                        'url' => "https://en.wikipedia.org/wiki/" . urlencode($title)
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Wikipedia search error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch from DailyMed (NIH) API
     */
    public function fetchDailyMed(string $medicineName): ?array
    {
        $cacheKey = 'dailymed_' . md5($medicineName);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($medicineName) {
            try {
                // Search for the drug
                $searchUrl = "https://dailymed.nlm.nih.gov/dailymed/services/v2/spls.json";
                $response = Http::timeout(self::TIMEOUT)
                    ->get($searchUrl, [
                        'drug_name' => $medicineName,
                        'pagesize' => 1
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $results = $data['data'] ?? [];

                    if (!empty($results)) {
                        $setId = $results[0]['setid'] ?? null;

                        if ($setId) {
                            return $this->fetchDailyMedDetails($setId, $medicineName);
                        }
                    }
                }

                // Alternative: try direct drug name endpoint
                return $this->fetchDailyMedByName($medicineName);

            } catch (\Exception $e) {
                Log::warning('DailyMed API error: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Fetch DailyMed details by setId
     */
    private function fetchDailyMedDetails(string $setId, string $medicineName): ?array
    {
        try {
            $url = "https://dailymed.nlm.nih.gov/dailymed/services/v2/spls/{$setId}.json";
            $response = Http::timeout(self::TIMEOUT)->get($url);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'name' => $data['data']['title'] ?? $medicineName,
                    'indications' => $this->extractSection($data, 'INDICATIONS AND USAGE'),
                    'dosage' => $this->extractSection($data, 'DOSAGE AND ADMINISTRATION'),
                    'warnings' => $this->extractSection($data, 'WARNINGS'),
                    'precautions' => $this->extractSection($data, 'PRECAUTIONS'),
                    'adverse_reactions' => $this->extractSection($data, 'ADVERSE REACTIONS'),
                    'pregnancy' => $this->extractSection($data, 'PREGNANCY'),
                    'storage' => $this->extractSection($data, 'STORAGE'),
                    'manufacturer' => $data['data']['author'] ?? null,
                    'url' => "https://dailymed.nlm.nih.gov/dailymed/drugInfo.cfm?setid={$setId}"
                ];
            }
        } catch (\Exception $e) {
            Log::warning('DailyMed details error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Fetch DailyMed by drug name directly
     */
    private function fetchDailyMedByName(string $name): ?array
    {
        try {
            $url = "https://dailymed.nlm.nih.gov/dailymed/services/v2/drugname/{$name}.json";
            $response = Http::timeout(self::TIMEOUT)->get($url);

            if ($response->successful()) {
                $data = $response->json();

                if (!empty($data['data'])) {
                    return [
                        'name' => $name,
                        'products' => array_slice($data['data'], 0, 3),
                        'url' => "https://dailymed.nlm.nih.gov/dailymed/search.cfm?query={$name}"
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('DailyMed name search error: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Extract section from DailyMed data
     */
    private function extractSection(array $data, string $sectionName): ?string
    {
        // This would parse the structured data - simplified for now
        return null;
    }

    /**
     * Fetch from OpenFDA Drug Label API
     */
    public function fetchOpenFDA(string $searchTerm): ?array
    {
        $cacheKey = 'openfda_' . md5($searchTerm);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($searchTerm) {
            try {
                // Try active ingredient search first
                $url = "https://api.fda.gov/drug/label.json";
                $response = Http::timeout(self::TIMEOUT)
                    ->get($url, [
                        'search' => "openfda.generic_name:\"{$searchTerm}\" OR openfda.brand_name:\"{$searchTerm}\"",
                        'limit' => 1
                    ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $results = $data['results'] ?? [];

                    if (!empty($results)) {
                        $result = $results[0];

                        return [
                            'brand_name' => $result['openfda']['brand_name'][0] ?? null,
                            'generic_name' => $result['openfda']['generic_name'][0] ?? null,
                            'manufacturer' => $result['openfda']['manufacturer_name'][0] ?? null,
                            'purpose' => $this->cleanText($result['purpose'] ?? $result['indications_and_usage'] ?? null),
                            'indications' => $this->cleanText($result['indications_and_usage'] ?? null),
                            'warnings' => $this->cleanText($result['warnings'] ?? null),
                            'dosage' => $this->cleanText($result['dosage_and_administration'] ?? null),
                            'adverse_reactions' => $this->cleanText($result['adverse_reactions'] ?? null),
                            'drug_interactions' => $this->cleanText($result['drug_interactions'] ?? null),
                            'pregnancy' => $this->cleanText($result['pregnancy'] ?? $result['pregnancy_or_breast_feeding'] ?? null),
                            'storage' => $this->cleanText($result['storage_and_handling'] ?? null),
                            'active_ingredient' => $this->cleanText($result['active_ingredient'] ?? null),
                            'inactive_ingredient' => $this->cleanText($result['inactive_ingredient'] ?? null),
                        ];
                    }
                }

            } catch (\Exception $e) {
                Log::warning('OpenFDA API error: ' . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Fetch from PubChem API
     */
    public function fetchPubChem(string $compound): ?array
    {
        $cacheKey = 'pubchem_' . md5($compound);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($compound) {
            try {
                $encodedCompound = urlencode($compound);
                $url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/{$encodedCompound}/JSON";

                $response = Http::timeout(self::TIMEOUT)->get($url);

                if ($response->successful()) {
                    $data = $response->json();
                    $compound = $data['PC_Compounds'][0] ?? null;

                    if ($compound) {
                        // Extract properties
                        $props = $compound['props'] ?? [];
                        $properties = [];

                        foreach ($props as $prop) {
                            $label = $prop['urn']['label'] ?? '';
                            $value = $prop['value']['sval'] ?? $prop['value']['ival'] ?? $prop['value']['fval'] ?? null;

                            if ($label && $value) {
                                $properties[$label] = $value;
                            }
                        }

                        return [
                            'cid' => $compound['id']['id']['cid'] ?? null,
                            'molecular_formula' => $properties['Molecular Formula'] ?? null,
                            'molecular_weight' => $properties['Molecular Weight'] ?? null,
                            'iupac_name' => $properties['IUPAC Name'] ?? null,
                            'url' => "https://pubchem.ncbi.nlm.nih.gov/compound/" . ($compound['id']['id']['cid'] ?? '')
                        ];
                    }
                }

            } catch (\Exception $e) {
                Log::warning('PubChem API error: ' . $e->getMessage());
            }

            return null;
        });
    }

    /**
     * Clean and format text from API responses
     */
    private function cleanText($text): ?string
    {
        if (is_array($text)) {
            $text = implode(' ', $text);
        }

        if (empty($text)) {
            return null;
        }

        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        // Limit length
        $text = substr($text, 0, 2000);
        // Trim
        $text = trim($text);

        return $text ?: null;
    }
}
