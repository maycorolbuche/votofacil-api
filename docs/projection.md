# Consulta pública da projeção

GET /view/{hash} aceita o hash de 64 caracteres criado por POST /admin/view. Não exige JWT; renovar ou excluir hash revoga o link anterior. Sala ausente/inativa/deleted ou hash inválido retorna 404. Hash é um segredo de compartilhamento: quem o possui pode visualizar os nomes/progresso da sala.

Payload: phase (`preparing`, `voting`, `results`), room (id, name, code, status, is_active, candidates, devices) e configs.votes.items.num_candidates.value. Candidatos contêm id, name, sequence; total_votes aparece apenas em results com sala fechada. Dispositivos approved/pending aparecem com status e users (id, name, votes_count). Não há token, UUID, IDs de dispositivo ou escolhas individuais.

O estado preparing distingue sala nova de votação encerrada. Abrir define voting; fechar define results. Limpar votos/candidatos redefine preparing em sala fechada ou voting em aberta. Reabrir oculta resultados. Para registros sem view.phase, sala fechada com votos online é tratada como results; não se presume resultado apenas por votos manuais.

O payload usa um único SELECT no D1, sem cache e sem consultas separadas que possam misturar fases. Cache-Control no-store, Pragma no-cache e X-Robots-Tag noindex,nofollow são enviados. App deve continuar fazendo polling; não foi introduzido WebSocket. Nova tela recebe a fase persistida, sem depender de histórico no navegador.
