---
name: Eixo
description: Registro pessoal de saúde — exames, bioimpedância e doses num só eixo
colors:
  verde-profundo: "#2d5546"
  verde-medio: "#456c57"
  verde-pressionado: "#1f3d32"
  ambar-de-foco: "#b4773d"
  ambar-de-texto: "#8d5c2b"
  vermelho-de-alerta: "#9b2c1d"
  verde-tinta: "#273e35"
  papel-claro: "#f3f3ed"
  superficie-cartao: "#fffefa"
  texto-secundario: "#627267"
  fio: "#dce2d7"
  fio-forte: "#cbd4c7"
  realce-de-borda: "#bccdb6"
  fundo-aviso: "#e7efe8"
  fundo-erro: "#f6e3df"
  creme-quarentena: "#fbf6ec"
  faixa-de-referencia: "#dce9de"
  chip-neutro: "#e4e8e0"
  chip-neutro-tinta: "#556158"
  chip-bom: "#d6ead9"
  chip-bom-tinta: "#1f5137"
  chip-ruim: "#f7ded8"
  chip-ruim-tinta: "#8a2416"
  ponto-bom: "#2f6b4f"
  rosa-destrutiva: "#d9a79e"
  marcador-de-grafico: "#9aa79e"
  grade-de-grafico: "#e5e9e1"
  fundo-de-citacao: "#f7f7f1"
typography:
  display:
    fontFamily: "Georgia, 'Times New Roman', serif"
    fontSize: "clamp(30px, 4vw, 44px)"
    fontWeight: 400
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  headline:
    fontFamily: "Georgia, 'Times New Roman', serif"
    fontSize: "24px"
    fontWeight: 400
    lineHeight: 1.15
    letterSpacing: "-0.02em"
  title:
    fontFamily: "Georgia, 'Times New Roman', serif"
    fontSize: "22px"
    fontWeight: 400
    lineHeight: 1.15
  value:
    fontFamily: "Georgia, 'Times New Roman', serif"
    fontSize: "28px"
    fontWeight: 400
    lineHeight: 1.1
  body:
    fontFamily: "'Segoe UI', system-ui, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.6
  lead:
    fontFamily: "'Segoe UI', system-ui, sans-serif"
    fontSize: "19px"
    fontWeight: 400
    lineHeight: 1.6
  detail:
    fontFamily: "'Segoe UI', system-ui, sans-serif"
    fontSize: "13px"
    fontWeight: 400
    lineHeight: 1.5
  caption:
    fontFamily: "'Segoe UI', system-ui, sans-serif"
    fontSize: "12px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "'Segoe UI', system-ui, sans-serif"
    fontSize: "11px"
    fontWeight: 600
    letterSpacing: "0.14em"
  mono:
    fontFamily: "ui-monospace, Menlo, monospace"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.5
rounded:
  flag: "4px"
  alert: "6px"
  control: "8px"
  panel: "10px"
  card: "12px"
  pill: "999px"
  circle: "50%"
spacing:
  xs: "6px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
  xxl: "32px"
  gutter: "clamp(16px, 4vw, 48px)"
components:
  button-primary:
    backgroundColor: "{colors.verde-profundo}"
    textColor: "#ffffff"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
  button-primary-hover:
    backgroundColor: "{colors.verde-pressionado}"
    textColor: "#ffffff"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.verde-profundo}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
  button-ghost-hover:
    backgroundColor: "{colors.fundo-aviso}"
    textColor: "{colors.verde-profundo}"
  button-danger:
    backgroundColor: "transparent"
    textColor: "{colors.vermelho-de-alerta}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
    height: "44px"
  input:
    backgroundColor: "{colors.superficie-cartao}"
    textColor: "{colors.verde-tinta}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    padding: "10px 12px"
    height: "44px"
  consent-check:
    backgroundColor: "{colors.superficie-cartao}"
    textColor: "{colors.verde-tinta}"
    rounded: "{rounded.panel}"
    padding: "14px"
  card:
    backgroundColor: "{colors.superficie-cartao}"
    textColor: "{colors.verde-tinta}"
    rounded: "{rounded.card}"
    padding: "16px"
  card-exam:
    backgroundColor: "{colors.superficie-cartao}"
    textColor: "{colors.verde-tinta}"
    rounded: "{rounded.card}"
    padding: "14px 16px"
  arrow-chip:
    backgroundColor: "{colors.chip-neutro}"
    textColor: "{colors.chip-neutro-tinta}"
    rounded: "{rounded.pill}"
    padding: "4px 8px"
  arrow-chip-bom:
    backgroundColor: "{colors.chip-bom}"
    textColor: "{colors.chip-bom-tinta}"
    rounded: "{rounded.pill}"
    padding: "4px 8px"
  arrow-chip-ruim:
    backgroundColor: "{colors.chip-ruim}"
    textColor: "{colors.chip-ruim-tinta}"
    rounded: "{rounded.pill}"
    padding: "4px 8px"
  flash:
    backgroundColor: "{colors.fundo-aviso}"
    textColor: "{colors.verde-tinta}"
    rounded: "{rounded.alert}"
    padding: "12px 16px"
  error:
    backgroundColor: "{colors.fundo-erro}"
    textColor: "{colors.verde-tinta}"
    rounded: "{rounded.alert}"
    padding: "12px 16px"
  table-wrap:
    backgroundColor: "{colors.superficie-cartao}"
    rounded: "{rounded.panel}"
  chart:
    backgroundColor: "{colors.superficie-cartao}"
    rounded: "{rounded.card}"
    padding: "12px"
  empty:
    backgroundColor: "transparent"
    textColor: "{colors.texto-secundario}"
    rounded: "{rounded.card}"
    padding: "28px"
---

# Design System: Eixo

## Overview

**Creative North Star: "O Arquivo Calmo"**

O Eixo é lido por alguém que acabou de receber um resultado e não sabe ainda o que ele significa, muitas vezes no celular, às vezes minutos antes da consulta. A interface recua para que o resultado apareça. Nada pulsa, nada comemora, nada alarma: a tela não acrescenta estresse ao que o paciente já trouxe. O que o sistema oferece em troca da discrição é a certeza de que cada número veio de uma página que ele pode abrir.

A atmosfera é **silenciosa, precisa e confiável**. A ausência de ruído é a promessa do produto, não economia de esforço: não há sombra, não há gradiente, não há animação além de um marcador de 150 ms que respeita `prefers-reduced-motion`. A hierarquia é feita de papel, fio de 1px e tipografia — o verde editorial e a Georgia vêm do site `andreycosta.com` e são herança deliberada, não acidente. Onde um painel de métricas usaria cor para impressionar, o Eixo usa cor para dizer uma única coisa: o que o laboratório imprimiu.

A precisão é o acabamento visível. Alvos de 44px, foco âmbar inequívoco de 3px em tudo que recebe teclado, campo nunca abaixo de 16px, legenda em `sr-only` para quem navega por leitor de tela. Componente preciso e discreto: não chama atenção para si, mas não falha quando alguém depende dele.

**Key Characteristics:**

- Papel morno e cartão marfim, sem nenhuma sombra: profundidade por borda e tom.
- Georgia para todo número que o paciente foi ler; sans só para rótulo, navegação e controle.
- Verde é a única voz de ação; âmbar é foco e marcação no tempo; vermelho é alerta e destruição.
- Cor nunca é o único portador de significado — sempre acompanhada de glifo e texto.
- Densidade de leitura, não de painel: 1100px de contêiner, 760px de prosa, 520px de formulário.

## Colors

Uma paleta verde editorial herdada de `/assets/site.css`, com dois acentos de serviço (âmbar e vermelho) e uma família de chips de estado que só repete o que o laudo trouxe impresso.

### Primary

- **Verde Profundo** (#2d5546): a única voz de ação. Botão primário, link, borda do item de navegação ativo, linha e ponto do gráfico, borda do bloco de convite criado. É o verde do acento do site, não um verde de marca próprio do app.
- **Verde Médio** (#456c57): ênfase de segunda ordem. Itálico do título na página inicial, barra lateral do aviso, contorno do marcador da linha do tempo. Onde o Verde Profundo seria pesado demais.
- **Verde Pressionado** (#1f3d32): exclusivamente o `:hover` do botão primário. Não aparece em superfície nem em texto.

### Secondary

- **Âmbar de Foco** (#b4773d): o anel de foco de todo elemento focável, e a marcação de tempo desenhada como traço — evento de dose na linha do tempo, linha de quebra de série no gráfico. Um leitor aprende que âmbar significa "aqui houve uma intervenção, ou aqui está você". Como elemento não-textual, 3,34:1 sobre o papel cumpre o critério de 3:1.
- **Âmbar de Texto** (#8d5c2b): o mesmo âmbar escurecido para quando ele é *letra*, e só para isso — hoje, o rótulo do marcador no gráfico. Em 12px o âmbar de foco dá 3,34:1 e falha no AA; esta variante passa sobre os três fundos onde o rótulo pode cair: cartão (5,63:1), faixa de referência (4,53:1) e papel (5,10:1).

### Tertiary

- **Vermelho de Alerta** (#9b2c1d): erro de formulário, ação destrutiva, ponto de estado ruim. Nunca decora e nunca aparece em superfície grande.

### Neutral

- **Verde Tinta** (#273e35): todo texto corrente e todo título. Não é preto: é o verde escuro do site, e trocá-lo por preto quebra a herança.
- **Papel Claro** (#f3f3ed): o fundo da página e da barra fixa.
- **Superfície de Cartão** (#fffefa): cartão, campo, tabela, gráfico, bloco de código. Quase branco, meio grau mais quente — é o que cria a profundidade sem sombra.
- **Texto Secundário** (#627267): rótulo, unidade, faixa de referência, legenda, cabeçalho de tabela, nome do exame no cartão.
- **Fio** (#dce2d7): a divisória de 1px que faz todo o trabalho de separação.
- **Fio Forte** (#cbd4c7): borda de campo de formulário, borda tracejada do estado vazio, ponto neutro, contorno de etiqueta de laboratório. Mais presente onde o elemento é interativo.
- **Realce de Borda** (#bccdb6): `:hover` de cartão e barra de citação do trecho do laudo. Único sinal de que um cartão é clicável.

### Estado e dados

- **Creme de Quarentena** (#fbf6ec): fundo da linha de tabela cujo valor não passou na checagem. Quente, não vermelho: quarentena não é erro do paciente.
- **Faixa de Referência** (#dce9de): a faixa impressa pelo laboratório, desenhada atrás da série no gráfico.
- **Chips de direção** — neutro (#e4e8e0 sobre #556158), bom (#d6ead9 sobre #1f5137), ruim (#f7ded8 sobre #8a2416) — e **Ponto Bom** (#2f6b4f) para estado qualitativo. O chip neutro não usa o Texto Secundário: sobre o fundo tingido ele dava 4,10:1 em 12px, abaixo do AA.

### Named Rules

**A Regra da Marcação do Laboratório.** Cor de estado só repete o que o laudo imprimiu. O sistema nunca pinta um valor como alterado por conta própria, nem calcula faixa, nem decide o que é bom. Se o laboratório não imprimiu faixa, o cartão diz "sem faixa no laudo" em itálico e fica neutro.

**A Regra da Voz Única.** O verde é a única cor de ação. Âmbar é foco e tempo, vermelho é alerta e destruição, e nenhum dos dois convida ao clique. Introduzir um quarto matiz exige mudar esta regra primeiro.

## Typography

**Display Font:** Georgia (com *Times New Roman* e serif como alternativas)
**Body Font:** Segoe UI (com `system-ui` e sans-serif)
**Label/Mono Font:** `ui-monospace`, Menlo, monospace

**Character:** Uma serifa de livro para tudo que se lê como resultado, e a sans do sistema para tudo que se opera. O par não tem personalidade de marca: tem a neutralidade de um documento bem composto, o que é exatamente a autoridade que um registro de saúde pode reivindicar sem mentir.

### Hierarchy

- **Display** (400, `clamp(30px, 4vw, 44px)`, 1.15, `-0.02em`): o `h1` de cada página. Georgia em peso regular — nunca negrito, porque peso aqui soaria a manchete.
- **Headline** (400, 24px, 1.15): o `h2` que abre uma seção. 22px no cabeçalho de medicação.
- **Value** (400, 28px/1.1): o número do exame no cartão, o papel tipográfico mais característico do sistema. A unidade vem ao lado em sans de 13px e cor secundária, para que o número seja o que se lê de longe.
- **Body** (400, 16px/1.6): texto corrente. Prosa limitada a 760px; o `.lead` de entrada é 19px em Texto Secundário, até 720px.
- **Label** (600, 11px, `0.14em`, caixa alta): o `eyebrow` acima do título. A variante de tabela é 12px com `0.04em`, também em caixa alta e Texto Secundário. O nome do exame no cartão é 13px/1.3 em 600, cortado em duas linhas com altura mínima reservada para que a grade não dance.
- **Mono** (400, 14px): credencial e evidência — código de acesso, chave, trecho citado do laudo. 18px em 600 quando é algo que o usuário precisa ditar ou copiar, com `user-select: all`.

### Named Rules

**A Regra do Número Serifado.** Todo número que o paciente foi ler está em Georgia; a sans fica com rótulo, navegação e controle. É assim que o olho encontra o resultado numa tela cheia de interface.

**A Regra dos 16px.** Campo de formulário nunca abaixo de 16px. Não é preferência estética: abaixo disso o Safari no iPhone dá zoom no campo e desloca a página no meio do envio de um laudo.

**A Regra da Quebra Cuidada.** Título leva `text-wrap: balance` e o corpo leva `text-wrap: pretty`. Um `h1` em `clamp()` quebra em duas linhas no celular com a segunda em um terço da primeira, e uma palavra sozinha na última linha de um parágrafo abre um buraco — os dois são corrigíveis por uma declaração, sem tocar na medida.

## Layout

Coluna única centrada, de 1100px no máximo, com goteira de `clamp(16px, 4vw, 48px)` — a mesma função de medida da barra do site, um grau mais apertada. Três larguras de leitura deliberadas: 1100px para conteúdo em grade, 760px para prosa (`.prose-app`), 520px para formulário curto (`.narrow`), e 720px para o parágrafo de entrada.

A barra de aplicação é fixa no topo (`sticky`, `z-index: 5`) e volta a estática abaixo de 640px, onde a tela é curta e rolar vale mais que navegar. O único ponto de quebra declarado é **640px**; todo o resto é grade fluida por `auto-fill`/`auto-fit`:

- Catálogo de exames: `minmax(200px, 1fr)`, intervalo de 12px.
- Cartões gerais: `minmax(240px, 1fr)`, intervalo de 14px.
- Colunas de texto: `auto-fit minmax(280px, 1fr)`, intervalo de 24px.
- Formulário em grade: `minmax(220px, 1fr)`, alinhado pela base dos campos.

O ritmo vertical é de passo curto e reutilizado — 6, 8, 12, 14, 16, 24, 28 — com 32px para abrir uma seção nova e 64px de respiro no fim da página. A escala não é estrita e não finge ser: é um conjunto pequeno de valores repetidos.

Há folha de impressão: barra, busca, botões, formulários e o visualizador de documento desaparecem, o fundo vira branco, a tabela perde a moldura e o link perde a cor. Uma página do Eixo tem de sair numa folha e ir para a consulta.

O rodapé **não** desaparece — só os links dele. A frase de limite ("não interpreta, não diagnostica, não sugere doses") viaja com o papel, porque é no papel que ela deixa de ter um rodapé de site por perto para a explicar. Esconder o rodapé inteiro na impressão tirava da folha exatamente a declaração que o DESIGN.md chama de não-removível.

### Named Rules

**A Regra do Alvo de 44px.** Botão, campo, seletor e área de toque têm 44px de altura mínima. A caixa de consentimento é de 20px por 20px dentro de um rótulo de 14px de recheio, porque o alvo real é o rótulo inteiro.

## Elevation & Depth

**Este sistema não tem sombras.** Não há uma única `box-shadow` no CSS do Eixo, nem gradiente, nem `backdrop-filter`. A profundidade é construída por duas coisas: o fio de 1px (#dce2d7) e a diferença de meio grau entre a Superfície de Cartão (#fffefa) e o Papel Claro (#f3f3ed). Um cartão não flutua sobre a página; ele é um recorte mais claro dela, delimitado.

A consequência é que a hierarquia tem de ser resolvida por tipografia, espaço e borda — e é. O `:hover` de um cartão escurece a borda para Realce de Borda (#bccdb6) em vez de levantá-lo. A barra fixa se separa do conteúdo por um fio, não por elevação.

### Named Rules

**A Regra do Chapado.** Sombra nunca, em nenhum estado, em nenhum componente. Profundidade é borda de 1px e tom de superfície. Quem precisar de destaque usa borda mais forte, peso tipográfico ou espaço — não altura.

## Shapes

Cantos arredondados em cinco passos, cada um ligado a um papel, não a um tamanho: etiqueta de laboratório em 4px (`flag`), aviso e erro em 6px (`alert`), botão, campo e bloco de código em 8px (`control`), moldura de tabela, conjunto de campos e visualizador de documento em 10px (`panel`), cartão, gráfico, estado vazio e bloco de criação em 12px (`card`). Duas formas absolutas fecham o vocabulário: a pílula (999px) do chip de direção e o círculo (50%) do ponto de estado e do marcador da linha do tempo.

A borda de 1px é a forma dominante do sistema — presente em cartão, campo, tabela, aviso, gráfico e etiqueta. A exceção de peso é o bloco de convite recém-criado, com 2px em Verde Profundo, e a linha de quebra de série, com 2px tracejados em âmbar.

### Named Rules

**A Regra do Tracejado Significa Interrupção.** Borda tracejada aparece só onde há ausência ou descontinuidade: o estado vazio (1px tracejado em Fio Forte) e a quebra de série no gráfico e na tabela (âmbar tracejado). Nunca como decoração.

## Components

### Buttons

- **Shape:** cantos suaves de controle (8px), borda de 1px, altura mínima de 44px, recheio de 10px por 18px, sans de 14px em 600.
- **Primary:** Verde Profundo com texto branco e borda da própria cor; `:hover` fecha para Verde Pressionado (#1f3d32). Sem transição declarada — a troca é imediata.
- **Ghost:** fundo transparente com texto Verde Profundo; `:hover` recebe Fundo de Aviso (#e7efe8). É a ação secundária em par com a primária.
- **Danger:** fundo transparente, texto Vermelho de Alerta, borda rosada (#d9a79e). Usado em revogar compartilhamento, apagar documento e excluir conta.
- **Link:** `<button class="link">` sem fundo nem borda, sublinhado com deslocamento de 3px, herdando a fonte. É o "Sair" da barra e as ações dentro de tabela — um botão que se comporta como link porque a ação exige POST.

### Inputs / Fields

- **Style:** Superfície de Cartão, borda de 1px em Fio Forte (#cbd4c7), 8px de raio, recheio de 10px por 12px, fonte de 16px, 44px de altura mínima.
- **Focus:** anel âmbar de 3px com 2px de deslocamento (`outline: 3px solid #b4773d; outline-offset: 2px`), o mesmo em campo, botão, link, seletor e `summary`. Nunca removido, nunca substituído por sombra.
- **Rótulo:** empilhado acima do campo, 14px em 600, com 6px de intervalo. O formulário em grade alinha os campos pela base para que rótulos de uma e duas linhas não desalinhem.
- **Consentimento:** `label.check` é um painel próprio — 10px de raio, borda de Fio, Superfície de Cartão, 14px de recheio, caixa de 20px no topo e descrição em Texto Secundário abaixo do título em negrito. Cada finalidade é um bloco clicável inteiro.

### Cards / Containers

- **Corner Style:** 12px.
- **Background:** Superfície de Cartão sobre Papel Claro.
- **Shadow Strategy:** nenhuma (ver Elevation & Depth).
- **Border:** 1px em Fio; `:hover` escurece para Realce de Borda.
- **Internal Padding:** 16px no cartão geral, 14px por 16px no cartão de exame.

### Cartão de exame (signature)

O componente que define o sistema. Lê-se de relance, em quatro camadas fixas: nome do exame em 13px, **em Verde Tinta**, cortado em duas linhas com altura reservada; valor em Georgia de 28px com a unidade em sans ao lado; chip de direção em pílula; e a faixa impressa pelo laboratório em 12px — ou "sem faixa no laudo" em itálico quando não houve.

O nome não é secundário. O paciente procura o cartão **pelo nome do exame**, não pelo número: deixá-lo em Texto Secundário punha o elemento de busca como o mais apagado do cartão, com o número a gritar ao lado. O tamanho continua pequeno porque o espaço é pequeno; o peso de cor é que estava errado.

O chip de direção carrega um **triângulo cheio** (▲ ▼ =), não a seta tipográfica, porque precisa ler-se de longe: neutro em #e4e8e0, bom em #d6ead9 sobre #1f5137, ruim em #f7ded8 sobre #8a2416. Estado qualitativo troca o chip por um ponto de 12px. Em ambos os casos uma legenda em `sr-only` descreve a mudança por extenso.

**A Regra do Triângulo.** Direção se lê por glifo cheio dentro de um chip, nunca por cor sozinha e nunca pela seta tipográfica. O glifo e a legenda são obrigatórios; a cor é reforço.

### Tables

Moldura de 10px em Fio sobre Superfície de Cartão, com rolagem horizontal própria. Cabeçalho em 12px caixa alta, Texto Secundário, 600; células de 10px por 12px alinhadas ao topo, separadas por fio. Três estados de linha: **quarentena** em Creme de Quarentena (#fbf6ec), **corrigida** a 60% de opacidade, e **quebra** com borda superior de 2px tracejados em âmbar.

A proveniência vive num `<details>` dentro da célula: `summary` de 13px em Verde Profundo, e o trecho citado do laudo numa citação monoespaçada de 13px com barra de 3px em Realce de Borda, fundo #f7f7f1 e `white-space: pre-wrap`. A evidência é discreta por padrão e está sempre a um clique.

### Navigation

Barra fixa com a marca à esquerda — quadrado de 35px em Verde Profundo, raio de 10px, com as letras "ei" em Georgia — e a navegação em sans de 14px. O item ativo recebe borda inferior de 2px em Verde Profundo e peso 600, marcado com `aria-current="page"`; os demais ficam em Verde Tinta com borda transparente, sem sublinhado. Abaixo de 640px a barra deixa de ser fixa e as linhas se redistribuem.

### Chart (signature)

SVG inline em cartão de 12px, largura total e altura automática. A faixa de referência impressa pelo laboratório é desenhada como banda (#dce9de) atrás da série; a grade é um fio de #e5e9e1; a linha é Verde Profundo de 2px; o ponto cheio é Verde Profundo e o ponto aberto tem miolo de Superfície de Cartão. Marcadores de evento são linhas verticais pontilhadas em #9aa79e com rótulo âmbar e `cursor: help`; a quebra de série é âmbar tracejado.

### Timeline

Lista sem marcadores com fio vertical de 2px à esquerda. Cada item tem um ponto de 12px com fundo de Superfície de Cartão e contorno de 2px em Verde Médio; eventos de dose preenchem o ponto em âmbar. A data fica em coluna de 96px em 600, para que as linhas se alinhem.

### Feedback

- **Flash:** Fundo de Aviso com barra esquerda de **4px** em Verde Médio, 6px de raio, recheio de 12px por 16px, anunciado com `role="status"`. É um evento: aparece uma vez, por uma ação que acabou de acontecer.
- **Nota:** o mesmo fundo e a mesma geometria, com barra de **1px**. É contexto que fica na página, e até três notas podem empilhar-se acima do conteúdo — com a barra do flash elas leriam-se como três alarmes. A diferença de peso é o que separa "acabou de acontecer" de "é assim".
- **Erro:** Fundo de Erro (#f6e3df) com barra de 4px em Vermelho de Alerta, mesma geometria.
- **Vazio:** 28px de recheio, borda de 1px tracejada em Fio Forte, raio de 12px, texto centrado em Texto Secundário.
- **Credencial:** `.key`, `.copy` e `.codes` em monoespaçada sobre Superfície de Cartão, com `user-select: all` e quebra por caractere, para o código de 8 dígitos e o link de convite serem copiados sem erro.

### Chrome persistente

Toda página carrega, no rodapé, a frase de limite — "O Eixo organiza e mostra seus documentos. Não interpreta exames, não diagnostica e não sugere doses" — seguida dos links de privacidade e da ficha da IA. Onde dados organizados aparecem, uma nota de divulgação de IA acompanha, com link para a ficha. Nenhum dos dois é ornamento removível: respondem à Resolução CFM 2.454/2026. A aplicação também vai com `<meta name="robots" content="noindex">`.

## Do's and Don'ts

### Do:

- **Do** consumir as cores por `var(--brand-*)` de `/assets/site.css`. O Eixo não redeclara a paleta: herda-a, inclusive no gráfico e na linha do tempo. Dois valores próprios com uso repetido têm variável local (`--eixo-fio-forte`, `--eixo-vermelho-de-alerta`); os de uso único ficam literais, nomeados aqui — uma variável para um só ponto de uso é indireção, não sistema.
- **Do** manter o foco âmbar de 3px com 2px de deslocamento em todo elemento focável, inclusive `summary` e `select`.
- **Do** dar 44px de altura mínima a botão e campo, e 16px de fonte em todo campo.
- **Do** pôr o número em Georgia e a unidade em sans secundária, nessa ordem de peso visual.
- **Do** acompanhar toda marcação por cor de um glifo e de uma legenda em `sr-only`.
- **Do** mostrar a faixa que o laboratório imprimiu e, quando não houver, dizer "sem faixa no laudo" em itálico em vez de omitir a linha.
- **Do** manter a folha de impressão viva: uma página do Eixo tem de sair legível numa folha.
- **Do** declarar o estado de quarentena com cor quente e link para o laudo, nunca com cor de erro.
- **Do** medir a cor como ela é composta, não como foi escrita: conferir o contraste no resultado renderizado, porque opacidade e fundo tingido alteram o valor que chega ao olho.

### Don't:

- **Don't** usar `box-shadow`, gradiente ou `backdrop-filter`. Nenhum estado, nenhum componente.
- **Don't** pintar um valor como alterado, bom ou ruim sem marcação impressa pelo laboratório — nem calcular faixa, nem inferir normalidade.
- **Don't** usar cor como único portador de significado.
- **Don't** des-enfatizar texto com `opacity`. Ela compõe *todas* as cores do elemento contra o fundo de uma vez e derruba o contraste sem aviso — foi assim que a linha corrigida chegou a 2,4:1. Para baixar o tom, troque a cor por uma do sistema.
- **Don't** trazer vocabulário de app de bem-estar gamificado: anel de progresso, medalha, sequência de dias, pontuação, verde neón, emoji de parabéns. Transformar exame em placar sugere um julgamento que o Eixo não faz e não tem autoridade para fazer.
- **Don't** trocar o Verde Tinta (#273e35) por preto, nem o Papel Claro por branco puro: a herança do site é o que une o app à sua página pública.
- **Don't** acrescentar animação além do marcador de 150 ms existente sem um `@media (prefers-reduced-motion: reduce)` que a desligue.
- **Don't** introduzir um quarto matiz de ação. Verde age, âmbar foca e marca o tempo, vermelho alerta.
- **Don't** remover a frase de limite do rodapé nem a nota de divulgação de IA por motivo de composição.
