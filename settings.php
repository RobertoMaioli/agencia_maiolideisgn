<?php
require_once __DIR__ . '/config/config.php';
requireAuth();
$client = auth();

// Busca agentes
$stmt = db()->prepare('SELECT * FROM agents WHERE client_id = ? ORDER BY pos_x ASC');
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

// Mascara chave para exibição — mostra só os últimos 4 chars
function maskKey(?string $val): string {
    if (empty($val)) return '';
    $len = mb_strlen($val);
    if ($len <= 4) return str_repeat('•', $len);
    return str_repeat('•', min($len - 4, 20)) . mb_substr($val, -4);
}
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

/* ── Topbar ─────────────────────────────────────────────── */
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

/* ── Tabs ───────────────────────────────────────────────── */
.tabs{display:flex;gap:4px;margin-bottom:24px;
  border-bottom:1px solid rgba(74,222,128,0.12);padding-bottom:0}
.tab{
  padding:10px 18px;font-size:12px;letter-spacing:.06em;
  border:1px solid transparent;border-bottom:none;
  border-radius:6px 6px 0 0;background:transparent;
  color:rgba(100,180,130,0.5);cursor:pointer;transition:all .15s;
  font-family:'DM Mono',monospace;
}
.tab:hover{color:#a7f3d0;background:rgba(74,222,128,0.05)}
.tab.active{
  border-color:rgba(74,222,128,0.2);
  background:rgba(74,222,128,0.07);
  color:#4ade80;
}
.tab-content{display:none}
.tab-content.active{display:block}

/* ── Cards ──────────────────────────────────────────────── */
.card{
  background:rgba(10,20,12,0.8);
  border:1px solid rgba(74,222,128,0.12);
  border-radius:10px;padding:20px;margin-bottom:16px;
}
.card-title{
  font-size:13px;font-weight:500;color:#4ade80;
  margin-bottom:16px;letter-spacing:.06em;
}

/* ── Formulário ─────────────────────────────────────────── */
.form-row{margin-bottom:14px}
.form-row label{
  display:block;font-size:11px;color:rgba(100,200,140,0.7);
  margin-bottom:5px;letter-spacing:.04em;
}
.form-row input,
.form-row textarea,
.form-row select{
  width:100%;background:rgba(0,0,0,0.35);
  border:1px solid rgba(74,222,128,0.15);
  border-radius:6px;padding:9px 12px;
  color:#d1fae5;font-size:12px;
  font-family:'DM Mono',monospace;
  outline:none;transition:border-color .15s;
}
.form-row input:focus,
.form-row textarea:focus,
.form-row select:focus{border-color:rgba(74,222,128,0.5)}
.form-row textarea{resize:vertical;min-height:90px;line-height:1.5}
.form-row select option{background:#0a0f0a}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}

/* Campo de chave com botão de mostrar/esconder */
.key-wrap{position:relative}
.key-wrap input{padding-right:72px}
.key-wrap .btn-eye{
  position:absolute;right:6px;top:50%;transform:translateY(-50%);
  padding:4px 10px;font-size:10px;
  background:rgba(74,222,128,0.08);
  border:1px solid rgba(74,222,128,0.2);
  border-radius:4px;color:#4ade80;cursor:pointer;
  font-family:'DM Mono',monospace;
  transition:background .15s;
}
.key-wrap .btn-eye:hover{background:rgba(74,222,128,0.16)}

/* Badge de status da chave */
.key-status{
  display:inline-block;font-size:10px;
  padding:2px 8px;border-radius:10px;margin-left:8px;
  vertical-align:middle;
}
.key-status.ok{background:rgba(74,222,128,0.1);color:#4ade80;border:1px solid rgba(74,222,128,0.25)}
.key-status.empty{background:rgba(239,68,68,0.1);color:#fca5a5;border:1px solid rgba(239,68,68,0.2)}

/* ── Botões ─────────────────────────────────────────────── */
.btn{
  padding:8px 18px;font-size:12px;border-radius:6px;
  border:1px solid rgba(74,222,128,0.25);
  background:rgba(74,222,128,0.08);color:#4ade80;
  cursor:pointer;font-family:'DM Mono',monospace;
  transition:background .15s;
}
.btn:hover{background:rgba(74,222,128,0.16)}
.btn-primary{
  background:rgba(74,222,128,0.15);
  border-color:rgba(74,222,128,0.4);
}
.btn-primary:hover{background:rgba(74,222,128,0.25)}
.btn-danger{
  border-color:rgba(239,68,68,0.3);
  background:rgba(239,68,68,0.07);color:#fca5a5;
}
.btn-danger:hover{background:rgba(239,68,68,0.14)}
.btn-sm{padding:5px 12px;font-size:11px}

/* ── Agentes ────────────────────────────────────────────── */
.agent-item{
  display:flex;align-items:center;gap:12px;
  padding:12px 0;border-bottom:1px solid rgba(74,222,128,0.07);
}
.agent-item:last-child{border-bottom:none}
.agent-item.inactive{opacity:.45}
.agent-emoji{font-size:22px;width:32px;text-align:center}
.agent-details{flex:1;min-width:0}
.agent-name{font-size:13px;font-weight:500;color:#a7f3d0}
.agent-meta{font-size:11px;color:rgba(100,180,130,0.5);margin-top:2px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.agent-actions{display:flex;gap:6px;flex-shrink:0}
.badge-active{font-size:10px;padding:2px 8px;border-radius:10px;
  background:rgba(74,222,128,0.1);color:#4ade80;
  border:1px solid rgba(74,222,128,0.25)}
.badge-inactive{font-size:10px;padding:2px 8px;border-radius:10px;
  background:rgba(100,100,100,0.1);color:rgba(100,180,130,0.4);
  border:1px solid rgba(100,100,100,0.2)}

/* ── Formulário de agente ───────────────────────────────── */
#agent-form-wrap{
  display:none;border-top:1px solid rgba(74,222,128,0.1);
  margin-top:16px;padding-top:20px;
}

/* ── Toggle ─────────────────────────────────────────────── */
.toggle{position:relative;display:inline-block;width:34px;height:20px}
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
.toggle input:checked + .toggle-slider::before{transform:translateX(14px)}

/* ── Alerta ─────────────────────────────────────────────── */
.alert{
  padding:10px 14px;border-radius:8px;
  font-size:12px;margin-bottom:16px;display:none;
}
.alert-success{background:rgba(74,222,128,0.1);border:1px solid rgba(74,222,128,0.3);color:#4ade80}
.alert-error  {background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);color:#fca5a5}

/* ── Projetos ───────────────────────────────────────────── */
.project-item{
  display:flex;align-items:center;justify-content:space-between;
  padding:10px 0;border-bottom:1px solid rgba(74,222,128,0.07);
  gap:12px;
}
.project-item:last-child{border-bottom:none}
.project-name{font-size:12px;font-weight:500;color:#a7f3d0}
.project-meta{font-size:10px;color:rgba(100,180,130,0.45);margin-top:3px}
.project-actions{display:flex;gap:6px;flex-shrink:0}
.badge-done{font-size:10px;padding:2px 8px;border-radius:10px;
  background:rgba(74,222,128,0.1);color:#4ade80;border:1px solid rgba(74,222,128,0.2)}
.badge-progress{font-size:10px;padding:2px 8px;border-radius:10px;
  background:rgba(250,190,70,0.1);color:#fbbf24;border:1px solid rgba(250,190,70,0.2)}

/* ── Info box ───────────────────────────────────────────── */
.info-box{
  font-size:11px;color:rgba(100,180,130,0.45);
  margin-top:10px;padding:8px 12px;
  background:rgba(74,222,128,0.03);
  border:1px solid rgba(74,222,128,0.08);
  border-radius:6px;line-height:1.6;
}

@media(max-width:600px){
  .form-grid{grid-template-columns:1fr}
  .container{padding:16px 12px}
  .agent-actions .btn-sm span{display:none}
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
    <button class="tab"        onclick="switchTab('api')">🔑 APIs</button>
    <button class="tab"        onclick="switchTab('projects')">📁 Projetos</button>
  </div>

  <!-- ══════════════════════════════════════════════════════
       TAB — AGENTES
  ══════════════════════════════════════════════════════ -->
  <div id="tab-agents" class="tab-content active">
    <div class="card">
      <div class="card-title">Agentes cadastrados (<?= count($agents) ?>)</div>

      <?php foreach ($agents as $a): ?>
      <div class="agent-item <?= $a['active'] ? '' : 'inactive' ?>" id="agent-item-<?= $a['id'] ?>">
        <span class="agent-emoji"><?= agentEmoji($a['slug']) ?></span>
        <div class="agent-details">
          <div class="agent-name"><?= htmlspecialchars($a['name']) ?></div>
          <div class="agent-meta">
            <?= htmlspecialchars($a['role']) ?> ·
            <?= htmlspecialchars($a['model']) ?> ·
            <?= htmlspecialchars($a['provider']) ?>
          </div>
        </div>
        <span class="<?= $a['active'] ? 'badge-active' : 'badge-inactive' ?>">
          <?= $a['active'] ? 'ativo' : 'inativo' ?>
        </span>
        <div class="agent-actions">
          <button class="btn btn-sm btn-primary" onclick="editAgent(<?= $a['id'] ?>)">
            <span>editar</span>
          </button>
          <button class="btn btn-sm btn-danger" onclick="toggleAgent(<?= $a['id'] ?>, <?= $a['active'] ?>)">
            <?= $a['active'] ? 'desativar' : 'ativar' ?>
          </button>
          <button class="btn btn-sm btn-danger" onclick="deleteAgent(<?= $a['id'] ?>, '<?= htmlspecialchars($a['name'], ENT_QUOTES) ?>')">
            apagar
          </button>
        </div>
      </div>
      <?php endforeach ?>

      <!-- Formulário editar/criar agente -->
      <div id="agent-form-wrap">
        <div class="card-title" id="agent-form-title">Novo agente</div>
        <input type="hidden" id="agent-id" value=""/>

        <div class="form-grid">
          <div class="form-row">
            <label>Nome do agente</label>
            <input type="text" id="agent-name" placeholder="ex: Maya"/>
          </div>
          <div class="form-row">
            <label>Slug (identificador único)</label>
            <input type="text" id="agent-slug" placeholder="ex: ux_ui"/>
          </div>
        </div>

        <div class="form-row">
          <label>Função / papel</label>
          <input type="text" id="agent-role" placeholder="ex: Designer UX/UI"/>
        </div>

        <div class="form-grid">
          <div class="form-row">
            <label>Provedor</label>
            <select id="agent-provider">
              <option value="anthropic">Anthropic</option>
              <option value="openai">OpenAI</option>
              <option value="openrouter">OpenRouter</option>
              <option value="google">Google</option>
            </select>
          </div>
          <div class="form-row">
            <label>Modelo</label>
            <input type="text" id="agent-model" placeholder="ex: claude-haiku-4-5-20251001"/>
          </div>
        </div>

        <div class="form-row">
          <label>System prompt</label>
          <textarea id="agent-prompt" rows="6" placeholder="Descreva o papel e as regras do agente..."></textarea>
        </div>

        <div class="form-grid">
          <div class="form-row">
            <label>Posição X (coluna no mapa)</label>
            <input type="number" id="agent-posx" value="5" min="0" max="30"/>
          </div>
          <div class="form-row">
            <label>Posição Y (linha no mapa)</label>
            <input type="number" id="agent-posy" value="4" min="0" max="20"/>
          </div>
        </div>

        <div style="display:flex;gap:8px;margin-top:4px">
          <button class="btn btn-primary" onclick="saveAgent()">💾 Salvar agente</button>
          <button class="btn" onclick="resetAgentForm()">Cancelar</button>
        </div>
      </div>

      <div style="margin-top:20px">
        <button class="btn btn-primary" onclick="showAgentForm()">+ Novo agente</button>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       TAB — APIs
  ══════════════════════════════════════════════════════ -->
  <div id="tab-api" class="tab-content">
    <div class="card">
      <div class="card-title">Chaves de API</div>

      <div class="form-row">
        <label>
          OpenRouter API Key
          <?php if (!empty($settings['openrouter_key'])): ?>
            <span class="key-status ok">✓ configurada</span>
          <?php else: ?>
            <span class="key-status empty">não configurada</span>
          <?php endif ?>
        </label>
        <div class="key-wrap">
          <input type="password" id="api-openrouter"
            value="<?= htmlspecialchars($settings['openrouter_key'] ?? '') ?>"
            placeholder="sk-or-v1-..."/>
          <button class="btn-eye" onclick="toggleKey('api-openrouter', this)">mostrar</button>
        </div>
      </div>

      <div class="form-row">
        <label>
          Anthropic API Key
          <?php if (!empty($settings['anthropic_key'])): ?>
            <span class="key-status ok">✓ configurada</span>
          <?php else: ?>
            <span class="key-status empty">não configurada</span>
          <?php endif ?>
        </label>
        <div class="key-wrap">
          <input type="password" id="api-anthropic"
            value="<?= htmlspecialchars($settings['anthropic_key'] ?? '') ?>"
            placeholder="sk-ant-..."/>
          <button class="btn-eye" onclick="toggleKey('api-anthropic', this)">mostrar</button>
        </div>
      </div>

      <div class="form-row">
        <label>
          OpenAI API Key
          <?php if (!empty($settings['openai_key'])): ?>
            <span class="key-status ok">✓ configurada</span>
          <?php else: ?>
            <span class="key-status empty">não configurada</span>
          <?php endif ?>
        </label>
        <div class="key-wrap">
          <input type="password" id="api-openai"
            value="<?= htmlspecialchars($settings['openai_key'] ?? '') ?>"
            placeholder="sk-..."/>
          <button class="btn-eye" onclick="toggleKey('api-openai', this)">mostrar</button>
        </div>
      </div>

      <div class="form-row">
        <label>
          GitHub Token
          <span style="font-size:10px;color:rgba(100,180,130,0.4)">(opcional — para push automático)</span>
          <?php if (!empty($settings['github_token'])): ?>
            <span class="key-status ok">✓ configurado</span>
          <?php endif ?>
        </label>
        <div class="key-wrap">
          <input type="password" id="api-github"
            value="<?= htmlspecialchars($settings['github_token'] ?? '') ?>"
            placeholder="ghp_..."/>
          <button class="btn-eye" onclick="toggleKey('api-github', this)">mostrar</button>
        </div>
      </div>

      <div class="form-row">
        <label>GitHub Username</label>
        <input type="text" id="api-github-user"
          value="<?= htmlspecialchars($settings['github_user'] ?? '') ?>"
          placeholder="seu-usuario"/>
      </div>

      <div style="display:flex;gap:10px;align-items:center;margin-top:6px">
        <button class="btn btn-primary" onclick="saveApiKeys()">💾 Salvar chaves</button>
        <span id="save-status" style="font-size:11px;color:rgba(100,180,130,0.5)"></span>
      </div>

      <div class="info-box">
        ⚠️ As chaves são armazenadas no banco de dados e sincronizadas com o .env do servidor.<br>
        Os campos exibem os valores reais — edite somente se quiser trocar a chave.<br>
        Deixar um campo em branco mantém a chave atual sem apagá-la.
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       TAB — PROJETOS
  ══════════════════════════════════════════════════════ -->
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
          <div style="flex:1;min-width:0">
            <div class="project-name">
              <?= htmlspecialchars($p['custom_name'] ?: $p['title']) ?>
            </div>
            <div class="project-meta">
              <?= $p['total_messages'] ?> mensagens ·
              <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?> ·
              stage: <?= htmlspecialchars($p['pipeline_stage'] ?? '—') ?>
            </div>
          </div>

          <span class="<?= $p['status'] === 'done' ? 'badge-done' : 'badge-progress' ?>">
            <?= $p['status'] ?>
          </span>

          <div class="project-actions">
            <?php
              $slug    = $p['slug'] ?? '';
              $appUrl  = rtrim($_ENV['APP_URL'] ?? '', '/');
              $dirPath = __DIR__ . '/projetos/' . $slug;
              if ($slug && is_dir($dirPath)):
            ?>
              <a href="<?= $appUrl ?>/projetos/<?= htmlspecialchars($slug) ?>/index.html"
                 target="_blank" class="btn btn-sm">ver</a>
            <?php endif ?>
            <button class="btn btn-sm" onclick="renameProject(<?= $p['id'] ?>, '<?= htmlspecialchars($p['custom_name'] ?: $p['title'], ENT_QUOTES) ?>')">
              renomear
            </button>
            <button class="btn btn-sm btn-danger" onclick="deleteProject(<?= $p['id'] ?>)">
              apagar
            </button>
          </div>
        </div>
        <?php endforeach ?>
      <?php endif ?>
    </div>
  </div>
</div><!-- /container -->

<script>
// ─── Tabs ──────────────────────────────────────────────────
function switchTab(name) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-' + name).classList.add('active');
  event.currentTarget.classList.add('active');
}

// ─── Alert ─────────────────────────────────────────────────
function showAlert(msg, type = 'success') {
  const el = document.getElementById('alert');
  el.className = 'alert alert-' + type;
  el.textContent = msg;
  el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 3500);
}

// ─── Mostrar/esconder chave ─────────────────────────────────
function toggleKey(inputId, btn) {
  const input = document.getElementById(inputId);
  if (input.type === 'password') {
    input.type = 'text';
    btn.textContent = 'ocultar';
  } else {
    input.type = 'password';
    btn.textContent = 'mostrar';
  }
}

// ─── Salvar chaves de API ───────────────────────────────────
async function saveApiKeys() {
  const status = document.getElementById('save-status');
  status.textContent = 'salvando...';

  const openrouter = document.getElementById('api-openrouter').value.trim();
  const anthropic  = document.getElementById('api-anthropic').value.trim();
  const openai     = document.getElementById('api-openai').value.trim();
  const github     = document.getElementById('api-github').value.trim();
  const githubUser = document.getElementById('api-github-user').value.trim();

  const res  = await fetch('/api/settings.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({
      action:          'save_keys',
      openrouter_key:  openrouter,
      anthropic_key:   anthropic,
      openai_key:      openai,
      github_token:    github,
      github_user:     githubUser,
    }),
  });
  const data = await res.json();

  if (data.success) {
    showAlert('Chaves salvas com sucesso!');
    status.textContent = '✓ salvo';
    setTimeout(() => { status.textContent = ''; }, 3000);
    // Atualiza badges sem recarregar a página
    updateKeyBadges({ openrouter, anthropic, openai, github });
  } else {
    showAlert(data.error || 'Erro ao salvar.', 'error');
    status.textContent = '';
  }
}

function updateKeyBadges({ openrouter, anthropic, openai, github }) {
  const map = [
    ['api-openrouter', openrouter],
    ['api-anthropic',  anthropic],
    ['api-openai',     openai],
    ['api-github',     github],
  ];
  map.forEach(([id, val]) => {
    const wrap  = document.getElementById(id)?.closest('.form-row');
    const badge = wrap?.querySelector('.key-status');
    if (!badge) return;
    if (val) {
      badge.className = 'key-status ok';
      badge.textContent = '✓ configurada';
    } else {
      badge.className = 'key-status empty';
      badge.textContent = 'não configurada';
    }
  });
}

// ─── Agentes ────────────────────────────────────────────────
function showAgentForm() {
  resetAgentForm();
  document.getElementById('agent-form-wrap').style.display = 'block';
  document.getElementById('agent-form-wrap').scrollIntoView({ behavior: 'smooth' });
}

function resetAgentForm() {
  document.getElementById('agent-id').value       = '';
  document.getElementById('agent-name').value     = '';
  document.getElementById('agent-slug').value     = '';
  document.getElementById('agent-role').value     = '';
  document.getElementById('agent-model').value    = '';
  document.getElementById('agent-prompt').value   = '';
  document.getElementById('agent-posx').value     = '5';
  document.getElementById('agent-posy').value     = '4';
  document.getElementById('agent-provider').value = 'anthropic';
  document.getElementById('agent-form-title').textContent = 'Novo agente';
  document.getElementById('agent-form-wrap').style.display = 'none';
}

async function editAgent(id) {
  const res  = await fetch('/api/agents.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action: 'get', id }),
  });
  const data = await res.json();
  if (!data.agent) { showAlert('Agente não encontrado.', 'error'); return; }

  const a = data.agent;
  document.getElementById('agent-id').value       = a.id;
  document.getElementById('agent-name').value     = a.name;
  document.getElementById('agent-slug').value     = a.slug;
  document.getElementById('agent-role').value     = a.role;
  document.getElementById('agent-model').value    = a.model;
  document.getElementById('agent-prompt').value   = a.system_prompt;
  document.getElementById('agent-posx').value     = a.pos_x;
  document.getElementById('agent-posy').value     = a.pos_y;
  document.getElementById('agent-provider').value = a.provider;
  document.getElementById('agent-form-title').textContent = 'Editar agente — ' + a.name;
  document.getElementById('agent-form-wrap').style.display = 'block';
  document.getElementById('agent-form-wrap').scrollIntoView({ behavior: 'smooth' });
}

async function saveAgent() {
  const id     = document.getElementById('agent-id').value;
  const action = id ? 'update' : 'create';

  const payload = {
    action,
    id:            id ? parseInt(id) : undefined,
    name:          document.getElementById('agent-name').value.trim(),
    slug:          document.getElementById('agent-slug').value.trim(),
    role:          document.getElementById('agent-role').value.trim(),
    model:         document.getElementById('agent-model').value.trim(),
    provider:      document.getElementById('agent-provider').value,
    system_prompt: document.getElementById('agent-prompt').value.trim(),
    pos_x:         parseInt(document.getElementById('agent-posx').value),
    pos_y:         parseInt(document.getElementById('agent-posy').value),
  };

  const res  = await fetch('/api/agents.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(payload),
  });
  const data = await res.json();

  if (data.success) {
    showAlert(action === 'create' ? 'Agente criado!' : 'Agente atualizado!');
    setTimeout(() => location.reload(), 1200);
  } else {
    showAlert(data.error || 'Erro ao salvar.', 'error');
  }
}

async function toggleAgent(id, active) {
  const res  = await fetch('/api/agents.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action: 'toggle', id, active: active ? 0 : 1 }),
  });
  const data = await res.json();
  if (data.success) {
    showAlert(active ? 'Agente desativado.' : 'Agente ativado!');
    setTimeout(() => location.reload(), 1000);
  } else {
    showAlert(data.error || 'Erro.', 'error');
  }
}

async function deleteAgent(id, name) {
  if (!confirm(`Apagar o agente "${name}"? Esta ação não pode ser desfeita.`)) return;
  const res  = await fetch('/api/agents.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action: 'delete', id }),
  });
  const data = await res.json();
  if (data.success) {
    showAlert('Agente apagado!');
    setTimeout(() => location.reload(), 1000);
  } else {
    showAlert(data.error || 'Erro ao apagar.', 'error');
  }
}

// ─── Projetos ────────────────────────────────────────────────
async function renameProject(id, currentName) {
  const name = prompt('Novo nome para o projeto:', currentName);
  if (!name || name.trim() === currentName) return;

  const res  = await fetch('/api/projects.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action: 'rename', id, name: name.trim() }),
  });
  const data = await res.json();
  if (data.success) {
    showAlert('Projeto renomeado.');
    setTimeout(() => location.reload(), 1000);
  } else {
    showAlert('Erro ao renomear.', 'error');
  }
}

async function deleteProject(id) {
  if (!confirm('Apagar projeto e todas as mensagens? Esta ação não pode ser desfeita.')) return;
  const res  = await fetch('/api/projects.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify({ action: 'delete', id }),
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
    'tech_leader' => '🧠',
    'ux_ui'       => '🎨',
    'dev'         => '💻',
    'qa'          => '🔍',
    'seo'         => '📈',
    default       => '🤖',
  };
}
?>
</body>
</html>