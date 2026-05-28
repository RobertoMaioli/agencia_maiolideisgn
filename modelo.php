<?php
require_once __DIR__ . '/config/config.php';

$key = env('OPENAI_KEY');

// Busca o agent Dev no banco
$stmt = db()->prepare('SELECT * FROM agents WHERE slug = ? AND client_id = 1 LIMIT 1');
$stmt->execute(['dev']);
$agent = $stmt->fetch();

echo "<pre>";
echo "Modelo: " . $agent['model'] . "\n";
echo "Provider: " . $agent['provider'] . "\n";
echo "Chave OpenAI: " . (empty($key) ? 'VAZIA' : substr($key, 0, 20) . '...') . "\n\n";

$payload = json_encode([
    'model'    => $agent['model'],
    'messages' => [
        ['role' => 'system', 'content' => $agent['system_prompt']],
        ['role' => 'user',   'content' => 'Crie uma landing page simples de carro. Retorne só o HTML.'],
    ],
    'max_tokens' => 3500,
]);

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $key,
    ],
    CURLOPT_TIMEOUT        => 120,
    CURLOPT_CONNECTTIMEOUT => 10,
]);

$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Curl Error: " . ($curlError ?: 'nenhum') . "\n\n";

$data    = json_decode($response, true);
$content = $data['choices'][0]['message']['content'] ?? null;

echo "Tamanho: " . strlen($content ?? '') . " chars\n";
echo "Tem HTML: " . (stripos($content ?? '', '<!DOCTYPE') !== false ? 'SIM' : 'NÃO') . "\n";
echo "HTML completo: " . (stripos($content ?? '', '</html>') !== false ? 'SIM' : 'NÃO') . "\n\n";

if ($data['error'] ?? null) {
    echo "ERRO API: " . json_encode($data['error']) . "\n";
}

echo "Primeiros 500 chars:\n" . substr($content ?? 'NULL', 0, 500) . "\n";
echo "</pre>";