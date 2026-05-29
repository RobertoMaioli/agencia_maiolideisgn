// ─── Chat ao vivo ─────────────────────────────────────────────
function addChatMessage(name, text, color, isUser = false) {
  const container = document.getElementById('chat-messages');
  const now  = new Date();
  const time = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');

  const msg = document.createElement('div');
  msg.className = 'chat-msg' + (isUser ? ' user-msg' : '');
  msg.innerHTML = `
    <div class="chat-msg-header">
      <span class="chat-msg-name" style="color:${color}">${name}</span>
      <span class="chat-msg-time">${time}</span>
    </div>
    <div class="chat-msg-text">${text}</div>
  `;
  container.appendChild(msg);
  container.scrollTop = container.scrollHeight;
}

function showTypingIndicator(name, color) {
  const container = document.getElementById('chat-messages');
  const existing  = container.querySelector('.chat-typing');
  if (existing) existing.remove();

  const el = document.createElement('div');
  el.className = 'chat-typing';
  el.innerHTML = `
    <span style="color:${color};font-size:10px;font-weight:bold">${name}</span>
    <div class="chat-typing-dots">
      <span></span><span></span><span></span>
    </div>
  `;
  container.appendChild(el);
  container.scrollTop = container.scrollHeight;
}

function removeTypingIndicator() {
  const el = document.querySelector('.chat-typing');
  if (el) el.remove();
}

function setAgentThinking(slug, thinking) {
  if (!window._thinkingAgents) window._thinkingAgents = new Set();
  thinking
    ? window._thinkingAgents.add(slug)
    : window._thinkingAgents.delete(slug);
}

// ─── Chamada individual de agente ─────────────────────────────
// projectId é passado em todas as chamadas a partir da segunda,
// para o backend persistir o estado do pipeline no banco
// (em vez de depender da $_SESSION que se perdia entre requests)
async function callAgent(agent, task, projectName, prevReply, projectId = 0) {
  if (agent.slug === 'dev') {
    await new Promise(r => setTimeout(r, 3000));
  }

  setAgentThinking(agent.slug, true);
  spawnBubble(agent, '...');
  showTypingIndicator(agent.name, getAgentColor(agent.slug));
  await new Promise(r => setTimeout(r, 900 + Math.random() * 600));

  const payload = {
    task,
    project_name: projectName,
    agent_id:     agent.id,
    agent_slug:   agent.slug,
    prev_reply:   prevReply,
    project_id:   projectId,   // ← novo: garante continuidade do pipeline
  };

  try {
    const res  = await fetch('/api/task.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    });

    const data = await res.json();

    activeBubbles
      .filter(b => b.agent.id === agent.id)
      .forEach(b => { b.life = 0; });
    removeTypingIndicator();

    if (!data.reply || data.reply.includes('Não consegui processar')) {
      throw new Error('falhou');
    }

    spawnBubble(agent, data.reply);
    addChatMessage(agent.name, data.reply, getAgentColor(agent.slug), false);
    setAgentThinking(agent.slug, false);
    return data;

  } catch (e) {
    activeBubbles
      .filter(b => b.agent.id === agent.id)
      .forEach(b => { b.life = 0; });
    removeTypingIndicator();
    spawnBubble(agent, '⚠️ Aguardando modelo...');
    addChatMessage(agent.name, '⚠️ Tentando novamente em 5s...', getAgentColor(agent.slug), false);

    await new Promise(r => setTimeout(r, 8000));

    const res2  = await fetch('/api/task.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),   // reutiliza o mesmo payload (com project_id)
    });

    const data2 = await res2.json();

    activeBubbles
      .filter(b => b.agent.id === agent.id)
      .forEach(b => { b.life = 0; });

    if (data2.reply && !data2.reply.includes('Não consegui processar')) {
      spawnBubble(agent, data2.reply);
      addChatMessage(agent.name, data2.reply, getAgentColor(agent.slug), false);
      setAgentThinking(agent.slug, false);
      return data2;
    }

    spawnBubble(agent, '❌ Modelo indisponível.');
    addChatMessage(agent.name, '❌ Modelo indisponível. Tente novamente.', getAgentColor(agent.slug), false);
    setAgentThinking(agent.slug, false);
    return { reply: null, file_url: null, project_id: projectId, failed: true };
  }
}

// ─── Envio de tarefa ──────────────────────────────────────────
let taskRunning = false;

async function sendTask() {
  if (taskRunning) return;

  const input       = document.getElementById('task-input');
  const status      = document.getElementById('task-status');
  const btn         = document.getElementById('send-task');
  const task        = input.value.trim();
  const projectName = document.getElementById('project-name').value.trim();

  if (!task) return;

  taskRunning  = true;
  btn.disabled = true;
  input.value  = '';
  document.getElementById('project-name').value = '';
  document.getElementById('active-task-tag').style.display = 'inline';

  addChatMessage('Você', task, '#4ade80', true);

  const agentList = [...AGENTS_DATA];
  const devAgent  = agentList.find(a => a.slug === 'dev');
  const qaAgent   = agentList.find(a => a.slug === 'qa');

  let prevReply = '';
  let projectId = 0;   // ← será preenchido na resposta do primeiro agente

  // ─── Pipeline principal ───────────────────────────────────
  for (const agent of agentList) {
    status.textContent = `${agent.name} está pensando...`;

    try {
      const data = await callAgent(agent, task, projectName, prevReply, projectId);

      // Captura o project_id criado pelo backend no primeiro agente
      // e propaga para todos os agentes seguintes
      if (data.project_id && !projectId) {
        projectId = data.project_id;
      }

      // Agente falhou — para o pipeline
      if (data.failed) {
        addChatMessage('Sistema', `⚠️ ${agent.name} não respondeu. Tente novamente.`, '#fbbf24', false);
        break;
      }

      const replyPreview = (data.reply || '').length > 300
        ? data.reply.substring(0, 300) + '...'
        : (data.reply || '');

      // QA reprovou — Dev corrige e QA revisa de novo
      if (agent.slug === 'qa' && !data.file_url) {
        const lower    = (data.reply || '').toLowerCase();
        const reproved = lower.includes('reprovado')
          || lower.includes('ajuste')
          || lower.includes('corrigir')
          || lower.includes('incorreto')
          || lower.includes('problema');

        if (reproved && devAgent && qaAgent) {
          addChatMessage('Sistema', '🔄 QA reprovou. Dev vai aplicar correções...', '#fbbf24', false);

          status.textContent = 'Dev aplicando correções...';
          const devData = await callAgent(
            devAgent, task, projectName,
            `QA reprovou e pediu: "${data.reply}". Corrija o HTML e retorne o código completo corrigido.`,
            projectId
          );

          if (devData.failed) {
            addChatMessage('Sistema', '⚠️ Dev não conseguiu corrigir. Tente novamente.', '#fbbf24', false);
            break;
          }

          await new Promise(r => setTimeout(r, 5000));

          status.textContent = 'QA revisando correções...';
          const qaData2 = await callAgent(
            qaAgent, task, projectName,
            'Dev aplicou as correções. Revise novamente e responda com ✅ APROVADO ou ❌ REPROVADO.',
            projectId
          );

          if (qaData2.file_url) {
            addProjectToPanel(projectName || task, qaData2.file_url);
          }

          break;
        }
      }

      prevReply = data.file_url ? 'Arquivo gerado com sucesso.' : replyPreview;

      if (data.file_url) {
        addProjectToPanel(projectName || task, data.file_url);
      }

    } catch (e) {
      spawnBubble(agent, 'Erro ao conectar.');
      addChatMessage(agent.name, 'Erro ao conectar.', getAgentColor(agent.slug), false);
    }

    await new Promise(r => setTimeout(r, 6000));
  }

  status.textContent = '✓ Tarefa concluída!';
  setTimeout(() => { status.textContent = ''; }, 4000);

  taskRunning  = false;
  btn.disabled = false;
  document.getElementById('active-task-tag').style.display = 'none';
}

// ─── Painel de projetos ───────────────────────────────────────
function addProjectToPanel(name, url) {
  const list = document.getElementById('projects-list');
  const empty = list.querySelector('.no-projects');
  if (empty) empty.remove();

  const item = document.createElement('div');
  item.className = 'project-panel-item';
  item.innerHTML = `
    <span class="project-panel-name">${name || 'Projeto'}</span>
    <a href="${url}" target="_blank" class="project-panel-link">ver →</a>
  `;
  list.prepend(item);
}

// ─── Eventos ──────────────────────────────────────────────────
document.getElementById('send-task').addEventListener('click', sendTask);
document.getElementById('task-input').addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendTask();
  }
});