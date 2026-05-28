<?php
require_once __DIR__ . '/config/config.php';
requireAuth();
$client = auth();

// Busca agentes
$stmt = db()->prepare('SELECT * FROM agents WHERE client_id = ? ORDER BY id');
$stmt->execute([$client['id']]);
$agents = $stmt->fetchAll();

// Busca configurações de API
$stmt = db()->prepare('SELECT * FROM settings WHERE client_id = ? LIMIT 1');
$stmt->execute([$client['id']]);
$settings = $stmt->fetch() ?: [];

// Busca projetos
$stmt = db()->prepare('
    SELECT p.*, COUNT(m.id) as total_messages
    FROM projects p
    LEFT JOIN messages m ON m.project_id = p.id
    WHERE p.client_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 50
');
$stmt->execute([$client['id']]);
$projects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Configurações — Agência Virtual</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%;background:#0a0f0a;font-family:'DM Mono',monospace;color:#d1fae5}
body::before{content:'';position:fixed;inset:0;
  background-image:linear-gradient(rgba(74,222,128,0.03) 1px,transparent 1px),
  linear-gradient(90deg,rgba(74,222,128,0.03) 1px,transparent 1px);
  background-size:32px 32px;pointer-events:none;}

.topbar{
  display:flex;align-items:center;justify-content:space-between;
  padding:12px 24px;
  background:rgba(6,12,8,0.95);
  border-bottom:1px solid rgba(74,222,128,0.15);
  position:sticky;top:0;z-index:10;
}
.topbar-title{color:#4ade80;font-size:14px;font-weight:bold;letter-spacing:.08em}
.back-btn{
  display:flex;align-items:center;gap:6px;
  padding:7px 14px;font-size:12px;
  border:1px solid rgba(74,222,128,0.25);border-radius:20px;
  background:rgba(74,222,128,0.08);color:#4ade80;
  text-decoration:none;transition:background .15s;
}
.back-btn:hover{background:rgba(74,222,128,0.16)}

.container{max-width:900px;margin:0 auto;padding:28px 20px}

/* Tabs */
.tabs{display:flex;gap:4px;margin-bottom:24px;
  border-bottom:1px solid rgba(74,222,128,0.12);padding-bottom:0}
.tab{
  padding:10px 18px;font-size:12px;letter-spacing:.06em;
  border:1px solid transparent;border-bottom:none;
  border-radius:8px 8px 0 0;cursor:pointer;
  color:rgba(100,180,130,0.6);background:transparent;
  transition:all .15s;
}
.tab:hover{color:#4ade80;background:rgba(74,222,128,0.05)}
.tab.active{
  color:#4ade80;background:rgba(74,222,128,0.08);
  border-color:rgba(74,222,128,0.2);
  border-bottom-color:transparent;
  margin-bottom:-1px;
}
.tab-content{display:none}
.tab-content.active{display:block}

/* Cards */
.card{
  background:rgba(8,16,10,0.9);
  border:1px solid rgba(74,222,128,0.12);
  border-radius:12px;padding:20px;margin-bottom:16px;
}
.card-title{
  font-size:11px;color:rgba(74,222,128,0.5);
  letter-spacing:.12em;text-transform:uppercase;
  margin-bottom:16px;padding-bottom:10px;
  border-bottom:1px solid rgba(74,222,128,0.08);
}

/* Form */
.form-row{margin-bottom:14px}
.form-row label{
  display:block;font-size:11px;color:rgba(100,180,130,0.6);
  letter-spacing:.06em;margin-bottom:6px;
}
.form-row input,
.form-row select,
.form-row textarea{
  width:100%;padding:9px 12px;
  background:rgba(0,0,0,0.3);
  border:1px solid rgba(74,222,128,0.2);
  border-radius:8px;color:#a7f3d0;
  font-family:'DM Mono',monospace;font-size:12px;
  outline:none;transition:border-color .2s;
}
.form-row input:focus,
.form-row select:focus,
.form-row textarea:focus{border-color:rgba(74,222,128,.55)}
.form-row textarea{resize:vertical;min-height:80px}
.form-row select option{background:#0a140c}

.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}

/* Buttons */
.btn{
  padding:9px 18px;font-size:12px;font-family:'DM Mono',monospace;
  border-radius:8px;cursor:pointer;letter-spacing:.05em;
  transition:all .15s;border:1px solid;
}
.btn-primary{
  background:rgba(74,222,128,0.15);border-color:rgba(74,222,128,0.35);
  color:#4ade80;
}
.btn-primary:hover{background:rgba(74,222,128,0.25)}
.btn-danger{
  background:rgba(239,68,68,0.1);border-color:rgba(239,68,68,0.3);
  color:#fca5a5;
}
.btn-danger:hover{background:rgba(239,68,68,0.2)}
.btn-sm{padding:5px 12px;font-size:11px}

/* Agent list */
.agent-item{
  display:flex;align-items:center;gap:12px;
  padding:12px;border-radius:8px;
  background:rgba(255,255,255,0.02);
  border:1px solid rgba(255,255,255,0.05);
  margin-bottom:8px;
}
.agent-item.inactive{opacity:.5}
.agent-emoji{font-size:20px;flex-shrink:0}
.agent-details{flex:1;min-width:0}
.agent-name{font-size:13px;color:#d1fae5;font-weight:bold}
.agent-meta{font-size:10px;color:rgba(100,180,130,0.5);margin-top:2px}
.agent-actions{display:flex;gap:6px;flex-shrink:0}
.badge-active{
  font-size:10px;padding:2px 8px;border-radius:20px;
  background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.25);
  color:#4ade80;
}
.badge-inactive{
  font-size:10px;padding:2px 8px;border-radius:20px;
  background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.2);
  color:#fca5a5;
}

/* Projects list */
.project-item{
  display:flex;align-items:center;gap:12px;
  padding:12px;border-radius:8px;
  background:rgba(255,255,255,0.02);
  border:1px solid rgba(255,255,255,0.05);
  margin-bottom:8px;
}
.project-name{font-size:13px;color:#d1fae5;font-weight:bold}
.project-meta{font-size:10px;color:rgba(100,180,130,0.5);margin-top:2px}
.project-actions{display:flex;gap:6px;margin-left:auto;flex-shrink:0}

/* Toggle */
.toggle{
  position:relative;width:36px;height:20px;flex-shrink:0;
}
.toggle input{opacity:0;width:0;height:0}
.toggle-slider{
  position:absolute;inset:0;border-radius:20px;
  background:rgba(255,255,255,0.1);cursor:pointer;
  transition:background .2s;
}
.toggle input:checked + .toggle-slider{background:rgba(74,222,128,0.5)}
.toggle-slider::before{
  content:'';position:absolute;
  width:14px;height:14px;left:3px;top:3px;
  border-radius:50%;background:#fff;
  transition:transform .2s;
}
.toggle input:checked + .toggle-slider::before{transform:translateX(16px)}

/* Alert */
.alert{
  padding:10px 14px;border-radius:8px;
  font-size:12px;margin-bottom:16px;display:none;
}
.alert-success{background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.3);color:#4ade80}
.alert-error{background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5}

@media(max-width:600px){
  .form-grid{grid-template-columns:1fr}
  .container{padding:16px 12px}
}
</style>
</head>
<body>

<div class="topbar">
  <span class="topbar-title">⚙️ CONFIGURAÇÕES</span>
  <a href="/index.php" class="back-btn">← Voltar ao escritório</a>
</div>

<div class="container">
  <div id="alert" class="alert"></div>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab active" onclick="switchTab('agents')">🤖 Agentes</button>
    <button class="tab" onclick="switchTab('api')">🔑 APIs</button>
    <button class="tab" onclick="switchTab('projects')">📁 Projetos</button>
  </div>

  <!-- Tab Agentes -->
  <div id="tab-agents" class="tab-content active">

    <!-- Lista de agentes -->
    <div class="card">
      <div class="card-title">Agentes cadastrados</div>
      <?php foreach ($agents as $a): ?>
      <div class="agent-item <?= $a['active'] ? '' : 'inactive' ?>" id="agent-item-<?= $a['id'] ?>">
        <span class="agent-emoji"><?= agentEmoji($a['slug']) ?></span>
        <div class="agent-details">
          <div class="agent-name"><?= htmlspecialchars($a['name']) ?></div>
          <div class="agent-meta"><?= htmlspecialchars($a['role']) ?> · <?= htmlspecialchars($a['model']) ?></div>
        </div>
        <span class="<?= $a['active'] ? 'badge-active' : 'badge-inactive' ?>">
          <?= $a['active'] ? 'ativo' : 'inativo' ?>
        </span>
        <div class="agent-actions">
          <button class="btn btn-sm btn-primary" onclick="editAgent(<?= $a['id'] ?>)">editar</button>
          <button class="btn btn-sm btn-danger" onclick="toggleAgent(<?= $a['id'] ?>, <?= $a['active'] ?>)">
            <?= $a['active'] ? 'desativar' : 'ativar' ?>
          </button>
          <button class="btn btn-sm btn-danger" onclick="deleteAgent(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name']) ?>')">
            apagar
          </button>
        </div>
      </div>
      <?php endforeach ?>
    </div>

    <!-- Criar / editar agente -->
    <div class="card">
      <div class="card-title" id="agent-form-title">Criar novo agente</div>
      <input type="hidden" id="agent-id" value=""/>
      <div class="form-grid">
        <div class="form-row">
          <label>Nome</label>
          <input type="text" id="agent-name" placeholder="Ex: Luna"/>
        </div>
        <div class="form-row">
          <label>Cargo / Papel</label>
          <input type="text" id="agent-role" placeholder="Ex: UX/UI Designer"/>
        </div>
        <div class="form-row">
          <label>Slug (identificador único)</label>
          <input type="text" id="agent-slug" placeholder="Ex: ux_ui"/>
        </div>
        <div class="form-row">
          <label>Modelo de IA</label>
          <select id="agent-model">
            <option value="openai/gpt-oss-20b:free">GPT-OSS 20B (free)</option>
            <option value="meta-llama/llama-3.3-70b-instruct:free">Llama 3.3 70B (free)</option>
            <option value="deepseek/deepseek-v4-flash:free">DeepSeek V4 Flash (free)</option>
            <option value="google/gemma-4-31b-it:free">Gemma 4 31B (free)</option>
            <option value="openrouter/free">OpenRouter Free (automático)</option>
          </select>
        </div>
        <div class="form-row">
          <label>Posição X no mapa</label>
          <input type="number" id="agent-pos-x" value="5" min="1" max="28"/>
        </div>
        <div class="form-row">
          <label>Posição Y no mapa</label>
          <input type="number" id="agent-pos-y" value="4" min="1" max="20"/>
        </div>
      </div>
      <div class="form-row">
        <label>System Prompt (instruções do agente)</label>
        <textarea id="agent-prompt" rows="4" placeholder="Ex: Você é Luna, designer UX/UI. Defina wireframe, paleta de cores e componentes..."></textarea>
      </div>
      <div style="display:flex;gap:8px;margin-top:4px">
        <button class="btn btn-primary" onclick="saveAgent()">💾 Salvar agente</button>
        <button class="btn" style="border-color:rgba(255,255,255,.1);color:rgba(150,200,170,.5)" onclick="resetAgentForm()">Cancelar</button>
      </div>
    </div>
  </div>

  <!-- Tab APIs -->
  <div id="tab-api" class="tab-content">
    <div class="card">
      <div class="card-title">Chaves de API</div>
      <div class="form-row">
        <label>OpenRouter API Key</label>
        <input type="password" id="api-openrouter"
          value="<?= htmlspecialchars($settings['openrouter_key'] ?? '') ?>"
          placeholder="sk-or-v1-..."/>
      </div>
      <div class="form-row">
        <label>Anthropic API Key (opcional)</label>
        <input type="password" id="api-anthropic"
          value="<?= htmlspecialchars($settings['anthropic_key'] ?? '') ?>"
          placeholder="sk-ant-..."/>
      </div>
      <div class="form-row">
        <label>GitHub Token (opcional)</label>
        <input type="password" id="api-github"
          value="<?= htmlspecialchars($settings['github_token'] ?? '') ?>"
          placeholder="ghp_..."/>
      </div>
      <div class="form-row">
        <label>GitHub Username (opcional)</label>
        <input type="text" id="api-github-user"
          value="<?= htmlspecialchars($settings['github_user'] ?? '') ?>"
          placeholder="seu-usuario"/>
      </div>
      <button class="btn btn-primary" onclick="saveApiKeys()">💾 Salvar chaves</button>
      <p style="font-size:10px;color:rgba(100,180,130,0.4);margin-top:10px">
        ⚠️ As chaves são salvas de forma segura no banco de dados.
      </p>
    </div>
  </div>

  <!-- Tab Projetos -->
  <div id="tab-projects" class="tab-content">
    <div class="card">
      <div class="card-title">Projetos gerados (<?= count($projects) ?>)</div>
      <?php if (empty($projects)): ?>
        <p style="font-size:12px;color:rgba(100,180,130,0.4);text-align:center;padding:16px">
          Nenhum projeto ainda.
        </p>
      <?php else: ?>
        <?php foreach ($projects as $p): ?>
        <div class="project-item">
          <div>
            <div class="project-name">
              <?= htmlspecialchars($p['custom_name'] ?: $p['title']) ?>
            </div>
            <div class="project-meta">
              <?= $p['total_messages'] ?> mensagens ·
              <?= $p['status'] ?> ·
              <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
            </div>
          </div>
          <div class="project-actions">
            <?php
            $projDir = __DIR__ . '/projetos/';
            $projSlug = $p['slug'] ?: '';
            if ($projSlug && is_dir($projDir . $projSlug)):
            ?>
            <a href="/projetos/<?= htmlspecialchars($projSlug) ?>/index.html"
               target="_blank" class="btn btn-sm btn-primary">ver</a>
            <?php endif ?>
            <button class="btn btn-sm btn-danger"
              onclick="deleteProject(<?= $p['id'] ?>)">apagar</button>
          </div>
        </div>
        <?php endforeach ?>
      <?php endif ?>
    </div>
  </div>
</div>

<script>
// ─── Tabs ─────────────────────────────────────────────────────
function switchTab(name) {
  document.querySelectorAll('.tab').forEach((t,i) => {
    const names = ['agents','api','projects'];
    t.classList.toggle('active', names[i] === name);
  });
  document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
}

// ─── Alert ────────────────────────────────────────────────────
function showAlert(msg, type='success') {
  const el = document.getElementById('alert');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => el.style.display = 'none', 3500);
}

// ─── Agentes ──────────────────────────────────────────────────
const agentsData = <?= json_encode($agents) ?>;

function editAgent(id) {
  const a = agentsData.find(x => x.id == id);
  if (!a) return;
  document.getElementById('agent-id').value      = a.id;
  document.getElementById('agent-name').value    = a.name;
  document.getElementById('agent-role').value    = a.role;
  document.getElementById('agent-slug').value    = a.slug;
  document.getElementById('agent-model').value   = a.model;
  document.getElementById('agent-pos-x').value   = a.pos_x;
  document.getElementById('agent-pos-y').value   = a.pos_y;
  document.getElementById('agent-prompt').value  = a.system_prompt;
  document.getElementById('agent-form-title').textContent = 'Editar agente — ' + a.name;
  document.getElementById('agent-form-title').scrollIntoView({behavior:'smooth'});
}

function resetAgentForm() {
  document.getElementById('agent-id').value     = '';
  document.getElementById('agent-name').value   = '';
  document.getElementById('agent-role').value   = '';
  document.getElementById('agent-slug').value   = '';
  document.getElementById('agent-prompt').value = '';
  document.getElementById('agent-pos-x').value  = '5';
  document.getElementById('agent-pos-y').value  = '4';
  document.getElementById('agent-form-title').textContent = 'Criar novo agente';
}

async function saveAgent() {
  const id     = document.getElementById('agent-id').value;
  const name   = document.getElementById('agent-name').value.trim();
  const role   = document.getElementById('agent-role').value.trim();
  const slug   = document.getElementById('agent-slug').value.trim();
  const model  = document.getElementById('agent-model').value;
  const posX   = document.getElementById('agent-pos-x').value;
  const posY   = document.getElementById('agent-pos-y').value;
  const prompt = document.getElementById('agent-prompt').value.trim();

  if (!name || !role || !slug || !prompt) {
    showAlert('Preencha todos os campos obrigatórios.', 'error'); return;
  }

  const res  = await fetch('/api/agents.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action: id ? 'update' : 'create', id, name, role, slug, model, pos_x:posX, pos_y:posY, system_prompt:prompt}),
  });
  const data = await res.json();
  if (data.success) {
    showAlert(id ? 'Agente atualizado!' : 'Agente criado!');
    setTimeout(() => location.reload(), 1200);
  } else {
    showAlert(data.error || 'Erro ao salvar.', 'error');
  }
}

async function toggleAgent(id, active) {
  const res  = await fetch('/api/agents.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'toggle', id, active: active ? 0 : 1}),
  });
  const data = await res.json();
  if (data.success) {
    showAlert(active ? 'Agente desativado.' : 'Agente ativado!');
    setTimeout(() => location.reload(), 1000);
  } else {
    showAlert('Erro ao alterar agente.', 'error');
  }
}

async function deleteAgent(id, name) {
  if (!confirm(`Apagar o agente "${name}"? Esta ação não pode ser desfeita.`)) return;

  const res  = await fetch('/api/agents.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'delete', id}),
  });
  const data = await res.json();
  if (data.success) {
    showAlert('Agente apagado!');
    setTimeout(() => location.reload(), 1000);
  } else {
    showAlert(data.error || 'Erro ao apagar.', 'error');
  }
}

// ─── API Keys ─────────────────────────────────────────────────
async function saveApiKeys() {
  const res = await fetch('/api/settings.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({
      action:          'save_keys',
      openrouter_key:  document.getElementById('api-openrouter').value.trim(),
      anthropic_key:   document.getElementById('api-anthropic').value.trim(),
      github_token:    document.getElementById('api-github').value.trim(),
      github_user:     document.getElementById('api-github-user').value.trim(),
    }),
  });
  const data = await res.json();
  if (data.success) showAlert('Chaves salvas com sucesso!');
  else showAlert('Erro ao salvar chaves.', 'error');
}

// ─── Projetos ─────────────────────────────────────────────────
async function deleteProject(id) {
  if (!confirm('Apagar projeto e todas as mensagens?')) return;
  const res  = await fetch('/api/projects.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({action:'delete', id}),
  });
  const data = await res.json();
  if (data.success) {
    showAlert('Projeto apagado.');
    setTimeout(() => location.reload(), 1000);
  } else {
    showAlert('Erro ao apagar.', 'error');
  }
}
</script>

<?php
function agentEmoji(string $slug): string {
  return match($slug) {
    'tech_leader' => '🧠', 'ux_ui' => '🎨',
    'dev'         => '💻', 'qa'    => '🔍',
    'seo'         => '📈', default => '🤖',
  };
}
?>
</body>
</html>