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

    case 'delete':
        $id = intval($body['id'] ?? 0);

        if (!$id) {
            echo json_encode(['success' => false, 'error' => 'ID inválido.']);
            exit;
        }

        // Busca slug para apagar pasta
        $stmt = db()->prepare('SELECT slug FROM projects WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $client['id']]);
        $project = $stmt->fetch();

        if (!$project) {
            echo json_encode(['success' => false, 'error' => 'Projeto não encontrado.']);
            exit;
        }

        // Apaga pasta de arquivos se existir
        if (!empty($project['slug'])) {
            $dir = __DIR__ . '/../projetos/' . $project['slug'];
            if (is_dir($dir)) {
                deleteDirectory($dir);
            }
        }

        // Apaga mensagens e projeto do banco
        $stmt = db()->prepare('DELETE FROM messages WHERE project_id = ? AND client_id = ?');
        $stmt->execute([$id, $client['id']]);

        $stmt = db()->prepare('DELETE FROM projects WHERE id = ? AND client_id = ?');
        $stmt->execute([$id, $client['id']]);

        echo json_encode(['success' => true]);
        break;

    case 'rename':
        $id   = intval($body['id']   ?? 0);
        $name = trim($body['name']   ?? '');

        if (!$id || !$name) {
            echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
            exit;
        }

        $stmt = db()->prepare('
            UPDATE projects
            SET custom_name = ?
            WHERE id = ? AND client_id = ?
        ');
        $stmt->execute([$name, $id, $client['id']]);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ação inválida.']);
}

// ─── Helper ───────────────────────────────────────────────────
function deleteDirectory(string $dir): void {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}