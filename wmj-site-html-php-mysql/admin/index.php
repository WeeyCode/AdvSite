<?php
/**
 * Painel administrativo (PHP + MySQL).
 *
 * Controles: senha com password_hash (bcrypt), sessão com cookie HttpOnly +
 * SameSite=Strict, CSRF em todo POST, verificação de origem, rate limit de
 * login, cabeçalhos no-store/noindex e auditoria sem dados pessoais.
 */

declare(strict_types=1);

require_once __DIR__ . '/../inc/functions.php';

define('WMJ_PAINEL', true); // marca para as views

iniciar_sessao();

/* Cabeçalhos de área privada */
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$acao = $_POST['acao'] ?? $_GET['acao'] ?? 'painel';

/* ---------------------------- LOGIN ---------------------------- */

if ($acao === 'entrar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!origem_permitida()) {
        $erro = 'Origem não autorizada.';
    } elseif (!rate_limit('login:' . ip_hash(), LIMITE_LOGIN, LIMITE_LOGIN_SEGUNDOS)) {
        auditar('bloqueio', 'limite de tentativas de login');
        http_response_code(429);
        $erro = 'Muitas tentativas. Aguarde 15 minutos antes de tentar novamente.';
    } else {
        $usuario = texto_linha($_POST['usuario'] ?? '', 64);
        $senha   = (string) ($_POST['senha'] ?? '');

        $stmt = db()->prepare('SELECT id, usuario, senha_hash FROM admin_users WHERE usuario = ? LIMIT 1');
        $stmt->execute([$usuario]);
        $registro = $stmt->fetch();

        // password_verify roda mesmo sem usuário, para não vazar tempo de resposta.
        // O hash falso usa o MESMO custo (12) dos hashes reais: custo diferente
        // criava um oráculo de tempo (usuário existente respondia 4x mais devagar).
        $hashFalso = '$2y$12$W8eb2yLQSK3pZxnpCsHa5.Q1rSGDBZv5XG/RzrXysASLVxkdrcwI2';
        $confere   = password_verify($senha, $registro['senha_hash'] ?? $hashFalso);

        if ($registro && $confere) {
            session_regenerate_id(true);
            $_SESSION['admin_id']  = (int) $registro['id'];
            $_SESSION['usuario']   = $registro['usuario'];
            $_SESSION['expira_em'] = time() + 8 * 3600;
            $_SESSION['csrf']      = bin2hex(random_bytes(32));

            auditar('login_ok', $registro['usuario']);
            header('Location: index.php', true, 303);
            exit;
        }

        auditar('login_falha', $usuario !== '' ? 'usuario informado' : 'vazio');
        http_response_code(401);
        $erro = 'Usuário ou senha incorretos.';
    }
}

if ($acao === 'sair' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (csrf_valido($_POST['_csrf'] ?? null)) {
        auditar('logout', $_SESSION['usuario'] ?? null);
    }
    encerrar_sessao();
    header('Location: index.php', true, 303);
    exit;
}

/* ------------------------- ALTERAR STATUS ------------------------- */

if ($acao === 'status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!origem_permitida() || !csrf_valido($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Sessão inválida ou expirada. Recarregue a página e entre novamente.');
    }
    exigir_login();

    $id     = (int) ($_POST['id'] ?? 0);
    $status = (string) ($_POST['status'] ?? '');

    if ($id > 0 && in_array($status, ['novo', 'atendido', 'arquivado'], true)) {
        $stmt = db()->prepare('UPDATE contatos SET status = ? WHERE id = ?');
        $stmt->execute([$status, $id]);
        auditar('status_alterado', $status . ' #' . $id);
    } else {
        auditar('status_invalido', $status . ' #' . $id);
    }

    header('Location: index.php', true, 303);
    exit;
}

/* ------------------------ ANONIMIZAR (LGPD) ------------------------ */

if ($acao === 'anonimizar' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!origem_permitida() || !csrf_valido($_POST['_csrf'] ?? null)) {
        http_response_code(403);
        exit('Sessão inválida ou expirada. Recarregue a página e entre novamente.');
    }
    exigir_login();

    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $ok = anonimizar($id);
        auditar($ok ? 'anonimizacao' : 'anonimizacao_ignorada', '#' . $id . ' — art. 18, VI, LGPD');
    }

    header('Location: index.php', true, 303);
    exit;
}

/* ---------------------------- EXPORTAR ---------------------------- */

if ($acao === 'exportar' && sessao_autenticada()) {
    $formato = ($_GET['formato'] ?? 'csv') === 'json' ? 'json' : 'csv';

    $stmt = db()->query(
        'SELECT protocolo, created_at, status, nome, email, telefone, assunto, mensagem, consentimento, expira_em, expurgado_em
         FROM contatos ORDER BY created_at DESC LIMIT 500'
    );
    $linhas = $stmt->fetchAll();

    auditar('exportacao', strtoupper($formato) . ' — ' . count($linhas) . ' registro(s)');

    $carimbo = date('Y-m-d');

    if ($formato === 'json') {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="contatos-' . $carimbo . '.json"');
        echo json_encode(
            ['gerado_em' => date('c'), 'total' => count($linhas), 'contatos' => $linhas],
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        exit;
    }

    /** Neutraliza fórmulas de planilha (CSV injection). */
    $csv = static function (?string $v): string {
        if ($v === null || $v === '') {
            return '';
        }
        if (preg_match('/^[=+\-@\t\r]/', $v)) {
            $v = "'" . $v;
        }
        return '"' . str_replace('"', '""', $v) . '"';
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="contatos-' . $carimbo . '.csv"');

    echo "\u{FEFF}"; // BOM: abre corretamente no Excel em português
    echo implode(';', ['protocolo','recebido','status','nome','email','telefone','assunto','mensagem','consentimento','expira_em','expurgado_em']) . "\r\n";

    foreach ($linhas as $l) {
        echo implode(';', array_map($csv, [
            $l['protocolo'], $l['created_at'], $l['status'], $l['nome'], $l['email'],
            $l['telefone'], $l['assunto'], $l['mensagem'], $l['consentimento'],
            $l['expira_em'], $l['expurgado_em'],
        ])) . "\r\n";
    }
    exit;
}

/* ----------------------------- PAINEL ----------------------------- */

if (!sessao_autenticada()) {
    $errosLogin = [
        'sessao' => 'Sessão expirada. Entre novamente.',
        'config' => 'Área não configurada: cadastre um usuário na tabela admin_users.',
    ];
    $mensagem = $erro ?? ($errosLogin[$_GET['erro'] ?? ''] ?? null);
    $aviso    = $_GET['aviso'] ?? null;

    require __DIR__ . '/login.view.php';
    exit;
}

/* Expurgo por retenção, executado no próprio acesso ao painel. */
$expurgados = expurgar_expirados();
if ($expurgados > 0) {
    auditar('expurgo', $expurgados . ' registro(s)');
}

/* Filtros */
$filtroStatus = (string) ($_GET['status'] ?? '');
$busca        = texto_linha($_GET['busca'] ?? '', 60);

$sql    = 'SELECT * FROM contatos';
$where  = [];
$params = [];

if (in_array($filtroStatus, ['novo', 'atendido', 'arquivado'], true)) {
    $where[]  = 'status = ?';
    $params[] = $filtroStatus;
}

if (mb_strlen($busca, 'UTF-8') >= 2) {
    $where[]  = '(nome LIKE ? OR email LIKE ? OR protocolo LIKE ?)';
    // Escapa curingas do LIKE para não ampliar a busca acidentalmente.
    $termo    = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $busca);
    $params[] = '%' . $termo . '%';
    $params[] = '%' . $termo . '%';
    $params[] = '%' . $termo . '%';
}

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC LIMIT 100';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$contatos = $stmt->fetchAll();

/* Estatísticas */
$stats = ['novo' => 0, 'atendido' => 0, 'arquivado' => 0, 'total' => 0];
foreach (db()->query('SELECT status, COUNT(*) AS total FROM contatos GROUP BY status')->fetchAll() as $row) {
    $stats[$row['status']] = (int) $row['total'];
    $stats['total']        += (int) $row['total'];
}

/* Auditoria */
$auditoria = db()->query(
    'SELECT id, evento, detalhe, ip_hash, created_at FROM auditoria ORDER BY created_at DESC LIMIT 100'
)->fetchAll();

$csrf = csrf_token();

require __DIR__ . '/painel.view.php';
