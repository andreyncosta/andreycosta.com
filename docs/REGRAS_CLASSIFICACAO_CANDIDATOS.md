# Classificação de candidatos — regras funcionais

Data: 30/09/2026.

Este documento registra o planejamento da página `andreycosta.com/candidatos/`, do painel administrativo e da área dos revisores. Não representa uma implementação já existente. Os pontos ainda sujeitos a definição estão explicitados ao final.

## 1. Objetivo e escopo

Permitir que revisores autorizados classifiquem candidaturas importadas das planilhas de 2022 e 2026. O administrador controla os acessos, distribui o trabalho e acompanha sua conclusão.

O trabalho poderá começar por deputados federais, sem restringir o sistema a esse cargo. Ano, cargo e UF devem permitir selecionar e distribuir qualquer recorte disponível nas fontes.

A unidade de trabalho é a candidatura em determinada eleição, não a pessoa independentemente do ano. Uma classificação de 2022 não será automaticamente aplicada a 2026.

## 2. Perfis e acesso

### Administrador

O administrador também pode receber lotes, classificar suas próprias candidaturas e ter suas pendências retomadas ou redistribuídas, sem perder as permissões de gestão.

- Convidar revisores, aprovar acessos e suspender contas.
- Consultar candidaturas, avaliações, lotes e histórico.
- Atribuir, retomar e redistribuir trabalho.
- Devolver avaliações concluídas para correção.
- Acompanhar progresso e exportar classificações.

### Revisor

- Acessar sua lista de candidaturas atribuídas.
- Filtrar a lista e acompanhar seu progresso.
- Salvar rascunhos e concluir avaliações sob sua responsabilidade.
- Corrigir avaliações devolvidas pelo administrador.
- Não editar candidaturas atribuídas a outras pessoas ou já retiradas de sua responsabilidade.

Para a primeira versão, o cadastro será por convite. O planejamento prevê autenticação por e-mail e senha, confirmação de e-mail e recuperação de senha.

## 3. Dados e apresentação

A tabela deve apresentar as colunas nesta ordem:

| Ordem | Coluna | Origem ou controle |
|---|---|---|
| 1 | Ano | `ANO_ELEICAO` |
| 2 | UF | `SG_UF` |
| 3 | Partido | `SG_PARTIDO` |
| 4 | Cargo | `DS_CARGO` |
| 5 | Nome | `NM_URNA_CANDIDATO`, com nome completo disponível para consulta |
| 6 | Ideológico? | Seletor Não / Sim |
| 7 | Perfil | Seletor de cinco posições |

No celular, os registros podem ser apresentados como cartões, preservando a ordem dos dados. Os filtros devem incluir ano, UF, partido, cargo, nome e situação do trabalho.

### Marcadores

| Campo | Valores |
|---|---|
| Ideológico | Não ou Sim |
| Perfil | −2: Esquerda; −1: Centro-esquerda; 0: Centro; +1: Centro-direita; +2: Direita |

- Os controles devem ter aparência de switches ou seletores segmentados, com rótulos explícitos e operação por teclado.
- Ambos começam sem resposta. Ausência de resposta é diferente de Não e de 0.
- Os campos são independentes: responder Não em Ideológico não impede preencher Perfil.
- Salvar rascunho permite preenchimento parcial; concluir uma classificação exige os dois campos preenchidos.
- Avaliações concluídas ficam bloqueadas para o revisor até devolução pelo administrador.
- O significado operacional de Ideológico deverá ser definido e exibido junto ao controle antes do início das avaliações.

## 4. Importação e identidade das candidaturas

Fontes iniciais:

- `consulta_cand_2022_BRASIL.csv`.
- `consulta_cand_2026_BRASIL.csv`.

A importação deve preservar o identificador `SQ_CANDIDATO` associado à identificação da eleição, validando a chave definitiva contra os arquivos. Nomes não devem ser usados como identificadores.

Deve tratar codificação de caracteres, valores ausentes e possíveis repetições por turno, sem gerar tarefas duplicadas para a mesma candidatura. Reimportações devem atualizar os dados de origem sem apagar atribuições, avaliações ou histórico. Somente campos necessários à aplicação devem ser disponibilizados na interface.

## 5. Estados e elegibilidade

O sistema deve distinguir a situação da conta, a responsabilidade pela candidatura e o andamento da avaliação. Esses controles não são intercambiáveis.

| Situação de trabalho | Significado |
|---|---|
| Disponível | Sem classificação concluída e sem responsável ativo |
| Não iniciada | Atribuída, sem rascunho salvo |
| Em andamento | Atribuída, com rascunho salvo |
| Concluída | Classificação finalizada e bloqueada para edição pelo revisor |
| Devolvida para correção | Reaberta pelo administrador para o responsável corrigir |

Uma candidatura não classificada, mas já atribuída, não está disponível para nova distribuição. Uma conta suspensa pode continuar com atribuições vigentes; a suspensão não libera automaticamente essas candidaturas.

Cada candidatura terá no máximo um revisor responsável por vez. Dupla revisão independente não faz parte do fluxo inicial e exigirá regras próprias caso seja adicionada.

## 6. Atribuição em bloco

### Parâmetros

O administrador seleciona:

- Ano da eleição.
- Cargo.
- Uma ou mais UFs.
- Quantidade X por revisor.
- Um ou mais revisores com acesso ativo.
- Modo de distribuição.

Partido e seleção manual de candidaturas podem complementar os filtros. A quantidade deve ser um inteiro positivo.

### Modos

**Lote fixo:** atribuir mais X candidaturas disponíveis a cada revisor selecionado, independentemente de sua carga anterior.

**Completar carga:** atribuir apenas o necessário para que cada revisor alcance X candidaturas pendentes. Neste documento, pendentes compreendem atribuições não iniciadas, em andamento ou devolvidas para correção. O recorte usado para contar a carga deve aparecer explicitamente na prévia; a escolha entre carga total e carga do recorte permanece pendente de definição.

Exemplo de completar carga: para um alvo de 50 pendências, Ana com 20 recebe 30; Bruno com 45 recebe 5. Quem já tem 50 ou mais não recebe novas candidaturas nessa operação e não perde atribuições existentes.

### Prévia e confirmação

Antes de confirmar, o painel deve mostrar:

- Filtros e modo utilizados.
- Quantidade de candidaturas elegíveis.
- Carga considerada de cada revisor e quantidade que receberá.
- Total a distribuir e saldo disponível após a operação.
- Eventual insuficiência de candidaturas e a divisão possível.

Exemplo: “150 candidaturas serão distribuídas: 50 para cada revisor. Restarão 320 disponíveis.”

A seleção deve usar ordem estável, inicialmente por UF e nome, com identificador como desempate. O critério deve ser informado. O sistema nunca deve ampliar silenciosamente os filtros para preencher um lote.

Cada operação deve gerar um registro de lote com os filtros, o modo, o administrador, a data e as atribuições efetivamente realizadas.

## 7. Retomada e redistribuição

A ação **Retomar atribuições** deve existir na página do revisor e ser distinta de **Suspender acesso**.

### Seleção do trabalho

O administrador poderá selecionar todas as atribuições pendentes ou filtrar por ano, cargo, UF, lote e situação. A prévia deve separar trabalho não iniciado de trabalho em andamento.

| Situação | Regra de retomada |
|---|---|
| Não iniciada | Pode voltar à fila ou ser transferida |
| Em andamento | Pode ser retomada; rascunho e autoria devem ser preservados no histórico |
| Devolvida para correção | Pode ser retomada, preservando avaliação anterior e motivo da devolução |
| Concluída | Excluída da retomada comum; encaminhamento para nova revisão exige ação específica |

### Destinos

1. **Devolver à fila:** encerrar a responsabilidade atual e tornar a candidatura disponível para distribuição posterior, desde que não exista classificação concluída vigente.
2. **Transferir para outro revisor:** encerrar a responsabilidade anterior e atribuir ao destinatário na mesma operação.
3. **Redistribuir entre vários revisores:** dividir o trabalho selecionado procurando equilibrar as cargas pendentes dos destinatários.

O painel deve apresentar a divisão resultante antes da confirmação. A regra exata de desempate e o recorte de carga para equilíbrio ainda devem ser definidos.

A operação deve registrar candidaturas afetadas, responsáveis anteriores e novos, administrador, data e motivo. O histórico de autoria nunca será transferido para o novo responsável.

### Independência entre acesso e atribuições

| Ação | Acesso à conta | Efeito sobre atribuições |
|---|---|---|
| Retomar todas as pendências | Mantido | Encerra as responsabilidades selecionadas; preserva histórico e avaliações concluídas |
| Transferir parte do trabalho | Mantido | Transfere apenas a seleção; mantém o restante |
| Suspender acesso | Bloqueado | Mantém as atribuições até uma decisão separada de redistribuição |

Suspender uma conta não deve apagar avaliações nem redistribuir trabalho automaticamente. Retomar trabalho não deve suspender ou revogar o acesso da conta.

## 8. Concorrência e integridade

- O banco deve garantir uma única atribuição ativa por candidatura.
- Distribuições e transferências devem ser atômicas, evitando dupla reserva ou estados intermediários inconsistentes.
- A elegibilidade deve ser validada novamente na confirmação, pois a prévia pode ficar desatualizada. Se houver mudança que afete a divisão, apresentar uma prévia atualizada.
- Toda gravação deve verificar no servidor a conta ativa, a responsabilidade atual e a permissão de editar a avaliação.
- Se uma atribuição for retomada com a tela do revisor aberta, uma tentativa posterior de salvar deve ser bloqueada e explicar a mudança de responsabilidade.
- Gravações de versões antigas, inclusive de outra aba, não podem sobrescrever silenciosamente versões mais recentes.
- Validar no servidor Ideológico como booleano e Perfil como inteiro entre −2 e +2, admitindo ausência de resposta em rascunhos.
- Repetições de uma mesma solicitação não devem duplicar atribuições ou avaliações.

## 9. Acompanhamento e histórico

O painel deve exibir, por revisor e por recorte, quantidades atribuídas, não iniciadas, em andamento, devolvidas e concluídas, além do volume disponível para distribuição.

O histórico deve permitir reconstruir atribuições, retomadas, transferências, conclusões e devoluções, preservando autoria e datas. Alterações de acesso também devem ser registradas separadamente.

A estrutura lógica prevista inclui usuários, candidaturas, lotes de distribuição, atribuições, avaliações e eventos de histórico. A definição física do banco será feita na implementação.

## 10. Critérios de aceite essenciais

1. Distribuir 50 deputados federais de uma UF para cada um de dois revisores gera até 100 atribuições distintas, respeitando o ano e a disponibilidade confirmada.
2. Candidaturas pendentes de outro revisor não entram na seleção disponível.
3. Completar até 50 pendências atribui 30 para quem tem 20 e 5 para quem tem 45, segundo o recorte de carga explicitado.
4. Retomar pendências mantém a conta do revisor ativa e preserva trabalhos concluídos.
5. Suspender acesso impede uso da conta sem liberar automaticamente as atribuições.
6. Transferir trabalho em andamento preserva o rascunho anterior e sua autoria no histórico.
7. O antigo responsável não consegue salvar após a transferência, mesmo com a página aberta anteriormente.
8. Duas distribuições simultâneas não atribuem a mesma candidatura a revisores diferentes.
9. Reimportar uma planilha não apaga classificações nem cria tarefas duplicadas por turno.
10. Campos sem resposta não são contabilizados como Não ou Centro.
11. A tabela e os cartões apresentam ano, UF, partido, cargo, nome e os dois marcadores, nessa ordem.

## 11. Pontos pendentes e propostas complementares

- Definir o significado operacional de Ideológico e as instruções de classificação.
- Definir se completar carga e equilibrar redistribuição consideram todas as pendências do revisor ou somente as do recorte selecionado.
- Definir desempates e divisão quando faltarem candidaturas para atender todos os revisores.
- Definir se um novo responsável visualiza ou aproveita o rascunho anterior; a preservação histórica é obrigatória em qualquer caso.
- Confirmar a proposta “Não consigo avaliar”, com justificativa, e seu encaminhamento ao administrador. Essa opção não deve ser contabilizada como classificação concluída sem regra explícita.
- Definir o procedimento específico de nova revisão de avaliações já concluídas.
- Definir a visibilidade pública da consulta e de eventuais resultados agregados. A área do revisor permanece restrita ao trabalho atribuído.
- Confirmar os recursos da hospedagem antes de escolher API, autenticação e banco. O frontend deverá manter a identidade visual do site existente.

## 12. Sequência de implementação prevista

1. Resolver as definições operacionais pendentes e validar a identidade das candidaturas nas fontes.
2. Preparar banco e importação, incluindo cargo e prevenção de duplicidades.
3. Implementar convites, login e permissões.
4. Implementar painel, prévias, lotes fixos e completar carga.
5. Implementar área do revisor, rascunhos, conclusão e correções.
6. Implementar retomada, transferência, redistribuição e histórico.
7. Implementar acompanhamento e exportação.
8. Validar critérios de aceite, concorrência e experiência no celular antes da publicação.
