# Eixo — produto mínimo viável

Registro pessoal de saúde: exames de sangue, bioimpedância e medicações numa linha do tempo, com extração por IA em leitura dupla. PHP 8.2+ e SQLite, no mesmo padrão do `/candidatos/`. Requisitos completos no documento “Eixo — Requisitos e documentação inicial” (20/10/2026).

**Princípio:** a IA lê duas vezes, o código confere e calcula, o médico decide.

## Antes de abrir para usuários reais

O código está pronto para um piloto fechado, por convite. O próprio documento de requisitos pede o que segue antes de receber dados de saúde de terceiros. Nada disso é código:

- [ ] Contrato **sem retenção e sem treinamento** com a Anthropic (zero data retention, pedido à Anthropic) e com o Google (Gemini pago; o Eixo já manda `store: false`). RNF-06.
- [ ] Cláusulas-padrão da ANPD para a transferência internacional (Resolução CD/ANPD 19/2024).
- [ ] RIPD e encarregado nomeado (RNF-18); preencher `controller_*` e `dpo_*` no `config.php`.
- [ ] Revisão do texto de privacidade (`?r=privacy`) e dos consentimentos por advogado.
- [ ] Teste de intrusão independente (RNF-04).
- [ ] Consulta formal de enquadramento à Anvisa (fronteira SaMD).
- [ ] `php eixo/cli.php eval 20 --yes` com acerto ≥ 98% e **zero** valor errado carregado (RNF-10).
- [ ] Cópia externa diária do backup, de `blobs/` e da `master.key`, com restauração testada.

## Instalação na Hostinger (SSH)

O deploy pelo Git publica o código em `public_html/eixo/`. Dados, chaves e sessões ficam fora do site, em `~/eixo-private/` (ou no caminho de `EIXO_DATA_DIR`).

```bash
cd ~/public_html/eixo
composer install --no-dev --optimize-autoloader   # SDK da Anthropic + Guzzle (não vão para o git)
php cli.php init                                  # cria ~/eixo-private, master.key, config.php e o banco
nano ~/eixo-private/config.php                    # chaves de API, controlador, encarregado, e-mail
php cli.php check                                 # tudo "ok" antes de convidar alguém
```

Cron no painel (a cada minuto), para a fila de leitura, e um diário para backup e expurgo:

```
* * * * *  /usr/bin/php ~/public_html/eixo/cli.php work 50 >/dev/null 2>&1
15 3 * * * /usr/bin/php ~/public_html/eixo/cli.php backup && /usr/bin/php ~/public_html/eixo/cli.php purge
```

**Na Hostinger, na prática.** O cron do painel não aceita `cd`, `&&` nem redirecionamentos, e o PATH dele não inclui o `composer`. Use um comando por cron, sempre com caminhos absolutos: `/usr/bin/php /usr/local/bin/composer --working-dir=<eixo> install --no-dev -o -n` uma vez, depois `/usr/bin/php <eixo>/cli.php setup` uma vez, e um cron separado para cada um de `work 50`, `backup` e `purge`. Para gerar um convite sem SSH, crie um cron num minuto fixo (`49 * * * *`), leia a saída e apague o cron. A extensão `sodium` vem desligada: ligue-a em hPanel → PHP → Extensões. `shell_exec` é bloqueado; o `pdftotext` roda por `proc_open` e, sem o binário, a leitura vai por imagem.

Use o PHP da mesma versão do site (`php -v`); ele precisa de `sodium`, `pdo_sqlite`, `curl`, `zip` e `fileinfo`. O `pdftotext` (poppler) dá a camada de texto dos PDFs; sem ele, as duas leituras vão por imagem e a checagem fica mais fraca (`cli.php check` avisa). Se o binário estiver em outro caminho, ajuste `pdftotext` no `config.php`.

**Convites.** Não há cadastro aberto. Cada paciente entra por convite de uso único, válido por 48 h:

```bash
php cli.php invite pessoa@exemplo.com          # imprime o link
php cli.php invite pessoa@exemplo.com --send   # e envia por e-mail (mail_from no config.php)
```

O mesmo comando serve para quem perdeu a senha ou o celular do autenticador: o convite refaz a senha e o segundo fator.

Verifique em produção que `/eixo/src/core.php`, `/eixo/cli.php`, `/eixo/vendor/` e `/eixo/migrations/001.sql` respondem 403 ou 404.

## Como funciona

| Etapa | Onde | O quê |
| --- | --- | --- |
| Envio | `index.php?r=upload` | PDF, JPG, PNG ou WebP, vários por vez; duplicata por SHA-256; arquivo cifrado em `blobs/` |
| Leitura A | `src/extraction.php` | Claude (`claude_model`), pela camada de texto do PDF, com nome, CPF, pedido e médico removidos; sem camada de texto, pelo arquivo |
| Leitura B | idem | Gemini (`gemini_model`), pelo arquivo (imagem da página) |
| Desempate | idem | Só quando A e B discordam: Claude pelo arquivo; precisa coincidir com uma das duas |
| Checagem cruzada | `cross_check()` | Valor no texto da página; unidade compatível com a propriedade LOINC; ordem de grandeza coerente com a faixa impressa e com o histórico no mesmo laboratório; data válida |
| Carga | `persist_outcome()` | Entra na série só o que passa; o resto vai para a quarentena, com link para o laudo |
| Séries | `series_key()` | Junta só com mesmo LOINC, material e propriedade (as duas leituras concordando, confiança ≥ 0,7); senão, nome + laboratório + unidade. O paciente junta ou separa |
| Unidades | `src/ucum.php` | Conversão por álgebra UCUM e só com a mesma propriedade: % ↔ mmol/mol e ng/mL ↔ nmol/L nunca convertem |

Cada valor guarda página, trecho do laudo, as duas leituras, a resolução, as checagens e as versões de modelo, instrução e biblioteca de unidades (RNF-09).

**Cifragem.** Uma chave mestra (`master.key`, 0600) cifra uma chave por usuário, e esta cifra documentos e todo campo de saúde (`*_enc`). Nas colunas abertas ficam só ids, datas, estados e hashes com chave. Excluir a conta destrói a chave do usuário. A chave mestra está no mesmo servidor, então isso protege cópias do banco e backups, não um servidor comprometido. Para isso, ver “Limites”.

**Médico.** O paciente cria um compartilhamento (escopo tudo ou período, validade, revogação imediata) e recebe um link e um código de 8 dígitos. O código vale uma vez e por 30 minutos; para um novo acesso, o paciente gera outro código. A sessão do médico expira após 30 minutos sem uso. Todo acesso aparece na trilha de auditoria do paciente.

## Testes

```bash
php eixo/tests.php                # 48 checagens, sem rede: unidades, TOTP, cifragem, esteira com leitores falsos,
                                  # desempate, quarentena, duplicata, séries, escopo de compartilhamento, exclusão
php eixo/cli.php eval 10 --yes    # laudos sintéticos pelos modelos reais (custa dinheiro); registra o acerto
                                  # que aparece na ficha de transparência (?r=ai)
```

## Limites desta versão

- Proveniência por página e trecho citado, não por região desenhada na imagem.
- Fotos não têm camada de texto: a minimização de identificadores e a checagem “valor no texto” valem só para PDFs com texto.
- LOINC proposto pelo modelo, sem tabela LOINC local; a confiança e a concordância entre leituras decidem a junção.
- Sem busca em base de medicamentos registrados (RF-19); o cadastro é livre.
- Sem envio de laudos por e-mail (RF-09), registro de adesão (RF-23) nem fila em servidor dedicado.
- Chave mestra no servidor da hospedagem, sem serviço gerenciado de chaves (RNF-01 pede um).
- Hospedagem compartilhada, sem garantia de região Brasil para a Hostinger (RNF-08) nem SLA de 99,5% (RNF-14).
- Interface só em português; a página do projeto no site é bilíngue.
