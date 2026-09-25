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

// Máscaras e login (checkout exige conta)
await go('/cadastro?volta=/checkout');
r = await ev(`${wait} const t = document.querySelector('#f-phone'); t.value = '18999990000'; t.dispatchEvent(new Event('input', {bubbles:true})); return t.value;`);
check('máscara de telefone', r === '(18) 99999-0000', r);
await go('/login?volta=/checkout');
r = await ev(`${wait} document.querySelector('#f-email').value = 'ana@example.com'; document.querySelector('#f-password').value = 'dafnis123'; document.querySelector('.auth-form').submit(); return true;`);
await sleep(1500);
r = await ev(`return { path: location.pathname, pj: document.querySelector('[data-pj]')?.hidden };`);
check('login volta para o checkout', r.path === '/checkout', r);
r = await ev(`${wait} document.querySelector('[data-buyer-type][value=pj]').click(); await w(50);
  const cnpj = document.querySelector('#f-company-document'); cnpj.value = '11222333000181'; cnpj.dispatchEvent(new Event('input', {bubbles:true}));
  return { pjVisible: !document.querySelector('[data-pj]').hidden, pfHidden: document.querySelector('[data-pf]').hidden, label: document.querySelector('label[for="f-buyer-name"]').textContent, cnpj: cnpj.value, cpfRequired: document.querySelector('#f-buyer-document').required };`);
check('checkout alterna pessoa física / empresa', r.pjVisible && r.pfHidden && r.label === 'Responsável pela compra' && !r.cpfRequired, r);
check('máscara de CNPJ', r.cnpj === '11.222.333/0001-81', r.cnpj);

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

check('nenhum erro de JavaScript', errors.length === 0, errors);
console.log(fails ? `\n${fails} falha(s)` : '\nTudo certo');
ws.close(); chrome.kill(); process.exit(fails ? 1 : 0);
