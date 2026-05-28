# 🤖 Agência Virtual — Maiolidesign

Sistema web de agência de criação com inteligência artificial, onde um time de agentes de IA trabalha em pipeline para gerar projetos (landing pages, sites) a partir de uma única tarefa enviada pelo usuário.

---

## 🖥️ Visão Geral

A interface simula um escritório virtual em canvas 2D onde cada agente de IA ocupa uma mesa. O usuário envia uma tarefa e o time trabalha em sequência, cada agente passando o contexto para o próximo, até a entrega final do projeto.

**Pipeline de agentes:**

```
🧠 Tech Leader → 🎨 UX/UI → 💻 Dev → 🔍 QA → 📈 SEO
```

Cada agente tem seu próprio modelo de IA configurável (OpenRouter, Anthropic ou OpenAI), prompt de sistema e especialidade.

---

## 🚀 Funcionalidades

- Escritório virtual interativo com movimentação em canvas
- Pipeline de agentes de IA encadeados
- Suporte a múltiplos provedores: **OpenRouter**, **Anthropic** e **OpenAI**
- Chat ao vivo mostrando as respostas dos agentes em tempo real
- Geração automática de landing pages em HTML
- Revisão automática pelo agente QA antes de publicar
- Gerenciamento de agentes (criar, editar, ativar/desativar)
- Painel de projetos gerados com visualização direta
- Autenticação por e-mail e senha por cliente

---

## 🗂️ Estrutura do Projeto

```
├── index.php              # Escritório virtual (tela principal)
├── login.php              # Autenticação
├── logout.php             # Encerramento de sessão
├── settings.php           # Painel de configurações
├── config/
│   └── config.php         # Conexão com banco, sessão e helpers
├── agents/
│   ├── Agent.php          # Classe base dos agentes
│   ├── DevAgent.php       # Agente desenvolvedor
│   ├── QAAgent.php        # Agente de qualidade
│   ├── SEOAgent.php       # Agente de SEO
│   ├── TechLeader.php     # Agente líder técnico
│   └── UIUX.php           # Agente de UX/UI
├── api/
│   ├── agents.php         # CRUD de agentes
│   ├── projects.php       # Gerenciamento de projetos
│   ├── settings.php       # Salvar chaves de API
│   └── task.php           # Execução do pipeline de IA
├── assets/
│   ├── css/game.css       # Estilos do escritório virtual
│   └── js/
│       ├── game.js        # Motor do canvas 2D
│       └── agency.js      # Lógica do pipeline de agentes
├── sql/
│   └── schema.sql         # Estrutura do banco de dados
└── projetos/              # Projetos gerados (ignorado pelo Git)
```

---

## ⚙️ Requisitos

- PHP 8.1+
- MySQL 5.7+ ou MariaDB
- Servidor web com suporte a `.htaccess` (Apache/LiteSpeed)
- Conta em pelo menos um dos provedores de IA:
  - [OpenRouter](https://openrouter.ai)
  - [Anthropic](https://console.anthropic.com)
  - [OpenAI](https://platform.openai.com)

---

## 🔧 Instalação

### 1. Clone o repositório

```bash
git clone git@github.com:RobertoMaioli/agencia_maiolideisgn.git
cd agencia_maiolideisgn
```

### 2. Configure o banco de dados

Importe o schema SQL:

```bash
mysql -u seu_usuario -p seu_banco < sql/schema.sql
```

### 3. Configure o `.env`

Crie o arquivo `.env` na raiz do projeto:

```env
APP_URL=https://seu-dominio.com.br

DB_HOST=localhost
DB_NAME=nome_do_banco
DB_USER=usuario
DB_PASS=senha

OPENROUTER_KEY=sk-or-v1-...
ANTHROPIC_KEY=sk-ant-...
OPENAI_KEY=sk-...
```

### 4. Configure as permissões

```bash
chmod 755 projetos/
```

---

## 🔐 Segurança

- Arquivo `.env` bloqueado via `.htaccess`
- Pastas `config/`, `agents/` e `sql/` inacessíveis via browser
- Sessões com `httponly`, `secure` e `samesite=Strict`
- Chaves de API armazenadas no banco de dados (criptografadas pela sessão)
- HTTPS forçado via redirect no `.htaccess`

---

## 📦 Variáveis de Ambiente

| Variável | Descrição |
|---|---|
| `APP_URL` | URL base da aplicação |
| `DB_HOST` | Host do banco de dados |
| `DB_NAME` | Nome do banco de dados |
| `DB_USER` | Usuário do banco |
| `DB_PASS` | Senha do banco |
| `OPENROUTER_KEY` | Chave da API OpenRouter |
| `ANTHROPIC_KEY` | Chave da API Anthropic |
| `OPENAI_KEY` | Chave da API OpenAI |

---

## 🤝 Autor

Desenvolvido por **Roberto Maioli** — [maiolidesign.com.br](https://maiolidesign.com.br)