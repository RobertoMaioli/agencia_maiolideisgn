<?php
require_once __DIR__ . '/config/config.php';

if (auth()) {
    header('Location: /index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = db()->prepare('SELECT * FROM clients WHERE email = ? AND active = 1 LIMIT 1');
        $stmt->execute([$email]);
        $client = $stmt->fetch();

        if ($client && password_verify($password, $client['password'])) {
            $_SESSION['client'] = [
                'id'   => $client['id'],
                'name' => $client['name'],
                'slug' => $client['slug'],
                'plan' => $client['plan'],
            ];
            header('Location: /index.php');
            exit;
        }
    }
    $error = 'E-mail ou senha incorretos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Login</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
html,body{
  height:100%;
  background:#0a0f0a;
  display:flex;
  align-items:center;
  justify-content:center;
  font-family:monospace;
}
.box{
  width:340px;
  padding:32px;
  background:#0e1a10;
  border:1px solid #4ade80;
  border-radius:12px;
}
h1{color:#4ade80;font-size:16px;margin-bottom:24px;text-align:center}
label{display:block;color:#a7f3d0;font-size:12px;margin-bottom:6px}
input{
  display:block;width:100%;padding:10px;margin-bottom:16px;
  background:#0a0f0a;border:1px solid #2a4a2a;
  border-radius:6px;color:#fff;font-family:monospace;font-size:13px;
  outline:none;
}
input:focus{border-color:#4ade80}
button{
  width:100%;padding:11px;
  background:#1a3a1a;border:1px solid #4ade80;
  border-radius:6px;color:#4ade80;
  font-family:monospace;font-size:13px;cursor:pointer;
}
button:hover{background:#2a4a2a}
.error{
  padding:8px;margin-bottom:14px;
  background:#2a0a0a;border:1px solid #ef4444;
  border-radius:6px;color:#fca5a5;font-size:12px;
}
</style>
</head>
<body>
<div class="box">
  <h1>▸ AGÊNCIA VIRTUAL</h1>

  <?php if ($error): ?>
    <div class="error"><?= htmlspecialchars($error) ?></div>
  <?php endif ?>

  <form method="post" action="/login.php">
    <label>E-mail</label>
    <input type="email" name="email" placeholder="seu@email.com" required autofocus/>

    <label>Senha</label>
    <input type="password" name="password" placeholder="••••••••" required/>

    <button type="submit">ENTRAR</button>
  </form>
</div>
</body>
</html>