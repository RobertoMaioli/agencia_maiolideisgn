<?php
require_once __DIR__ . '/config/config.php';
requireAuth();

$client = auth();

// Busca chaves do banco + .env
$settingsStmt = db()->prepare('SELECT * FROM settings WHERE client_id = ? LIMIT 1');
$settingsStmt->execute([$client['id']]);
$settingsRow = $settingsStmt->fetch() ?: [];

$anthropicKey  = !empty($settingsRow['anthropic_key'])  ? $settingsRow['anthropic_key']  : env('ANTHROPIC_KEY');
$openaiKey     = !empty($settingsRow['openai_key'])      ? $settingsRow['openai_key']      : env('OPENAI_KEY');
$openrouterKey = !empty($settingsRow['openrouter_key'])  ? $settingsRow['openrouter_key']  : env('OPENROUTER_KEY');

// Busca agentes
$stmt = db()->prepare('SELECT id, slug, name, model, provider FROM agents WHERE client_id = ? ORDER BY id');
$stmt->execute([$client['id']]);
$agents = $stmt->fetchAll();

function testAnthropic(string $key, string $model): array {
    if (empty($key)) return ['ok' => false, 'msg' => 'Chave não configurada', 'ms' => 0];
    $t = microtime(true);
    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'      => $model,
            'max_tokens' => 20,
            'messages'   => [['role' => 'user', 'content' => 'Responda apenas: ok']],
        ]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    $ms = round((microtime(true) - $t) * 1000);
    if ($curlErr) return ['ok' => false, 'msg' => "cURL: $curlErr", 'ms' => $ms];
    $data = json_decode($response, true);
    if ($httpCode !== 200) return ['ok' => false, 'msg' => "HTTP $httpCode — " . ($data['error']['message'] ?? ''), 'ms' => $ms];
    return ['ok' => true, 'msg' => 'OK', 'ms' => $ms];
}

function testOpenAI(string $key, string $model): array {
    if (empty($key)) return ['ok' => false, 'msg' => 'Chave não configurada', 'ms' => 0];
    $t = microtime(true);
    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'      => $model,
            'max_tokens' => 20,
            'messages'   => [['role' => 'user', 'content' => 'Responda apenas: ok']],
        ]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    $ms = round((microtime(true) - $t) * 1000);
    if ($curlErr) return ['ok' => false, 'msg' => "cURL: $curlErr", 'ms' => $ms];
    $data = json_decode($response, true);
    if ($httpCode !== 200) return ['ok' => false, 'msg' => "HTTP $httpCode — " . ($data['error']['message'] ?? ''), 'ms' => $ms];
    return ['ok' => true, 'msg' => 'OK', 'ms' => $ms];
}

function testOpenRouter(string $key, string $model): array {
    if (empty($key)) return ['ok' => false, 'msg' => 'Chave não configurada', 'ms' => 0];
    $t = microtime(true);
    $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode([
            'model'      => $model,
            'max_tokens' => 20,
            'messages'   => [['role' => 'user', 'content' => 'Responda apenas: ok']],
        ]),
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $key,
        ],
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);
    $ms = round((microtime(true) - $t) * 1000);
    if ($curlErr) return ['ok' => false, 'msg' => "cURL: $curlErr", 'ms' => $ms];
    $data = json_decode($response, true);
    if ($httpCode !== 200) return ['ok' => false, 'msg' => "HTTP $httpCode — " . ($data['error']['message'] ?? ''), 'ms' => $ms];
    return ['ok' => true, 'msg' => 'OK', 'ms' => $ms];
}

// Roda os testes
$results = [];

// Testa chaves brutas
$results['keys'] = [
    'anthropic'  => testAnthropic($anthropicKey, 'claude-haiku-4-5-20251001'),
    'openai'     => testOpenAI($openaiKey, 'gpt-4o-mini'),
    'openrouter' => testOpenRouter($openrouterKey, 'openai/gpt-4o-mini'),
];

// Testa cada agente com seu próprio modelo
foreach ($agents as $agent) {
    $key = match($agent['provider']) {
        'anthropic'  => $anthropicKey,
        'openai'     => $openaiKey,
        default      => $openrouterKey,
    };
    $testFn = match($agent['provider']) {
        'anthropic' => fn() => testAnthropic($key, $agent['model']),
        'openai'    => fn() => testOpenAI($key, $agent['model']),
        default     => fn() => testOpenRouter($key, $agent['model']),
    };
    $results['agents'][] = array_merge($agent, ['result' => $testFn()]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diagnóstico — Agência Virtual</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0a0f0a;font-family:'DM Mono',monospace;color:#d1fae5;padding:32px 20px;min-height:100vh}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(rgba(74,222,128,0.03) 1px,transparent 1px),linear-gradient(90deg,rgba(74,222,128,0.03) 1px,transparent 1px);background-size:32px 32px;pointer-events:none}
h1{color:#4ade80;font-size:18px;margin-bottom:8px;letter-spacing:.08em}
.subtitle{color:rgba(100,180,130,0.5);font-size:12px;margin-bottom:32px}
h2{font-size:12px;color:rgba(74,222,128,0.6);letter-spacing:.1em;text-transform:uppercase;margin:28px 0 12px;padding-bottom:8px;border-bottom:1px solid rgba(74,222,128,0.1)}
.grid{display:flex;flex-direction:column;gap:10px}
.card{background:rgba(255,255,255,0.03);border:1px solid rgba(74,222,128,0.1);border-radius:8px;padding:14px 18px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.card.ok{border-color:rgba(74,222,128,0.3)}
.card.fail{border-color:rgba(239,68,68,0.3);background:rgba(239,68,68,0.04)}
.card-left{display:flex;align-items:center;gap:12px}
.dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.dot.ok{background:#4ade80;box-shadow:0 0 8px #4ade80}
.dot.fail{background:#ef4444;box-shadow:0 0 8px #ef4444}
.name{font-size:13px;font-weight:500}
.model{font-size:11px;color:rgba(100,180,130,0.5);margin-top:2px}
.badge{font-size:10px;padding:3px 10px;border-radius:20px;font-weight:500}
.badge.ok{background:rgba(74,222,128,0.12);color:#4ade80;border:1px solid rgba(74,222,128,0.2)}
.badge.fail{background:rgba(239,68,68,0.12);color:#f87171;border:1px solid rgba(239,68,68,0.2)}
.ms{font-size:10px;color:rgba(100,180,130,0.4)}
.err{font-size:10px;color:#f87171;margin-top:4px}
.back{display:inline-block;margin-top:32px;padding:8px 20px;border:1px solid rgba(74,222,128,0.25);border-radius:20px;color:#4ade80;text-decoration:none;font-size:12px}
.back:hover{background:rgba(74,222,128,0.08)}
</style>
</head>
<body>
<h1>🔍 Diagnóstico de APIs</h1>
<p class="subtitle">Testando conectividade e autenticação com cada provedor</p>

<h2>Chaves de API</h2>
<div class="grid">
<?php foreach ($results['keys'] as $provider => $r): ?>
<div class="card <?= $r['ok'] ? 'ok' : 'fail' ?>">
  <div class="card-left">
    <div class="dot <?= $r['ok'] ? 'ok' : 'fail' ?>"></div>
    <div>
      <div class="name"><?= strtoupper($provider) ?></div>
      <?php if (!$r['ok']): ?><div class="err"><?= htmlspecialchars($r['msg']) ?></div><?php endif ?>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <?php if ($r['ok']): ?><span class="ms"><?= $r['ms'] ?>ms</span><?php endif ?>
    <span class="badge <?= $r['ok'] ? 'ok' : 'fail' ?>"><?= $r['ok'] ? '✓ OK' : '✗ ERRO' ?></span>
  </div>
</div>
<?php endforeach ?>
</div>

<h2>Agentes</h2>
<div class="grid">
<?php foreach ($results['agents'] as $a): $r = $a['result']; ?>
<div class="card <?= $r['ok'] ? 'ok' : 'fail' ?>">
  <div class="card-left">
    <div class="dot <?= $r['ok'] ? 'ok' : 'fail' ?>"></div>
    <div>
      <div class="name"><?= htmlspecialchars($a['name']) ?> <span style="color:rgba(100,180,130,0.4);font-size:11px">(<?= $a['slug'] ?>)</span></div>
      <div class="model"><?= htmlspecialchars($a['provider']) ?> · <?= htmlspecialchars($a['model']) ?></div>
      <?php if (!$r['ok']): ?><div class="err"><?= htmlspecialchars($r['msg']) ?></div><?php endif ?>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <?php if ($r['ok']): ?><span class="ms"><?= $r['ms'] ?>ms</span><?php endif ?>
    <span class="badge <?= $r['ok'] ? 'ok' : 'fail' ?>"><?= $r['ok'] ? '✓ OK' : '✗ ERRO' ?></span>
  </div>
</div>
<?php endforeach ?>
</div>

<a href="/settings.php" class="back">← Voltar para configurações</a>
</body>
</html>