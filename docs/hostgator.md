# Publicação na HostGator (cPanel)

Passo a passo para colocar a loja no ar numa hospedagem compartilhada da HostGator. Os nomes dos menus
são os do cPanel; alguns aparecem em português ou em inglês, conforme o idioma escolhido.

## 1. O que separar antes

- **Plano** de hospedagem Linux com cPanel e o **domínio** apontado para a HostGator (se o domínio foi
  registrado em outro lugar, troque os servidores DNS pelos que a HostGator informar no e-mail de boas-vindas).
- **Pacote da loja**: no computador de desenvolvimento, na pasta do projeto:

  ```bash
  git archive --format=zip -o ../dafnis-loja-publicacao.zip HEAD
  ```

  Ele leva só o que vai para o servidor (sem testes, sem `.env`, sem dados locais).
- **Pacote do curso NR-10** exportado do Rise (`nr10-basico-scorm12.zip`), se for publicar o curso próprio.

## 2. PHP

1. **cPanel › MultiPHP Manager**: escolha **PHP 8.2** (ou mais nova) para o domínio. Faça isto **depois
   de extrair os arquivos** (passo 4): o cPanel grava a versão no `.htaccess` do `public_html`, e o
   `.htaccess` da loja substitui o que estava lá. Se a versão do PHP voltar para a antiga, salve de novo aqui.
2. **cPanel › Select PHP Version › Extensions** (se existir no seu plano): confirme `pdo_mysql`, `mbstring`,
   `openssl`, `curl`, `fileinfo`, `gd`, `zip` e `phar`. Em geral já vêm ativas.
3. **cPanel › MultiPHP INI Editor** (modo básico), para os pacotes de curso:
   `upload_max_filesize = 128M`, `post_max_size = 128M`, `memory_limit = 256M`, `max_execution_time = 120`.

## 3. Banco de dados

1. **cPanel › Bancos de dados MySQL**: crie o banco (ex.: `dafnis`). A HostGator acrescenta o seu usuário
   do cPanel na frente: fica algo como `usuario_dafnis`.
2. Na mesma tela, crie um **usuário** com senha forte e **adicione-o ao banco com todos os privilégios**.
3. Anote os três nomes completos (banco, usuário, senha). O servidor é `localhost`.

## 4. Arquivos

**Domínio principal** (o mais comum): tudo vai dentro de `public_html`.

1. **cPanel › Gerenciador de arquivos** › `public_html` › **Carregar** o `dafnis-loja-publicacao.zip`.
2. Clique com o botão direito no zip › **Extract** (extrair em `public_html`). Apague o zip e, se houver,
   a página padrão da HostGator (`index.html` ou `default.html`).
3. O `.htaccess` da raiz manda os visitantes para a pasta `public/` e bloqueia todo o resto
   (`.env`, código, banco, `storage`).
4. Volte ao **MultiPHP Manager** e escolha a versão do PHP (passo 2.1).

**Domínio adicional ou subdomínio**: dá para fazer do jeito ideal. Extraia o projeto numa pasta fora de
`public_html` (ex.: `~/dafnis-loja`) e, em **cPanel › Domínios**, aponte a **raiz do documento** para
`dafnis-loja/public`.

## 5. Arquivo .env

No Gerenciador de arquivos, copie `.env.example` para `.env` (ative "Mostrar arquivos ocultos" nas
configurações) e edite:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=http://seudominio.com.br        # troque para https:// no passo 6
APP_KEY=                                # gere uma (abaixo) e guarde junto com o backup
DB_HOST=localhost
DB_DATABASE=usuario_dafnis
DB_USERNAME=usuario_dafnisuser
DB_PASSWORD=a-senha-do-banco
SESSION_SECURE=false                    # true no passo 6
TWO_FACTOR_TEAM_REQUIRED=true
INSTALL_TOKEN=uma-frase-longa-qualquer  # só para instalar; apague depois
```

`APP_KEY`: rode `php -r "echo base64_encode(random_bytes(32));"` no seu computador (ou no Terminal do
cPanel) e cole o resultado. **Guarde essa chave** com os backups: sem ela, quem ativou a verificação em
duas etapas precisa ativar de novo.

## 6. HTTPS

1. **cPanel › SSL/TLS Status**: rode o **AutoSSL** para o domínio (o certificado gratuito sai em minutos,
   às vezes em algumas horas).
2. Com `https://seudominio.com.br` abrindo sem aviso de segurança, mude no `.env`:
   `APP_URL=https://seudominio.com.br` e `SESSION_SECURE=true`. A partir daí a loja leva quem chega por
   `http://` para `https://` sozinha. O Mercado Pago só funciona com https.

## 7. Instalar

1. Abra `https://seudominio.com.br/instalar`. O topo da página mostra o **diagnóstico do servidor**
   (PHP, extensões, pastas, banco, HTTPS). Resolva o que estiver como obrigatório.
2. Informe o `INSTALL_TOKEN`, o seu nome, e-mail e senha de administrador e clique em **Instalar**:
   cria as tabelas, importa os 119 cursos e cadastra você.
3. **Apague o `INSTALL_TOKEN` do `.env`.**
4. Entre em `/login`. No primeiro acesso ao painel, ative a verificação em duas etapas (obrigatória para a equipe).

Com acesso SSH (cPanel › Terminal, ou SSH liberado no plano), o mesmo pode ser feito por comando, dentro
da pasta da loja: `php bin/console check`, `php bin/console migrate`, `php bin/console seed` e
`php bin/console admin:create --name="..." --email=... --password=...`.

## 8. E-mail

1. **cPanel › Contas de e-mail**: crie, por exemplo, `contato@seudominio.com.br` (recebe os avisos da
   equipe) e `naoresponda@seudominio.com.br` (envia as mensagens da loja).
2. No `.env`:

   ```ini
   MAIL_DRIVER=smtp
   MAIL_HOST=mail.seudominio.com.br
   MAIL_PORT=465
   MAIL_ENCRYPTION=ssl
   MAIL_USERNAME=naoresponda@seudominio.com.br
   MAIL_PASSWORD=a-senha-da-conta
   MAIL_FROM_ADDRESS=naoresponda@seudominio.com.br
   MAIL_ADMIN_ADDRESS=contato@seudominio.com.br
   ```
3. **cPanel › Email Deliverability**: deixe SPF e DKIM como "válidos" (o botão "Repair" resolve), para as
   mensagens não caírem no spam.

## 9. Curso próprio (NR-10)

O pacote tem uns 32 MB. Dois caminhos:

- **Pelo painel**: *Painel › Cursos › NR-10 Básico › Conteúdo on-line próprio › Importar pacote*
  (precisa dos limites do passo 2.3).
- **Pela pasta de entrada** (sem limite de envio): no Gerenciador de arquivos, envie o zip para
  `storage/scorm/entrada` e, no mesmo painel do curso, use **Importar do servidor**.

Antes de usar com alunos de verdade, exporte de novo do Rise com a assinatura do Articulate ativa (o
pacote exportado durante o teste grátis pode sair com selo de avaliação).

## 10. Mercado Pago

Siga a seção "Mercado Pago" do README: `MP_ACCESS_TOKEN` de produção no `.env` e o webhook
`https://seudominio.com.br/webhooks/mercadopago` (evento Pagamentos) com a chave em `MP_WEBHOOK_SECRET`.

## 11. Conferir depois de publicar

- `https://seudominio.com.br/.env` e `https://seudominio.com.br/storage/` respondem **403 ou 404**.
- Home, catálogo, página de curso, carrinho e checkout abrem; `http://` vai para `https://`.
- Cadastro de cliente, e-mail de boas-vindas chegando (olhe o spam na primeira vez).
- Painel: login com a verificação em duas etapas, pedidos, matrículas.
- Curso NR-10: uma matrícula de teste, abrir o curso, vídeo do avatar tocando, andamento salvo.
- **Backup**: *cPanel › Backup* (arquivos e banco). Guarde também o `.env` (com a `APP_KEY`).

## 12. Atualizar a loja depois

**Pelo Git do cPanel (como a loja da Dafnis está publicada):** *cPanel › Git Version Control › dafnis-loja ›
Manage › Pull or Deploy*: **Update from Remote** e depois **Deploy HEAD Commit**. O `.cpanel.yml` copia a
loja para o `public_html` sem tocar no `.env` nem em `storage/` e aplica as mudanças de banco pendentes
(`php bin/console migrate`, com o PHP 8.3 do MultiPHP). O resultado fica no log do deploy, na mesma tela.

**Pelo pacote zip:**

1. Gere o pacote novo (`git archive`, passo 1) e extraia por cima, **sem apagar** o `.env` nem a pasta
   `storage/` (cadastros enviados, certificados, cursos e sessões ficam ali). Depois, confira a versão do
   PHP no **MultiPHP Manager** (o `.htaccess` é substituído a cada atualização).
2. Se a versão trouxer mudanças de banco: coloque um `INSTALL_TOKEN` no `.env`, abra `/atualizar`,
   aplique e apague o token de novo (com SSH: `php bin/console migrate`).

## Ponto de atenção: cursos próprios e o limite de processos

Na hospedagem compartilhada há um limite de processos PHP ao mesmo tempo. Os arquivos dos cursos SCORM
passam pela loja (é assim que só o aluno matriculado consegue abrir), e a primeira abertura de um curso
pede dezenas de arquivos de uma vez. Depois disso eles ficam no cache do navegador do aluno por 7 dias.
Se aparecer erro **508** ao abrir um curso, ou o curso carregar pela metade, o próximo passo é servir esses
arquivos direto pelo Apache, com um endereço secreto por pacote, ou passar para um plano com mais processos.
