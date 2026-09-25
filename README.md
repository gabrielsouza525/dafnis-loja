# Dafnis Treinamentos — loja de cursos

Loja online de treinamentos da **Dafnis Soluções em EPI**: Normas Regulamentadoras, segurança do
trabalho, primeiros socorros, brigada de incêndio, simuladores e cursos corporativos. O visual segue o
protótipo *"Dafnis Treinamentos — Loja NR"* (Claude Design).

1. **Loja**: home, catálogo com busca e filtros combinados, página de cada curso, páginas por NR e
   categoria, carrinho, checkout (pessoa física ou empresa), contato, termos e privacidade.
2. **Área do aluno** (`/minha-conta`): painel, meus cursos com progresso, certificados, pedidos,
   indicação dos participantes de cada vaga (compras para empresas) e dados pessoais.
3. **Painel da equipe** (`/admin`): pedidos e baixa de pagamento, matrículas (liberar acesso,
   progresso, anexar certificado), cursos, categorias, cupons, usuários, contatos e configurações.

Os cursos são feitos na **plataforma de ensino white label**: a loja vende, registra as vagas e leva o
aluno até a plataforma pelo botão "Continuar".

---

## Tecnologias

- **PHP 8.1+** sem framework (mesma base do Rafa Car): roteador, middlewares, views, PDO com prepared
  statements reais, sessões seguras, CSRF, limite de tentativas, CSP.
- **MySQL 5.7+ / MariaDB 10.3+** (InnoDB, utf8mb4, chaves estrangeiras).
- **HTML, CSS e JavaScript puros**, sem bibliotecas. Fontes do protótipo (Schibsted Grotesk, IBM Plex
  Sans, IBM Plex Mono) pelo Google Fonts. Tudo funciona sem JavaScript; com ele, filtros, carrinho e
  avisos ficam instantâneos.

## Rodando localmente (XAMPP)

```bash
cp .env.example .env                 # ajuste DB_* se precisar
php bin/console db:create            # cria o banco "dafnis"
php bin/console migrate              # cria as tabelas
php bin/console seed                 # categorias, 119 cursos da planilha e configurações
php bin/console admin:create --name="Seu Nome" --email=voce@dominio.com.br --password=SenhaForte123
php -S localhost:8000 -t public public/index.php
```

No Windows, use `C:\xampp\php\php.exe` no lugar de `php` se ele não estiver no PATH, e deixe o MySQL
do XAMPP ligado. Loja: `http://localhost:8000` · Painel: `http://localhost:8000/admin`.

### Dados de demonstração (só desenvolvimento)

```bash
php bin/console db:fresh --demo      # APAGA o banco e recria com dados fictícios
```

Usuários (senha `dafnis123`): `admin@dafnis.test` (equipe), `ana@example.com` (aluna com curso em
andamento, concluído com certificado e pedido aguardando pagamento), `rh@example.com` (empresa com
vagas para indicar). Cupom de teste `BEMVINDO10`. Os e-mails ficam em `storage/mail` (`MAIL_DRIVER=log`).

### Testes

Com o servidor rodando e o banco recém-criado com `db:fresh --demo`:

```bash
php tests/flow.php http://localhost:8000     # compra de ponta a ponta, 72 verificações
php tests/smoke.php http://localhost:8000    # todas as páginas por perfil
php tests/links.php http://localhost:8000    # rastreia links quebrados
node tests/browser.mjs http://localhost:8000 # comportamento do JavaScript no Chrome (headless)
```

Rode `db:fresh --demo` antes de cada um: eles criam pedidos e mudam o estado do banco.

---

## Como funciona a venda

1. O cliente escolhe o curso e o **número de participantes** (vagas) e finaliza pelo carrinho.
2. No checkout, entra ou cria a conta, informa CPF (pessoa física) ou CNPJ (empresa), a forma de
   pagamento (Pix, cartão ou boleto) e aceita os termos. O pedido nasce **aguardando pagamento**.
3. **Pagamento**
   - **Mercado Pago configurado:** o cliente paga no Checkout Pro do Mercado Pago. A confirmação chega
     pelo webhook (e pelo retorno do checkout) e é conferida direto na API; valor e moeda precisam bater.
   - **Sem Mercado Pago:** a loja avisa que o pagamento online está em ativação, a equipe envia as
     instruções e dá a **baixa manual** em *Painel › Pedidos*. Nada é aprovado sozinho e nenhuma tela
     mostra "pagamento aprovado" sem pagamento confirmado.
4. Com o pagamento confirmado são criadas as **vagas**. Compra de pessoa física com 1 vaga já fica no
   nome do comprador; nas demais, o comprador indica nome, e-mail e CPF de cada participante em
   *Minha conta › Pedidos e vagas*.
5. A equipe cadastra o participante na plataforma de ensino e marca a vaga como **Em andamento**
   (*Painel › Matrículas*). O participante recebe o link por e-mail e vê o curso em *Meus cursos*.
6. Ao concluir, a equipe anexa o **PDF do certificado** (ou o link da plataforma). O participante é
   avisado e baixa em *Minha conta › Certificados*; a empresa compradora também vê os certificados da equipe.

O participante enxerga os cursos quando entra com o **mesmo e-mail** informado na vaga.

## Mercado Pago

1. Em *Mercado Pago › Suas integrações*, crie uma aplicação (Checkout Pro) e copie o **Access Token de
   produção** para `MP_ACCESS_TOKEN` no `.env`.
2. Em *Webhooks*, cadastre `https://SEU-DOMINIO/webhooks/mercadopago` com o evento **Pagamentos** e
   copie a **chave secreta** para `MP_WEBHOOK_SECRET` (a loja confere a assinatura `x-signature`).
3. Para testar antes, use credenciais de teste e `MP_SANDBOX=true`.
4. O webhook e o retorno automático só funcionam com `APP_URL` em **https** (exigência do Mercado Pago).

Se um aviso se perder, o botão **Consultar** no pedido (painel) busca o pagamento pelo ID.
Para trocar de gateway, implemente `app/Services/Payments/PaymentGateway.php` e registre em
`Payments::gateway()`.

## E-mail

Com `MAIL_DRIVER=smtp` e os dados do provedor no `.env`, a loja envia: conta criada, pedido recebido,
pagamento confirmado, acesso liberado, certificado disponível e recuperação de senha. A equipe recebe
novos pedidos, pagamentos e contatos no `MAIL_ADMIN_ADDRESS` (ou no e-mail de contato das Configurações).

## Publicação em hospedagem

1. Envie os arquivos e aponte o domínio para a pasta **`public/`** (se não der, o `.htaccess` da raiz
   redireciona para ela e bloqueia o resto).
2. Crie o banco e o `.env` com `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...` e
   `SESSION_SECURE=true`.
3. Com SSH: `php bin/console migrate && php bin/console seed && php bin/console admin:create ...`.
   Sem SSH: defina `INSTALL_TOKEN` no `.env`, abra `/instalar`, e depois apague o token.
4. Em *Painel › Configurações*, preencha contatos, CNPJ, redes sociais, link da plataforma de ensino e
   os indicadores reais da empresa (campos vazios não aparecem na loja).

## Prévia no GitHub Pages

O GitHub Pages não roda PHP, então a prévia para o cliente é uma **cópia estática** gerada da loja
real: todas as páginas públicas (com busca e filtros rodando no navegador) e "fotografias" do carrinho,
checkout, confirmação, Minha conta e painel, feitas durante uma compra de exemplo. Os botões levam de
uma tela à outra; nada é gravado. Fica no branch `gh-pages`, sem indexação no Google.

```bash
php bin/console db:fresh --demo
STATIC_DEMO=true APP_URL=https://gabrielsouza525.github.io/dafnis-loja php -S localhost:8001 -t public public/index.php
STATIC_DEMO=true APP_URL=https://gabrielsouza525.github.io/dafnis-loja php bin/static-export.php http://localhost:8001 ../dafnis-pages
```

Depois, publique o conteúdo de `../dafnis-pages` no branch `gh-pages`. Na loja publicada o modo
prévia fica desligado (sem `STATIC_DEMO` no ambiente).

## Catálogo

Os 119 cursos vêm da planilha de valores sugeridos (EAD 2025), com preço = "Valor do mercado".
Regras, correções e as **divergências de carga horária com o catálogo 2026** estão em
[`docs/catalogo.md`](docs/catalogo.md).

## Estrutura

```
app/Controllers/{Site,Auth,Account,Admin}   rotas em routes/web.php
app/Services/Catalog.php                     busca, filtros combinados e ordenação
app/Services/Cart.php, Orders.php            carrinho na sessão e ciclo do pedido
app/Services/Enrollments.php                 vagas, liberação e certificados
app/Services/Payments/                       interface do gateway, Mercado Pago e modo manual
app/Views/                                   layouts, loja, conta, painel e e-mails
public/assets/{css,js}                       estilos do protótipo e JavaScript
database/migrations/001_schema.sql           esquema do banco
database/data/cursos.json                    catálogo importado da planilha
```
