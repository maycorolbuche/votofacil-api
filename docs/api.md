# Contrato HTTP

JSON UTF-8; Authorization mantém `admin=<JWT>, user=<JWT>` (um ou ambos). Tokens têm claims type, room_id e code; token user também contém device_id. Middleware verifica registros atuais do banco. Sem cookies.

POST/PATCH/DELETE aceitam objeto JSON ou formulário URL encoded. IDs podem ser números ou strings numéricas positivas. Os erros de negócio preservam HTTP 500 e objeto `{ "error": "mensagem" }`; JSON inválido usa 400, rota/link ausente 404 e segredo não configurado 503. CORS OPTIONS usa 204. Campos internos name_key/last_sync_at não são expostos nos modelos.

| Caminho          | Método | Entrada                         | Retorno                                     |
| ---------------- | ------ | ------------------------------- | ------------------------------------------- |
| /                | GET    | —                               | []                                          |
| /admin/room      | POST   | — (público)                     | sala + token administrativo                 |
| /admin/room      | GET    | admin                           | room_id, code, is_active                    |
| /admin/room      | PATCH  | name e/ou status open/closed    | sala                                        |
| /admin/room      | DELETE | admin                           | sala excluída                               |
| /admin/candidate | POST   | name string ou lista            | candidato ou lista                          |
| /admin/candidate | PATCH  | id; name e/ou admin_votes       | candidato                                   |
| /admin/candidate | DELETE | id opcional; sem id limpa todos | candidato ou lista de votos removidos       |
| /admin/device    | PATCH  | id ou user_id; status           | dispositivo                                 |
| /admin/user      | POST   | device_id, name                 | eleitor adicional                           |
| /admin/user      | PATCH  | id, name                        | eleitor                                     |
| /admin/user      | DELETE | id                              | eleitor removido                            |
| /admin/vote      | DELETE | —                               | votos removidos; zera votos manuais         |
| /admin/config    | POST   | key, value                      | configuração                                |
| /admin/view      | POST   | —                               | configuração view.hash renovada             |
| /admin/view      | DELETE | —                               | configuração removida ou null               |
| /admin/sync      | GET    | admin                           | estado administrativo                       |
| /user/device     | POST   | room_code (público)             | dispositivo + token                         |
| /user/device     | GET    | user                            | room_id, code, is_active, device_id, status |
| /user/device     | DELETE | user                            | dispositivo removido                        |
| /user/user       | POST   | name                            | eleitor principal                           |
| /user/vote       | POST   | user_id, candidate_id           | voto registrado                             |
| /user/sync       | GET    | user                            | estado do dispositivo                       |
| /view/{hash}     | GET    | hash público                    | projeção                                    |

Todos os endpoints admin, exceto POST room, exigem token admin. Endpoints user, exceto POST device, exigem token user. Registro de voto exige aprovação; cadastro/sync aceitam dispositivo pendente. Reprovado perde acesso.

## Configurações

| Chave                     | Valores                   | Padrão |
| ------------------------- | ------------------------- | ------ |
| candidates.repeated_names | block/vote                | block  |
| candidates.insert_number  | block/allow/vote          | block  |
| candidates.initial_votes  | inteiro >= 0              | 0      |
| votes.room_auto_close     | booleano, true/false, 1/0 | true   |
| votes.num_candidates      | inteiro >= 1              | 1      |

`view.hash` e `view.phase` são internos, sem edição via admin/config.

## Sincronização administrativa

Retorna datetime UTC, users_last_sync (mapa por dispositivo ou [] se vazio), room, configs, view.hash e resume. Room contém id, code, is_active, status, uuid, name, candidates e devices. Candidatos incluem id, room_id, name, sequence, admin_votes, user_votes, total_votes, position e initials. Dispositivos têm id, room_id, status e users (id, device_id, name, is_primary, votes_count).

Resume contém admin_votes, user_votes, total_votes, total_candidates, total_users, total_devices e total_approved/pending/disapproved_users/devices. Configs mantém title/items/type/options/default/value como no PHP.

## Sincronização do eleitor

Sempre datetime, room_name e status. Estados: no-users, pending, open, closed, no-votes-left. users aparece quando há eleitores. Com votação aberta e escolhas pendentes, também retorna user (com votes_count), users_pending_count, votes (IDs de candidatos já escolhidos por esse eleitor), votes_max e candidates (id, sequence, name). Próximo eleitor prioriza is_primary e depois id. Candidatos são ordenados por nome em minúsculas. Não existe campo cache na nova versão, pois não há resposta de cache.
