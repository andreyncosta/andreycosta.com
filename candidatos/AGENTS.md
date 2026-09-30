# Regras para evoluir esta aplicação

O requisito prioritário do usuário é preservar dados em futuras evoluções. Antes de alterar persistência, leia `README.md` e `../docs/REGRAS_CLASSIFICACAO_CANDIDATOS.md`.

- Nunca apague/substitua banco, avaliações, eventos, atribuições antigas ou backups para resolver um problema.
- Dados persistentes ficam fora da raiz pública e fora do pacote de código. Não inclua dados reais, tokens ou senhas no Git, em testes ou em artefatos de deploy.
- Migrações aplicadas são imutáveis, incluindo `001.sql`. Evolua por novos arquivos numerados, aditivos, com backup e transação. Não use recriação de banco como migração.
- Preserve IDs e autoria. Cada salvamento de avaliação gera versão nova. Não remova as restrições de uma atribuição ativa e de imutabilidade do histórico.
- Toda escrita de revisor verifica conta ativa, responsabilidade atual, estado e versão dentro da transação.
- Mantenha suspensão de conta e retomada de trabalho como operações independentes.
- Mudanças em distribuição, importação ou persistência exigem os testes comportamentais e de concorrência em `tests.php`, incluindo recuperação do backup. Mudanças em formulários exigem também teste HTTP.
- Não execute testes sobre produção. Bancos de teste são temporários e isolados.
- Não publique uma versão de código incompatível com o schema. Ensaiar atualização e recuperação em cópia antes de aplicar em produção.
- Backups externos e recuperação são requisitos operacionais; não prometa perda zero só por existir backup no mesmo servidor.
