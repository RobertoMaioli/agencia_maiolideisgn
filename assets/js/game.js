// ─── Config ───────────────────────────────────────────────────
const TILE = 16;
const SCALE = 2.5;
const TS = TILE * SCALE;
const MAP_W = 30;
const MAP_H = 22;

const canvas = document.getElementById('game-canvas');
const ctx = canvas.getContext('2d');
ctx.imageSmoothingEnabled = false;

canvas.width  = MAP_W * TS;
canvas.height = MAP_H * TS;
canvas.style.width  = (MAP_W * TS) + 'px';
canvas.style.height = (MAP_H * TS) + 'px';

// ─── Cores pixel art ──────────────────────────────────────────
const C = {
  floor:        '#1e2d1e',
  floor2:       '#1a2818',
  wall:         '#0d1a0d',
  wall_light:   '#1a2a1a',
  carpet_blue:  '#1a2340',
  carpet_blue2: '#1e2848',
  carpet_teal:  '#0f2520',
  carpet_teal2: '#122820',
  rug_purple:   '#2a1a40',
  rug_border:   '#3d2a5a',
  carpet_meet:  '#1e2a1e',
  carpet_meet2: '#1a261a',
  desk_dark:    '#2d1f12',
  desk_mid:     '#3d2a18',
  desk_light:   '#4a3220',
  monitor:      '#0a0f1a',
  monitor_glow: '#1a4a6a',
  chair:        '#1a3020',
  chair_dark:   '#142518',
  plant:        '#2d6b2d',
  plant_dark:   '#1e4a1e',
  plant_pot:    '#5a3a1a',
  window_bg:    '#1a3050',
  window_light: '#4a8acc',
  bookshelf:    '#2a1a10',
  book1:        '#8b1a1a',
  book2:        '#1a4a8b',
  book3:        '#2a6b2a',
  wb_frame:     '#2a3d2a',
  lamp_on:      '#ffdd88',
};

// ─── Mapa de tiles ────────────────────────────────────────────
// 0=floor 1=wall 2=carpet_blue 3=carpet_teal 4=rug_purple 5=carpet_meet
const MAP = [
  [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
  [1,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,0,1],
  [1,0,2,2,2,2,2,2,2,0,1,0,3,3,3,3,3,3,3,0,1,0,4,4,4,4,4,4,0,1],
  [1,0,2,2,2,2,2,2,2,0,1,0,3,3,3,3,3,3,3,0,1,0,4,4,4,4,4,4,0,1],
  [1,0,2,2,2,2,2,2,2,0,1,0,3,3,3,3,3,3,3,0,1,0,4,4,4,4,4,4,0,1],
  [1,0,2,2,2,2,2,2,2,0,1,0,3,3,3,3,3,3,3,0,1,0,4,4,4,4,4,4,0,1],
  [1,0,2,2,2,2,2,2,2,0,1,0,3,3,3,3,3,3,3,0,1,0,4,4,4,4,4,4,0,1],
  [1,0,2,2,2,2,2,2,2,0,1,0,3,3,3,3,3,3,3,0,1,0,4,4,4,4,4,4,0,1],
  [1,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,0,0,1,0,0,0,0,0,0,0,0,1],
  [1,1,1,1,1,0,1,1,1,1,1,1,1,0,1,1,1,1,0,1,1,1,1,1,1,0,1,1,1,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,5,5,5,5,5,5,5,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,5,5,5,5,5,5,5,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,5,5,5,5,5,5,5,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,5,5,5,5,5,5,5,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,5,5,5,5,5,5,5,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,5,5,5,5,5,5,5,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,5,5,5,5,5,5,5,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1],
  [1,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,1],
  [1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1,1],
];

const SOLID = new Set([1]);
function isSolid(tx, ty) {
  if (tx < 0 || ty < 0 || tx >= MAP_W || ty >= MAP_H) return true;
  return SOLID.has(MAP[ty]?.[tx]);
}

// ─── Tiles ────────────────────────────────────────────────────
function drawTile(tx, ty) {
  const x = tx * TS, y = ty * TS;
  const t = MAP[ty]?.[tx] ?? 0;
  const checker = (tx + ty) % 2 === 0;

  if (t === 1) {
    ctx.fillStyle = C.wall;       ctx.fillRect(x, y, TS, TS);
    ctx.fillStyle = C.wall_light; ctx.fillRect(x, y, TS, TS * .2);
    return;
  }
  if (t === 2) ctx.fillStyle = checker ? C.carpet_blue  : C.carpet_blue2;
  else if (t === 3) ctx.fillStyle = checker ? C.carpet_teal  : C.carpet_teal2;
  else if (t === 4) ctx.fillStyle = checker ? C.rug_purple   : C.rug_border;
  else if (t === 5) ctx.fillStyle = checker ? C.carpet_meet  : C.carpet_meet2;
  else ctx.fillStyle = checker ? C.floor : C.floor2;
  ctx.fillRect(x, y, TS, TS);
}

// ─── Móveis ───────────────────────────────────────────────────
function drawDesk(tx, ty) {
  const x = tx * TS, y = ty * TS;
  ctx.fillStyle = C.desk_mid;   ctx.fillRect(x + 2, y + 4, TS * 2 - 4, TS - 8);
  ctx.fillStyle = C.desk_dark;  ctx.fillRect(x + 2, y + TS - 8, TS * 2 - 4, 4);
  ctx.fillStyle = C.desk_light; ctx.fillRect(x + 2, y + 4, TS * 2 - 4, 4);
  // monitor
  ctx.fillStyle = C.monitor;      ctx.fillRect(x + TS * .3, y - TS * .6, TS * .8, TS * .55);
  ctx.fillStyle = C.monitor_glow; ctx.fillRect(x + TS * .32, y - TS * .58, TS * .76, TS * .45);
  ctx.fillStyle = 'rgba(96,168,224,0.5)';
  ctx.fillRect(x + TS * .36, y - TS * .52, TS * .4, 2);
  ctx.fillRect(x + TS * .36, y - TS * .46, TS * .3, 2);
  ctx.fillRect(x + TS * .36, y - TS * .40, TS * .5, 2);
  // stand
  ctx.fillStyle = C.desk_mid; ctx.fillRect(x + TS * .65, y - TS * .05, TS * .1, TS * .1);
  // chair
  ctx.fillStyle = C.chair;      ctx.fillRect(x + TS * .35, y + TS, TS * .65, TS * .45);
  ctx.fillStyle = C.chair_dark; ctx.fillRect(x + TS * .35, y + TS, TS * .65, TS * .1);
}

function drawRoundTable(cx, cy) {
  const x = cx * TS + TS / 2, y = cy * TS + TS / 2;
  const r = TS * 1.4;
  ctx.fillStyle = '#1e3020';
  ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.fill();
  ctx.strokeStyle = 'rgba(74,222,128,0.28)'; ctx.lineWidth = 2;
  ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.stroke();
  ctx.fillStyle = 'rgba(74,222,128,0.1)';
  ctx.beginPath(); ctx.arc(x, y, r * .35, 0, Math.PI * 2); ctx.fill();
}

function drawPlant(tx, ty) {
  const x = tx * TS, y = ty * TS;
  ctx.fillStyle = C.plant_pot;  ctx.fillRect(x + TS * .3, y + TS * .5, TS * .4, TS * .5);
  ctx.fillStyle = C.plant_dark; ctx.fillRect(x + TS * .25, y + TS * .35, TS * .5, TS * .2);
  ctx.fillStyle = C.plant;
  ctx.beginPath(); ctx.ellipse(x + TS * .5, y + TS * .18, TS * .32, TS * .32, 0, 0, Math.PI * 2); ctx.fill();
  ctx.fillStyle = C.plant_dark;
  ctx.beginPath(); ctx.ellipse(x + TS * .32, y + TS * .26, TS * .18, TS * .18, -.5, 0, Math.PI * 2); ctx.fill();
  ctx.beginPath(); ctx.ellipse(x + TS * .68, y + TS * .22, TS * .18, TS * .18, .5, 0, Math.PI * 2); ctx.fill();
}

function drawBookshelf(tx, ty) {
  const x = tx * TS, y = ty * TS;
  ctx.fillStyle = C.bookshelf; ctx.fillRect(x, y, TS * 2, TS * .88);
  ctx.fillStyle = '#1a0e08';
  ctx.fillRect(x, y + TS * .28, TS * 2, 3);
  ctx.fillRect(x, y + TS * .58, TS * 2, 3);
  const books = [C.book1, C.book2, C.book3, '#6b2a8b', '#8b6b1a', '#1a6b6b'];
  const bw = TS * .16;
  books.forEach((c, i) => {
    ctx.fillStyle = c;
    ctx.fillRect(x + 4 + i * bw, y + 4,          bw - 2, TS * .22);
    ctx.fillRect(x + 4 + i * bw, y + TS * .32,   bw - 2, TS * .22);
  });
}

function drawWindow(tx, ty) {
  const x = tx * TS, y = ty * TS;
  ctx.fillStyle = C.wall;         ctx.fillRect(x, y, TS * 2, TS);
  ctx.fillStyle = C.window_bg;    ctx.fillRect(x + 4, y + 3, TS * 2 - 8, TS - 8);
  ctx.fillStyle = C.window_light; ctx.fillRect(x + 4, y + 3, TS * 2 - 8, (TS - 8) * .38);
  ctx.fillStyle = C.wall;
  ctx.fillRect(x + TS - 2, y + 3, 4, TS - 6);
  ctx.fillRect(x + 4, y + TS * .5, TS * 2 - 8, 3);
}

function drawWhiteboard(tx, ty) {
  const x = tx * TS, y = ty * TS;
  ctx.fillStyle = C.wb_frame; ctx.fillRect(x, y, TS * 3, TS * .82);
  ctx.fillStyle = '#dde8dd';  ctx.fillRect(x + 3, y + 3, TS * 3 - 6, TS * .82 - 6);
  ctx.fillStyle = 'rgba(30,60,40,0.55)';
  ctx.fillRect(x + 8,        y + 8,  TS, 3);
  ctx.fillRect(x + 8,        y + 14, TS * .7, 3);
  ctx.fillRect(x + 8,        y + 20, TS * .85, 3);
  ctx.fillStyle = 'rgba(160,40,40,0.4)';
  ctx.fillRect(x + TS + 12,  y + 8,  TS * .5, 3);
  ctx.fillRect(x + TS + 12,  y + 14, TS * .65, 3);
}

function drawLamp(tx, ty) {
  const x = tx * TS, y = ty * TS;
  ctx.fillStyle = '#3a3a2a'; ctx.fillRect(x + TS * .44, y + TS * .22, TS * .12, TS * .78);
  ctx.fillStyle = C.lamp_on;
  ctx.beginPath();
  ctx.moveTo(x + TS * .18, y + TS * .36);
  ctx.lineTo(x + TS * .82, y + TS * .36);
  ctx.lineTo(x + TS * .66, y + TS * .2);
  ctx.lineTo(x + TS * .34, y + TS * .2);
  ctx.closePath(); ctx.fill();
  ctx.fillStyle = 'rgba(255,221,136,0.1)';
  ctx.beginPath(); ctx.arc(x + TS * .5, y + TS * .36, TS * .48, 0, Math.PI * 2); ctx.fill();
  ctx.fillStyle = '#3a3a2a'; ctx.fillRect(x + TS * .3, y + TS * .9, TS * .4, TS * .1);
}

// ─── Labels das salas ─────────────────────────────────────────
function drawRoomLabels() {
  const labels = [
    { x: 1.2, y: 1.7, text: 'LÍDER TÉCNICO',   color: 'rgba(139,92,246,.55)' },
    { x: 11.2,y: 1.7, text: 'DEV & QA',         color: 'rgba(56,189,248,.55)' },
    { x: 21.2,y: 1.7, text: 'UX / SEO',         color: 'rgba(244,114,182,.55)' },
    { x: 11.5,y: 11.5,text: 'SALA DE REUNIÃO',  color: 'rgba(74,222,128,.38)' },
  ];
  ctx.font = `bold ${Math.floor(TS * .21)}px "Courier New"`;
  labels.forEach(l => {
    ctx.fillStyle = l.color;
    ctx.fillText(l.text, l.x * TS, l.y * TS);
  });
}

// ─── Mapa completo ────────────────────────────────────────────
function drawMap() {
  for (let ty = 0; ty < MAP_H; ty++)
    for (let tx = 0; tx < MAP_W; tx++)
      drawTile(tx, ty);

  // Sala 1 — Líder
  drawWindow(1, 1); drawWindow(3, 1);
  drawDesk(1, 3);   drawDesk(1, 5);
  drawBookshelf(6, 2);
  drawPlant(8, 6);  drawPlant(1, 7);
  drawLamp(7, 6);

  // Sala 2 — Dev / QA
  drawWindow(11, 1); drawWindow(13, 1);
  drawDesk(11, 3);   drawDesk(11, 5);
  drawDesk(15, 3);   drawDesk(15, 5);
  drawPlant(19, 2);  drawPlant(19, 6);

  // Sala 3 — UX / SEO
  drawWindow(21, 1); drawWindow(23, 1);
  drawWhiteboard(21, 2);
  drawDesk(21, 4);   drawDesk(25, 4);
  drawPlant(21, 7);  drawPlant(27, 2);
  drawLamp(26, 6);

  // Sala de reunião
  drawRoundTable(14, 14);
  [[13,12],[15,12],[12,14],[17,14],[13,17],[15,17]].forEach(([x, y]) => {
    ctx.fillStyle = C.chair;
    ctx.fillRect(x * TS + TS * .2, y * TS + TS * .1, TS * .6, TS * .7);
    ctx.fillStyle = C.chair_dark;
    ctx.fillRect(x * TS + TS * .2, y * TS + TS * .1, TS * .6, TS * .15);
  });

  // Corredor
  drawPlant(2, 10);  drawPlant(28, 10);
  drawLamp(5, 11);   drawLamp(24, 11);

  drawRoomLabels();
}

// ─── Personagem pixel art ─────────────────────────────────────
function drawCharacter(px, py, color, label, isPlayer, frame, agent) {
  const s = TS;
  const x = px, y = py;
  const moving = isPlayer && window._playerMoving;
    const thinking = !isPlayer && window._thinkingAgents && window._thinkingAgents.has(agent ? agent.slug : '');
    const bob = moving ? Math.sin(frame * .38) * (s * .055)
          : thinking ? Math.sin(frame * .15) * (s * .03)
          : 0;

  // sombra
  ctx.fillStyle = 'rgba(0,0,0,0.3)';
  ctx.beginPath();
  ctx.ellipse(x + s * .5, y + s * .96, s * .26, s * .07, 0, 0, Math.PI * 2);
  ctx.fill();

  // pernas
  const swing = moving ? Math.sin(frame * .38) * (s * .07) : 0;
  ctx.fillStyle = isPlayer ? '#2a4a8a' : '#1a2a3a';
  ctx.fillRect(x + s * .28, y + s * .62 + bob, s * .17, s * .3 + swing * .3);
  ctx.fillRect(x + s * .55, y + s * .62 + bob, s * .17, s * .3 - swing * .3);

  // corpo
  ctx.fillStyle = isPlayer ? '#2a5090' : '#1a2a3a';
  ctx.fillRect(x + s * .22, y + s * .3 + bob, s * .56, s * .36);
  ctx.fillStyle = isPlayer ? 'rgba(80,160,255,.35)' : `${color}44`;
  ctx.fillRect(x + s * .26, y + s * .32 + bob, s * .48, s * .1);

  // braços
  const armSwing = moving ? Math.sin(frame * .38 + Math.PI) * (s * .055) : 0;
  ctx.fillStyle = isPlayer ? '#2a5090' : '#162030';
  ctx.fillRect(x + s * .08, y + s * .32 + bob + armSwing, s * .14, s * .26);
  ctx.fillRect(x + s * .78, y + s * .32 + bob - armSwing, s * .14, s * .26);

  // cabeça
  ctx.fillStyle = '#d4a574';
  ctx.fillRect(x + s * .3, y + s * .07 + bob, s * .4, s * .27);
  ctx.fillStyle = isPlayer ? '#1a3a1a' : '#1a1a1a';
  ctx.fillRect(x + s * .3, y + s * .07 + bob, s * .4, s * .09);
  ctx.fillStyle = '#0a0a0a';
  ctx.fillRect(x + s * .36, y + s * .15 + bob, s * .07, s * .06);
  ctx.fillRect(x + s * .57, y + s * .15 + bob, s * .07, s * .06);

  // nome
  ctx.font = `bold ${Math.floor(s * .19)}px "Courier New"`;
  ctx.fillStyle = 'rgba(0,0,0,0.55)';
  ctx.fillRect(x + s * .08, y - s * .22, s * .84, s * .2);
  ctx.fillStyle = isPlayer ? '#4ade80' : color;
  ctx.textAlign = 'center';
  ctx.fillText(label, x + s * .5, y - s * .07);
  ctx.textAlign = 'left';

  // coroa do chefe
  if (isPlayer) {
    ctx.font = `${Math.floor(s * .26)}px serif`;
    ctx.fillText('👑', x + s * .28, y + s * .05 + bob);
  }
}

// ─── Câmera ───────────────────────────────────────────────────
let camX = 0, camY = 0;

function updateCamera() {
  const vw = window.innerWidth - 240;
  const vh = window.innerHeight;
  const targetX = window._player.x - vw / 2 + TS / 2;
  const targetY = window._player.y - vh / 2 + TS / 2;
  camX += (targetX - camX) * .1;
  camY += (targetY - camY) * .1;
  camX = Math.max(0, Math.min(MAP_W * TS - vw, camX));
  camY = Math.max(0, Math.min(MAP_H * TS - vh, camY));
}

// ─── Player ───────────────────────────────────────────────────
window._player = { tx: 14, ty: 11, x: 14 * TS, y: 11 * TS };
window._playerMoving = false;

const keys = {};
window.addEventListener('keydown', e => {
  keys[e.key] = true;
  if (['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].includes(e.key)) e.preventDefault();
});
window.addEventListener('keyup', e => { keys[e.key] = false; });

let playerFrame = 0, playerFrameTimer = 0;

function updatePlayer(dt) {
  let dx = 0, dy = 0;
  if (keys['ArrowLeft']  || keys['a']) dx = -1;
  if (keys['ArrowRight'] || keys['d']) dx =  1;
  if (keys['ArrowUp']    || keys['w']) dy = -1;
  if (keys['ArrowDown']  || keys['s']) dy =  1;

  window._playerMoving = dx !== 0 || dy !== 0;

  if (window._playerMoving) {
    playerFrameTimer += dt;
    if (playerFrameTimer > .09) { playerFrame++; playerFrameTimer = 0; }

    const spd = 3 * dt;
    const nx = window._player.tx + dx * spd;
    const ny = window._player.ty + dy * spd;

    if (!isSolid(Math.floor(nx + .5), Math.floor(window._player.ty + .5)))
      window._player.tx = nx;
    if (!isSolid(Math.floor(window._player.tx + .5), Math.floor(ny + .5)))
      window._player.ty = ny;

    window._player.x = window._player.tx * TS;
    window._player.y = window._player.ty * TS;
  }
}

// ─── Agentes no mapa ──────────────────────────────────────────
const AGENT_COLORS = {
  tech_leader: '#8b5cf6',
  ux_ui:       '#f472b6',
  dev:         '#38bdf8',
  qa:          '#34d399',
  seo:         '#fbbf24',
};

function getAgentColor(slug) {
  return AGENT_COLORS[slug] || '#4ade80';
}

// ─── Loop principal ───────────────────────────────────────────
let lastTime = 0;

function loop(ts) {
  requestAnimationFrame(loop);
  const dt = Math.min((ts - lastTime) / 1000, .05);
  lastTime = ts;

  updatePlayer(dt);
  updateCamera();

  ctx.fillStyle = '#080e08';
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  ctx.save();
  ctx.translate(-camX, -camY);

  drawMap();

  // agentes
  if (typeof AGENTS_DATA !== 'undefined') {
    AGENTS_DATA.forEach(a => {
      drawCharacter(
        a.pos_x * TS,
        a.pos_y * TS,
        getAgentColor(a.slug),
        a.name,
        false,
        ts * .01,
        a
      );
    });
  }

  // player
  drawCharacter(
    window._player.x,
    window._player.y,
    '#4ade80',
    'VOCÊ',
    true,
    playerFrame
  );

  ctx.restore();

  // atualiza bolhas
  if (typeof updateBubblePositions === 'function') {
    updateBubblePositions(camX, camY);
  }
}

requestAnimationFrame(ts => { lastTime = ts; loop(ts); });

// resize
window.addEventListener('resize', () => {
  document.getElementById('game-container').style.width = (window.innerWidth - 240) + 'px';
});
document.getElementById('game-container').style.width = (window.innerWidth - 240) + 'px';