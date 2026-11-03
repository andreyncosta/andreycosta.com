# Product

<!-- impeccable:product-schema 1 -->

Registro de produto do **Eixo**, tratado como produto próprio, com escopo no diretório `eixo/`.
Não cobre o restante de `andreycosta.com`; a página de projeto do Eixo no site segue as regras do
site. Cabeçalhos em inglês por contrato do esquema; o texto segue a língua da documentação do Eixo.

## Platform

web

## Users

**Primário — o paciente do piloto fechado.** Entra por convite de uso único válido por 48 h; não
existe cadastro aberto. É alguém que acumulou laudos de laboratórios diferentes, em PDF, e chega à
consulta sem série histórica para mostrar. Usa o Eixo antes da consulta: envia os laudos, confere o
que caiu em quarentena, registra medicação e dose, e cria um compartilhamento para o médico.

**O médico convidado pelo paciente.** Não tem conta e não é cliente: abre o link que o paciente
mandou e usa um código de 8 dígitos de uso único, válido por 30 minutos, com sessão que expira após
30 minutos sem uso. Vê apenas o escopo que o paciente deu (tudo, ou um período), e todo acesso dele
aparece na trilha de auditoria do paciente. Decide a conduta — o produto não decide nada.

**O administrador.** Hoje o próprio autor, pela CLI: convites, fila de leitura, backup, expurgo,
diagnóstico e avaliação. Não há painel administrativo na web.

## Product Purpose

Juntar exames de sangue, bioimpedância e medicações numa linha do tempo pessoal, com cada número
apontando para a página e o trecho do laudo de onde saiu. Sucesso é o paciente chegar à consulta com
a série pronta e o médico poder conferir qualquer valor no laudo de origem em um clique.

O produto **organiza, não interpreta**. Está explicitamente fora do escopo, e assim está escrito na
própria interface: interpretar, diagnosticar, sugerir exame ou dose, calcular índices (TFG,
HOMA-IR), marcar valor como alterado por conta própria — a única marcação exibida é a que o
laboratório imprimiu — e vender ou ceder dados de saúde.

## Positioning

> A IA lê duas vezes, o código confere e calcula, o médico decide.

O mecanismo que um produto vizinho não copia sem fazer o mesmo trabalho:

- **Leitura dupla por modelos de empresas diferentes**, para que os erros não sejam os mesmos:
  `gpt-5-mini` (OpenAI) e `gemini-2.5-flash` (Google), operados pela Replicate. O desempate
  (`gpt-oss-120b`) entra só nas páginas em disputa, e precisa coincidir com uma das duas leituras.
- **Carga condicionada a checagem por código**, não à confiança do modelo: o valor existe no texto
  da página; a unidade é compatível com a propriedade LOINC; a ordem de grandeza é coerente com a
  faixa impressa e com o histórico no mesmo laboratório; a data é válida.
- **Quarentena em vez de palpite.** O que não passa fica fora das séries e aparece com link para o
  laudo. Quando a mesma linha repetida dá valores diferentes, nenhum é carregado: a evidência não
  diz qual está certo.
- **Conversão de unidade por álgebra UCUM**, só entre unidades da mesma propriedade — % ↔ mmol/mol
  e ng/mL ↔ nmol/L nunca convertem.
- **Proveniência registrada por valor**: página, trecho do laudo, as duas leituras, a resolução, as
  checagens e as versões de modelo, instrução e biblioteca de unidades.

Concordância entre as duas leituras não é prova de acerto, e o produto diz isso ao usuário na ficha
de transparência em vez de esconder.

## Operating Context

- **Entrada.** PDF, JPG, PNG ou WebP, vários por vez; duplicata detectada por SHA-256; arquivo
  cifrado em `blobs/`.
- **A camada de texto é a única entrada dos leitores.** Foto de laudo e PDF digitalizado não têm
  camada de texto: o arquivo é guardado e cifrado, nada é extraído, e o documento termina em
  `no_text_layer`. Não existe estágio de OCR.
- **Leitura assíncrona, em grupos de 5 páginas.** O cron chama `cli.php work` a cada minuto e mata o
  trabalhador em 30 minutos; cada grupo lido é guardado cifrado para a corrida seguinte continuar de
  onde parou, e apagado quando o documento sai da fila. Um laudo longo não cabe numa corrida.
- **Superfícies do paciente.** Painel, linha do tempo, envio, documentos, documento, série,
  medicações, corpo e bioimpedância, compartilhamentos, resumo, trilha de auditoria, conta,
  consentimentos, privacidade e ficha de transparência da IA.
- **Superfícies do médico.** Código de acesso, lista de compartilhamentos, resumo, documento, série.
- **Hospedagem.** Hostinger compartilhada, PHP 8.2+ e SQLite, sem framework. Dados, chaves e sessões
  ficam fora da raiz pública (`~/eixo-private/`). O cron do painel não aceita `cd`, `&&` nem
  redirecionamento; `shell_exec` é bloqueado, e o `pdftotext` roda por `proc_open`, com o
  `smalot/pdfparser` como alternativa em PHP puro; a extensão `sodium` vem desligada.
- **Custo.** Cerca de US$ 0,10 por laudo de 25 páginas, somando os dois leitores.

## Capabilities and Constraints

Confirmado e em funcionamento:

- Convite de uso único (48 h) para entrar, refazer senha ou refazer o segundo fator; segundo fator
  obrigatório; nenhum cadastro aberto.
- Consentimentos por finalidade, separados e revogáveis: guardar os dados (obrigatório), processar
  com IA, compartilhar, e uso anonimizado para melhorar o produto (hoje sem nenhum uso).
- Antes do envio ao fornecedor, nome, CPF, número do pedido e dados do médico solicitante são
  removidos do texto quando possível. O documento em si nunca é enviado.
- Cifragem: uma chave mestra (`master.key`, 0600) cifra uma chave por usuário, que cifra documentos e
  todo campo de saúde. Nas colunas abertas ficam só ids, datas, estados e hashes com chave. Excluir a
  conta destrói a chave do usuário.
- Compartilhamento com escopo, validade e revogação imediata, com auditoria de todo acesso.
- Exportação dos dados e dos documentos originais, em formato inspirado no HL7 FHIR.
- Séries juntam só com mesmo LOINC, material e propriedade, com as duas leituras concordando e
  confiança ≥ 0,7; senão por nome, laboratório e unidade. O paciente junta ou separa manualmente.
- Testes: `php eixo/tests.php` (48 checagens, sem rede) e `cli.php eval` contra os modelos reais.

Restrições que trabalho futuro precisa preservar:

- **Interface só em português.** Páginas públicas do site são bilíngues; o app, não. Decidido.
- A chave mestra fica no mesmo servidor: a cifragem protege cópias do banco e backups, não um
  servidor comprometido. Nenhuma superfície pode prometer mais do que isso.
- Proveniência é por página e trecho citado, não por região desenhada na imagem.
- O código LOINC é proposto pelo modelo, sem tabela LOINC local nem curadoria; na dúvida, séries não
  se juntam.
- Bioimpedância de aparelhos diferentes não forma série contínua.
- A divulgação de que há IA envolvida acompanha os dados organizados, com link para a ficha de
  transparência. É exigência da Resolução CFM 2.454/2026 (art. 3º, II, e art. 13), não escolha de
  design, e não pode ser removida por motivo estético.

Fatos de produto explicitamente **não decididos**:

- Enquadramento junto à Anvisa (fronteira SaMD): consulta formal pendente. O risco autodeclarado é
  baixo para transcrição e organização, e o resumo pré-consulta pode ser lido como médio por apoiar
  decisão clínica.
- Serviço gerenciado de chaves (RNF-01), garantia de região Brasil na hospedagem (RNF-08) e SLA de
  99,5% (RNF-14): não atendidos nesta versão.
- Estágio de OCR antes dos leitores: não existe, e sem ele fotos e digitalizações nunca entram em
  série.
- Fora desta versão: envio de laudos por e-mail (RF-09), busca em base de medicamentos registrados
  (RF-19), registro de adesão (RF-23) e fila em servidor dedicado.
- Se a regra de migrações imutáveis e aditivas documentada para o app irmão em
  `candidatos/AGENTS.md` vale formalmente para o Eixo: nenhum documento do Eixo afirma isso, embora
  as migrações de fato vão de `001.sql` a `004.sql`.

## Brand Commitments

- Nome: **Eixo**. A frase que o acompanha: "exames num só eixo" / "labs on one axis".
- O princípio "a IA lê duas vezes, o código confere e calcula, o médico decide" está escrito em três
  lugares — README, página inicial e ficha de transparência. Superfície nova precisa poder
  sustentá-lo.
- Voz: direta, sem promessa clínica, e **diz o que não faz com o mesmo peso do que faz** — a página
  inicial põe "O que faz" e "O que não faz" lado a lado, com o mesmo tamanho.
- O produto não se apresenta como dispositivo médico.
- A identidade visual é herdada do site `andreycosta.com` (camada compartilhada em
  `assets/site.css`). Registrá-la é tarefa de `DESIGN.md`, não deste arquivo.

## Evidence on Hand

- **Documento de autoridade: "Eixo — Requisitos e documentação inicial" (20/10/2026)**, origem dos
  RF/RNF citados aqui e no README. Ele **não está no repositório**; o README é a única referência a
  ele. Trabalho futuro que dependa de um requisito numerado precisa do documento em mão.
- Comparação medida de sete modelos em duas páginas densas de laudos reais (verdade de 14 e 17
  itens), com preço de entrada e saída e veredito por modelo, em `eixo/README.md`. É medição, não
  estimativa, e é o que justifica a escolha atual dos leitores.
- Caso documentado do laço de repetição: página de 1585 caracteres que devolveu 84 itens com 14 nomes
  e 14 trechos distintos — a evidência que motivou `drop_looped_results()`.
- `cli.php eval` grava a última avaliação em laudos sintéticos (acerto, cobertura e valores errados
  carregados), e ela aparece na ficha de transparência em `?r=ai`.
- Lista de pendências não-código antes de abrir o piloto, no README: contrato sem retenção e sem
  treinamento com a Replicate e cobertura equivalente dos fornecedores a montante, cláusulas-padrão
  da ANPD (Resolução CD/ANPD 19/2024), RIPD e encarregado nomeado, revisão jurídica do texto de
  privacidade, teste de intrusão independente, consulta de enquadramento à Anvisa, `eval` com acerto
  ≥ 98% e zero valor errado carregado, e cópia externa do backup com restauração testada.

Ausências que trabalho futuro **não deve inventar**: não há usuários reais, métrica de uso,
depoimento, caso de cliente, parecer jurídico, laudo de teste de intrusão, resposta da Anvisa nem
contrato assinado com o fornecedor de IA. `controller_*` e `dpo_*` estão vazios no `config.php`
padrão, e a página de privacidade mostra "[definir]" até alguém preencher.

## Product Principles

1. **Organiza, não interpreta.** Número derivado, índice e juízo clínico ficam fora do produto, mesmo
   quando são fáceis de calcular e o usuário pediria.
2. **Evidência antes de valor.** Nada entra na série sem página e trecho que o sustentem. Na dúvida,
   quarentena com link para o laudo — nunca o palpite.
3. **Concordância não é prova.** Duas leituras que erram igual são um erro, e cabe ao código pegá-lo.
4. **O paciente é dono.** Consentimento por finalidade, escopo e validade em cada compartilhamento,
   revogação imediata, auditoria de todo acesso e exclusão que destrói a chave.
5. **Dizer o limite onde ele acontece.** O que o produto não faz, e até onde a proteção vai, aparece
   na interface — não só no termo de uso.

## Accessibility & Inclusion

- O público lê resultado de exame sob estresse, muitas vezes no celular, às vezes minutos antes da
  consulta. Valor e direção da última mudança precisam ler-se de longe: o indicador usa triângulo
  cheio em chip colorido, não a seta tipográfica.
- **Cor não é o único portador de significado.** Estado qualitativo vem com marcador e texto, e cada
  cartão de exame carrega uma legenda em `sr-only` descrevendo a mudança para leitor de tela.
- Instalável como PWA (`display: standalone`, `lang: pt-BR`).
- Padrão formal de conformidade exigido: **não estabelecido**. Decisão aberta.
