<?php
require_once __DIR__ . '/../config/config.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método não permitido.']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true);
$action = $body['action'] ?? '';
$client = auth();

switch ($action) {

    case 'save_keys':
        $openrouterKey = trim($body['openrouter_key'] ?? '');
        $anthropicKey  = trim($body['anthropic_key']  ?? '');
        $githubToken   = trim($body['github_token']   ?? '');
        $githubUser    = trim($body['github_user']    ?? '');

        // Verifica se já existe registro
        $check = db()->prepare('SELECT id FROM settings WHERE client_id = ?');
        $check->execute([$client['id']]);
        $exists = $check->fetch();

        if ($exists) {
            $stmt = db()->prepare('
                UPDATE settings
                SET openrouter_key = ?,
                    anthropic_key  = ?,
                    github_token   = ?,
                    github_user    = ?
                WHERE client_id = ?
            ');
            $stmt->execute([
                $openrouterKey ?: null,
                $anthropicKey  ?: null,
                $githubToken   ?: null,
                $githubUser    ?: null,
                $client['id'],
            ]);
        } else {
            $stmt = db()->prepare('
                INSERT INTO settings (client_id, openrouter_key, anthropic_key, github_token, github_user)
                VALUES (?, ?, ?, ?, ?)
            ');
            $stmt->execute([
                $client['id'],
                $openrouterKey ?: null,
                $anthropicKey  ?: null,
                $githubToken   ?: null,
                $githubUser    ?: null,
            ]);
        }

        // Atualiza o .env também para manter sincronizado
        $envPath = __DIR__ . '/../.env';
        if (file_exists($envPath) && $openrouterKey) {
            $env = file_get_contents($envPath);
            $env = preg_replace('/^OPENROUTER_KEY=.*/m', 'OPENROUTER_KEY=' . $openrouterKey, $env);
            file_put_contents($envPath, $env);
        }

        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
}