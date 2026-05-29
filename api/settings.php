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
$projectId   = intval($body['project_id'] ?? 0);

if (!$task || !$agentId) {
    http_response_code(400);
    echo json_encode(['error' => 'Dados incompletos.']);
    exit;
}

$client = auth();

// ─── Busca agente ─────────────────────────────────────────────
$stmt = db()->prepare('SELECT * FROM agents WHERE id = ? AND client_id = ? AND active = 1 LIMIT 1');
$stmt->execute([$agentId, $client['id']]);
$agent = $stmt->fetch();

if (!$agent) {
    http_response_code(404);
    echo json_encode(['error' => 'Agente não encontrado.']);
    exit;
}

// ─── Chaves de API (banco primeiro, fallback .env) ────────────
$settingsStmt = db()->prepare('SELECT * FROM settings WHERE client_id = ? LIMIT 1');
$settingsStmt->execute([$client['id']]);
$settingsRow = $settingsStmt->fetch() ?: [];

$anthropicKey  = !empty($settingsRow['anthropic_key'])  ? $settingsRow['anthropic_key']  : env('ANTHROPIC_KEY');
$openaiKey     = !empty($settingsRow['openai_key'])      ? $settingsRow['openai_key']      : env('OPENAI_KEY');
$openrouterKey = !empty($settingsRow['openrouter_key'])  ? $settingsRow['openrouter_key']  : env('OPENROUTER_KEY');

error_log("KEYS — anthropic:" . ($anthropicKey ? 'ok' : 'VAZIA') . " openai:" . ($openaiKey ? 'ok' : 'VAZIA') . " openrouter:" . ($openrouterKey ? 'ok' : 'VAZIA'));

// ─── Cria ou recupera projeto no banco ───────────────────────
// O project_id vem do front na segunda chamada em diante
if ($agentSlug === 'tech_leader' || !$projectId) {
    // Primeiro agente da cadeia — cria projeto novo
    $slug = slugify($projectName ?: $task) . '-' . date('His');
    $stmt = db()->prepare('
        INSERT INTO projects (client_id, title, custom_name, slug, status, pipeline_stage)
        VALUES (?, ?, ?, ?, "in_progress", "tech_leader")
    ');
    $stmt->execute([
        $client['id'],
        mb_substr($task, 0, 200),
        mb_substr($projectName ?: $task, 0, 100),
        $slug,
    ]);
    $projectId = (int) db()->lastInsertId();
} else {
    // Atualiza o stage atual para rastreabilidade
    $stmt = db()->prepare('UPDATE projects SET pipeline_stage = ? WHERE id = ? AND client_id = ?');
    $stmt->execute([$agentSlug, $projectId, $client['id']]);
}

// ─── Busca estado persistido do pipeline ─────────────────────
$projStmt = db()->prepare('SELECT * FROM projects WHERE id = ? AND client_id = ? LIMIT 1');
$projStmt->execute([$projectId, $client['id']]);
$project = $projStmt->fetch();

// ─── Monta a mensagem para o agente ──────────────────────────
// Contexto do agente anterior — aumentado para 2000 chars
// para o Dev receber o briefing completo do UX/UI
$prevContext = '';
if ($prevReply) {
    $prevTrimmed = mb_substr($prevReply, 0, 2000);
    $prevContext = "\n\nContexto do agente anterior:\n\"{$prevTrimmed}\"";
}

$userMessage = "Tarefa: \"{$task}\"{$prevContext}\n\nExecute sua parte agora em português.";

// QA recebe o HTML completo (do banco, não da sessão)
if ($agentSlug === 'qa') {
    $pendingHtml = $project['pending_html'] ?? '';
    if (empty($pendingHtml)) {
        echo json_encode([
            'reply'      => '⚠️ Sem código para revisar. O Dev precisa gerar o HTML primeiro.',
            'agent'      => $agent['name'],
            'agent_id'   => $agent['id'],
            'project_id' => $projectId,
            'file_url'   => null,
        ]);
        exit;
    }
    // Passa o HTML completo — sem truncamento
    $userMessage = "Tarefa original: \"{$task}\"\n\nO Dev gerou este HTML:\n{$pendingHtml}\n\nRevise e responda com ✅ APROVADO ou ❌ REPROVADO e o motivo.";
}

// ─── Chama o provedor ─────────────────────────────────────────
$reply = callProvider($agent, $userMessage, $anthropicKey, $openaiKey, $openrouterKey);

$fileUrl    = null;
$bubbleText = null;

// Dev gerou HTML — persiste no banco (não na sessão)
if ($agentSlug === 'dev' && containsHtml($reply)) {
    $stmt = db()->prepare('
        UPDATE projects
        SET pending_html = ?, pipeline_task = ?, pipeline_stage = "dev"
        WHERE id = ? AND client_id = ?
    ');
    $stmt->execute([$reply, $task, $projectId, $client['id']]);
    $bubbleText = '💻 Código gerado e enviado para revisão do QA.';
}

// QA aprova ou reprova
if ($agentSlug === 'qa') {
    $qaApproved = isQaApproved($reply);

    if ($qaApproved) {
        $pendingHtml = $project['pending_html'] ?? '';

        if (containsCompleteHtml($pendingHtml)) {
            $fileUrl = saveProjectFile(
                $project['pipeline_task'] ?? $task,
                $pendingHtml,
                $client,
                $projectId
            );
            // Limpa o estado pendente e marca como done
            $stmt = db()->prepare('
                UPDATE projects
                SET pending_html = NULL, pipeline_task = NULL,
                    pipeline_stage = "done", status = "done"
                WHERE id = ? AND client_id = ?
            ');
            $stmt->execute([$projectId, $client['id']]);
        } else {
            $bubbleText = '⚠️ HTML incompleto. O Dev precisa refazer.';
        }
    } else {
        // QA reprovou — limpa o HTML para o Dev refazer
        $stmt = db()->prepare('
            UPDATE projects
            SET pending_html = NULL, pipeline_stage = "qa_rejected"
            WHERE id = ? AND client_id = ?
        ');
        $stmt->execute([$projectId, $client['id']]);
    }
}

// SEO — marca pipeline como finalizado
if ($agentSlug === 'seo') {
    $stmt = db()->prepare('
        UPDATE projects SET pipeline_stage = "seo_done"
        WHERE id = ? AND client_id = ?
    ');
    $stmt->execute([$projectId, $client['id']]);
}

$cleanReply = $bubbleText ?? cleanMarkdown($reply);
saveMessage($client['id'], $agentId, $task, $cleanReply, $projectName, $projectId);

echo json_encode([
    'reply'      => $fileUrl ? '✅ QA aprovou! Landing page publicada.' : $cleanReply,
    'agent'      => $agent['name'],
    'agent_id'   => $agent['id'],
    'project_id' => $projectId,
    'file_url'   => $fileUrl,
]);
exit;

// ═══════════════════════════════════════════════════════════════
// FUNÇÕES
// ═══════════════════════════════════════════════════════════════

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
    $maxTokens = match($agentSlug) {
        'dev'        => 6000,
        'qa'         => 1000,
        'ux_ui'      => 800,
        'seo'        => 800,
        'tech_leader' => 500,
        default      => 500,
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
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("Anthropic [{$agentSlug}/{$model}] HTTP:{$httpCode} ERR:" . ($curlError ?: 'none'));

    if ($curlError) return 'Não consegui processar agora. Tente novamente.';
    if ($httpCode !== 200) {
        $data   = json_decode($response, true);
        $errMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
        error_log("Anthropic erro: {$errMsg}");
        return 'Não consegui processar agora. Tente novamente.';
    }

    $data = json_decode($response, true);
    return $data['content'][0]['text'] ?? 'Sem resposta.';
}

function callOpenAI(string $key, string $model, string $system, string $userMsg, string $agentSlug = ''): string {
    $maxTokens = match($agentSlug) {
        'dev'   => 6000,
        'qa'    => 1000,
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
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    error_log("OpenAI [{$agentSlug}/{$model}] HTTP:{$httpCode} ERR:" . ($curlError ?: 'none'));

    if ($curlError) return 'Não consegui processar agora. Tente novamente.';
    if ($httpCode !== 200) {
        $data   = json_decode($response, true);
        $errMsg = $data['error']['message'] ?? "HTTP {$httpCode}";
        error_log("OpenAI erro: {$errMsg}");
        return 'Não consegui processar agora. Tente novamente.';
    }

    $data = json_decode($response, true);
    return $data['choices'][0]['message']['content'] ?? 'Sem resposta.';
}

function callOpenRouter(string $key, string $model, string $system, string $userMsg, string $agentSlug = ''): string {
    $maxTokens = match($agentSlug) {
        'dev'   => 6000,
        'qa'    => 1000,
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

        error_log("OpenRouter [{$agentSlug}/{$model}] tentativa {$attempt} HTTP:{$httpCode}");

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
                $content   = implode('. ', array_slice($sentences, -3)) . '.';
            }
        }

        if (!empty($content)) return $content;
    }

    return 'Não consegui processar agora. Tente novamente.';
}

function containsHtml(string $text): bool {
    return str_contains(strtolower($text), '<!doctype html') ||
           str_contains(strtolower($text), '<html');
}

function containsCompleteHtml(string $text): bool {
    $lower = strtolower($text);
    return str_contains($lower, '<!doctype html') &&
           str_contains($lower, '</html>') &&
           str_contains($lower, '</body>');
}

function isQaApproved(string $reply): bool {
    return str_contains($reply, '✅ APROVADO');
}

function cleanMarkdown(string $text): string {
    $text = preg_replace('/```[a-z]*\n?/', '', $text);
    $text = preg_replace('/```/',          '', $text);
    $text = preg_replace('/\*\*(.*?)\*\*/', '$1', $text);
    $text = preg_replace('/#{1,6}\s+/',    '', $text);
    return trim($text);
}

function slugify(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return mb_substr($text, 0, 40);
}

function saveProjectFile(string $task, string $html, array $client, int $projectId): ?string {
    $slug    = slugify($task) . '-' . date('His');
    $dir     = __DIR__ . '/../projetos/' . $slug;
    $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');

    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        error_log("Falha ao criar diretório: {$dir}");
        return null;
    }

    $filePath = $dir . '/index.html';
    if (file_put_contents($filePath, $html) === false) {
        error_log("Falha ao salvar HTML: {$filePath}");
        return null;
    }

    // Atualiza o slug do projeto no banco
    $stmt = db()->prepare('UPDATE projects SET slug = ? WHERE id = ? AND client_id = ?');
    $stmt->execute([$slug, $projectId, $client['id']]);

    return $appUrl . '/projetos/' . $slug . '/index.html';
}

function saveMessage(int $clientId, int $agentId, string $task, string $reply, string $projectName, int $projectId = 0): void {
    // Garante que o projeto existe
    if (!$projectId) {
        $stmt = db()->prepare('SELECT id FROM projects WHERE client_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->execute([$clientId]);
        $row = $stmt->fetch();
        $projectId = $row ? (int)$row['id'] : 0;
    }

    if (!$projectId) return;

    // Salva a mensagem do usuário (só na primeira vez, evita duplicatas)
    $check = db()->prepare('SELECT id FROM messages WHERE project_id = ? AND role = "user" LIMIT 1');
    $check->execute([$projectId]);
    if (!$check->fetch()) {
        $stmt = db()->prepare('
            INSERT INTO messages (project_id, client_id, agent_id, role, content)
            VALUES (?, ?, NULL, "user", ?)
        ');
        $stmt->execute([$projectId, $clientId, $task]);
    }

    // Salva a resposta do agente
    $stmt = db()->prepare('
        INSERT INTO messages (project_id, client_id, agent_id, role, content)
        VALUES (?, ?, ?, "agent", ?)
    ');
    $stmt->execute([$projectId, $clientId, $agentId, $reply]);
}