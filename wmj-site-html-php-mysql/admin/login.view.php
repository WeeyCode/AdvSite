<?php
/**
 * Tela de login do painel administrativo.
 * Recebe: $mensagem (erro), $aviso.
 */

declare(strict_types=1);

// Bloqueia acesso direto: esta view só deve ser incluída pelo controller.
if (!defined('WMJ_PAINEL')) { http_response_code(404); exit; }

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acesso restrito — <?= e(SITE_NOME) ?></title>
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="referrer" content="no-referrer">
<link rel="icon" type="image/svg+xml" href="../static/favicon.svg">
<link rel="stylesheet" href="../static/vendor/fa/css/all.min.css">
<link rel="stylesheet" href="../static/site.css">
</head>
<body class="admin-page">
<main class="admin-wrap">
  <section class="admin-login">
    <h1><i class="fas fa-lock" aria-hidden="true"></i> Acesso restrito</h1>
    <p>Área de gestão de contatos e registros de segurança. Uso exclusivo do advogado responsável.</p>

    <?php if (!empty($mensagem)): ?>
      <div class="form-msg err"><strong><?= e($mensagem) ?></strong></div>
    <?php elseif (!empty($aviso)): ?>
      <div class="form-msg ok"><?= e($aviso) ?></div>
    <?php endif; ?>

    <form method="post" action="index.php" autocomplete="off">
      <input type="hidden" name="acao" value="entrar">
      <div class="field">
        <label for="usuario">Usuário</label>
        <input type="text" id="usuario" name="usuario" required maxlength="64"
               autocomplete="username" autocapitalize="none">
      </div>
      <div class="field">
        <label for="senha">Senha</label>
        <input type="password" id="senha" name="senha" required maxlength="200"
               autocomplete="current-password">
      </div>
      <button type="submit" class="btn-primary form-submit">
        <i class="fas fa-right-to-bracket" aria-hidden="true"></i> Entrar
      </button>
    </form>

    <p class="form-secure">
      <i class="fas fa-shield-halved" aria-hidden="true"></i>
      Sessão de 8 horas. Tentativas limitadas por origem.
    </p>
    <p class="form-secure"><a href="../index.html">Voltar ao site</a></p>
  </section>
</main>
</body>
</html>
