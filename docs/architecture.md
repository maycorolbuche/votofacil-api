# Arquitetura e regras

`src/index.ts` recebe `fetch` e `scheduled`, resolve as rotas, valida entrada e monta JSON. `src/auth.ts` assina/verifica JWT HS256 usando Web Crypto. `src/db.ts` define o contrato mínimo de D1 e helpers de statements parametrizados. `src/services.ts` concentra configurações, encerramento, projeção e manutenção. Não há dependência em framework HTTP no runtime.

## Banco

A migration `0001_initial.sql` cria rooms, devices, users, candidates, votes e configs. IDs autoincrementais não são reutilizados ao excluir registros. Datas persistidas seguem UTC no formato `YYYY-MM-DD HH:mm:ss`. Flags são 0/1 nos endpoints administrativos; a projeção usa booleano JSON para `is_active`. `users_last_sync` usa strings UTC com seis casas fracionárias, como a serialização Carbon anterior.

Chaves compostas impedem votos vinculados a eleitor/dispositivo/candidato de outra sala. Exclusões usam cascata; remover o eleitor principal remove o dispositivo e seus demais eleitores. Restrição única impede repetir candidato por eleitor. Todos os valores externos usam bindings SQL; nomes das tabelas/colunas são internos.

`candidate_results` deriva `user_votes`, `total_votes` e `position` dos votos persistidos e dos votos manuais; não há contadores armazenados que possam divergir. Posição usa DENSE_RANK por sala: empate 1,1,2. Diferentemente do PHP, a administração recebe apuração atualizada também durante votação; a API pública e a do eleitor nunca recebem esses totais enquanto aberta. Após limpar votos, candidatos empatam na posição 1, em vez do marcador antigo 0.

Nomes de candidatos têm chave Unicode NFC em minúsculas (`pt-BR`). A comparação preserva diferenças entre nomes com e sem acento. Sequência é calculada dentro do INSERT e é única por sala. Cadastro em lote ignora nomes vazios/repetidos, trunca em 50 caracteres e limita 100 inserções; regras de nomes numéricos e votos por repetição aplicam-se ao cadastro individual, conforme o legado.

## Consistência da votação

O trigger BEFORE INSERT valida sala ativa/aberta, dispositivo aprovado, duplicidade e limite de escolhas no mesmo statement da gravação. As relações compostas validam propriedade. O AFTER INSERT verifica conclusão de todos os eleitores aprovados e fecha a sala quando configurado. As mutações de configuração/dispositivo/exclusão que demandam verificação usam batch com o fechamento. Nenhuma decisão crítica depende de estado em memória ou cache eventual.

O trigger de status registra `view.phase` na transição abrir/fechar. Limpar candidatos/votos redefine a fase sem alterar status. Projeção inteira usa um SELECT que produz JSON em um snapshot coerente, evitando exposição de totais durante reabertura.

## Cache e atividade

Sem cache em arquivos/KV; toda resposta tem `Cache-Control: no-store`. `last_sync_at` substitui presença em cache, com no máximo uma gravação por minuto/dispositivo. Não altera `updated_at`, logo polling não mantém salas indefinidamente ativas. Custo de polling deve ser medido no tráfego real antes de introduzir cache.

## Manutenção

Cron a cada hora, em UTC: desativa salas sem alterações de negócio por oito horas; limpa dados das salas inativas sem alterações por 48 horas e mantém a linha rooms como `deleted`. Considera updated_at de sala, dispositivos, usuários, candidatos, configs e votos. Cleanup e desativação são um batch transacional; erros são propagados para os logs. A frequência é agendada, não condicionada ao próximo acesso HTTP.

## Correções intencionais

- Dispositivo pendente não pode registrar voto (no PHP, o middleware só bloqueava reprovados).
- Booleanos textuais false/0 são interpretados corretamente; opções e inteiros são validados.
- Erros internos não são enviados com SQL ou detalhes de infraestrutura ao cliente.
- CORS não combina origem `*` com credenciais; autenticação continua por Authorization.
- `/install` e `/clear` são removidos (404); instalação é responsabilidade do Wrangler.
- JWT exige segredo de pelo menos 32 caracteres e valida algoritmo e vínculo do dispositivo à sala.

Não há importação/backup dos dados antigos, WebSockets, anexos, recuperação de token administrativo, login por senha ou mecanismo de renovação JWT. Tokens não têm expiração, preservando o contrato legado; exclusão/inatividade da sala ou exclusão/reprovação do dispositivo revogam acesso nas consultas seguintes.
