// Testa o comportamento do JavaScript da loja no Chrome headless (CDP, sem dependências).
// Requer o Chrome instalado (CHROME_PATH para outro caminho) e o banco recém-criado com db:fresh --demo.
//   node tests/browser.mjs http://localhost:8000
import { spawn } from 'node:child_process';
import { mkdtempSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const B = process.argv[2] || 'http://localhost:8000';
const port = 9800 + Math.floor(Math.random() * 100);
const chrome = spawn(process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', [
  '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${mkdtempSync(join(tmpdir(), 'js-'))}`,
  '--no-first-run', '--window-size=1440,900', 'about:blank'], { stdio: 'ignore' });
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
let wsUrl;
for (let i = 0; i < 50 && !wsUrl; i++) {
  try { wsUrl = (await (await fetch(`http://127.0.0.1:${port}/json/list`)).json()).find((t) => t.type === 'page')?.webSocketDebuggerUrl; } catch {}
  if (!wsUrl) await sleep(200);
}
const ws = new WebSocket(wsUrl);
await new Promise((r) => ws.addEventListener('open', r));
let id = 0; const pending = new Map(); const errors = []; let loaded = false;
ws.addEventListener('message', (m) => {
  const msg = JSON.parse(m.data);
  if (msg.id && pending.has(msg.id)) { pending.get(msg.id)(msg); pending.delete(msg.id); }
  if (msg.method === 'Runtime.exceptionThrown') errors.push(msg.params.exceptionDetails?.exception?.description || msg.params.exceptionDetails?.text);
  if (msg.method === 'Page.loadEventFired') loaded = true;
});
const send = (method, params = {}) => new Promise((res) => { const mid = ++id; pending.set(mid, res); ws.send(JSON.stringify({ id: mid, method, params })); });
await send('Page.enable'); await send('Runtime.enable');
const go = async (path, width = 1440) => {
  await send('Emulation.setDeviceMetricsOverride', { width, height: width < 768 ? 844 : 900, deviceScaleFactor: 1, mobile: width < 768 });
  loaded = false; await send('Page.navigate', { url: B + path });
  for (let i = 0; i < 80 && !loaded; i++) await sleep(100);
  await sleep(500);
};
const ev = async (expr) => {
  const r = await send('Runtime.evaluate', { expression: `(async () => { ${expr} })()`, awaitPromise: true, returnByValue: true });
  if (r.result.exceptionDetails) return 'EXC: ' + (r.result.exceptionDetails.exception?.description || r.result.exceptionDetails.text);
  return r.result.result.value;
};
let fails = 0;
const check = (label, ok, detail = '') => { if (!ok) fails++; console.log((ok ? '  ok   ' : '  FAIL ') + label + (!ok && detail ? '  → ' + JSON.stringify(detail) : '')); };
const wait = 'const w = (ms) => new Promise(r => setTimeout(r, ms));';

// Catálogo: filtro ao vivo
await go('/cursos');
let r = await ev(`${wait} const before = location.href; document.querySelector('#f-nr-10').click(); await w(1200);
  return { url: location.href, count: document.querySelector('.results-count strong')?.textContent, heading: document.querySelector('[data-catalog-heading]').textContent, reloaded: performance.getEntriesByType('navigation').length, checked: document.querySelector('#f-nr-10').checked };`);
check('marcar NR 10 filtra sem recarregar (5 cursos, URL atualizada)', r.count === '5' && r.url.includes('nr=10') && r.checked, r);
check('título muda para a NR escolhida', r.heading === 'NR 10 — Eletricidade', r.heading);
r = await ev(`${wait} document.querySelector('#f-modalidade-online').click(); await w(1200);
  return { count: document.querySelector('.results-count strong')?.textContent, chips: [...document.querySelectorAll('.achip')].map(a => a.textContent.trim()) };`);
check('filtros combinados ao vivo (NR 10 + Online = 1)', r.count === '1' && r.chips.length === 2, r);
r = await ev(`${wait} document.querySelector('.achip').click(); await w(1200); return { count: document.querySelector('.results-count strong')?.textContent, url: location.search };`);
check('remover chip de filtro', r.count !== '1' && !r.url.includes('nr=10'), r);
await go('/cursos');
r = await ev(`${wait} const i = document.querySelector('[data-catalog-search]'); i.value = 'espaço confinado'; i.dispatchEvent(new Event('input', {bubbles:true})); await w(1400);
  return { count: document.querySelector('.results-count strong')?.textContent, q: new URLSearchParams(location.search).get('q'), first: document.querySelector('.card-title a')?.textContent };`);
check('busca ao digitar (espaço confinado = 5 NR 33 + simulador)', r.count === '6' && r.q === 'espaço confinado' && /Confinados/.test(r.first), r);
r = await ev(`${wait} const s = document.querySelector('.results-bar [data-sort]'); s.value = 'menor-preco'; s.dispatchEvent(new Event('change', {bubbles:true})); await w(1200);
  const prices = [...document.querySelectorAll('.cgrid .price')].map(p => parseFloat(p.textContent.replace(/[^\\d,]/g,'').replace(',','.'))); return { prices, ordem: new URLSearchParams(location.search).get('ordem') };`);
check('ordenar por menor preço', r.ordem === 'menor-preco' && r.prices.every((p, i, a) => i === 0 || a[i - 1] <= p), r);
await go('/cursos');
r = await ev(`${wait} const n0 = document.querySelectorAll('[data-results-grid] .card').length; document.querySelector('[data-load-more-btn]').click(); await w(1200);
  return { n0, n1: document.querySelectorAll('[data-results-grid] .card').length, shown: document.querySelector('[data-shown]')?.textContent };`);
check('"Carregar mais" acrescenta 12 cursos', r.n0 === 12 && r.n1 === 24 && r.shown === '24', r);

// Adicionar ao carrinho pelo card
r = await ev(`${wait} document.querySelector('.card [data-add-to-cart] button').click(); await w(1200);
  return { count: document.querySelector('[data-cart-count]').textContent, hidden: document.querySelector('[data-cart-count]').hidden, toast: document.querySelector('.toast span')?.textContent, action: document.querySelector('.toast a')?.textContent };`);
check('"Comprar" no card adiciona sem sair da página e mostra aviso', r.count === '1' && !r.hidden && /adicionado/.test(r.toast) && r.action === 'Ver carrinho', r);

// Página do curso: stepper e acordeões
await go('/cursos/nr-33-espacos-confinados-trabalhador-e-vigia');
r = await ev(`${wait} const plus = document.querySelector('#buy-form [data-step="1"]'); plus.click(); plus.click(); await w(100);
  return { qty: document.querySelector('#qty').value, total: document.querySelector('[data-qty-sum]').textContent, visible: !document.querySelector('[data-qty-total]').hidden, minus: document.querySelector('#buy-form [data-step="-1"]').disabled };`);
check('stepper de participantes calcula o total (3 × 228)', r.qty === '3' && r.total === 'R$ 684,00' && r.visible && !r.minus, r);
r = await ev(`${wait} document.querySelector('#buy-form .buy-actions button:not([name])').click(); await w(1200); return document.querySelector('[data-cart-count]').textContent;`);
check('"Adicionar ao carrinho" soma 3 participantes (total 4)', r === '4', r);
r = await ev(`${wait} const q = document.querySelector('#c-faq .acc-q'); q.click(); await w(50); const open1 = q.getAttribute('aria-expanded'); const p1 = !document.getElementById(q.getAttribute('aria-controls')).hidden;
  const q2 = document.querySelectorAll('#c-faq .acc-q')[1]; q2.click(); await w(50); return { open1, p1, first: q.getAttribute('aria-expanded'), second: q2.getAttribute('aria-expanded') };`);
check('acordeão abre e fecha o anterior', r.open1 === 'true' && r.p1 && r.first === 'false' && r.second === 'true', r);

// Menu de NRs
await go('/');
r = await ev(`${wait} document.querySelector('[data-mega-toggle]').click(); await w(100); const open = !document.getElementById('mega-nr').hidden;
  document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'})); await w(50); return { open, closed: document.getElementById('mega-nr').hidden, items: document.querySelectorAll('#mega-nr .mega-item').length };`);
check('menu NRs abre e fecha com Esc', r.open && r.closed && r.items === 19, r);

// Carrinho: stepper com envio automático e remoção
await go('/carrinho');
r = await ev(`${wait} const row = document.querySelector('[data-cart-row]'); row.querySelector('[data-step="1"]').click(); await w(1500);
  return { count: document.querySelector('[data-cart-count]').textContent, total: document.querySelector('.sum-total strong').textContent };`);
check('alterar participantes no carrinho atualiza total e cabeçalho', r.count === '5', r);
r = await ev(`${wait} document.querySelector('[data-remove] button').click(); await w(1500);
  return { rows: document.querySelectorAll('[data-cart-row]').length, toast: [...document.querySelectorAll('.toast span')].map(t => t.textContent) };`);
check('remover item do carrinho sem recarregar', r.rows === 1 && r.toast.some((t) => /removido/.test(t)), r);
r = await ev(`${wait} const i = document.querySelector('#cupom'); i.value = 'BEMVINDO10'; i.form.requestSubmit(); await w(1500);
  return { ok: document.querySelector('.msg-ok')?.textContent || '', total: document.querySelector('.sum-total strong').textContent };`);
check('aplicar cupom sem recarregar', /BEMVINDO10/.test(r.ok), r);
await go('/carrinho', 390);
r = await ev(`${wait} const bar = document.querySelector('.cart-bar'); const before = { visible: bar.getBoundingClientRect().height > 0 && bar.getBoundingClientRect().bottom <= innerHeight + 1, bar: bar.querySelector('strong').textContent, sum: document.querySelector('.sum-total strong').textContent };
  document.querySelector('[data-cart-row] [data-step="1"]').click(); await w(1500);
  const after = { bar: document.querySelector('.cart-bar strong').textContent, sum: document.querySelector('.sum-total strong').textContent };
  return { before, after };`);
check('carrinho no celular: barra fixa com o total, atualizada ao mudar participantes', r.before.visible && r.before.bar === r.before.sum && r.after.bar === r.after.sum && r.after.sum !== r.before.sum, r);

// Entrar: tela dividida (painel escuro com a foto do topo à esquerda, formulário à direita)
await go('/login');
r = await ev(`const side = document.querySelector('.auth-side').getBoundingClientRect(); const card = document.querySelector('.auth-card').getBoundingClientRect();
  return { sideW: Math.round(side.width), vw: document.documentElement.clientWidth, photo: getComputedStyle(document.querySelector('.auth-side'), '::before').backgroundImage.includes('hero-1600'), cardLeft: Math.round(card.left) };`);
check('entrar: painel com foto à esquerda e formulário à direita', Math.abs(r.sideW - r.vw / 2) <= 2 && r.photo && r.cardLeft > r.sideW, r);

// Máscaras e login (checkout exige conta)
await go('/cadastro?volta=/checkout');
r = await ev(`${wait} const t = document.querySelector('#f-phone'); t.value = '18999990000'; t.dispatchEvent(new Event('input', {bubbles:true})); return t.value;`);
check('máscara de telefone', r === '(18) 99999-0000', r);
await go('/login?volta=/checkout');
r = await ev(`${wait} document.querySelector('#f-email').value = 'ana@example.com'; document.querySelector('#f-password').value = 'dafnis123'; document.querySelector('.auth-form').submit(); return true;`);
await sleep(1500);
r = await ev(`return { path: location.pathname, pj: document.querySelector('[data-pj]')?.hidden };`);
check('login volta para o checkout', r.path.endsWith('/checkout'), r);
r = await ev(`${wait} document.querySelector('[data-buyer-type][value=pj]').click(); await w(50);
  const cnpj = document.querySelector('#f-company-document'); cnpj.value = '11222333000181'; cnpj.dispatchEvent(new Event('input', {bubbles:true}));
  return { pjVisible: !document.querySelector('[data-pj]').hidden, pfHidden: document.querySelector('[data-pf]').hidden, label: document.querySelector('label[for="f-buyer-name"]').textContent, cnpj: cnpj.value, cpfRequired: document.querySelector('#f-buyer-document').required };`);
check('checkout alterna pessoa física / empresa', r.pjVisible && r.pfHidden && r.label === 'Responsável pela compra' && !r.cpfRequired, r);
check('máscara de CNPJ', r.cnpj === '11.222.333/0001-81', r.cnpj);
// Logado, o cabeçalho mostra "Minha conta" (mais longo que "Entrar"): tudo precisa caber na largura
const hdrOver = [];
for (const width of [1181, 1280, 1366, 1440]) {
  await go('/carrinho', width);
  const o = await ev(`const h = document.querySelector('.hdr-in'); const edge = h.getBoundingClientRect().right - parseFloat(getComputedStyle(h).paddingRight);
    const right = Math.max(...[...h.children].map((k) => k.getBoundingClientRect().right)); return { over: Math.round(right - edge), label: document.querySelector('.hdr .link-btn')?.textContent.trim() };`);
  if (o.over > 0) hdrOver.push(`${width}px: +${o.over}px (${o.label})`);
}
check('cabeçalho logado cabe na largura de 1181 a 1440 px', hdrOver.length === 0, hdrOver);

// Celular: gaveta do menu e painel de filtros
await go('/', 390);
r = await ev(`${wait} document.querySelector('[data-drawer-open]').click(); await w(200); const open = !document.getElementById('menu-drawer').hidden; const lock = document.body.classList.contains('lock');
  document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'})); await w(100); return { open, lock, closed: document.getElementById('menu-drawer').hidden };`);
check('menu do celular abre, trava a rolagem e fecha com Esc', r.open && r.lock && r.closed, r);
await go('/cursos', 390);
r = await ev(`${wait} document.querySelector('[data-filters-open]').click(); await w(300); const open = document.querySelector('[data-filters]').classList.contains('open');
  document.querySelector('#f-nr-33').click(); await w(1200); const n = document.querySelector('.sheet-foot [data-result-count]').textContent; const badge = document.querySelector('[data-filter-count]').textContent;
  document.querySelector('.sheet-foot [data-filters-close]').click(); await w(200); return { open, n, badge, closed: !document.querySelector('[data-filters]').classList.contains('open') };`);
check('painel de filtros no celular: filtra, conta e fecha', r.open && r.n === '5' && r.badge === '1' && r.closed, r);

// Topo da home: foto de fundo e texto centralizado; categorias em cartões separados
await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'light' }] });
await go('/');
r = await ev(`${wait} await w(300); const img = document.querySelector('.hero-bg'); const h1 = document.querySelector('.hero h1').getBoundingClientRect();
  const cats = [...document.querySelectorAll('.cats .cat')].map(c => c.getBoundingClientRect());
  return { img: !!img && img.complete && img.naturalWidth > 0, center: Math.round(h1.left + h1.width / 2 - document.documentElement.clientWidth / 2), gap: Math.round(cats[1].left - cats[0].right), rowGap: Math.round(cats[4].top - cats[0].bottom) };`);
check('topo com foto de fundo e título centralizado', r.img && Math.abs(r.center) <= 2, r);
check('cartões de categoria separados', r.gap >= 12 && r.rowGap >= 12, r);
r = await ev(`const imgs = [...document.querySelectorAll('.logo-mark img')];
  const fav = await fetch(document.querySelector('link[rel="icon"]').href);
  return { n: imgs.length, loaded: imgs.every(i => i.complete && i.naturalWidth > 0), h: Math.round(imgs[0].getBoundingClientRect().height), fav: fav.ok && fav.headers.get('content-type') };`);
check('símbolo da Dafnis no cabeçalho, no menu e no rodapé; ícone da aba carrega', r.n === 3 && r.loaded && r.h === 40 && /image\/png/.test(r.fav), r);
r = await ev(`const nrs = [...document.querySelectorAll('#nrs .nrc')];
  return { nrs: nrs.length, nrLinks: nrs.every(a => a.getAttribute('href').includes('/nr/')), cats: document.querySelector('#categorias .sec-head .text-link')?.getAttribute('href'), cert: !!document.querySelector('.cert-doc .cert-doc-seal'), cta: !!document.querySelector('.cta-card .btn-gold'), band: !!document.querySelector('.cta-band') };`);
check('home: 8 NRs em cartões, link para as categorias, certificado ilustrado e chamada final em cartão', r.nrs === 8 && r.nrLinks && String(r.cats).endsWith('/categorias') && r.cert && r.cta && !r.band, r);
r = await ev(`${wait} const hdr = document.querySelector('.hdr'); const hero = document.querySelector('.hero').getBoundingClientRect();
  const top = { clear: hdr.classList.contains('is-clear'), bg: getComputedStyle(hdr).backgroundColor, bar: !!document.querySelector('.demobar'), heroTop: Math.round(hero.top), heroH: Math.round(hero.height) };
  scrollTo(0, 700); await w(400); const scrolled = { clear: hdr.classList.contains('is-clear'), shadow: hdr.classList.contains('is-scrolled') };
  scrollTo(0, 0); await w(300); document.querySelector('[data-mega-toggle]').click(); await w(200); const megaClear = hdr.classList.contains('is-clear');
  document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'})); await w(200);
  return { top, scrolled, megaClear, back: hdr.classList.contains('is-clear'), vh: innerHeight };`);
check('home sem barra de aviso e topo ocupando a tela, por trás do cabeçalho', !r.top.bar && r.top.heroTop === 0 && r.top.heroH >= r.vh, r.top);
check('cabeçalho transparente sobre a foto; sólido com sombra ao rolar e com o menu de NRs aberto', r.top.clear && r.top.bg === 'rgba(0, 0, 0, 0)' && !r.scrolled.clear && r.scrolled.shadow && !r.megaClear && r.back, r);
await go('/cursos');
r = await ev(`return { clear: document.querySelector('.hdr').classList.contains('is-clear'), over: document.querySelector('.hdr').hasAttribute('data-over') }`);
check('nas outras páginas o cabeçalho é sólido', !r.clear && !r.over, r);

// Modo escuro: segue o sistema sem escolha salva; o botão alterna e a escolha fica salva
r = await ev(`${wait} localStorage.removeItem('dafnis-theme'); const before = document.documentElement.dataset.theme; const btn = document.querySelector('.hdr [data-theme-toggle]');
  btn.click(); await w(50); return { before, after: document.documentElement.dataset.theme, pressed: btn.getAttribute('aria-pressed'), saved: localStorage.getItem('dafnis-theme'), bg: getComputedStyle(document.body).backgroundColor };`);
check('botão alterna para o modo escuro', r.before === 'light' && r.after === 'dark' && r.pressed === 'true' && r.saved === 'dark' && r.bg !== 'rgb(255, 255, 255)', r);
await go('/cursos');
r = await ev(`return document.documentElement.dataset.theme`);
check('modo escuro continua na próxima página', r === 'dark', r);
await ev(`localStorage.removeItem('dafnis-theme'); return 1`);
await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'dark' }] });
await go('/');
r = await ev(`return document.documentElement.dataset.theme`);
check('sem escolha salva, segue o tema escuro do sistema', r === 'dark', r);
await send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-color-scheme', value: 'light' }] });
await go('/', 390);
r = await ev(`${wait} const hdrBtn = document.querySelector('.hdr [data-theme-toggle]'); document.querySelector('[data-drawer-open]').click(); await w(200);
  const btn = document.querySelector('#menu-drawer [data-theme-toggle]'); const visible = btn.offsetParent !== null; btn.click(); await w(50);
  return { hdrHidden: hdrBtn.offsetParent === null, visible, theme: document.documentElement.dataset.theme };`);
check('celular: botão de tema dentro do menu', r.hdrHidden && r.visible && r.theme === 'dark', r);
await ev(`localStorage.removeItem('dafnis-theme'); return 1`);

// Catálogo: topo escuro e NRs como botões (clicar no botão filtra e ele fica marcado)
await go('/cursos');
r = await ev(`${wait} const chip = document.querySelector('label[for="f-nr-33"]'); const bgBefore = getComputedStyle(chip).backgroundColor;
  chip.click(); await w(1200); return { checked: document.querySelector('#f-nr-33').checked, bgBefore, bgAfter: getComputedStyle(document.querySelector('label[for="f-nr-33"]').closest('.f-opt')).backgroundColor,
  count: document.querySelector('.results-count strong').textContent, heroBg: getComputedStyle(document.querySelector('.phead-dark')).backgroundColor };`);
check('catálogo: botão de NR filtra e fica marcado', r.checked && r.count === '5' && r.bgAfter !== r.bgBefore, r);

// Contato: assunto em cartões, com o assunto do link já marcado
await go('/contato?assunto=empresas');
r = await ev(`return { topics: document.querySelectorAll('.topics input[name=subject]').length, checked: document.querySelector('.topics input[name=subject]:checked')?.value, select: !!document.querySelector('select[name=subject]') }`);
check('contato: assunto em 4 cartões, o do link já marcado', r.topics === 4 && r.checked === 'empresas' && !r.select, r);

// Sobre nós: página própria (antes era um trecho da home)
await go('/sobre');
r = await ev(`const n = [...document.querySelectorAll('.about-stats .stat-n')].map(e => e.textContent);
  return { n, nav: document.querySelector('.nav-link[aria-current="page"]')?.textContent, steps: document.querySelectorAll('.how-step').length, areas: document.querySelectorAll('.area').length, whys: document.querySelectorAll('.why-item').length, link: document.querySelector('.ftr-link[href$="/sobre"]') !== null };`);
check('sobre nós: números do catálogo no topo, menu marcado, passos, áreas e diferenciais', r.n[0] === '119' && r.nav === 'Sobre nós' && r.steps === 4 && r.areas > 0 && r.whys === 6 && r.link, r);

// Categorias: um cartão por área, com atalhos no topo e o menu marcado
await go('/categorias');
r = await ev(`const cards = [...document.querySelectorAll('.catx')];
  return { nav: document.querySelector('.nav-link[aria-current="page"]')?.textContent, cards: cards.length, jump: document.querySelectorAll('.cat-jump a').length, withList: cards.filter(c => c.querySelectorAll('.catx-list li').length === 3).length, firstMeta: cards[0]?.querySelector('.catx-meta')?.textContent };`);
check('categorias: cartões com 3 treinamentos, atalhos no topo e menu marcado', r.nav === 'Categorias' && r.cards >= 8 && r.jump >= 8 && r.withList >= 8 && /treinamentos/.test(r.firstMeta), r);
await go('/categorias/primeiros-socorros');
r = await ev(`return { crumb: [...document.querySelectorAll('.crumbs a')].map(a => a.textContent).join(' › '), nav: document.querySelector('.nav-link[aria-current="page"]')?.textContent };`);
check('página de uma categoria: caminho por Categorias e menu marcado', r.crumb === 'Início › Categorias' && r.nav === 'Categorias', r);

// NRs: um cartão por norma e filtro ao digitar (número ou nome, com ou sem acento)
await go('/nrs');
r = await ev(`${wait} const i = document.querySelector('[data-nr-filter]');
  const shown = async (v) => { i.value = v; i.dispatchEvent(new Event('input')); await w(50); return [...document.querySelectorAll('[data-nr-item]')].filter(e => !e.hidden).map(e => e.dataset.nr).join(','); };
  const all = document.querySelectorAll('.nrc').length;
  const r = { all, n33: await shown('33'), nr3: await shown('NR 3'), inc: await shown('incendio'), acc: await shown('incêndio'), none: await shown('xyz'), empty: !document.querySelector('[data-nr-empty]').hidden };
  await shown(''); r.back = document.querySelectorAll('.nrc:not([hidden])').length; return r;`);
check('NRs: filtro por número ou nome, com ou sem acento', r.all >= 19 && r.n33 === '33' && r.nr3 === '31,32,33,34,37' && r.inc === '23' && r.acc === '23' && r.none === '' && r.empty && r.back === r.all, r);

// Empresas: página própria com a prévia do pedido e o formulário de proposta
await go('/empresas');
r = await ev(`const f = document.querySelector('#proposta form');
  return { nav: document.querySelector('.nav-link[aria-current="page"]')?.textContent, seats: document.querySelectorAll('.seat-demo .sd-row').length, perks: document.querySelectorAll('.pillar').length, steps: document.querySelectorAll('.how-step').length, subject: f?.querySelector('[name=subject]')?.value, from: f?.querySelector('[name=_from]')?.value, cta: document.querySelector('.intro-ctas a')?.getAttribute('href') };`);
check('empresas: menu marcado, prévia do pedido, vantagens, passos e proposta na página', r.nav === 'Empresas' && r.seats === 4 && r.perks === 6 && r.steps === 4 && r.subject === 'empresas' && r.from === 'empresas' && r.cta === '#proposta', r);

// Página do curso: compra visível sem rolar, cartão fixo ao rolar; no celular a placa gerada some
await go('/cursos/nr-33-espacos-confinados-trabalhador-e-vigia');
r = await ev(`${wait} const btn = document.querySelector('.buy-card [name=buy_now]'); const before = Math.round(btn.getBoundingClientRect().bottom);
  scrollTo(0, 1500); await w(300); const card = Math.round(document.querySelector('.buy-card').getBoundingClientRect().top); scrollTo(0, 0);
  return { before, vh: innerHeight, card, heroDark: getComputedStyle(document.querySelector('.c-hero-bg')).backgroundColor };`);
check('curso: "Comprar agora" visível sem rolar e cartão fixo ao rolar', r.before < r.vh && r.card === 96, r);
await go('/cursos/nr-33-espacos-confinados-trabalhador-e-vigia', 390);
r = await ev(`const cover = document.querySelector('.c-aside .cover'); return { coverHidden: cover.offsetParent === null, price: !!document.querySelector('.buy-card .buy-price') }`);
check('curso no celular: placa gerada escondida, preço logo depois do topo', r.coverHidden && r.price, r);

// Procura o que passa da borda da tela (regras na checagem "nada passa da borda" mais abaixo)
const outside = `const vw = document.documentElement.clientWidth; const out = [];
  for (const el of document.querySelectorAll('body *')) {
    if (el.closest('[hidden], [aria-hidden="true"], .drawer-root, .toasts, .skip-link, template, svg')) continue;
    const r = el.getBoundingClientRect(); if (!r.width || !r.height || getComputedStyle(el).position === 'fixed') continue;
    let limit = vw, clip = null;
    for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) {
      const ox = getComputedStyle(a).overflowX;
      if (ox === 'auto' || ox === 'scroll') { limit = null; break; }
      if (!clip && (ox === 'hidden' || ox === 'clip')) clip = a;
    }
    if (limit !== null && clip) limit = Math.min(vw, clip.getBoundingClientRect().right);
    if (limit !== null && r.right > limit + 1) out.push((typeof el.className === 'string' && el.className ? '.' + el.className.split(' ')[0] : el.tagName) + ' +' + Math.round(r.right - limit) + 'px');
  }
  return { scroll: document.documentElement.scrollWidth - vw, out: [...new Set(out)].slice(0, 3) };`;

// Minha conta (logado como a aluna): topo com a saudação, abas com a atual marcada e nada passando da borda
await go('/minha-conta/certificados');
r = await ev(`return { tabs: document.querySelectorAll('.acc-tabs a').length, current: document.querySelector('.acc-tabs [aria-current]')?.textContent.trim(), hello: document.querySelector('.acc-hello')?.textContent.trim(), avatar: getComputedStyle(document.querySelector('.acc-avatar')).display };`);
check('minha conta: saudação no topo e abas com a atual marcada', r.tabs === 5 && r.current === 'Certificados' && /^Olá, /.test(r.hello) && r.avatar === 'grid', r);
const accOver = [];
for (const width of [390, 1440]) {
  for (const path of ['/minha-conta', '/minha-conta/cursos', '/minha-conta/certificados', '/minha-conta/pedidos', '/minha-conta/dados']) {
    await go(path, width);
    const o = await ev(outside);
    if (o.scroll > 0 || o.out.length) accOver.push(`${path} @${width}px: ${o.scroll > 0 ? 'rola +' + o.scroll + 'px ' : ''}${o.out.join(', ')}`);
  }
}
check('minha conta: nada passa da borda (390 e 1440 px)', accOver.length === 0, accOver);

// Sai da conta: as telas de entrar, criar conta e recuperar senha só aparecem para visitantes
await go('/minha-conta');
await ev(`document.querySelector('form[action$="/sair"]').submit(); return 1`);
await sleep(1500);
// Nada passa da borda: nem rolagem lateral, nem conteúdo cortado por um contêiner com overflow
// escondido (ex.: a busca do catálogo saindo pela direita do topo). Só faixas com rolagem própria
// (carrosséis, abas) e desenhos decorativos (aria-hidden, svg) podem passar.
const overflow = [];
for (const width of [360, 768, 960, 1024, 1180, 1280, 1440]) {
  for (const path of ['/', '/cursos', '/cursos/nr-33-espacos-confinados-trabalhador-e-vigia', '/carrinho', '/login', '/cadastro', '/esqueci-senha', '/contato', '/sobre', '/empresas', '/categorias', '/nrs']) {
    await go(path, width);
    const o = await ev(outside);
    const at = await ev(`return location.pathname`);
    if (!at.replace(/\/$/, '').endsWith(path.replace(/\/$/, ''))) { overflow.push(`${path} @${width}px: abriu ${at}`); continue; }
    if (o.scroll > 0 || o.out.length) overflow.push(`${path} @${width}px: ${o.scroll > 0 ? 'rola +' + o.scroll + 'px ' : ''}${o.out.join(', ')}`);
  }
}
check('nada passa da borda da tela de 360 a 1440 px', overflow.length === 0, overflow);

// Painel da equipe: entra como admin de demonstração e confere as telas com colunas laterais
await go('/login');
await ev(`document.querySelector('#f-email').value = 'admin@dafnis.test'; document.querySelector('#f-password').value = 'dafnis123'; document.querySelector('.auth-form').submit(); return 1`);
await sleep(1500);
const admOver = [];
for (const width of [390, 1024, 1440]) {
  for (const path of ['/admin', '/admin/pedidos', '/admin/pedidos/1', '/admin/matriculas', '/admin/matriculas/1', '/admin/cursos', '/admin/configuracoes']) {
    await go(path, width);
    const at = await ev(`return location.pathname`);
    if (!at.replace(/\/$/, '').endsWith(path)) { admOver.push(`${path} @${width}px: abriu ${at}`); continue; }
    const o = await ev(outside);
    if (o.scroll > 0 || o.out.length) admOver.push(`${path} @${width}px: ${o.scroll > 0 ? 'rola +' + o.scroll + 'px ' : ''}${o.out.join(', ')}`);
  }
}
check('painel: nada passa da borda (390, 1024 e 1440 px)', admOver.length === 0, admOver);
await go('/admin/pedidos');
r = await ev(`return { groups: [...document.querySelectorAll('.adm-nav-label')].map((l) => l.textContent), current: document.querySelector('.adm-nav [aria-current]')?.textContent.trim(), crumb: document.querySelector('.adm-crumbs [aria-current]')?.textContent }`);
check('painel: menu em grupos, item atual marcado e caminho no topo', r.groups.join() === 'Vendas,Catálogo,Pessoas,Sistema' && /^Pedidos/.test(r.current) && r.crumb === 'Pedidos', r);
await go('/admin', 390);
r = await ev(`${wait} document.querySelector('[data-admin-menu]').click(); await w(300); const open = document.querySelector('#adm-side').classList.contains('open');
  const backdrop = getComputedStyle(document.querySelector('.adm'), '::after').content !== 'none';
  document.dispatchEvent(new KeyboardEvent('keydown', {key: 'Escape'})); await w(300);
  return { open, backdrop, closed: !document.querySelector('#adm-side').classList.contains('open') };`);
check('painel no celular: menu abre com fundo escurecido e fecha com Esc', r.open && r.backdrop && r.closed, r);

check('nenhum erro de JavaScript', errors.length === 0, errors);
console.log(fails ? `\n${fails} falha(s)` : '\nTudo certo');
ws.close(); chrome.kill(); process.exit(fails ? 1 : 0);
