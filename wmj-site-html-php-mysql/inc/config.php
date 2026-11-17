<?php
/**
 * Configuração central do site.
 *
 * EDITE APENAS ESTE ARQUIVO ao instalar no XAMPP ou em hospedagem.
 * Nunca versione este arquivo com dados reais de produção.
 */

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

/* ---------------- BANCO DE DADOS (MySQL / MariaDB) ---------------- */
const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'wmj_site';
const DB_USER = 'root';
const DB_PASS = '';          // XAMPP padrão: senha vazia. Em produção, defina uma senha forte.

/* ---------------- SEGREDOS ---------------- */
// Gere um valor novo e aleatório para cada ambiente:
//   php -r "echo bin2hex(random_bytes(16));"
const IP_SALT = 'f89f5a85f139322031698f33644fbf2d';

/* ---------------- DADOS DO ESCRITÓRIO ---------------- */
const SITE_NOME   = 'Wesley Martins Jardim';
const SITE_OAB    = 'OAB/SP 518.369';
const SITE_EMAIL  = 'wesley.martins.jardim@adv.oabsp.org.br';
const SITE_WHATS  = '5512992136428';
const SITE_WHATS_EXIBIR = '(12) 99213-6428';

/* ---------------- POLÍTICA DE RETENÇÃO (LGPD) ---------------- */
const RETENCAO_MESES = 24;

/* ---------------- LIMITES DE REQUISIÇÃO ---------------- */
const LIMITE_CONTATO_JANELA   = 5;    // envios por origem
const LIMITE_CONTATO_GLOBAL   = 60;   // envios totais
const LIMITE_JANELA_SEGUNDOS  = 600;  // 10 minutos
const LIMITE_LOGIN            = 6;    // tentativas de login por origem
const LIMITE_LOGIN_SEGUNDOS   = 900;  // 15 minutos

/* ---------------- AMBIENTE ---------------- */

/**
 * Atrás de proxy/CDN (Cloudflare, proxy reverso, alguns painéis de hospedagem)
 * o Apache recebe a conexão em HTTP e o TLS termina no proxy. Nesse caso
 * $_SERVER['HTTPS'] vem vazio e o cookie de sessão sairia SEM a flag Secure.
 *
 * Deixe false em hospedagem direta (XAMPP, cPanel comum).
 * Ligue apenas se o site estiver atrás de um proxy que envia X-Forwarded-Proto.
 */
const PROXY_CONFIAVEL = false;

define('EM_PRODUCAO', (static function (): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (PROXY_CONFIAVEL) {
        $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        return strtolower((string) $proto) === 'https';
    }
    return false;
})());

/* ---------------- ERROS ---------------- */
if (EM_PRODUCAO) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
} else {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
