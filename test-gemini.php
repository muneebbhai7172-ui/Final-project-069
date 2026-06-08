<?php

// Test Google Gemini API connection
$apiKey = 'AIzaSyDUWI-rHSB36f0Qu4dafj0PIpkd3oxqpiM';
$apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent';

$data = [
    'contents' => [
        [
            'parts' => [
                ['text' => 'Say hello in a friendly way, maximum 10 words.']
            ]
        ]
    ],
    'generationConfig' => [
        'temperature' => 0.4,
        'maxOutputTokens' => 100,
    ]
];

echo "Testing Google Gemini API...\n";
echo "URL: $apiUrl\n";
echo "Model: gemini-2.5-flash\n\n";

$ch = curl_init($apiUrl . '?key=' . $apiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: $httpCode\n";

if ($error) {
    echo "cURL Error: $error\n";
}

if ($response) {
    echo "\nResponse:\n";
    $json = json_decode($response, true);
    echo json_encode($json, JSON_PRETTY_PRINT);

    if (isset($json['candidates'][0]['content']['parts'][0]['text'])) {
        echo "\n\n✓ SUCCESS! AI Response: " . $json['candidates'][0]['content']['parts'][0]['text'] . "\n";
    } elseif (isset($json['error'])) {
        echo "\n\n✗ ERROR: " . $json['error']['message'] . "\n";
    }
} else {
    echo "No response received.\n";
}
