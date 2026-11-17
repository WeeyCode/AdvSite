-- =============================================================
--  Wesley Martins Jardim - Advocacia Extrajudicial
--  Banco de dados MySQL / MariaDB (XAMPP, hospedagem compartilhada)
--
--  Importe pelo phpMyAdmin ou execute:
--    mysql -u root -p < sql/schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS wmj_site
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE wmj_site;

-- -------------------------------------------------------------
-- Contatos recebidos pelo formulário
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contatos (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  protocolo      VARCHAR(24)  NOT NULL,
  nome           VARCHAR(120) NOT NULL,
  email          VARCHAR(160) NOT NULL,
  telefone       VARCHAR(20)  DEFAULT NULL,
  assunto        VARCHAR(40)  DEFAULT NULL,
  mensagem       TEXT         NOT NULL,
  status         ENUM('novo','atendido','arquivado') NOT NULL DEFAULT 'novo',
  ip_hash        CHAR(32)     DEFAULT NULL,   -- SHA-256(sal + IP), nunca o IP
  user_agent     VARCHAR(200) DEFAULT NULL,
  origem         VARCHAR(200) DEFAULT NULL,
  consentimento  VARCHAR(20)  NOT NULL DEFAULT 'lgpd',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_em      DATETIME     NOT NULL,       -- prazo de retenção (LGPD)
  expurgado_em   DATETIME     DEFAULT NULL,   -- quando foi anonimizado
  PRIMARY KEY (id),
  UNIQUE KEY uq_contatos_protocolo (protocolo),
  KEY idx_contatos_created (created_at),
  KEY idx_contatos_status (status),
  KEY idx_contatos_expira (expira_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Limitação de requisições (anti-flood / anti-força-bruta)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rate_limites (
  chave          VARCHAR(80) NOT NULL,
  contador       INT UNSIGNED NOT NULL DEFAULT 0,
  janela_inicio  INT UNSIGNED NOT NULL,
  atualizado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (chave),
  KEY idx_rate_atualizado (atualizado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Auditoria de eventos de segurança (sem dados pessoais)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auditoria (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  evento      VARCHAR(40)  NOT NULL,
  detalhe     VARCHAR(300) DEFAULT NULL,
  ip_hash     CHAR(32)     DEFAULT NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_auditoria_created (created_at),
  KEY idx_auditoria_evento (evento)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Usuário do painel administrativo
-- A senha usa password_hash() do PHP (bcrypt/argon2), nunca texto puro.
-- Gere o hash com:  php scripts/gerar-senha.php "SuaSenha"
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario     VARCHAR(64)  NOT NULL,
  senha_hash  VARCHAR(255) NOT NULL,
  criado_em   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Dados de exemplo (OPCIONAL — remover em produção)
-- -------------------------------------------------------------
-- INSERT INTO admin_users (usuario, senha_hash) VALUES
--   ('wesley', '$2y$10$SUBSTITUA_POR_HASH_GERADO');
