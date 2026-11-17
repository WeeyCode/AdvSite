<?php
/**
 * Painel de contatos.
 * Recebe: $contatos, $stats, $auditoria, $csrf, $filtroStatus, $busca, $expurgados.
 */

declare(strict_types=1);

// Bloqueia acesso direto: esta view só deve ser incluída pelo controller.
if (!defined('WMJ_PAINEL')) { http_response_code(404); exit; }


$rotulosStatus = ['novo' => 'Novo', 'atendido' => 'Atendido', 'arquivado' => 'Arquivado'];

$rotulosAssunto = [
    'inventario'   => 'Inventário',
    'familia'      => 'Família em cartório',
    'usucapiao'    => 'Usucapião',
    'adjudicacao'  => 'Adjudicação compulsória',
    'escrituras'   => 'Escrituras e contratos',
    'ata-notarial' => 'Ata notarial',
    'protesto'     => 'Protesto',
    'requerimentos'=> 'Requerimentos',
    'lgpd'         => 'LGPD',
    'condominio'   => 'Condomínio',
    'negociacao'   => 'Negociação',
    'tributos'     => 'Tributos',
    'consultoria'  => 'Consultoria',
    'cobranca'     => 'Cobrança',
    'outro'        => 'Outro',
];

/** Formata 'Y-m-d H:i:s' em dd/mm/aaaa hh:mm. */
function data_hora(?string $v): string
{
    if (!$v) {
        return '—';
    }
    $ts = strtotime($v);
    return $ts ? date('d/m/Y H:i', $ts) : e($v);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel administrativo — <?= e(SITE_NOME) ?></title>
<meta name="robots" content="noindex, nofollow, noarchive">
<meta name="referrer" content="no-referrer">
<link rel="icon" type="image/svg+xml" href="../static/favicon.svg">
<link rel="stylesheet" href="../static/vendor/fa/css/all.min.css">
<link rel="stylesheet" href="../static/site.css">
</head>
<body class="admin-page">
<div class="admin-wrap">

  <header class="admin-head">
    <div>
      <h1><i class="fas fa-gauge-high" aria-hidden="true"></i> Painel de contatos</h1>
      <p>Sessão de <strong><?= e($_SESSION['usuario']) ?></strong> &bull; dados pessoais sob sigilo profissional</p>
    </div>
    <div class="filtros" class="filtros-inline">
      <a href="index.php?acao=exportar&amp;formato=csv"><i class="fas fa-file-csv" aria-hidden="true"></i> Exportar CSV</a>
      <a href="index.php?acao=exportar&amp;formato=json"><i class="fas fa-file-code" aria-hidden="true"></i> Exportar JSON</a>
      <form method="post" action="index.php" class="inline-form">
        <input type="hidden" name="acao" value="sair">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <button type="submit" class="btn-xs">Sair</button>
      </form>
    </div>
  </header>

  <div class="stats">
    <div class="stat"><p class="stat-v"><?= (int) $stats['total'] ?></p><p class="stat-l">Total</p></div>
    <div class="stat"><p class="stat-v"><?= (int) $stats['novo'] ?></p><p class="stat-l">Novos</p></div>
    <div class="stat"><p class="stat-v"><?= (int) $stats['atendido'] ?></p><p class="stat-l">Atendidos</p></div>
    <div class="stat"><p class="stat-v"><?= (int) $stats['arquivado'] ?></p><p class="stat-l">Arquivados</p></div>
  </div>

  <?php if ($expurgados > 0): ?>
    <div class="form-msg ok">
      Expurgo automático executado: <?= (int) $expurgados ?> registro(s) anonimizado(s) por prazo de retenção.
    </div>
  <?php endif; ?>

  <form method="get" action="index.php" class="filtros" role="search">
    <?php
    $abas = ['' => 'Todos', 'novo' => 'Novo', 'atendido' => 'Atendido', 'arquivado' => 'Arquivado'];
    foreach ($abas as $valor => $rotulo):
        $qs = [];
        if ($valor !== '') { $qs['status'] = $valor; }
        if ($busca !== '') { $qs['busca'] = $busca; }
        $href = 'index.php' . ($qs ? '?' . http_build_query($qs) : '');
        $conta = $valor !== '' ? ' (' . (int) $stats[$valor] . ')' : '';
    ?>
      <a href="<?= e($href) ?>" class="<?= $filtroStatus === $valor ? 'active' : '' ?>"><?= $rotulo . $conta ?></a>
    <?php endforeach; ?>

    <input type="search" name="busca" value="<?= e($busca) ?>" maxlength="60"
           placeholder="Buscar nome, e-mail ou protocolo" aria-label="Buscar contatos">
    <?php if ($filtroStatus !== ''): ?>
      <input type="hidden" name="status" value="<?= e($filtroStatus) ?>">
    <?php endif; ?>
    <button type="submit" class="btn-xs"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Buscar</button>
  </form>

  <section class="msg-list" aria-label="Mensagens recebidas">
  <?php if (!$contatos): ?>
    <p class="empty"><i class="fas fa-inbox" aria-hidden="true"></i><br>
      Nenhuma mensagem <?= $filtroStatus !== '' ? 'com este status' : 'recebida' ?><?= $busca !== '' ? ' para esta busca' : '' ?>.</p>
  <?php else: ?>
    <?php foreach ($contatos as $m):
        $classe  = $m['status'] === 'novo' ? 'novo' : ($m['status'] === 'atendido' ? 'atendido' : '');
        $assunto = $m['assunto'] ? ($rotulosAssunto[$m['assunto']] ?? $m['assunto']) : 'Não informado';
        $telLink = $m['telefone']
            ? '<a href="https://wa.me/55' . e($m['telefone']) . '" target="_blank" rel="noopener noreferrer">' . e($m['telefone']) . '</a>'
            : '—';
    ?>
      <article class="msg <?= $classe ?>">
        <div class="msg-top">
          <div>
            <p class="msg-proto"><?= e($m['protocolo']) ?></p>
            <h3><?= e($m['nome']) ?></h3>
            <p class="msg-contato">
              <a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a> &bull;
              <?= $telLink ?> &bull; <?= e($assunto) ?>
            </p>
          </div>
          <div class="msg-lado">
            <p class="admin-date"><?= data_hora($m['created_at']) ?></p>
            <p class="admin-date"><?= e($rotulosStatus[$m['status']] ?? $m['status']) ?><?= $m['expurgado_em'] ? ' &bull; anonimizado' : '' ?></p>
          </div>
        </div>

        <p class="msg-body"><?= e($m['mensagem']) ?></p>

        <div class="msg-actions">
          <?php foreach (['novo', 'atendido', 'arquivado'] as $st):
              if ($st === $m['status']) { continue; } ?>
            <form method="post" action="index.php">
              <input type="hidden" name="acao" value="status">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <input type="hidden" name="status" value="<?= $st ?>">
              <button type="submit" class="btn-xs">Marcar <?= e($rotulosStatus[$st]) ?></button>
            </form>
          <?php endforeach; ?>

          <?php if (!$m['expurgado_em']): ?>
            <form method="post" action="index.php">
              <input type="hidden" name="acao" value="anonimizar">
              <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
              <button type="submit" class="btn-xs danger"
                      title="Elimina os dados pessoais mantendo o registro do atendimento (art. 18, VI, LGPD)">
                Anonimizar (LGPD)
              </button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
  </section>

  <section class="audit" aria-labelledby="t-audit">
    <h2 id="t-audit"><i class="fas fa-clipboard-list" aria-hidden="true"></i> Registro de eventos de segurança</h2>
    <p class="admin-date">Últimos 100 eventos. Não contém nome, e-mail, telefone nem conteúdo de mensagem.</p>

    <?php if (!$auditoria): ?>
      <p class="empty">Sem eventos registrados.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Data</th><th>Evento</th><th>Detalhe</th><th>IP (hash)</th></tr>
          </thead>
          <tbody>
          <?php foreach ($auditoria as $a): ?>
            <tr>
              <td><?= data_hora($a['created_at']) ?></td>
              <td><code><?= e($a['evento']) ?></code></td>
              <td><?= e($a['detalhe'] ?? '—') ?></td>
              <td><?= e($a['ip_hash'] ?? '—') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="audit" aria-labelledby="t-politica">
    <h2 id="t-politica"><i class="fas fa-scale-balanced" aria-hidden="true"></i> Política aplicada a estes dados</h2>
    <div class="sec-block">
      <p><strong>Base legal:</strong> consentimento do titular (art. 7º, I, e art. 8º da LGPD), registrado na coluna <code>consentimento</code>.</p>
      <p><strong>Retenção:</strong> <?= RETENCAO_MESES ?> meses contados do envio; depois disso o registro é anonimizado automaticamente.</p>
      <p><strong>Eliminação antecipada:</strong> use <em>Anonimizar (LGPD)</em> ao receber pedido do titular (art. 18, VI). A operação remove nome, e-mail, telefone, mensagem, IP e agente, preservando apenas a existência do atendimento.</p>
      <p><strong>Sigilo:</strong> o conteúdo é protegido pelos arts. 35 a 38 do Código de Ética e Disciplina da OAB. Não exporte nem compartilhe fora do necessário à prestação do serviço.</p>
    </div>
  </section>

</div>
</body>
</html>
