<?php
/**
 * Gera o hash de senha para a tabela admin_users.
 *
 * Uso (terminal / prompt de comando):
 *   php scripts/gerar-senha.php "SuaSenhaForte!"
 *
 * Copie o valor de senha_hash e rode no MySQL:
 *   INSERT INTO admin_users (usuario, senha_hash) VALUES ('wesley', 'COLE_O_HASH');
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script só pode ser executado pelo terminal.');
}

$senha = $argv[1] ?? null;

if ($senha === null || $senha === '') {
    fwrite(STDERR, "Uso: php scripts/gerar-senha.php \"SuaSenhaForte!\"\n");
    exit(1);
}

if (strlen($senha) < 10) {
    fwrite(STDERR, "Aviso: use ao menos 10 caracteres, com letras, números e símbolos.\n");
}

$hash = password_hash($senha, PASSWORD_DEFAULT);

echo "\n=== Hash gerado (password_hash / bcrypt) ===\n";
echo $hash . "\n\n";
echo "=== SQL pronto para executar ===\n";
echo "INSERT INTO admin_users (usuario, senha_hash)\n";
echo "VALUES ('wesley', '" . $hash . "');\n\n";
echo "Se o usuário já existir, atualize a senha:\n";
echo "UPDATE admin_users SET senha_hash = '" . $hash . "' WHERE usuario = 'wesley';\n\n";
echo "Algoritmo usado: " . password_get_info($hash)['algoName'] . "\n";
