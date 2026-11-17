<?php
/**
 * Funções compartilhadas: banco, segurança, validação e auditoria.
 * Todas as consultas usam prepared statements (PDO) — sem concatenação de SQL.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/* ============================ BANCO ============================ */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // prepared statements reais
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
    } catch (PDOException $e) {
        error_log('[db] falha de conexão: ' . $e->getMessage());
        http_response_code(503);
        exit('Banco de dados indisponível. Verifique as credenciais em inc/config.php.');
    }

    return $pdo;
}

/* ============================ SAÍDA ============================ */

function e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_saida(array $dados, int $codigo = 200): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* ============================ IP / HASH ============================ */

function ip_cliente(): string
{
    // Em XAMPP/local não há proxy; em produção, respeita o cabeçalho do proxy.
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP'] as $chave) {
        if (!empty($_SERVER[$chave])) {
            return (string) $_SERVER[$chave];
        }
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $partes = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($partes[0]);
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconhecido');
}

/** O IP nunca é gravado: apenas SHA-256(sal + IP), truncado. */
function ip_hash(): string
{
    return substr(hash('sha256', IP_SALT . '|' . ip_cliente()), 0, 32);
}

/* ============================ LIMPEZA ============================ */

function texto_linha(mixed $v, int $max): string
{
    if (!is_string($v)) {
        return '';
    }
    $v = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $v);
    $v = preg_replace('/[\x00-\x1F\x7F]/u', '', $v) ?? '';
    $v = preg_replace('/\s{2,}/u', ' ', $v) ?? '';
    return mb_substr(trim($v), 0, $max, 'UTF-8');
}

function texto_longo(mixed $v, int $max): string
{
    if (!is_string($v)) {
        return '';
    }
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max, 'UTF-8');
}

/* ============================ RATE LIMIT ============================ */

/**
 * Consome uma unidade da janela. Retorna true se permitido.
 * Implementação atômica com INSERT ... ON DUPLICATE KEY UPDATE.
 */
function rate_limit(string $chave, int $limite, int $janelaSegundos): bool
{
    $pdo  = db();
    $agora = time();

    $stmt = $pdo->prepare(
        'INSERT INTO rate_limites (chave, contador, janela_inicio, atualizado_em)
         VALUES (?, 1, ?, NOW())
         ON DUPLICATE KEY UPDATE
            contador = IF(janela_inicio < ?, 1, contador + 1),
            janela_inicio = IF(janela_inicio < ?, VALUES(janela_inicio), janela_inicio),
            atualizado_em = NOW()'
    );
    $stmt->execute([$chave, $agora, $agora - $janelaSegundos, $agora - $janelaSegundos]);

    $consulta = $pdo->prepare('SELECT contador FROM rate_limites WHERE chave = ?');
    $consulta->execute([$chave]);
    $contador = (int) ($consulta->fetchColumn() ?: 0);

    // Limpeza oportunista de janelas antigas (~5% das chamadas).
    if (random_int(1, 20) === 1) {
        $pdo->prepare('DELETE FROM rate_limites WHERE atualizado_em < NOW() - INTERVAL 1 DAY')->execute();
    }

    return $contador <= $limite;
}

/* ============================ AUDITORIA ============================ */

/** Registra evento sem qualquer dado pessoal. Nunca derruba a requisição. */
function auditar(string $evento, ?string $detalhe = null): void
{
    try {
        $stmt = db()->prepare('INSERT INTO auditoria (evento, detalhe, ip_hash) VALUES (?, ?, ?)');
        $stmt->execute([substr($evento, 0, 40), $detalhe !== null ? mb_substr($detalhe, 0, 300) : null, ip_hash()]);
    } catch (Throwable $e) {
        error_log('[auditoria] ' . $e->getMessage());
    }
}

/* ============================ SESSÃO / CSRF ============================ */

function iniciar_sessao(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => EM_PRODUCAO,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_name('wmj_sessao');
    session_start();

    // Expira a sessão após 8 horas de inatividade.
    if (isset($_SESSION['expira_em']) && time() > (int) $_SESSION['expira_em']) {
        encerrar_sessao();
    }
}

function sessao_autenticada(): bool
{
    return isset($_SESSION['admin_id'], $_SESSION['usuario']);
}

function exigir_login(): void
{
    if (!sessao_autenticada()) {
        header('Location: index.php?erro=sessao', true, 303);
        exit;
    }
}

function encerrar_sessao(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_valido(?string $enviado): bool
{
    return is_string($enviado)
        && !empty($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $enviado);
}

/** Bloqueia POST de origem estrangeira. */
function origem_permitida(): bool
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if ($host === '') {
        return false;
    }

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $chave) {
        if (!empty($_SERVER[$chave])) {
            $h = parse_url((string) $_SERVER[$chave], PHP_URL_HOST);
            if (!is_string($h)) {
                return false;
            }
            return strcasecmp($h, explode(':', $host)[0]) === 0;
        }
    }

    return true; // sem Origin/Referer: requisição não navegacional
}

/* ============================ VALIDAÇÃO DO CONTATO ============================ */

function assuntos_validos(): array
{
    return [
        'inventario', 'familia', 'usucapiao', 'adjudicacao', 'escrituras',
        'ata-notarial', 'protesto', 'requerimentos', 'lgpd', 'condominio',
        'negociacao', 'tributos', 'consultoria', 'cobranca', 'outro',
    ];
}

/**
 * @return array{ok:bool, erros:array<int,string>, dados?:array<string,mixed>, honeypot?:bool}
 */
function validar_contato(array $corpo): array
{
    // Campo armadilha: humano não preenche.
    if (isset($corpo['website']) && is_string($corpo['website']) && trim($corpo['website']) !== '') {
        return ['ok' => false, 'erros' => [], 'honeypot' => true];
    }

    $erros    = [];
    $nome     = texto_linha($corpo['nome'] ?? '', 120);
    $email    = mb_strtolower(texto_linha($corpo['email'] ?? '', 160), 'UTF-8');
    $telefone = texto_linha($corpo['telefone'] ?? '', 30);
    $assunto  = texto_linha($corpo['assunto'] ?? '', 40);
    $mensagem = texto_longo($corpo['mensagem'] ?? '', 4000);

    if (mb_strlen($nome, 'UTF-8') < 3) {
        $erros[] = 'Informe seu nome completo (mínimo 3 caracteres).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'Informe um e-mail válido.';
    }
    if (mb_strlen($mensagem, 'UTF-8') < 20) {
        $erros[] = 'Descreva sua solicitação com pelo menos 20 caracteres.';
    }

    $telefoneFinal = null;
    if ($telefone !== '') {
        $digitos = preg_replace('/\D/', '', $telefone) ?? '';
        $ddd     = (int) substr($digitos, 0, 2);
        if (strlen($digitos) < 10 || strlen($digitos) > 11 || $ddd < 11 || $ddd > 99) {
            $erros[] = 'Telefone inválido. Use DDD + número (10 ou 11 dígitos).';
        } else {
            $telefoneFinal = $digitos;
        }
    }

    // Assunto é opcional, mas se enviado precisa estar na lista fechada.
    // Nunca gravar NULL silenciosamente: valor desconhecido é rejeitado.
    $assuntoFinal = null;
    if ($assunto !== '') {
        if (!in_array($assunto, assuntos_validos(), true)) {
            $erros[] = 'Selecione um assunto válido da lista.';
        } else {
            $assuntoFinal = $assunto;
        }
    }

    $consentimento = $corpo['consentimento'] ?? null;
    if ($consentimento !== true && $consentimento !== 'on' && $consentimento !== 1 && $consentimento !== '1') {
        $erros[] = 'É necessário concordar com a Política de Privacidade.';
    }

    if ($erros) {
        return ['ok' => false, 'erros' => $erros];
    }

    return [
        'ok'    => true,
        'erros' => [],
        'dados' => [
            'nome'     => $nome,
            'email'    => $email,
            'telefone' => $telefoneFinal,
            'assunto'  => $assuntoFinal,
            'mensagem' => $mensagem,
        ],
    ];
}

/** Protocolo legível: WMJ-2026-XXXXXXX (sem I, O, 0, 1). */
function gerar_protocolo(): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $sufixo   = '';
    for ($i = 0; $i < 7; $i++) {
        $sufixo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
    }
    return 'WMJ-' . date('Y') . '-' . $sufixo;
}

/* ============================ RETENÇÃO (LGPD) ============================ */

/** Anonimiza um registro mantendo a prova do atendimento (art. 18, VI). */
function anonimizar(int $id): bool
{
    $stmt = db()->prepare(
        "UPDATE contatos SET
            nome = '[anonimizado]',
            email = 'anonimizado@removido.local',
            telefone = NULL,
            mensagem = '[conteúdo eliminado a pedido do titular — art. 18, VI, LGPD]',
            ip_hash = NULL,
            user_agent = NULL,
            expurgado_em = NOW()
         WHERE id = ? AND expurgado_em IS NULL"
    );
    $stmt->execute([$id]);
    return $stmt->rowCount() > 0;
}

/** Expurgo por prazo de retenção. Executado no próprio tráfego (sem cron). */
function expurgar_expirados(): int
{
    $stmt = db()->prepare(
        "UPDATE contatos SET
            nome = '[anonimizado]',
            email = 'anonimizado@removido.local',
            telefone = NULL,
            mensagem = '[conteúdo expurgado pela política de retenção]',
            ip_hash = NULL,
            user_agent = NULL,
            expurgado_em = NOW()
         WHERE expira_em < NOW() AND expurgado_em IS NULL"
    );
    $stmt->execute();
    return $stmt->rowCount();
}
