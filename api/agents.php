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

    case 'create':
        $name   = trim($body['name']          ?? '');
        $role   = trim($body['role']          ?? '');
        $slug   = trim($body['slug']          ?? '');
        $model  = trim($body['model']         ?? 'openai/gpt-oss-20b:free');
        $prompt = trim($body['system_prompt'] ?? '');
        $posX   = intval($body['pos_x']       ?? 5);
        $posY   = intval($body['pos_y']       ?? 4);

        if (!$name || !$role || !$slug || !$prompt) {
            echo json_encode(['success' => false, 'error' => 'Campos obrigatórios faltando.']);
            exit;
        }

        // Verifica slug duplicado
        $check = db()->prepare('SELECT id FROM agents WHERE slug = ? AND client_id = ?');
        $check->execute([$slug, $client['id']]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Slug já existe.']);
            exit;
        }

        $stmt = db()->prepare('
            INSERT INTO agents (client_id, slug, name, role, model, provider, system_prompt, pos_x, pos_y, active)
            VALUES (?, ?, ?, ?, ?, "openrouter", ?, ?, ?, 1)
        ');
        $stmt->execute([$client['id'], $slug, $name, $role, $model, $prompt, $posX, $posY]);
        echo json_encode(['success' => true, 'id' => db()->lastInsertId()]);
        break;

    case 'update':
        $id     = intval($body['id']          ?? 0);
        $name   = trim($body['name']          ?? '');
        $role   = trim($body['role']          ?? '');
        $model  = trim($body['model']         ?? '');
        $prompt = trim($body['system_prompt'] ?? '');
        $posX   = intval($body['pos_x']       ?? 5);
        $posY   = intval($body['pos_y']       ?? 4);

        if (!$id || !$name || !$role || !$prompt) {
            echo json_encode(['success' => false, 'error' => 'Campos obrigatórios faltando.']);
            exit;
        }

        $stmt = db()->prepare('
            UPDATE agents
            SET name = ?, role = ?, model = ?, system_prompt = ?, pos_x = ?, pos_y = ?
            WHERE id = ? AND client_id = ?
        ');
        $stmt->execute([$name, $role, $model, $prompt, $posX, $posY, $id, $client['id']]);
        echo json_encode(['success' => true]);
        break;

    case 'toggle':
        $id     = intval($body['id']     ?? 0);
        $active = intval($body['active'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }

        $stmt = db()->prepare('UPDATE agents SET active = ? WHERE id = ? AND client_id = ?');
        $stmt->execute([$active, $id, $client['id']]);
        echo json_encode(['success' => true]);
        break;
        
    case 'delete':
        $id = intval($body['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }

        // Não permite apagar se for o único agente ativo
        $check = db()->prepare('SELECT COUNT(*) FROM agents WHERE client_id = ? AND active = 1');
        $check->execute([$client['id']]);
        if ((int) $check->fetchColumn() <= 1) {
            echo json_encode(['success' => false, 'error' => 'Precisa ter ao menos 1 agente ativo.']);
            exit;
        }

        $stmt = db()->prepare('DELETE FROM agents WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $client['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
}