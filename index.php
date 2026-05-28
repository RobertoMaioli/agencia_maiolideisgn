<?php
require_once __DIR__ . '/config/config.php';
requireAuth();

$client = auth();

// Busca agentes do cliente
$stmt = db()->prepare('SELECT * FROM agents WHERE client_id = ? AND active = 1 ORDER BY id');
$stmt->execute([$client['id']]);
$agents = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Agência Virtual — <?= htmlspecialchars($client['name']) ?></title>
<link rel="stylesheet" href="/assets/css/game.css?07"/>
</head>
<body>

<div id="game-container">
  <canvas id="game-canvas"></canvas>
</div>

<div id="hud">
  <div id="topbar">
    <div class="topbar-left">
      <span class="online-dot"></span>
      <span class="topbar-title">▸ <?= htmlspecialchars($client['name']) ?></span>
    </div>
    <div class="topbar-right">
      <span class="topbar-tag" id="agents-count">
        <?= count($agents) ?> agentes
      </span>
      <span class="topbar-tag" id="active-task-tag" style="display:none">
        ⚡ tarefa ativa
      </span>
      <a href="/settings.php" class="topbar-tag" style="text-decoration:none">
        ⚙️
      </a>
      <a href="/logout.php" class="topbar-tag" style="color:rgba(239,68,68,.7);border-color:rgba(239,68,68,.2);text-decoration:none">
        sair
      </a>
    </div>
    <!-- Projetos gerados -->
  </div>
  
  <div id="projects-panel">
    <h3>Projetos gerados</h3>
    <div id="projects-list">
      <p class="no-projects">Nenhum projeto ainda.</p>
    </div>
  </div>

  <!-- Painel lateral direito -->
  <div id="agent-panel">
    <h3>Time de agentes</h3>
    <div id="agents-list">
      <?php foreach ($agents as $agent): ?>
      <div class="ap-agent" id="ap-<?= $agent['slug'] ?>" data-slug="<?= $agent['slug'] ?>">
        <div class="ap-avatar" style="background:<?= agentColor($agent['slug']) ?>22">
          <?= agentEmoji($agent['slug']) ?>
        </div>
        <div class="ap-info">
          <div class="ap-name"><?= htmlspecialchars($agent['name']) ?></div>
          <div class="ap-role"><?= htmlspecialchars($agent['role']) ?> · <?= $agent['model'] ?></div>
        </div>
        <div class="ap-dot" style="background:#4ade80"></div>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Chat ao vivo -->
    <div id="live-chat">
      <h3>Conversa ao vivo</h3>
      <div id="chat-messages"></div>
    </div>

    <div id="task-box">
      <p>Enviar tarefa ao time:</p>
      <input type="text" id="project-name" placeholder="Nome do projeto (ex: Academia Fit)" style="
        width:100%;background:rgba(0,0,0,0.3);
        border:1px solid rgba(74,222,128,0.2);
        border-radius:6px;padding:7px 10px;
        color:#a7f3d0;font-size:11px;
        font-family:'DM Mono',monospace;
        outline:none;margin-bottom:8px;
      "/>
      <textarea id="task-input" rows="3" placeholder="Ex: criar landing page para academia..."></textarea>
      <button id="send-task">▸ ENVIAR PARA O TIME</button>
      <div id="task-status"></div>
    </div>
  </div>

  <div id="controls-hint">
    <span class="key">↑</span>
    <span class="key">↓</span>
    <span class="key">←</span>
    <span class="key">→</span>
    <span>mover pelo escritório</span>
  </div>
</div>

<div id="bubbles-layer"></div>

<!-- Dados dos agentes para o JS -->
<script>
  const AGENTS_DATA = <?= json_encode($agents) ?>;
  const CLIENT_DATA = <?= json_encode($client) ?>;
</script>
<script src="/assets/js/game.js?13"></script>
<script src="/assets/js/agency.js?13"></script>
</body>
</html>

<?php
// Helpers de cor e emoji por slug
function agentColor(string $slug): string {
    return match($slug) {
        'tech_leader' => '#8b5cf6',
        'ux_ui'       => '#f472b6',
        'dev'         => '#38bdf8',
        'qa'          => '#34d399',
        'seo'         => '#fbbf24',
        default       => '#4ade80',
    };
}

function agentEmoji(string $slug): string {
    return match($slug) {
        'tech_leader' => '🧠',
        'ux_ui'       => '🎨',
        'dev'         => '💻',
        'qa'          => '🔍',
        'seo'         => '📈',
        default       => '🤖',
    };
}
?>