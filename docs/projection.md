# Consulta pública da projeção

`GET /view/{hash}` usa o hash de 64 caracteres já criado por `POST /admin/view`. Não exige JWT e não modifica geração/renovação/revogação de links. Retorna 404 para hash inválido, revogado, sala ausente, inativa ou excluída.

Resposta 200: `phase` (`preparing`, `voting`, `results`), sala, lista de candidatos, dispositivos com status/nomes/quantidade de escolhas dos eleitores e limite de escolhas. O payload é montado explicitamente, sem serializar modelos completos, tokens, UUIDs, dados administrativos ou escolhas individuais. Enquanto aberta/em preparação, candidatos têm somente id, nome e sequência, sem totais, contadores, posições ou ordenação por votos. Após fechar, total_votes soma votos registrados e administrativos; não depende dos contadores desnormalizados durante o fechamento automático.

As leituras do payload ocorrem numa transação e não usam o cache administrativo. Cache-Control no-store evita manter resultados após reabertura e links após revogação. O app consulta periodicamente; as alterações aparecem na próxima resposta.

Para distinguir sala nova de encerrada em qualquer aparelho, transições de status gravam view.phase em configs (sem migration). A limpeza de votos/candidatos reinicia essa fase, sem alterar as regras ou o status existente da votação. Para salas antigas sem essa marca, votos online em sala fechada indicam resultado; não se presume resultado a partir de votos iniciais/manuais. Uma apuração somente manual anterior à instalação precisa de uma nova transição abrir/fechar.

Publicar a API antes da versão correspondente do front. Executar `composer install` e `vendor/bin/phpunit tests/ProjectionTest.php`. Os testes isolam o banco SQLite em memória e cobrem anonimato, sigilo durante votação, progresso, encerramento/reabertura, links existentes, revogação, isolamento de salas e limpeza. O workflow Projection tests executa essa suíte no PR.
