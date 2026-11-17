<?php
/**
 * Endpoint herdado — encerrado.
 *
 * O site público não possui formulário de contato desde 09/11/2026.
 * Este arquivo permanece apenas para responder de forma explícita a
 * qualquer chamada antiga (link, cache ou automação), sem processar,
 * gravar ou refletir nenhuma entrada.
 *
 * HTTP 410 Gone: o recurso existiu e foi removido de forma definitiva.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

http_response_code(410);

echo json_encode([
    'ok'     => false,
    'erro'   => 'Este endpoint foi descontinuado.',
    'motivo' => 'O site não possui formulário de contato. Use o e-mail ou o WhatsApp informados na página inicial.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
