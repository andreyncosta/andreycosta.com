# Candidatos — primeira versão funcional

Aplicação em PHP 8.2+ e SQLite, sem framework e sem dependências de frontend. Acesso em `/candidatos/`. Requer PHP com PDO SQLite, SQLite 3.27+ (incluindo funções JSON) e iconv; Apache com `.htaccess` habilitado na hospedagem. O servidor embutido do PHP serve apenas para testes locais.

## Funcionalidades

- Contas por convite e recuperação de senha administrada, com links de uso único válidos por 48 horas. O administrador entrega o link ao e-mail cadastrado; não há envio automático de e-mail nesta versão.
- Administrador inicializado por CLI, sem instalador público e sem senha padrão.
- Suspensão de acesso independente da atribuição de trabalho; invalidação de sessões ao alterar acesso/senha.
- Tabela com ano, UF, partido, cargo, nome, Ideológico e Perfil. Filtros e paginação; cartões no celular.
- Seletores sem valor padrão, rascunhos parciais, conclusão e devolução para correção.
- Lote fixo, completar carga, retomada para fila e redistribuição equilibrada com prévia.
- Histórico por candidatura, acompanhamento por revisor, exportação CSV e download de backup integral.
- Importação idempotente, transações, controle de versões, restrição de uma atribuição ativa e registro imutável de avaliações/eventos.

## Decisões para esta versão

- Administradores também podem receber lotes, classificar suas candidaturas e participar de redistribuições. O atalho Minhas candidaturas filtra seu trabalho; as permissões de gestão são mantidas. A suspensão de acesso pelo painel continua restrita a contas de revisor.

- Carga significa todas as pendências do revisor, em todos os recortes.
- No lote fixo, distribuição alternada até X por pessoa. Se faltarem candidatos, diferenças de quantidade serão no máximo uma unidade.
- Completar carga e transferir priorizam a menor carga total; empate pelo ID do revisor.
- Candidaturas ordenadas por UF, nome e ID. A prévia inclui o recorte e é revalidada na confirmação.
- O novo responsável começa sem respostas preenchidas, mas pode consultar versões anteriores em Histórico. A autoria antiga nunca muda.
- Concluídas não entram na retomada. Para revisar novamente, o administrador primeiro devolve para correção, preservando todas as versões.
- Consulta e classificações exigem login. Não há resultados públicos nem dupla revisão independente.
- “Não consigo avaliar” fica para evolução posterior. Nesta versão, o revisor pode manter rascunho e pedir ao administrador para retomar a atribuição.
- O administrador deve escrever o critério de Ideológico em Configuração. Até isso ocorrer, conclusões são bloqueadas; rascunhos continuam disponíveis.

## Instalação na Hostinger

Não publicar o repositório inteiro na pasta pública. Enviar somente a pasta `candidatos/` do pacote gerado por `tools/package-candidatos.ps1`. O pacote contém código e migrações, nunca banco, credenciais ou planilhas.

Exemplo de estrutura:

```text
/home/CONTA/domains/andreycosta.com/
  public_html/
    candidatos/                 # somente código
  candidatos-private/
    candidatos.sqlite           # estado persistente
    candidatos.sqlite-wal       # arquivos gerenciados pelo SQLite
    candidatos.sqlite-shm
    backups/
```

Por padrão, a pasta privada é criada ao lado da raiz do site. É possível definir `CANDIDATOS_DATA_DIR` com outro caminho absoluto privado. O processo PHP deve poder gravar nessa pasta. Não colocar os dados dentro de `public_html` ou dentro do repositório. Para instalações com diretórios/symlinks diferentes, configure o caminho explicitamente.

Com SSH, a partir de `public_html`:

```sh
php candidatos/cli.php migrate
php candidatos/cli.php admin seu-email@dominio.com "Seu nome"
php candidatos/cli.php import /caminho/privado/consulta_cand_2022_BRASIL.csv
php candidatos/cli.php import /caminho/privado/consulta_cand_2026_BRASIL.csv
php candidatos/cli.php check
```

O comando `admin` só funciona com a tabela de usuários vazia. Abra o link retornado no domínio correto e defina a senha. Esse link é uma credencial temporária: não o publique nem o adicione ao Git.

Se o PHP de linha de comando for diferente do PHP do site, use o executável da versão configurada na hospedagem. Confirme que ambos têm PDO SQLite. Sem SSH, será necessário executar os mesmos comandos por uma tarefa do painel ou preparar a inicialização por outro meio privado; não há instalador web nesta versão.

Depois de entrar:

1. Defina o critério de Ideológico em Configuração.
2. Crie convites em Revisores e entregue cada link exclusivamente ao destinatário.
3. Aguarde a definição de senha; só revisores ativos que aceitaram o convite entram na distribuição.
4. Selecione ano, cargo, UFs e revisores em Distribuição; confira a prévia e confirme.

O `.htaccess` da aplicação define `index.php` como índice e bloqueia acesso HTTP aos arquivos internos/CLI/SQL. Verifique em produção que `/candidatos/core.php`, `/candidatos/cli.php` e `/candidatos/migrations/001.sql` respondem 403. Em Nginx ou outro servidor, configure bloqueios equivalentes antes de publicar.

O workflow legado `.github/deploy.yml` está fora de `.github/workflows/` e referencia `public_html/`, que não existe neste checkout. Ele não foi usado nem alterado. A nova automação `candidatos.yml` só valida o código, sem publicar. A integração ao deploy deverá ser ajustada após confirmar a configuração real da hospedagem.

## Preservação de dados e futuras versões

Não é possível prometer perda zero diante de falha de disco ou perda da conta. A aplicação evita perda por atualizações normais; proteção contra desastre depende de backups externos e restaurações verificadas.

Regras obrigatórias de evolução:

1. Nunca substituir o banco de produção por banco de desenvolvimento ou por um banco inicial.
2. Nunca guardar dados na pasta de código nem incluí-los em sincronização FTP ou limpeza de deploy.
3. Nunca editar uma migração aplicada. Criar `002.sql`, `003.sql` etc. Os checksums são verificados; código e banco incompatíveis não são servidos.
4. Usar migrações aditivas. Não executar `DROP`, apagar histórico ou recriar tabelas com perda de registros. Transformações futuras exigem cópia preservada, validação e migração ensaiada.
5. Toda nova migração recebe um backup consistente antes de executar e roda dentro de transação. Uma falha provoca rollback.
6. Reimportações usam a chave ano + eleição + identificador de origem. Só atualizam os campos de cadastro; não apagam candidaturas ausentes no arquivo novo. Alterações de cadastro preservam os valores anteriores no histórico.
7. Cada salvamento cria nova versão da avaliação; não atualiza a versão anterior. Retomadas encerram atribuições, sem apagá-las.

### Atualização de código

1. Colocar a aplicação em manutenção, impedindo novos acessos e gravações.
2. Executar `php candidatos/cli.php backup` e copiar o arquivo resultante para local privado fora da hospedagem.
3. Ensaiar migrações e testes sobre uma cópia do backup.
4. Atualizar apenas os arquivos de código.
5. Executar `php candidatos/cli.php migrate` e `php candidatos/cli.php check`.
6. Validar login, consulta e salvamento antes de reabrir o acesso.

Se a migração falhar, ela é revertida. Não restaurar automaticamente um backup antigo sobre um banco em uso: isso descartaria respostas mais recentes. Não fazer rollback cego do código após migração; a checagem de versão impede essa combinação incompatível.

### Backup e recuperação

```sh
php candidatos/cli.php backup
php candidatos/cli.php check
```

O backup usa `VACUUM INTO`, produz uma cópia consistente, executa `integrity_check` e grava SHA-256 ao lado. Pode ser baixado também pelo painel administrativo. [Documentação oficial do PHP/SQLite](https://www.php.net/manual/en/sqlite3.backup.php).

Configure uma tarefa agendada diária para `backup`, e uma cópia privada externa desses arquivos. Considere frequência maior durante revisão intensiva: a frequência de cópia externa limita a perda possível em um desastre completo. Não há exclusão automática de backups; monitore espaço e mantenha uma política de retenção externa.

Para restaurar:

1. Interromper todo acesso e todos os processos PHP que usam o banco.
2. Preservar a pasta privada atual inteira em outro local, inclusive WAL/SHM. Nunca apagar o banco atual como primeiro passo.
3. Validar o SHA-256 do backup e abri-lo em SQLite para `PRAGMA integrity_check` e `PRAGMA foreign_key_check`.
4. Criar uma nova pasta privada e copiar o backup para `candidatos.sqlite`. Não reutilizar arquivos WAL/SHM antigos junto ao banco restaurado.
5. Apontar `CANDIDATOS_DATA_DIR` para essa pasta e usar código compatível com as migrações do backup.
6. Conferir usuários, candidaturas, atribuições, avaliações e eventos com `check`; fazer teste funcional antes de reabrir.
7. Manter a pasta anterior para reconciliação de registros posteriores à data do backup.

Exportação CSV é uma conveniência de análise, não substitui backup integral.

## Testes

```sh
php candidatos/tests.php
```

Os testes criam um banco temporário isolado e preservam o resultado para inspeção. Cobrem reimportação, nulos, conflito de edição, suspensão, retomada, transferência, conclusão, devolução, token de uso único, imutabilidade, distribuição com processos simultâneos e restauração de backup.

O script `tools/test-candidatos-http.ps1` testa formulários contra um servidor local usando **somente o banco temporário criado pelos testes**, com as contas fictícias deles. Nunca executar contra produção.

Para iniciar localmente, defina `CANDIDATOS_DATA_DIR` para o banco de teste e execute `php -S 127.0.0.1:8097 -t .` na raiz do repositório. Depois rode o script PowerShell. O servidor embutido não aplica `.htaccess`; não o exponha na rede.
