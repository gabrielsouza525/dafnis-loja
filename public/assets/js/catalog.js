/* Catálogo: busca e filtros combinados ao vivo, "carregar mais" e painel de filtros no celular.
   Sem JavaScript o formulário de filtros faz um GET normal e a paginação vira links. */
(function () {
  'use strict';

  var doc = document;
  var form = doc.getElementById('filtros-form');
  var results = doc.querySelector('[data-results]');
  if (!form || !results) return;

  var filtersBody = doc.querySelector('[data-filters-body]');
  var search = doc.querySelector('[data-catalog-search]');
  var panel = doc.querySelector('[data-filters]');
  var backdrop = doc.querySelector('[data-filters-backdrop]');
  var heading = doc.querySelector('[data-catalog-heading]');
  var skeleton = doc.getElementById('skeleton-template');
  var base = form.getAttribute('action');
  var controller = null;
  var searchTimer = null;

  function currentParams() {
    var params = new URLSearchParams();
    var q = search ? search.value.trim() : '';
    if (q) params.set('q', q);
    var groups = {};
    Array.prototype.forEach.call(filtersBody.querySelectorAll('input[type=checkbox]:checked'), function (cb) {
      var key = cb.name.replace('[]', '');
      (groups[key] = groups[key] || []).push(cb.value);
    });
    Object.keys(groups).forEach(function (key) { params.set(key, groups[key].join(',')); });
    var sort = doc.querySelector('[data-sort]');
    if (sort && sort.value && sort.value !== 'relevancia') params.set('ordem', sort.value);
    return params;
  }

  function showSkeleton() {
    results.setAttribute('aria-busy', 'true');
    var old = results.querySelector('.skel-grid');
    if (old || !skeleton) return;
    var grid = results.querySelector('.cgrid, .empty');
    var clone = skeleton.content.firstElementChild.cloneNode(true);
    if (grid) grid.parentNode.insertBefore(clone, grid); else results.appendChild(clone);
    var count = results.querySelector('.results-count');
    if (count) count.innerHTML = 'Buscando treinamentos…';
  }

  function load(url, push) {
    if (controller) controller.abort();
    controller = 'AbortController' in window ? new AbortController() : null;
    showSkeleton();
    var focusedId = doc.activeElement && filtersBody.contains(doc.activeElement) ? doc.activeElement.id : null;
    return fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
      signal: controller ? controller.signal : undefined
    }).then(function (r) {
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    }).then(function (json) {
      results.innerHTML = json.results;
      filtersBody.innerHTML = json.filters;
      results.removeAttribute('aria-busy');
      if (focusedId) {
        var again = doc.getElementById(focusedId);
        if (again) again.focus();
      }
      Array.prototype.forEach.call(doc.querySelectorAll('[data-result-count]'), function (el) { el.textContent = json.total; });
      Array.prototype.forEach.call(doc.querySelectorAll('[data-filter-count]'), function (el) { el.textContent = json.active; el.hidden = !json.active; });
      Array.prototype.forEach.call(doc.querySelectorAll('[data-clear-filters].f-clear'), function (el) {
        if (json.active) el.removeAttribute('aria-disabled'); else el.setAttribute('aria-disabled', 'true');
      });
      if (heading && json.heading) heading.textContent = json.heading;
      var clear = doc.querySelector('[data-clear-search]');
      if (clear) clear.hidden = !(search && search.value.trim());
      var next = json.url || url;
      if (push) history.pushState({ catalog: true }, '', next); else history.replaceState({ catalog: true }, '', next);
    }).catch(function (err) {
      if (err && err.name === 'AbortError') return;
      results.removeAttribute('aria-busy');
      window.location.href = url; // falhou o fetch: navegação normal
    });
  }

  function apply(push) {
    var qs = currentParams().toString();
    return load(base + (qs ? '?' + qs : ''), push);
  }

  form.addEventListener('submit', function (e) { e.preventDefault(); apply(true); });
  form.addEventListener('change', function (e) {
    if (e.target.matches('input[type=checkbox]')) apply(true);
  });
  doc.addEventListener('change', function (e) {
    if (!e.target.matches('[data-sort]')) return;
    Array.prototype.forEach.call(doc.querySelectorAll('[data-sort]'), function (s) { s.value = e.target.value; });
    apply(true);
  });
  if (search) {
    search.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(function () { apply(false); }, 350);
    });
    search.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') { e.preventDefault(); clearTimeout(searchTimer); apply(true); }
    });
  }

  // Chips, "limpar", links do estado vazio: mesmo resultado, sem recarregar a página.
  doc.addEventListener('click', function (e) {
    var link = e.target.closest('[data-catalog-link], [data-clear-filters], [data-clear-search]');
    if (!link || !link.href) return;
    if (link.getAttribute('aria-disabled') === 'true') { e.preventDefault(); return; }
    e.preventDefault();
    var url = new URL(link.href, location.href);
    if (search) search.value = url.searchParams.get('q') || '';
    load(url.pathname + url.search, true).then(function () {
      if (link.hasAttribute('data-clear-search') && search) search.focus();
    });
  });

  // Carregar mais
  doc.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-load-more-btn]');
    if (!btn) return;
    e.preventDefault();
    btn.classList.add('is-loading');
    var url = new URL(btn.href, location.href);
    url.searchParams.set('append', '1');
    fetch(url.pathname + url.search, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        var grid = results.querySelector('[data-results-grid]');
        var tmp = doc.createElement('div');
        tmp.innerHTML = json.items;
        var first = tmp.firstElementChild;
        while (tmp.firstChild) grid.appendChild(tmp.firstChild);
        if (first) { var link = first.querySelector('.card-title a'); if (link) link.focus({ preventScroll: true }); }
        var box = results.querySelector('[data-load-more]');
        var shown = box && box.querySelector('[data-shown]');
        if (shown) shown.textContent = json.shown;
        if (!json.has_more) {
          if (box) box.remove();
        } else {
          url.searchParams.delete('append');
          url.searchParams.set('pagina', json.next);
          btn.href = url.pathname + url.search;
          btn.classList.remove('is-loading');
        }
      })
      .catch(function () { window.location.href = btn.href; });
  });

  // Painel de filtros no celular
  function openPanel(opener) {
    panel.classList.add('open');
    backdrop.hidden = false;
    doc.body.classList.add('lock');
    if (opener) opener.setAttribute('aria-expanded', 'true');
    var first = panel.querySelector('.sheet-head button');
    if (first) first.focus();
  }
  function closePanel() {
    if (!panel.classList.contains('open')) return;
    panel.classList.remove('open');
    backdrop.hidden = true;
    doc.body.classList.remove('lock');
    var opener = doc.querySelector('[data-filters-open]');
    if (opener) { opener.setAttribute('aria-expanded', 'false'); opener.focus(); }
  }
  doc.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-filters-open]');
    if (opener) { openPanel(opener); return; }
    if (e.target.closest('[data-filters-close]')) closePanel();
  });
  doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') closePanel(); });
  window.matchMedia('(min-width: 941px)').addEventListener('change', function (m) { if (m.matches) closePanel(); });

  // Voltar/avançar do navegador
  window.addEventListener('popstate', function () {
    var params = new URLSearchParams(location.search);
    if (search) search.value = params.get('q') || '';
    load(location.pathname + location.search, false);
  });
})();
