---
version: 1
slug: "index-php"
primary_target: "index.php"
related_targets: []
---

# Casca do app Eixo — Visão geral

Escopo: a casca do app autenticado e a Visão geral como primeira rota. Modo do visitante: **Operate**.

Público e tarefa: o paciente do piloto fechado, no celular, preparando-se para a consulta. Precisa ver o que tem, o que mudou desde a última vez e o que ficou fora das séries — e sair com isso compartilhável.

Cena física, e são duas: monitor de consultório sob luz branca, lido em diagonal em quatro minutos; e celular perto do rosto à noite, sem luz ambiente. As duas decidem, então o sistema nasce com **tema duplo projetado**, não com um modo escuro pendurado depois.

## Direction contract

THESIS: O registro de saúde como painel vidrado — o catálogo inteiro num só olhar, a emenda entre laboratórios à vista, e o valor que mudou anunciando-se sem ser procurado. Recusa o arranjo-padrão da categoria: cartão arredondado com número herói, sombra suave e semáforo verde-âmbar-vermelho.

OWN-WORLD: Cobalto #1f4da8 comprometido, ocupando a barra inteira, as réguas de emenda e o módulo alterado; vidrado #fbfcfd no módulo; argamassa #e7e3da como chão, nunca creme; tinta #11295e em texto e rodapé. Uma só família: Archivo, com números e voz de display em Archivo Expanded e figuras tabulares. Componentes: módulo de altura fixa em grade, régua de emenda de 2px com laboratório e data de coleta, abas de filtro, módulo de borda tracejada que sangra fora do padrão.

STORY: O paciente entende que cada número veio de uma página que ele pode abrir; acredita nisso porque a origem está sempre a um toque e porque o que não passou na conferência está declarado, não escondido; e sai tendo compartilhado o histórico com o médico por convite com validade.

FIRST VIEWPORT: Barra de cobalto cheio no topo com a marca e a navegação em branco osso. Abaixo, faixa de vidrado com três abas de filtro (carregados, alterados, em quarentena) e a busca ao lado do botão de enviar laudo. O corpo é o painel: régua de emenda com o nome do laboratório, depois a grade de módulos de exame, cada um com nome, valor em figuras tabulares, unidade e a faixa que o laboratório imprimiu; o módulo alterado inverte para cobalto cheio e carrega o delta. Segunda régua de emenda abre o laboratório anterior. A quarentena fecha a tela como módulo tracejado que sangra pela direita, com o motivo de cada valor retido e link para a página do laudo. A ação primária, enviar laudo, fica na faixa de ferramentas, ao alcance do polegar no topo.

FORM: azulejo português-brasileiro, candidata 5 da minha lista ordenada de sete, atribuída pelo dado. seed:3695d5ba45ca4f4d

FINISH: unreviewed and undocumented is unfinished; this build ends with the finish review, the verdict, DESIGN.md, and every shipping raster carrying its provenance

## Elevações que a direção carrega

- **Coragem de densidade** (do catálogo de convenção, declinado): o catálogo inteiro de exames entra num só olhar, em módulo de grade, em vez de cartões que obrigam a rolar.
- **A mudança anuncia-se** (do painel basculante, declinado): o módulo alterado inverte de vidrado para cobalto cheio, perceptível sem ser procurado, com delta e legenda em `sr-only` para que a cor nunca seja o único portador.

## O que o build corrige no esboço escolhido

O esboço é contrato de composição, paleta, caráter e densidade — não de corpo de letra. Duas correções obrigatórias:

1. **Fraunces sai.** Está na lista de faces que o playbook trata como sinal de que se parou de procurar, e exige razão que nenhuma outra satisfaça; associação com o assunto não serve. Fica uma família só, Archivo, com o eixo expandido fazendo a voz de display. Razão: letra de cerâmica e de esmalte na tradição ibérica é capital pintada a pincel, sem serifa, muito mais do que é serifa de livro — a serifa era a associação óbvia que a calibração existe para quebrar.
2. **Piso de 11px para texto funcional.** O esboço desce a 6,5px. O detector já reprovou isso na home, e contraria a postura de acessibilidade do produto. A grade de três colunas re-proporciona-se para sustentar o piso: menos colunas no celular ou módulo mais alto, nunca letra menor.

## Decisões abertas

- **Valores do tema escuro ainda não desenhados.** A cena da cama exige um tema escuro de primeira classe; cobalto aguenta os dois fundos, argamassa inverte para tinta profunda, mas os valores precisam ser medidos, não invertidos.
- **A contradição do chip, herdada da critique e ainda sem resolução de produto**: `exames.php` traz dicionário de direção clínica e pinta verde/vermelho antes do ramo da faixa impressa, contra o princípio 1 do PRODUCT.md. O mundo novo não resolve isso sozinho; é decisão de produto.
- **A cópia que convida foto** sem que haja OCR, e a quarentena que nunca chega a zero: ambas continuam abertas.
