<?php

// Simple test to verify DeepSeek API connection
$apiKey = 'sk-77a293c1b6a347d49dc07833e4d88bf6';
$apiUrl = 'https://api.deepseek.com/v1/chat/completions';

$data = [
    'model' => 'deepseek-chat',
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant.'
        ],
        [
            'role' => 'user',
            'content' => 'Say hello in 5 words or less.'
        ]
    ],
    'max_tokens' => 50,
    'temperature' => 0.3
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "Testing DeepSeek API...\n";
echo "URL: $apiUrl\n";
echo "Model: deepseek-chat\n\n";

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

    if (isset($json['choices'][0]['message']['content'])) {
        echo "\n\n✓ SUCCESS! AI Response: " . $json['choices'][0]['message']['content'] . "\n";
    } elseif (isset($json['error'])) {
        echo "\n\n✗ ERROR: " . $json['error']['message'] . "\n";
    }
} else {
    echo "No response received.\n";
}
