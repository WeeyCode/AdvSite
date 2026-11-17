# Wesley Martins Jardim — Advocacia Extrajudicial
## Versão HTML + CSS + PHP + MySQL (XAMPP / hospedagem compartilhada)

Esta é a versão **estática + PHP/MySQL** do site. O visual (cores, tipografia, formas,
espaçamentos) é **idêntico** ao da versão anterior: tema claro creme + grafite + dourado.

Diferente da versão Cloudflare Workers, esta versão **roda em qualquer Apache com PHP 7.4+
e MySQL/MariaDB** — inclusive no XAMPP, na sua máquina, sem conta em nuvem.

---

## 1. Requisitos

| Item | Versão mínima |
|---|---|
| PHP | 7.4 (testado em 8.4) |
| Extensões PHP | `pdo_mysql`, `mbstring`, `json`, `openssl`, `session` |
| MySQL / MariaDB | 5.7 / 10.3 (testado em MariaDB 11.8) |
| Apache | 2.4 com `mod_headers`, `mod_rewrite`, `mod_deflate`, `mod_expires` |

No XAMPP tudo isso já vem ativo por padrão. Nada a instalar.

---

## 2. Instalação no XAMPP (passo a passo)

### 2.1 Copiar os arquivos
Descompacte esta pasta em:

```
C:\xampp\htdocs\wmj-site\
```

Resultado esperado:
```
C:\xampp\htdocs\wmj-site\index.html
C:\xampp\htdocs\wmj-site\politica-privacidade.html
C:\xampp\htdocs\wmj-site\admin\index.php
C:\xampp\htdocs\wmj-site\api\contato.php
...
```

### 2.2 Criar o banco de dados
1. Abra o **XAMPP Control Panel** e inicie **Apache** e **MySQL**.
2. Acesse `http://localhost/phpmyadmin`.
3. Aba **Importar** → selecione `sql/schema.sql` → **Executar**.

O arquivo cria o banco `wmj_site` e as 4 tabelas:
- `contatos` — tabela legada do formulário (desativada; mantida para histórico)
- `rate_limites` — controle de requisições
- `auditoria` — trilha de auditoria
- `admin_users` — acesso ao painel

### 2.3 Criar o usuário administrador
Abra o **Prompt de Comando** e rode:

```
C:\xampp\php\php.exe C:\xampp\htdocs\wmj-site\scripts\gerar-senha.php "SuaSenhaForteAqui"
```

O script imprime um `INSERT` pronto. Copie e execute no phpMyAdmin (aba **SQL**).
Exemplo de saída:

```sql
INSERT INTO admin_users (usuario, senha_hash)
VALUES ('wesley', '$2y$12$....hash.gerado....');
```

> A senha **nunca** é gravada em texto puro — apenas o hash bcrypt (custo 12).

### 2.4 Configurar o site
Edite **apenas** `inc/config.php`:

```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'wmj_site';
const DB_USER = 'root';
const DB_PASS = '';          // XAMPP padrão = vazio

const IP_SALT = '...';       // troque por um valor novo (comando abaixo)
```

Gere um `IP_SALT` próprio:

```
C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(16));"
```

Cole o resultado em `IP_SALT`. Esse valor serve para anonimizar IPs
(nenhum IP é gravado em texto claro — só um hash irreversível).

### 2.5 Acessar

| Página | URL |
|---|---|
| Site | `http://localhost/wmj-site/` |
| Política de Privacidade | `http://localhost/wmj-site/politica-privacidade.html` |
| Termos de Uso | `http://localhost/wmj-site/termos-uso.html` |
| Segurança da Informação | `http://localhost/wmj-site/seguranca.html` |
| Painel administrativo | `http://localhost/wmj-site/admin/` |

---

## 3. Estrutura de arquivos

```
wmj-site/
├── index.html                   Página inicial
├── politica-privacidade.html    LGPD
├── termos-uso.html              Termos
├── seguranca.html               Segurança da informação
├── erro.html                    Página 403/404
├── .htaccess                    CSP + cabeçalhos + bloqueios
│
├── static/
│   ├── site.css                 Todo o estilo (tema claro)
│   ├── site.js                  Menu, animação, aviso de cookie
│   ├── favicon.svg
│   ├── fonts/                   7 fontes locais (.woff2) — sem CDN
│   └── vendor/fa/               Font Awesome local
│
├── api/
│   └── contato.php              410 Gone — endpoint desativado
│
├── admin/
│   ├── index.php                Controlador (login, painel, ações)
│   ├── login.view.php           Tela de login
│   └── painel.view.php          Painel de mensagens
│
├── inc/
│   ├── config.php               ⚠ EDITE AQUI
│   └── functions.php            PDO, validação, CSRF, rate limit
│
├── sql/
│   └── schema.sql               Estrutura do banco
│
└── scripts/
    └── gerar-senha.php          Gerador de hash bcrypt
```

---

## 4. O que o painel faz

Acesse `admin/` e entre com o usuário criado no passo 2.3.

- **Métricas**: total, novos, atendidos, arquivados
- **Filtros**: por status e busca textual (nome, e-mail, protocolo, mensagem)
- **Ações por mensagem**: marcar como *novo*, *atendido*, *arquivado* ou **anonimizar** (LGPD art. 18, VI)
- **Exportar**: CSV (abre no Excel com acentuação correta) ou JSON
- **Auditoria**: últimas 100 operações com data e hash de IP

Sessão expira em **8 horas**. O botão *Sair* encerra a sessão imediatamente.

---

## 5. Segurança implementada

| Proteção | Como |
|---|---|
| SQL Injection | PDO com prepared statements reais (`EMULATE_PREPARES = false`) |
| XSS | Escape de saída com `htmlspecialchars` |
| CSRF | Token de sessão comparado com `hash_equals()` |
| Senhas | `password_hash()` bcrypt custo 12 |
| Força bruta | 6 tentativas de login / 15 min por origem |
| Superfície de ataque | Site público sem formulário nem `POST` — nada a injetar ou spammar |
| Origem cruzada | Validação de `Origin`/`Referer` contra o host |
| CSP | Sem `unsafe-inline`; o JSON-LD é liberado por hash sha256 |
| Clickjacking | `X-Frame-Options: DENY` + `frame-ancestors 'none'` |
| MIME sniffing | `X-Content-Type-Options: nosniff` |
| Sessão | Cookie `HttpOnly`, `SameSite=Strict`, `Secure` sob HTTPS |
| Arquivos sensíveis | `inc/`, `sql/`, `scripts/` bloqueados via `.htaccess` |
| Cache do painel | `Cache-Control: no-store` em todo `.php` |
| IP | Nunca gravado em claro — só hash com `IP_SALT` |
| LGPD | Retenção de 24 meses com expurgo automático |

---

## 6. LGPD — como funciona na prática

- O site público **não coleta dados**: não há formulário, cadastro nem campo de envio.
- O primeiro contato é feito pelo visitante por e-mail ou WhatsApp, fora do site.
- Retenção dos registros técnicos de segurança: **até 24 meses**. Ao acessar o painel,
  registros vencidos são expurgados automaticamente — não precisa de cron.
- **Anonimizar** substitui nome, e-mail, telefone e mensagem por marcadores,
  preservando a prova de que o atendimento existiu (art. 18, VI).
- Nenhum cookie de publicidade ou analytics é usado.

---

## 7. Publicar em hospedagem compartilhada

1. Envie todo o conteúdo de `wmj-site/` para `public_html/` (via FTP ou Gerenciador de Arquivos).
2. No cPanel, crie o banco e o usuário MySQL, e associe-os com **todos os privilégios**.
3. Importe `sql/schema.sql` pelo phpMyAdmin da hospedagem.
4. Ajuste `inc/config.php` com host, nome do banco, usuário e senha fornecidos pela hospedagem.
5. Ative o **SSL (Let's Encrypt)** no cPanel.
6. Depois do SSL ativo, descomente no `.htaccess`:
   ```
   Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
   ```
7. Crie o usuário admin rodando `scripts/gerar-senha.php` **localmente** e executando
   o SQL no phpMyAdmin da hospedagem.
8. **Apague** a pasta `scripts/` do servidor após criar o usuário (ela já está
   bloqueada pelo `.htaccess`, mas remover é mais seguro).

---

## 8. Problemas comuns

**Página em branco ou erro 500**
Verifique se as credenciais em `inc/config.php` conferem com o banco.
Em `http://localhost/` o PHP exibe o erro na tela; em produção ele fica oculto por segurança.

**"Não foi possível conectar ao banco"**
O MySQL do XAMPP está rodando? Confira no Control Panel. Porta padrão: 3306.

**`api/contato.php` responde 410**
Comportamento esperado. O formulário foi retirado em 09/11/2026 e o endpoint apenas
informa que foi descontinuado. Nada é gravado.

**Ícones ou fontes não aparecem**
Confirme que a pasta `static/` foi enviada completa, incluindo `fonts/` e `vendor/fa/webfonts/`.

### 8.1 Alterar os textos

É HTML puro e direto. Abra o arquivo `.html` e edite — não há template engine
nem build. Evite inserir `style="..."` inline: a CSP bloqueia estilos inline
de propósito. Use as classes já existentes em `static/site.css`.

---

## 9. Conformidade OAB

Conteúdo estritamente informativo, em conformidade com o **Provimento 205/2021** e o
**Código de Ética e Disciplina (Res. CFOAB 02/2015)**:

- sem promessa de resultado
- sem divulgação de honorários
- sem captação de clientela
- sem sensacionalismo ou mercantilização
- identificação profissional com nome e número de inscrição

---

**Wesley Martins Jardim — OAB/SP 518.369**
wesley.martins.jardim@adv.oabsp.org.br · (12) 99213-6428
