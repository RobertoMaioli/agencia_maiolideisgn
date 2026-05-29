<?php
require_once __DIR__ . '/../config/config.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$body        = json_decode(file_get_contents('php://input'), true);
$task        = trim($body['task']         ?? '');
$agentId     = intval($body['agent_id']   ?? 0);
$prevReply   = trim($body['prev_reply']   ?? '');
$projectName = trim($body['project_name'] ?? '');
$agentSlug   = trim($body['agent_slug']   ?? '');

// Reseta projeto se for o primeiro agente da cadeia
if ($agentSlug === 'tech_leader' && empty($prevReply)) {
    unset($_SESSION['current_project_id']);
    unset($_SESSION['current_project_slug']);
    unset($_SESSION['pending_html']);
    unset($_SESSION['pending_task']);
}

if (!$task || !$agentId) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados incompletos.']);
    exit;
}

$client = auth();

// Busca agente
$stmt = db()->prepare('SELECT * FROM agents WHERE id = ? AND client_id = ? AND active = 1 LIMIT 1');
$stmt->execute([$agentId, $client['id']]);
$agent = $stmt->fetch();

if (!$agent) {
    http_response_code(404);
    echo json_encode(['error' => 'Agente não encontrado.']);
    exit;
}

// ✅ CORRIGIDO: Busca chaves do BANCO primeiro, fallback para .env
$settingsStmt = db()->prepare('SELECT * FROM settings WHERE client_id = ? LIMIT 1');
$settingsStmt->execute([$client['id']]);
$settingsRow = $settingsStmt->fetch() ?: [];

$anthropicKey  = !empty($settingsRow['anthropic_key'])  ? $settingsRow['anthropic_key']  : env('ANTHROPIC_KEY');
$openaiKey     = !empty($settingsRow['openai_key'])      ? $settingsRow['openai_key']      : env('OPENAI_KEY');
$openrouterKey = !empty($settingsRow['openrouter_key'])  ? $settingsRow['openrouter_key']  : env('OPENROUTER_KEY');

// Log para diagnóstico
error_log("KEYS — anthropic:" . (empty($anthropicKey) ? 'VAZIA' : 'ok') . " openai:" . (empty($openaiKey) ? 'VAZIA' : 'ok') . " openrouter:" . (empty($openrouterKey) ? 'VAZIA' : 'ok'));

// Contexto limitado
$prevContext = '';
if ($prevReply) {
    $prevTrimmed = mb_substr($prevReply, 0, 400);
    $prevContext  = "\n\nContexto do agente anterior:\n\"{$prevTrimmed}\"";
}
$userMessage = "Tarefa: \"{$task}\"{$prevContext}\n\nExecute sua parte agora em português.";

// QA recebe HTML para revisar
if ($agent['slug'] === 'qa' && !empty($_SESSION['pending_html'])) {
    $htmlPreview = mb_substr($_SESSION['pending_html'], 0, 1500);
    $userMessage = "Tarefa original: \"{$task}\"\n\nO Dev gerou este HTML:\n{$htmlPreview}\n\nRevise e responda com ✅ APROVADO ou ❌ REPROVADO e o motivo.";
}

// Chama o provedor correto
$reply = callProvider($agent, $userMessage, $anthropicKey, $openaiKey, $openrouterKey);

$fileUrl    = null;
$bubbleText = null;

// Dev gera HTML — guarda na sessão
if ($agent['slug'] === 'dev' && containsHtml($reply)) {
    $_SESSION['pending_html'] = $reply;
    $_SESSION['pending_task'] = $task;
    $bubbleText = '💻 Código gerado e enviado para revisão do QA.';
}

// QA aprova ou reprova
if ($agent['slug'] === 'qa') {
    if (empty($_SESSION['pending_html'])) {
        $bubbleText = '⚠️ Sem código para revisar. Dev precisa gerar o HTML primeiro.';
    } else {
        $qaApproved = isQaApproved($reply);
        if ($qaApproved) {
            $pendingHtml = $_SESSION['pending_html'];
            if (containsCompleteHtml($pendingHtml)) {
                $fileUrl = saveProjectFile(
                    $_SESSION['pending_task'] ?? $task,
                    $pendingHtml,
                    $client
                );
                unset($_SESSION['pending_html']);
                unset($_SESSION['pending_task']);
            } else {
                $bubbleText = '⚠️ HTML incompleto. Dev precisa refazer.';
            }
        }
    }
}

$cleanReply = $bubbleText ?? cleanMarkdown($reply);
saveMessage($client['id'], $agentId, $task, $cleanReply, $projectName);

echo json_encode([
    'reply'    => $fileUrl ? '✅ QA aprovou! Landing page publicada.' : $cleanReply,
    'agent'    => $agent['name'],
    'agent_id' => $agent['id'],
    'file_url' => $fileUrl,
]);
exit;

// ─── Funções ──────────────────────────────────────────────────

function callProvider(array $agent, string $userMsg, string $anthropicKey, string $openaiKey, string $openrouterKey): string {
    switch ($agent['provider']) {
        case 'anthropic':
            if (!$anthropicKey) return 'Chave Anthropic não configurada.';
            return callAnthropic($anthropicKey, $agent['model'], $agent['system_prompt'], $userMsg, $agent['slug']);

        case 'openai':
            if (!$openaiKey) return 'Chave OpenAI não configurada.';
            return callOpenAI($openaiKey, $agent['model'], $agent['system_prompt'], $userMsg, $agent['slug']);

        default:
            if (!$openrouterKey) return 'Chave OpenRouter não configurada.';
            return callOpenRouter($openrouterKey, $agent['model'], $agent['system_prompt'], $userMsg, $agent['slug']);
    }
}

function callAnthropic(string $key, string $model, string $system, string $userMsg, string $agentSlug = ''): string {
    // ✅ CORRIGIDO: tokens adequados por agente
    $maxTokens = match($agentSlug) {
        'dev'   => 6000,
        'qa'    => 800,
        default => 500,
    };

    $payload = json_encode([
        'model'      => $model,
        'max_tokens' => $maxTokens,
        'system'     => $system,
        'messages'   => [['role' => 'user', 'content' => $userMsg]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $key,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 180, // ✅ CORRIGIDO: era 30s
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("Anthropic [$agentSlug/$model] HTTP:$httpCode ERR:" . ($curlError ?: 'none'));

    if ($curlError) return 'Não consegui processar agora. Tente novamente.';
    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        $errMsg = $data['error']['message'] ?? "HTTP $httpCode";
        error_log("Anthropic erro: $errMsg");
        return 'Não consegui processar agora. Tente novamente.';
    }

    $data = json_decode($response, true);
    return $data['content'][0]['text'] ?? 'Sem resposta.';
}

function callOpenAI(string $key, string $model, string $system, string $userMsg, string $agentSlug = ''): string {
    // ✅ CORRIGIDO: tokens adequados por agente
    $maxTokens = match($agentSlug) {
        'dev'   => 6000,
        'qa'    => 800,
        default => 500,
    };

    $payload = json_encode([
        'model'      => $model,
        'messages'   => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $userMsg],
        ],
        'max_tokens' => $maxTokens,
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
        CURLOPT_TIMEOUT        => 180, // ✅ CORRIGIDO: padronizado
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("OpenAI [$agentSlug/$model] HTTP:$httpCode ERR:" . ($curlError ?: 'none'));

    if ($curlError) return 'Não consegui processar agora. Tente novamente.';
    if ($httpCode !== 200) {
        $data = json_decode($response, true);
        $errMsg = $data['error']['message'] ?? "HTTP $httpCode";
        error_log("OpenAI erro: $errMsg");
        return 'Não consegui processar agora. Tente novamente.';
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'Sem resposta.';
}

function callOpenRouter(string $key, string $model, string $system, string $userMsg, string $agentSlug = ''): string {
    $maxTokens = match($agentSlug) {
        'dev'   => 6000,
        'qa'    => 800,
        default => 500,
    };

    $payload = json_encode([
        'model'             => $model,
        'messages'          => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $userMsg],
        ],
        'max_tokens'        => $maxTokens,
        'include_reasoning' => false,
    ]);

    $maxRetries = 3;
    $attempt    = 0;

    while ($attempt < $maxRetries) {
        $attempt++;
        if ($attempt > 1) sleep($attempt * 3);

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $key,
                'HTTP-Referer: ' . ($_ENV['APP_URL'] ?? ''),
                'X-Title: Agencia Virtual',
            ],
            CURLOPT_TIMEOUT        => 180,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        error_log("OpenRouter [$agentSlug/$model] tentativa $attempt HTTP:$httpCode");

        if ($httpCode === 429) { sleep(5); continue; }
        if ($httpCode !== 200 || $curlError) {
            if ($attempt < $maxRetries) continue;
            return 'Não consegui processar agora. Tente novamente.';
        }

        $data    = json_decode($response, true);
        $content = $data['choices'][0]['message']['content'] ?? null;

        if (empty($content)) {
            $reasoning = $data['choices'][0]['message']['reasoning'] ?? null;
            if (!empty($reasoning)) {
                $sentences = array_filter(array_map('trim', explode('.', $reasoning)));
                $last      = array_slice($sentences, -3);
                $content   = implode('. ', $last) . '.';
            }
        }

        if (!empty($content)) return $content;
    }

    return 'Não consegui processar agora. Tente novamente.';
}

function cleanMarkdown(string $text): string {
    $text = preg_replace('/```[a-z]*\n?[\s\S]*?```/m', '', $text);
    $text = preg_replace('/`[^`]+`/', '', $text);
    $text = preg_replace('/\*\*\*(.+?)\*\*\*/', '$1', $text);
    $text = preg_replace('/\*\*(.+?)\*\*/', '$1', $text);
    $text = preg_replace('/\*(.+?)\*/', '$1', $text);
    $text = preg_replace('/__(.+?)__/', '$1', $text);
    $text = preg_replace('/_(.+?)_/', '$1', $text);
    $text = preg_replace('/#{1,6}\s*(.+)/', '$1', $text);
    $text = preg_replace('/^\s*[-*+]\s+/m', '', $text);
    $text = preg_replace('/^\s*\d+\.\s+/m', '', $text);
    $text = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $text);
    $text = preg_replace('/\n{3,}/', "\n\n", $text);
    return trim($text);
}

function containsHtml(string $text): bool {
    return stripos($text, '<html') !== false
        || stripos($text, '<!DOCTYPE') !== false
        || (stripos($text, '<div') !== false && stripos($text, '</div>') !== false);
}

function containsCompleteHtml(string $text): bool {
    return (stripos($text, '<html') !== false || stripos($text, '<!DOCTYPE') !== false)
        && stripos($text, '</html>') !== false;
}

function saveProjectFile(string $task, string $html, array $client): ?string {
    $slug    = slugify($task) . '-' . date('His');
    $dir     = __DIR__ . '/../projetos/' . $slug;
    $urlPath = '/projetos/' . $slug . '/index.html';

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($dir . '/index.html', $html);

    if (!empty($_SESSION['current_project_id'])) {
        $stmt = db()->prepare('UPDATE projects SET slug = ?, status = "done" WHERE id = ?');
        $stmt->execute([$slug, $_SESSION['current_project_id']]);
        $_SESSION['current_project_slug'] = $slug;
    }

    return $urlPath;
}

function slugify(string $text): string {
    $text = strtolower($text);
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', trim($text));
    return substr($text, 0, 20);
}

function saveMessage(int $clientId, int $agentId, string $task, string $reply, string $projectName = ''): void {
    $db = db();

    if (empty($_SESSION['current_project_id'])) {
        $slugBase = $projectName ?: $task;
        $slug     = slugify($slugBase) . '-' . date('His');

        $stmt = $db->prepare('
            INSERT INTO projects (client_id, title, custom_name, slug, status)
            VALUES (?, ?, ?, ?, "in_progress")
        ');
        $stmt->execute([
            $clientId,
            mb_substr($task, 0, 80),
            $projectName ?: null,
            $slug,
        ]);
        $_SESSION['current_project_id']   = (int) $db->lastInsertId();
        $_SESSION['current_project_slug'] = $slug;
    }

    $projectId = $_SESSION['current_project_id'];

    $check = $db->prepare('SELECT COUNT(*) FROM messages WHERE project_id = ? AND role = "user"');
    $check->execute([$projectId]);
    if ((int) $check->fetchColumn() === 0) {
        $stmt = $db->prepare('INSERT INTO messages (project_id, client_id, role, content) VALUES (?, ?, "user", ?)');
        $stmt->execute([$projectId, $clientId, $task]);
    }

    $stmt = $db->prepare('INSERT INTO messages (project_id, client_id, agent_id, role, content) VALUES (?, ?, ?, "agent", ?)');
    $stmt->execute([$projectId, $clientId, $agentId, $reply]);
}

function isQaApproved(string $qaReply): bool {
    $reply    = mb_strtolower($qaReply);
    $rejected = [
        'reprovado', 'reprovar', 'não aprovado',
        'precisa de ajuste', 'precisa corrigir',
        'correção necessária', 'não está correto',
        'problema identificado', 'ajuste necessário',
    ];
    foreach ($rejected as $word) {
        if (str_contains($reply, $word)) return false;
    }
    return true;
}