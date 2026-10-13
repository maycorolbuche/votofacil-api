# VotoFácil API

API de votação em **TypeScript para Cloudflare Workers e D1**, substituindo PHP/Lumen. O banco começa vazio: não há importação nem backup dos dados antigos. Tokens e links antigos não valem neste banco.

## Configurar a Cloudflare e publicar

Pré-requisitos: Node.js 24+, npm, uma conta Cloudflare e acesso ao repositório. Execute na raiz do projeto, pelo terminal (PowerShell também funciona):

```sh
npm ci
npx wrangler login
npx wrangler d1 create votofacil
```

O último comando cria um D1 e mostra seu `database_id`. Copie esse UUID para `d1_databases[0].database_id` em `wrangler.jsonc`, substituindo o UUID de zeros. Preserve `binding: "DB"` e `database_name: "votofacil"`. O Worker acessa o banco pelo binding; não há host, porta, usuário ou senha de MySQL.

Configure `vars.CORS_ORIGINS` em `wrangler.jsonc`. Pode conter várias origens separadas por vírgula, por exemplo `https://app.exemplo.com,https://outro.exemplo.com`. Origem inclui protocolo e porta quando houver, sem caminho e sem barra final. O padrão `*` aceita qualquer origem sem cookies/credenciais; a autenticação usa Authorization. Para um app público com origem conhecida, prefira informar sua origem.

Crie as tabelas e publique:

```sh
npm run db:remote
npm run deploy
```

Cadastre o segredo de assinatura (Wrangler solicita o valor sem colocá-lo no comando):

```sh
npx wrangler secret put JWT_SECRET
```

Use um segredo aleatório de pelo menos 32 caracteres. Para gerar um valor no seu computador:

```sh
node -e "console.log(require('node:crypto').randomBytes(32).toString('hex'))"
```

O segredo não deve ir para Git, `vars` ou código. Até sua configuração, criação de salas/dispositivos retorna 503. Rotacionar o segredo invalida tokens emitidos antes da troca.

No painel: **Workers & Pages → votofacil-api → Settings → Variables and Secrets** permite conferir o Secret; **Bindings** deve conter o D1 `DB`; **Triggers/Cron Triggers** deve conter `0 * * * *`. O Wrangler configura binding e cron a partir do arquivo.

A implantação mostra uma URL `https://votofacil-api.<sua-conta>.workers.dev`. Configure esse endereço base no app consumidor (`src/services/Api.js` no repositório `maycorolbuche/votofacil`). O app atual usa `https://votofacil-api.maycorolbuche.com.br/`: se esse domínio for ligado ao Worker, o endereço de produção pode ser preservado. Para usar domínio próprio, abra **Settings → Domains & Routes → Add → Custom Domain**, informe um subdomínio de uma zona Cloudflare da conta e configure o app para esse endereço. Preserve os caminhos `/admin`, `/user` e `/view`.

Não há servidor PHP, `.htaccess`, Composer, Artisan ou conexão MySQL neste projeto. Não execute a migração local esperando criar tabelas na nuvem: `db:remote` usa explicitamente `--remote`.

## Desenvolvimento local

Copie `.dev.vars.example` para `.dev.vars` e substitua `JWT_SECRET` por um segredo local aleatório. Esse arquivo é ignorado pelo Git. Mantenha segredos locais diferentes dos de produção.

```sh
npm run db:local
npm run dev
npm run typecheck
npm test
npx wrangler deploy --dry-run
```

O banco local é separado do remoto. A suíte usa bancos descartáveis em memória e um teste no runtime Workers/D1 (Miniflare), sem acessar produção. A CI verifica tipos, testes e empacotamento; não publica automaticamente.

## Documentação

- [Arquitetura e regras](docs/architecture.md)
- [Rotas, autenticação e respostas](docs/api.md)
- [Projeção pública](docs/projection.md)
- [Validação e entrada em produção](docs/deployment.md)

Toda alteração deve avaliar e atualizar a documentação correspondente. Trabalhar em branch e PR, sem commit direto ou merge automático em main.
