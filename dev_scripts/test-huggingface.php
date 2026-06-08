<?php

$apiKey = 'YOUR_HUGGING_FACE_TOKEN';

// Test with various free models on the new HuggingFace router
$models = [
    'Qwen/Qwen2.5-72B-Instruct',
    'meta-llama/Llama-3.2-3B-Instruct',
    'mistralai/Mistral-Nemo-Instruct-2407',
    'microsoft/Phi-3.5-mini-instruct',
    'HuggingFaceH4/zephyr-7b-beta',
];

$url = 'https://router.huggingface.co/v1/chat/completions';

foreach ($models as $model) {
    echo "\n\nTesting model: $model\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'user', 'content' => 'What is paracetamol used for? Answer in one sentence.']
        ],
        'max_tokens' => 100,
        'temperature' => 0.5
    ]));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "Status: $httpCode\n";

    if ($httpCode == 200) {
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            echo "SUCCESS! Response: " . $data['choices'][0]['message']['content'] . "\n";
            break;
        }
    } else {
        echo "Error: " . substr($response, 0, 200) . "\n";
    }
}
