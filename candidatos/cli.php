<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/core.php';
try {
    switch ($argv[1] ?? 'help') {
        case 'migrate': migrate(); echo "Migrações verificadas/aplicadas.\n"; break;
        case 'admin':
            migrate();
            $email=strtolower(trim($argv[2]??'')); $name=trim($argv[3]??'Administrador');
            if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Informe e-mail válido.');
            $token=atomic(function () use ($email,$name) {
                if (query('SELECT COUNT(*) FROM users')->fetchColumn()) throw new DomainException('Inicialização disponível apenas antes do primeiro usuário.');
                query("INSERT INTO users(email,name,role) VALUES(?,?,'admin')",[$email,$name]);
                $id=(int)db()->lastInsertId(); event($id,'admin_created',[]); return issueToken($id,'invite');
            });
            echo 'Abra em até 48h: /candidatos/?token='.$token."\n"; break;
        case 'import': migrate(); echo jsonValue(importCsv($argv[2]??''))."\n"; break;
        case 'backup': echo backup()."\n"; break;
        case 'check':
            echo 'Integridade: '.query('PRAGMA integrity_check')->fetchColumn()."\n";
            echo 'Erros de chave estrangeira: '.count(query('PRAGMA foreign_key_check')->fetchAll())."\n";
            foreach (['users','candidates','assignments','evaluations','events'] as $table) echo $table.': '.query("SELECT COUNT(*) FROM $table")->fetchColumn()."\n";
            break;
        default: echo "Comandos: migrate | admin EMAIL NOME | import ARQUIVO.csv | backup | check\n";
    }
} catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n"); exit(1); }
