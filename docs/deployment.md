# Validação e entrada em produção

## Processo

1. Seguir README para criar D1 vazio, preencher database_id, configurar CORS, aplicar migration remota, publicar Worker e cadastrar JWT_SECRET.
2. Validar a API na URL workers.dev usando um app de homologação ou cliente HTTP. Nenhum dado antigo precisa ser importado.
3. Confirmar criação de sala/token, ingresso do dispositivo, cadastro principal/adicional, aprovação, candidatos individuais/em lote e configurações.
4. Abrir votação, enviar múltiplas escolhas, confirmar bloqueio de duplicidade/limite/dispositivo pendente e fechamento manual/automático.
5. Conferir totais online + manuais e empates; abrir projeção em outro navegador sem sessão, testar renovar/revogar link e reabrir votação.
6. Testar exclusão de eleitor principal/dispositivo, limpeza de votos/candidatos e invalidação de tokens após excluir sala.
7. Trocar o endereço base no aplicativo e validar CORS pelo navegador antes de remover a hospedagem PHP.

O repositório do frontend não é alterado nesta PR. A camada `src/services/Api.js` de `maycorolbuche/votofacil` foi consultada: usa JSON, DELETE com corpo e Authorization com os dois tokens. O endereço de produção está fixado em `https://votofacil-api.maycorolbuche.com.br/`. O router aceita barras duplicadas, pois o helper concatena uma barra a uma base que já termina com barra. Os caminhos e payloads centrais são preservados, mas o fluxo real deve ser homologado no app, especialmente flags 0/1, erros, ordenação e os comportamentos corrigidos descritos em architecture.md. Atualize qualquer referência ao endereço antigo. Links/salas/tokens antigos precisam ser recriados.

## Testes automatizados

`npm run typecheck` verifica tipos. `npm test` cobre projeção, votos concorrentes, limites, propriedade, sincronização, classificação, configuração, cascatas, manutenção, CORS e rotas removidas. O teste runtime usa Miniflare com um D1 real local do Workers e dispara requisições concorrentes. Bancos de testes são descartáveis e não exigem credenciais Cloudflare. `wrangler deploy --dry-run` verifica bundle/bindings sem publicar.

## Operação

Cron `0 * * * *` roda em UTC e usa as políticas de oito/48 horas. Execute acompanhamento no painel de logs ou `npx wrangler tail`. Não registre tokens ou corpos de voto nos logs. Avalie consumo e limites de Workers/D1 com tráfego e intervalo de polling reais; não há garantia de volume baseada apenas no código.

O schema utiliza triggers, chaves estrangeiras compostas e view com janela SQL. Migrations são versionadas e aplicadas por Wrangler, nunca por endpoint público. Nova mudança de schema exige novo arquivo de migration, sem editar migrations já aplicadas.

A CI não publica. Configurar GitHub como fonte de build da Cloudflare é opcional; antes de automatizar produção, configure credenciais/ambiente de implantação separadamente. Não há tentativa de acessar sua conta ou publicar recursos nesta PR.
