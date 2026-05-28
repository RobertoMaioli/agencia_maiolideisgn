<?php
require_once __DIR__ . '/config/config.php';

unset($_SESSION['current_project_id']);
unset($_SESSION['current_project_slug']);

echo "Sessão limpa! <a href='/index.php'>Voltar ao escritório</a>";