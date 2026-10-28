# Eixo — produto mínimo viável

Registro pessoal de saúde: exames de sangue, bioimpedância e medicações numa linha do tempo, com extração por IA em leitura dupla. PHP 8.2+ e SQLite, no mesmo padrão do `/candidatos/`. Requisitos completos no documento “Eixo — Requisitos e documentação inicial” (20/10/2026).

**Princípio:** a IA lê duas vezes, o código confere e calcula, o médico decide.

## Antes de abrir para usuários reais

O código está pronto para um piloto fechado, por convite. O próprio documento de requisitos pede o que segue antes de receber dados de saúde de terceiros. Nada disso é código:

- [ ] Contrato **sem retenção e sem treinamento** com a Replicate, o único fornecedor de IA do Eixo. Os dois leitores são modelos de peso aberto que rodam na infraestrutura da própria Replicate, então não há provedor a montante. RNF-06.
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
composer update --no-dev --optimize-autoloader    # uma vez: o lock ainda lista o SDK da Anthropic, hoje sem uso
php cli.php init                                  # cria ~/eixo-private, master.key, config.php e o banco
nano ~/eixo-private/config.php                    # chaves de API, controlador, encarregado, e-mail
php cli.php check                                 # tudo "ok" antes de convidar alguém
```

Cron no painel (a cada minuto), para a fila de leitura, e um diário para backup e expurgo:

```
* * * * *  /usr/bin/php ~/public_html/eixo/cli.php work 50 >/dev/null 2>&1
15 3 * * * /usr/bin/php ~/public_html/eixo/cli.php backup && /usr/bin/php ~/public_html/eixo/cli.php purge
```

**Na Hostinger, na prática.** O cron do painel não aceita `cd`, `&&` nem redirecionamentos, e o PATH dele não inclui o `composer`. Use um comando por cron, sempre com caminhos absolutos: `/usr/bin/php /usr/local/bin/composer --working-dir=<eixo> install --no-dev -o -n` uma vez, depois `/usr/bin/php <eixo>/cli.php setup` uma vez, e um cron separado para cada um de `work 50`, `backup` e `purge`. Para gerar um convite sem SSH, crie um cron num minuto fixo (`49 * * * *`), leia a saída e apague o cron. A extensão `sodium` vem desligada: ligue-a em hPanel → PHP → Extensões. `shell_exec` é bloqueado; o `pdftotext` roda por `proc_open` e, sem o binário, a camada de texto sai do `smalot/pdfparser`, em PHP puro.

Use o PHP da mesma versão do site (`php -v`); ele precisa de `sodium`, `pdo_sqlite`, `curl`, `zip` e `fileinfo`. O `pdftotext` (poppler) dá a camada de texto dos PDFs; sem ele, o `smalot/pdfparser` faz o mesmo em PHP puro, com resultado mais pobre em PDF complexo (`cli.php check` avisa). Se o binário estiver em outro caminho, ajuste `pdftotext` no `config.php`. A camada de texto é a única entrada dos leitores: ver “Documentos sem camada de texto”.

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
| Camada de texto | `pdf_pages_text()` | `pdftotext`, ou `smalot/pdfparser` quando o binário falta. Sem camada de texto o documento não é lido (ver abaixo) |
| Leitura A | `src/extraction.php` | `replicate_model_a`, pela camada de texto do documento, em grupos de páginas, com nome, CPF, pedido e médico removidos |
| Leitura B | idem | `replicate_model_b`, um segundo modelo, pela mesma camada de texto |
| Desempate | idem | Só quando A e B discordam: `replicate_tiebreak_model`; precisa coincidir com uma das duas |
| Checagem cruzada | `cross_check()` | Valor no texto da página; unidade compatível com a propriedade LOINC; ordem de grandeza coerente com a faixa impressa e com o histórico no mesmo laboratório; data válida |
| Carga | `persist_outcome()` | Entra na série só o que passa; o resto vai para a quarentena, com link para o laudo |
| Séries | `series_key()` | Junta só com mesmo LOINC, material e propriedade (as duas leituras concordando, confiança ≥ 0,7); senão, nome + laboratório + unidade. O paciente junta ou separa |
| Unidades | `src/ucum.php` | Conversão por álgebra UCUM e só com a mesma propriedade: % ↔ mmol/mol e ng/mL ↔ nmol/L nunca convertem |

Cada valor guarda página, trecho do laudo, as duas leituras, a resolução, as checagens e as versões de modelo, instrução e biblioteca de unidades (RNF-09).

**Documentos sem camada de texto.** Os modelos usados na Replicate leem texto, e não imagem. Um PDF digitalizado ou uma foto de laudo não tem camada de texto: o arquivo é guardado e cifrado como qualquer outro, mas nenhum valor é extraído e o documento termina com `no_text_layer` em `cli.php diag`. Para voltar a ler esses arquivos é preciso um estágio de OCR antes dos leitores, que ainda não existe.

**Um laudo longo não cabe numa corrida do cron.** O cron chama `cli.php work` a cada minuto e mata o trabalhador em trinta minutos. Um laudo de 45 páginas são 9 grupos por leitor, o que passa disso, e sem guardar nada o trabalho recomeçaria do zero a cada morte, sem nunca terminar e pagando de novo pelo que já tinha sido lido. Por isso cada grupo lido vai para `page_group_readings`, cifrado com a chave do paciente como qualquer outro conteúdo de saúde, e a corrida seguinte continua de onde parou. As linhas somem assim que o documento sai da fila, em `forget_group_readings()`, tanto no sucesso quanto na falha (RNF-07). A leitura de desempate não é guardada, porque vai com uma instrução a mais e não é a mesma leitura.

**Independência das duas leituras.** A RN-02 supõe que A e B erram de maneiras diferentes. Hoje os dois são da mesma família (`gpt-oss-120b` e `gpt-oss-20b`), porque foram os únicos modelos de peso aberto da Replicate que leram um laudo de 5 páginas em menos de dois minutos e devolveram o esquema completo: o `llama-4-maverick` estourou o teto de tokens, o `qwen3-235b` e o `deepseek-v3.1` foram interrompidos pela Replicate, e o `llama-4-scout` achou 12 dos 22 resultados. Modelos da mesma família erram de forma correlacionada, então a concordância entre A e B vale menos do que valeria com famílias distintas; a checagem feita em código (unidade, faixa, aritmética) continua sendo a defesa independente. O desempate usa o `llama-4-scout`, que é de outra família: quando ele não acha o item em disputa, o valor vai para quarentena, que é o desfecho seguro.

**Leitura em grupos de páginas.** Cada leitor recebe o documento em grupos de `replicate_pages_per_call` páginas (5 por padrão), não de uma vez, e os grupos são remontados em `merge_page_groups()`: as listas são concatenadas na ordem das páginas e um campo de cabeçalho fica com o primeiro grupo que o trouxe. O motivo é prático: um laudo de 25 páginas pedido numa chamada só leva de 4 a 10 minutos para ser gerado, e a Replicate interrompe a predição no meio (`prediction_failed`, código PA); um grupo de 5 páginas volta em cerca de um minuto, cabe com folga no teto de tokens e pode ser repetido sozinho.

**Validação do JSON.** Nenhum modelo da Replicate aceita esquema JSON, então o esquema vai no prompt e a resposta é conferida aqui: `json_from_model_text()` isola o objeto, `coerce_to_schema()` faz só reparos que não inventam valor (texto ausente vira `""`, lista ausente vira `[]`, número em forma de texto vira número, chave fora do esquema é descartada) e `schema_errors()` diz o que não encaixou. Cada grupo tem até três tentativas: se o JSON decodifica mas o esquema não encaixa, a lista de erros volta para o modelo; se a resposta não decodifica, a tentativa seguinte repete o mesmo pedido, porque não há o que dizer de útil sobre ela e a reclamação só ocuparia espaço no prompt. Esgotadas as tentativas, a leitura falha com `schema_mismatch` ou `no_json` e nada entra na série. Um grupo perdido perde a leitura inteira daquele leitor, e é por isso que a resiliência está no grupo e não no documento.

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
