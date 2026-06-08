<?php

// Test external medical APIs directly

echo "=== Testing Medical Data APIs ===\n\n";

$testDrug = 'Paracetamol';

// Test 1: Wikipedia
echo "1. WIKIPEDIA API\n";
echo str_repeat('-', 40) . "\n";
$url = "https://en.wikipedia.org/api/rest_v1/page/summary/" . urlencode($testDrug);
echo "URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: MuneebDrugHouse/1.0']);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";
if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "Title: " . ($data['title'] ?? 'N/A') . "\n";
    echo "Extract: " . substr($data['extract'] ?? 'N/A', 0, 200) . "...\n";
} else {
    echo "Error: " . substr($response, 0, 200) . "\n";
}

// Test 2: DailyMed
echo "\n\n2. DAILYMED (NIH) API\n";
echo str_repeat('-', 40) . "\n";
$url = "https://dailymed.nlm.nih.gov/dailymed/services/v2/spls.json?drug_name=" . urlencode($testDrug) . "&pagesize=1";
echo "URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";
if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "Results found: " . count($data['data'] ?? []) . "\n";
    if (!empty($data['data'])) {
        echo "First result: " . json_encode($data['data'][0], JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "Error: " . substr($response, 0, 200) . "\n";
}

// Test 3: OpenFDA
echo "\n\n3. OPENFDA API\n";
echo str_repeat('-', 40) . "\n";
$searchQuery = urlencode('openfda.generic_name:"' . $testDrug . '"');
$url = "https://api.fda.gov/drug/label.json?search={$searchQuery}&limit=1";
echo "URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";
if ($httpCode == 200) {
    $data = json_decode($response, true);
    echo "Results found: " . count($data['results'] ?? []) . "\n";
    if (!empty($data['results'])) {
        $r = $data['results'][0];
        echo "Brand: " . ($r['openfda']['brand_name'][0] ?? 'N/A') . "\n";
        echo "Generic: " . ($r['openfda']['generic_name'][0] ?? 'N/A') . "\n";
    }
} else {
    echo "Error: " . substr($response, 0, 200) . "\n";
}

// Test 4: PubChem
echo "\n\n4. PUBCHEM API\n";
echo str_repeat('-', 40) . "\n";
$url = "https://pubchem.ncbi.nlm.nih.gov/rest/pug/compound/name/" . urlencode($testDrug) . "/property/MolecularFormula,MolecularWeight,IUPACName/JSON";
echo "URL: $url\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status: $httpCode\n";
if ($httpCode == 200) {
    $data = json_decode($response, true);
    if (!empty($data['PropertyTable']['Properties'])) {
        $props = $data['PropertyTable']['Properties'][0];
        echo "IUPAC Name: " . ($props['IUPACName'] ?? 'N/A') . "\n";
        echo "Formula: " . ($props['MolecularFormula'] ?? 'N/A') . "\n";
        echo "Weight: " . ($props['MolecularWeight'] ?? 'N/A') . "\n";
    }
} else {
    echo "Error: " . substr($response, 0, 200) . "\n";
}

echo "\n\n=== Test Complete ===\n";
