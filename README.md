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
aluno até a plataforma pelo botão "Continuar". Os **cursos próprios** da Dafnis (feitos no Rise 360,
Storyline ou outra ferramenta e exportados em SCORM 1.2) são feitos aqui mesmo, na loja (veja
[Cursos próprios](#cursos-próprios-scorm-12)).

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

### No Apache do XAMPP (`http://localhost/dafnis-loja`)

Em vez do `php -S`, a loja pode rodar no Apache do XAMPP, numa subpasta do `htdocs`. O `.htaccess` da raiz
manda tudo para `public/` e bloqueia o resto (`.env`, código, banco), como numa hospedagem compartilhada.

1. Ligue a pasta do projeto no `htdocs` sem copiar (uma junção, no PowerShell):
   `New-Item -ItemType Junction -Path C:\xampp\htdocs\dafnis-loja -Target C:\caminho\do\projeto`
2. No `.env`: `APP_URL=http://localhost/dafnis-loja` (a loja usa esse caminho em todos os links).
3. No XAMPP Control Panel, dê **Start** em **Apache** e **MySQL**.

Loja: `http://localhost/dafnis-loja` · Painel: `http://localhost/dafnis-loja/admin`. Os testes abaixo
também rodam nesse endereço (ex.: `php tests/flow.php http://localhost/dafnis-loja`). Para voltar ao
`php -S`, devolva o `APP_URL` para `http://localhost:8000`.

### Dados de demonstração (só desenvolvimento)

```bash
php bin/console db:fresh --demo      # APAGA o banco e recria com dados fictícios
```

Usuários (senha `dafnis123`): `admin@dafnis.test` (equipe), `ana@example.com` (aluna com curso em
andamento, concluído com certificado e pedido aguardando pagamento), `rh@example.com` (empresa com
vagas para indicar). Cupom de teste `BEMVINDO10`. Os e-mails ficam em `storage/mail` (`MAIL_DRIVER=log`).

A equipe de exemplo já vem com a verificação em duas etapas ativa (obrigatória para a equipe). Para
entrar no painel, adicione esta chave no aplicativo autenticador, como "chave de configuração", e use o
código que ele mostrar: `DAFN ISDE MOAD MIN2 FAKE Y234 567D AFNI`. Ela só existe nos dados de
demonstração. Para desenvolver sem o celular, coloque `TWO_FACTOR_TEAM_REQUIRED=false` no `.env` e rode
o `db:fresh --demo` de novo: a equipe de exemplo passa a entrar só com a senha.

### Testes

Com o servidor rodando e o banco recém-criado com `db:fresh --demo`:

```bash
php tests/flow.php http://localhost:8000     # compra de ponta a ponta
php tests/smoke.php http://localhost:8000    # todas as páginas por perfil
php tests/links.php http://localhost:8000    # rastreia links quebrados
php tests/scorm.php http://localhost:8000    # cursos próprios: importação, página do curso, andamento e tempo
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

## Cursos próprios (SCORM 1.2)

Curso com conteúdo próprio não depende da plataforma de ensino: o aluno faz o curso dentro da loja.

1. **Exportar.** Na ferramenta de autoria, exporte para **LMS em SCORM 1.2** (no Rise 360: *Publish › LMS*,
   formato SCORM 1.2, acompanhamento pelo resultado da prova). Envie o `.zip` como veio, sem descompactar.
2. **Importar.** Em *Painel › Cursos › (curso) › Conteúdo on-line próprio*, envie o `.zip`. O limite de envio
   pelo painel é o menor entre `SCORM_MAX_MB` e o `upload_max_filesize`/`post_max_size` do PHP. Pacote
   maior (com vídeos): copie para o servidor e rode
   `php bin/console scorm:import --course=id-ou-slug --file=pacote.zip` (`--draft` importa fora de uso).
   A importação confere o `imsmanifest.xml`, recusa SCORM 2004 e qualquer arquivo de tipo não previsto
   (`.php`, por exemplo) e guarda tudo em `storage/scorm`, fora da web.
3. **Conferir.** "Pré-visualizar" abre o curso como o aluno vê, sem gravar nada.
4. **Liberar.** Com uma versão em uso, liberar a vaga (*Matrículas › Em andamento*) já basta: o e-mail de
   acesso e o botão de *Meus cursos* levam ao curso na loja, em `/minha-conta/cursos/{vaga}/estudar`.

**O que a loja registra.** A página do curso oferece a API do SCORM 1.2 (`public/assets/js/scorm-player.js`):
situação (`cmi.core.lesson_status`), nota, ponto de parada (`suspend_data`), respostas da prova e o tempo
informado pelo curso. À parte, a loja mede o **tempo de estudo**: a cada minuto a página avisa se o curso está
aberto, com a aba visível e com alguma atividade nos últimos 10 minutos (parado, o tempo pausa e aparece
"Você ainda está aí?"). Cada abertura é um acesso, com data, IP e duração, em *Matrículas › (vaga)*.

**Conclusão.** Aprovação (ou conclusão, conforme o acompanhamento escolhido na exportação) põe o progresso
em 100% e avisa a equipe por e-mail. Sem prática obrigatória, a vaga vira "Concluído" e a equipe emite o
certificado. Com prática obrigatória (NR 10 Básico, por exemplo), a vaga continua "Em andamento" até a equipe
registrar o certificado depois da parte presencial. Aprovação não volta atrás: refazer a prova depois de
aprovado não troca a situação nem a nota.

**Versões.** Cada envio é uma versão nova. Quem já começou continua na versão em que começou (o ponto de
parada de uma versão não serve para outra); os novos participantes recebem a versão em uso. Versões com
participantes não podem ser excluídas.

**Segurança.** Os arquivos só são entregues ao participante da vaga (ou à equipe, na pré-visualização) e só
abrem dentro da loja (`frame-ancestors 'self'`). O conteúdo exportado pelas ferramentas usa scripts
embutidos, então roda com uma CSP própria, mais aberta que a da loja, no mesmo domínio: envie só pacotes
gerados pela própria Dafnis.

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
pagamento confirmado, acesso liberado, certificado disponível, recuperação de senha e o aviso quando a
verificação em duas etapas é ativada ou desativada. A equipe recebe
novos pedidos, pagamentos e contatos no `MAIL_ADMIN_ADDRESS` (ou no e-mail de contato das Configurações).

## Verificação em duas etapas

**Obrigatória para a equipe** e opcional para alunos e empresas, em *Minha conta › Meus dados*. Quem
tem perfil de equipe e ainda não ativou é levado à ativação ao abrir o painel, e não pode desativar a
própria. Com `TWO_FACTOR_TEAM_REQUIRED=false` no `.env`, fica opcional também para a equipe (o painel
mostra um lembrete para ativar); em produção, deixe `true`. Depois da senha, o login pede o código de 6
dígitos de um aplicativo autenticador (Google Authenticator, Microsoft Authenticator...), que muda a
cada 30 segundos. Ao ativar, a pessoa lê um QR code (gerado na própria loja, sem serviço externo) e
recebe 10 códigos de recuperação, que valem uma vez cada, para quando perder o celular.

- A chave de cada conta fica criptografada no banco com a `APP_KEY` do `.env`. Sem ela, a loja cria
  `storage/app.key` (fora do Git). **Guarde essa chave com o backup do banco:** sem ela, quem ativou a
  verificação precisa ativar de novo.
- A senha nova pelo link de "Esqueci a senha" também pede o código antes de entrar.
- Quem perdeu o celular e os códigos: outra pessoa da equipe desativa em *Painel › Usuários › (pessoa)*,
  depois de confirmar que é a própria pessoa pedindo. Ela recebe um aviso por e-mail e, se for da
  equipe, ativa de novo no próximo acesso ao painel.
- Se for a única pessoa da equipe: no servidor, `php bin/console 2fa:disable --email=...`.

## Publicação em hospedagem

1. Envie os arquivos e aponte o domínio para a pasta **`public/`** (se não der, o `.htaccess` da raiz
   redireciona para ela e bloqueia o resto).
2. Crie o banco e o `.env` com `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`,
   `SESSION_SECURE=true` e uma `APP_KEY` (`php -r "echo base64_encode(random_bytes(32));"`).
3. Com SSH: `php bin/console migrate && php bin/console seed && php bin/console admin:create ...`.
   Sem SSH: defina `INSTALL_TOKEN` no `.env`, abra `/instalar`, e depois apague o token.
4. Em *Painel › Configurações*, preencha contatos, CNPJ, redes sociais, link da plataforma de ensino e
   os indicadores reais da empresa (campos vazios não aparecem na loja).

## Aparência

- **Foto do topo da home:** `public/assets/img/hero-960.jpg`, `hero-1600.jpg` e `hero-2400.jpg` (a mesma
  foto em três larguras). Para trocar, substitua os três arquivos mantendo os nomes; sem eles, o topo fica
  com o fundo azul e a grade. A foto atual é de Rafael Juárez no Unsplash (licença Unsplash, uso comercial
  livre).
- **Topo da home:** ocupa a tela inteira e o cabeçalho fica transparente sobre a foto até o visitante
  rolar a página (nas outras páginas ele é sempre sólido). A barra de aviso acima do cabeçalho só aparece
  quando a equipe escreve um aviso em *Painel › Configurações*; o aviso de pagamento em ativação fica no
  carrinho, no checkout e no pedido.
- **Modo escuro:** botão de sol/lua no cabeçalho (no celular, dentro do menu). Sem escolha, a loja segue o
  tema do sistema do visitante; a escolha fica salva no navegador. O painel da equipe fica sempre claro.
  As cores ficam em tokens no início de `app.css`, e a versão escura no bloco "Modo escuro" no fim.

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

Depois, publique o conteúdo de `../dafnis-pages` no branch `gh-pages` e ative o Pages no repositório
(Settings › Pages › branch `gh-pages`). **Em 30/09/2026 a prévia foi tirada do ar** (branch `gh-pages` apagado). Na loja publicada o modo
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
app/Services/Scorm/                          cursos próprios: pacotes SCORM, andamento e tempo de estudo
app/Services/Payments/                       interface do gateway, Mercado Pago e modo manual
app/Views/                                   layouts, loja, conta, painel e e-mails
public/assets/{css,js}                       estilos do protótipo e JavaScript
database/migrations/                         esquema do banco (001) e alterações seguintes
database/data/cursos.json                    catálogo importado da planilha
```
