/* Catálogo na prévia estática: mesma busca e mesmos filtros do servidor (Catalog.php),
   aplicados no navegador sobre todos os cursos já presentes na página. */
(function () {
  'use strict';

  var doc = document;
  var form = doc.getElementById('filtros-form');
  var results = doc.querySelector('[data-results]');
  if (!form || !results) return;

  var grid = results.querySelector('[data-results-grid]');
  var cards = grid ? Array.prototype.slice.call(grid.querySelectorAll('.card')) : [];
  cards.forEach(function (c, i) { c.dataset.order = i; });
  var ranges = JSON.parse(form.getAttribute('data-ranges') || '{"carga":{},"preco":{}}');
  var search = doc.querySelector('[data-catalog-search]');
  var panel = doc.querySelector('[data-filters]');
  var backdrop = doc.querySelector('[data-filters-backdrop]');
  var groups = ['nr', 'categoria', 'modalidade', 'carga', 'preco', 'tipo'];
  var stop = ['de', 'da', 'do', 'das', 'dos', 'e', 'em', 'para', 'com', 'a', 'o', 'as', 'os', 'no', 'na', 'curso', 'cursos', 'treinamento', 'treinamentos'];
  var timer = null;

  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  function checkboxes() { return Array.prototype.slice.call(form.querySelectorAll('input[type=checkbox]')); }

  function state() {
    var f = {};
    groups.forEach(function (g) { f[g] = []; });
    checkboxes().forEach(function (cb) { if (cb.checked) f[cb.name.replace('[]', '')].push(cb.value); });
    return f;
  }

  function inRange(value, ids, table) {
    return ids.some(function (id) {
      var r = table[id];
      return r && value >= r[0] && (r[1] === null || value <= r[1]);
    });
  }

  function test(card, group, values) {
    var d = card.dataset;
    switch (group) {
      case 'nr': return values.indexOf(d.nr) >= 0;
      case 'categoria': return values.indexOf(d.cat) >= 0;
      case 'modalidade': return values.indexOf(d.mod) >= 0;
      case 'tipo': return values.indexOf(d.type) >= 0;
      case 'carga': return inRange(Number(d.hours), values, ranges.carga);
      case 'preco':
        if (d.price === '') return values.indexOf('sob-consulta') >= 0;
        var ranged = values.filter(function (v) { return v !== 'sob-consulta'; });
        return ranged.length > 0 && inRange(Number(d.price), ranged, ranges.preco);
    }
    return true;
  }

  function pass(card, f, skip) {
    return groups.every(function (g) { return g === skip || !f[g].length || test(card, g, f[g]); });
  }

  function matches(card, query) {
    var q = norm(query).trim();
    if (!q) return true;
    var m = q.match(/^nr\s*-?\s*(\d{1,2})(?:[.,]\d+)?$/);
    if (m) return card.dataset.nr === String(Number(m[1]));
    var tokens = q.replace(/[^a-z0-9\s.]/g, ' ').split(/\s+/).filter(Boolean);
    var meaningful = tokens.filter(function (t) { return stop.indexOf(t) < 0; });
    if (meaningful.length) tokens = meaningful;
    var hay = card.dataset.search;
    for (var i = 0; i < tokens.length; i++) {
      var t = tokens[i];
      var nr = t.match(/^nr-?(\d{1,2})$/);
      if (nr) { if (card.dataset.nr !== String(Number(nr[1]))) return false; continue; }
      if (t === 'nr' && /^\d+$/.test(tokens[i + 1] || '')) continue;
      if (/^\d+$/.test(t) && tokens[i - 1] === 'nr') { if (card.dataset.nr !== String(Number(t))) return false; continue; }
      if (!new RegExp('(^|[^a-z0-9])' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')).test(hay)) return false;
    }
    return true;
  }

  function sortValue(order) {
    var price = function (c, dir) { return c.dataset.price === '' ? Infinity : Number(c.dataset.price) * dir; };
    var nr = function (c) { return c.dataset.nr === '' ? 999 : Number(c.dataset.nr); };
    var ord = function (a, b) { return Number(a.dataset.order) - Number(b.dataset.order); };
    return {
      'nr': function (a, b) { return nr(a) - nr(b) || a.dataset.title.localeCompare(b.dataset.title); },
      'menor-preco': function (a, b) { return price(a, 1) - price(b, 1) || ord(a, b); },
      'maior-preco': function (a, b) { return price(a, -1) - price(b, -1) || ord(a, b); },
      'carga-horaria': function (a, b) { return Number(a.dataset.hours) - Number(b.dataset.hours) || ord(a, b); },
      'nome': function (a, b) { return a.dataset.title.localeCompare(b.dataset.title); }
    }[order] || ord;
  }

  var empty = doc.createElement('div');
  empty.className = 'empty';
  empty.hidden = true;
  empty.innerHTML = '<span class="empty-ic"><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M18 11a7 7 0 1 1-14 0a7 7 0 1 1 14 0z M21 21l-4.6-4.6"></path></svg></span>' +
    '<h2 style="font-size:20px">Nenhum treinamento encontrado</h2><p>Não encontramos resultados para esta combinação. Tente remover alguns filtros ou pesquisar outro termo.</p>' +
    '<button class="btn btn-navy" type="button" data-static-clear>Limpar filtros e busca</button>';
  if (grid) grid.parentNode.insertBefore(empty, grid.nextSibling);

  function chipsBox() {
    var box = results.querySelector('.active-chips');
    if (!box) {
      box = doc.createElement('div');
      box.className = 'active-chips';
      grid.parentNode.insertBefore(box, grid);
    }
    return box;
  }

  function apply(updateUrl) {
    var f = state();
    var q = search ? search.value : '';
    var base = cards.filter(function (c) { return matches(c, q); });
    var visible = base.filter(function (c) { return pass(c, f, null); });
    var sort = doc.querySelector('[data-sort]');
    var ordered = visible.slice().sort(sortValue(sort ? sort.value : 'relevancia'));

    cards.forEach(function (c) { c.hidden = true; });
    ordered.forEach(function (c) { c.hidden = false; grid.appendChild(c); });
    empty.hidden = visible.length > 0;
    grid.hidden = visible.length === 0;

    checkboxes().forEach(function (cb) {
      var g = cb.name.replace('[]', '');
      var n = base.filter(function (c) { return pass(c, f, g) && test(c, g, [cb.value]); }).length;
      var label = cb.closest('.f-opt');
      label.querySelector('.f-count').textContent = n;
      label.classList.toggle('dim', n === 0 && !cb.checked);
    });

    var count = results.querySelector('.results-count');
    if (count) count.innerHTML = '<strong>' + visible.length + '</strong> ' + (visible.length === 1 ? 'curso encontrado' : 'cursos encontrados');
    var active = checkboxes().filter(function (cb) { return cb.checked; });
    Array.prototype.forEach.call(doc.querySelectorAll('[data-result-count]'), function (el) { el.textContent = visible.length; });
    Array.prototype.forEach.call(doc.querySelectorAll('[data-filter-count]'), function (el) { el.textContent = active.length; el.hidden = !active.length; });
    Array.prototype.forEach.call(doc.querySelectorAll('.f-clear'), function (el) {
      if (active.length) el.removeAttribute('aria-disabled'); else el.setAttribute('aria-disabled', 'true');
    });
    var clear = doc.querySelector('[data-clear-search]');
    if (clear) clear.hidden = !q.trim();

    var box = chipsBox();
    box.innerHTML = '';
    if (q.trim()) box.appendChild(chip('“' + q.trim() + '”', function () { search.value = ''; }));
    active.forEach(function (cb) {
      box.appendChild(chip(cb.parentNode.querySelector('span').textContent, function () { cb.checked = false; }));
    });
    if (box.children.length) {
      var all = doc.createElement('button');
      all.type = 'button';
      all.className = 'text-link';
      all.style.cssText = 'font-size:13.5px;padding:0 4px';
      all.textContent = 'Limpar tudo';
      all.setAttribute('data-static-clear', '');
      box.appendChild(all);
    }
    box.hidden = !box.children.length;

    if (updateUrl) {
      var params = new URLSearchParams();
      if (q.trim()) params.set('q', q.trim());
      groups.forEach(function (g) { if (f[g].length) params.set(g, f[g].join(',')); });
      if (sort && sort.value !== 'relevancia') params.set('ordem', sort.value);
      history.replaceState(null, '', location.pathname + (params.toString() ? '?' + params : ''));
    }
  }

  function chip(label, onRemove) {
    var b = doc.createElement('button');
    b.type = 'button';
    b.className = 'achip';
    b.setAttribute('aria-label', 'Remover filtro ' + label);
    b.innerHTML = '<span></span><svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12 M18 6L6 18"></path></svg>';
    b.firstChild.textContent = label;
    b.addEventListener('click', function () { onRemove(); apply(true); });
    return b;
  }

  function clearAll(keepSearch) {
    checkboxes().forEach(function (cb) { cb.checked = false; });
    if (!keepSearch && search) search.value = '';
    apply(true);
  }

  // Estado inicial vindo da URL (busca da home, links "NR 10", "Ver catálogo por número"...).
  var params = new URLSearchParams(location.search);
  if (search && params.get('q')) search.value = params.get('q');
  groups.forEach(function (g) {
    var values = (params.get(g) || '').split(',').filter(Boolean);
    checkboxes().forEach(function (cb) { if (cb.name === g + '[]' && values.indexOf(cb.value) >= 0) cb.checked = true; });
  });
  if (params.get('ordem')) Array.prototype.forEach.call(doc.querySelectorAll('[data-sort]'), function (s) { s.value = params.get('ordem'); });
  var loadMore = results.querySelector('[data-load-more]');
  if (loadMore) loadMore.remove();
  if (grid) apply(false);

  form.addEventListener('submit', function (e) { e.preventDefault(); apply(true); });
  form.addEventListener('change', function (e) { if (e.target.matches('input[type=checkbox]')) apply(true); });
  doc.addEventListener('change', function (e) {
    if (!e.target.matches('[data-sort]')) return;
    Array.prototype.forEach.call(doc.querySelectorAll('[data-sort]'), function (s) { s.value = e.target.value; });
    apply(true);
  });
  if (search) {
    search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { apply(true); }, 150); });
    search.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); apply(true); } });
  }
  doc.addEventListener('click', function (e) {
    if (e.target.closest('[data-static-clear]')) { clearAll(false); return; }
    var link = e.target.closest('[data-clear-filters], [data-clear-search], [data-catalog-link]');
    if (!link) return;
    e.preventDefault();
    if (link.hasAttribute('data-clear-search')) { search.value = ''; apply(true); search.focus(); return; }
    clearAll(link.hasAttribute('data-clear-filters'));
  });

  // Painel de filtros no celular
  function openPanel(opener) {
    panel.classList.add('open');
    backdrop.hidden = false;
    doc.body.classList.add('lock');
    if (opener) opener.setAttribute('aria-expanded', 'true');
  }
  function closePanel() {
    if (!panel.classList.contains('open')) return;
    panel.classList.remove('open');
    backdrop.hidden = true;
    doc.body.classList.remove('lock');
    var opener = doc.querySelector('[data-filters-open]');
    if (opener) opener.setAttribute('aria-expanded', 'false');
  }
  doc.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-filters-open]');
    if (opener) { openPanel(opener); return; }
    if (e.target.closest('[data-filters-close]')) closePanel();
  });
  doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePanel(); });
})();
