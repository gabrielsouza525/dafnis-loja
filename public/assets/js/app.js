/* Dafnis Treinamentos — comportamento comum da loja e do painel.
   Tudo aqui é melhoria progressiva: sem JavaScript, links e formulários funcionam normalmente. */
(function () {
  'use strict';

  var doc = document;
  var csrf = (doc.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function $(sel, root) { return (root || doc).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || doc).querySelectorAll(sel)); }

  /* ---------- toasts ---------- */
  var ICONS = {
    check: 'M5 12.5l4.5 4.5L19 7.5',
    alert: 'M12 3l10 17H2z M12 10v4 M12 17v.5',
    info: 'M21 12a9 9 0 1 1-18 0a9 9 0 1 1 18 0z M12 11v5 M12 7.5v.5',
    close: 'M6 6l12 12 M18 6L6 18'
  };
  function svg(name) {
    return '<svg class="ic" viewBox="0 0 24 24" aria-hidden="true"><path d="' + ICONS[name] + '"></path></svg>';
  }
  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
  }
  function toast(message, type, action) {
    var box = $('#toasts');
    if (!box || !message) return;
    type = type || 'success';
    var el = doc.createElement('div');
    el.className = 'toast toast-' + type;
    el.setAttribute('role', type === 'error' ? 'alert' : 'status');
    el.innerHTML = svg(type === 'error' ? 'alert' : (type === 'info' || type === 'warning' ? 'info' : 'check')) +
      '<span>' + escapeHtml(message) + '</span>' +
      (action ? '<a href="' + escapeHtml(action.href) + '">' + escapeHtml(action.label) + '</a>' : '') +
      '<button type="button" class="toast-x" aria-label="Fechar aviso">' + svg('close') + '</button>';
    box.appendChild(el);
    var remove = function () {
      el.classList.add('is-leaving');
      setTimeout(function () { el.remove(); }, 220);
    };
    el.querySelector('.toast-x').addEventListener('click', remove);
    setTimeout(remove, action ? 5200 : 3600);
  }
  window.dafnisToast = toast;

  var flash = $('#flash-data');
  if (flash) {
    try {
      var data = JSON.parse(flash.textContent);
      toast(data.message, data.type);
    } catch (e) { /* ignora */ }
  }

  /* ---------- fetch com CSRF ---------- */
  function send(url, body) {
    return fetch(url, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrf }
    }).then(function (r) {
      return r.json().catch(function () { return { ok: false, message: 'Resposta inesperada do servidor.' }; }).then(function (json) {
        if (!r.ok && json.ok !== false) json.ok = false;
        return json;
      });
    });
  }
  window.dafnisSend = send;

  /* ---------- contador do carrinho ---------- */
  function setCartCount(count) {
    $all('[data-cart-count]').forEach(function (badge) {
      badge.textContent = count;
      badge.hidden = !count;
    });
    $all('[data-cart-link]').forEach(function (link) {
      link.setAttribute('aria-label', 'Carrinho, ' + count + (count === 1 ? ' item' : ' itens'));
      link.classList.remove('bump');
      void link.offsetWidth;
      link.classList.add('bump');
    });
  }

  function setLoading(btn, on) {
    if (!btn) return;
    btn.classList.toggle('is-loading', on);
    btn.setAttribute('aria-busy', on ? 'true' : 'false');
    btn.disabled = on;
  }

  /* ---------- adicionar ao carrinho ---------- */
  doc.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('[data-add-to-cart]')) return;
    var submitter = e.submitter;
    var buyNow = submitter && submitter.name === 'buy_now';
    if (buyNow) {
      // "Comprar agora" segue o fluxo normal: o servidor adiciona e abre o carrinho.
      setLoading(submitter, true);
      return;
    }
    e.preventDefault();
    var btn = submitter || form.querySelector('[type=submit]');
    setLoading(btn, true);
    send(form.action, new FormData(form)).then(function (json) {
      if (json.ok) {
        setCartCount(json.count);
        toast(json.message, 'success', { label: 'Ver carrinho', href: json.cart_url });
      } else {
        toast(json.message || 'Não foi possível adicionar ao carrinho.', 'error');
      }
    }).catch(function () {
      form.submit();
    }).then(function () { setLoading(btn, false); });
  });

  /* ---------- carrinho (quantidade, remover, cupom) ---------- */
  doc.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form.matches('[data-cart-form]')) return;
    e.preventDefault();
    var root = $('[data-cart-root]');
    var btn = e.submitter || form.querySelector('[type=submit]');
    if (btn && form.matches('.coupon')) setLoading(btn, true);
    var row = form.closest('[data-cart-row]');
    if (form.matches('[data-remove]') && row && !reduceMotion) row.classList.add('is-leaving');
    send(form.action, new FormData(form)).then(function (json) {
      if (root && json.html !== undefined) {
        var focusedName = doc.activeElement && doc.activeElement.closest('[data-cart-row]') ? doc.activeElement.closest('[data-cart-row]').getAttribute('data-cart-row') : null;
        root.innerHTML = json.html;
        if (focusedName) {
          var again = root.querySelector('[data-cart-row="' + focusedName + '"] input[name=qty]');
          if (again) again.focus();
        }
      }
      if (typeof json.count === 'number') setCartCount(json.count);
      if (json.message) toast(json.message, json.ok ? 'success' : 'error');
      if (json.coupon_error) {
        var input = $('#cupom');
        if (input) input.focus();
      }
    }).catch(function () { form.submit(); });
  });

  /* ---------- stepper de participantes ---------- */
  var stepTimers = new WeakMap();
  function clampInput(input) {
    var min = parseInt(input.min || '1', 10);
    var max = parseInt(input.max || '200', 10);
    var v = parseInt(input.value, 10);
    if (isNaN(v)) v = min;
    v = Math.max(min, Math.min(max, v));
    input.value = v;
    var stepper = input.closest('[data-stepper]');
    if (stepper) {
      var minus = stepper.querySelector('[data-step="-1"]');
      var plus = stepper.querySelector('[data-step="1"]');
      if (minus) minus.disabled = v <= min;
      if (plus) plus.disabled = v >= max;
    }
    return v;
  }
  function stepperChanged(input) {
    var v = clampInput(input);
    var stepper = input.closest('[data-stepper]');
    var buyForm = input.closest('[data-buy-form]');
    if (buyForm) {
      var unit = parseFloat(buyForm.getAttribute('data-unit') || '0');
      var box = $('[data-qty-total]', buyForm);
      if (box) {
        box.hidden = v <= 1;
        $('[data-qty-n]', box).textContent = v;
        $('[data-qty-sum]', box).textContent = 'R$ ' + (unit * v).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      }
    }
    if (stepper && stepper.hasAttribute('data-autosubmit')) {
      var form = stepper.closest('form');
      clearTimeout(stepTimers.get(form));
      stepTimers.set(form, setTimeout(function () {
        if (form.requestSubmit) form.requestSubmit(); else form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
      }, 450));
    }
  }
  doc.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-step]');
    if (!btn) return;
    var stepper = btn.closest('[data-stepper]');
    var input = stepper && stepper.querySelector('input');
    if (!input) return;
    input.value = (parseInt(input.value, 10) || 1) + parseInt(btn.getAttribute('data-step'), 10);
    stepper.classList.remove('bumped');
    void stepper.offsetWidth;
    stepper.classList.add('bumped');
    stepperChanged(input);
  });
  doc.addEventListener('change', function (e) {
    if (e.target.matches('[data-stepper] input')) stepperChanged(e.target);
  });
  $all('[data-stepper] input').forEach(clampInput);

  /* ---------- modo escuro (o tema inicial é aplicado por boot.js, antes da pintura) ---------- */
  var htmlEl = doc.documentElement;
  var themeBtns = $all('[data-theme-toggle]');
  var themeMeta = $('meta[name="theme-color"]');
  function applyTheme(dark) {
    htmlEl.setAttribute('data-theme', dark ? 'dark' : 'light');
    themeBtns.forEach(function (b) { b.setAttribute('aria-pressed', dark ? 'true' : 'false'); });
    if (themeMeta) themeMeta.setAttribute('content', dark ? '#0A1322' : '#0B2545');
  }
  if (themeBtns.length && htmlEl.hasAttribute('data-themeable')) {
    applyTheme(htmlEl.getAttribute('data-theme') === 'dark');
    themeBtns.forEach(function (b) {
      b.hidden = false;
      b.addEventListener('click', function () {
        var dark = htmlEl.getAttribute('data-theme') !== 'dark';
        applyTheme(dark);
        try { localStorage.setItem('dafnis-theme', dark ? 'dark' : 'light'); } catch (e) {}
      });
    });
    // Sem escolha salva, acompanha a troca de tema do sistema.
    var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)');
    if (systemDark && systemDark.addEventListener) {
      systemDark.addEventListener('change', function (e) {
        var saved = null;
        try { saved = localStorage.getItem('dafnis-theme'); } catch (err) {}
        if (!saved) applyTheme(e.matches);
      });
    }
  }

  /* ---------- cabeçalho: sombra ao rolar; na home, transparente sobre a foto até rolar ---------- */
  var header = $('[data-header]');
  function updateHeader() {
    if (!header) return;
    var scrolled = window.scrollY > 8;
    header.classList.toggle('is-scrolled', scrolled);
    if (header.hasAttribute('data-over')) {
      header.classList.toggle('is-clear', !scrolled && !(mega && !mega.hidden));
    }
  }
  if (header) {
    var headerTick = false;
    window.addEventListener('scroll', function () {
      if (headerTick) return;
      headerTick = true;
      requestAnimationFrame(function () { headerTick = false; updateHeader(); });
    }, { passive: true });
    updateHeader();
  }

  /* ---------- menu de NRs ---------- */
  var megaToggle = $('[data-mega-toggle]');
  var mega = $('#mega-nr');
  function closeMega() {
    if (!mega || mega.hidden) return;
    mega.hidden = true;
    megaToggle.setAttribute('aria-expanded', 'false');
    updateHeader();
  }
  if (megaToggle && mega) {
    megaToggle.addEventListener('click', function (e) {
      if (window.matchMedia('(max-width: 940px)').matches) return;
      e.preventDefault();
      var open = mega.hidden;
      mega.hidden = !open;
      megaToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      updateHeader();
      if (open) {
        var first = mega.querySelector('a');
        if (first && e.detail === 0) first.focus();
      }
    });
    doc.addEventListener('click', function (e) {
      if (!mega.hidden && !mega.contains(e.target) && !megaToggle.contains(e.target)) closeMega();
    });
  }

  /* ---------- gaveta (menu do celular) ---------- */
  var lastFocus = null;
  function focusables(root) {
    return $all('a[href], button:not([disabled]), input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])', root)
      .filter(function (el) { return el.offsetParent !== null; });
  }
  function openDrawer(id, opener) {
    var root = doc.getElementById(id);
    if (!root) return;
    lastFocus = opener || doc.activeElement;
    root.hidden = false;
    doc.body.classList.add('lock');
    if (opener) opener.setAttribute('aria-expanded', 'true');
    var f = focusables(root.querySelector('.drawer') || root);
    if (f[1]) f[1].focus();
    root.addEventListener('keydown', trap);
  }
  function closeDrawer(root) {
    root.hidden = true;
    doc.body.classList.remove('lock');
    $all('[data-drawer-open="' + root.id + '"]').forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
    root.removeEventListener('keydown', trap);
    if (lastFocus) lastFocus.focus();
  }
  function trap(e) {
    if (e.key !== 'Tab') return;
    var f = focusables(e.currentTarget.querySelector('.drawer') || e.currentTarget);
    if (!f.length) return;
    if (e.shiftKey && doc.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
    else if (!e.shiftKey && doc.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
  }
  doc.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-drawer-open]');
    if (opener) { openDrawer(opener.getAttribute('data-drawer-open'), opener); return; }
    var closer = e.target.closest('[data-drawer-close], [data-drawer-close-on-click]');
    if (closer) {
      var root = closer.closest('.drawer-root');
      if (root) closeDrawer(root);
    }
  });
  doc.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    closeMega();
    $all('.drawer-root:not([hidden])').forEach(closeDrawer);
  });

  /* ---------- busca do cabeçalho ---------- */
  var catalogSearch = $('[data-catalog-search]');
  doc.addEventListener('click', function (e) {
    var link = e.target.closest('[data-open-search]');
    if (link && catalogSearch) {
      e.preventDefault();
      catalogSearch.focus();
      catalogSearch.scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
    }
  });
  if (catalogSearch && location.hash === '#busca') catalogSearch.focus();

  /* ---------- acordeões (FAQ, conteúdo programático) ---------- */
  doc.addEventListener('click', function (e) {
    var q = e.target.closest('[data-accordion] [aria-controls]');
    if (!q) return;
    var group = q.closest('[data-accordion]');
    var panel = doc.getElementById(q.getAttribute('aria-controls'));
    var open = q.getAttribute('aria-expanded') !== 'true';
    $all('[aria-controls]', group).forEach(function (other) {
      if (other !== q && other.getAttribute('aria-expanded') === 'true') {
        other.setAttribute('aria-expanded', 'false');
        var p = doc.getElementById(other.getAttribute('aria-controls'));
        if (p) p.hidden = true;
      }
    });
    q.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (panel) panel.hidden = !open;
  });

  /* ---------- submenu da página do curso: marca a seção visível ---------- */
  var subnav = $('[data-subnav]');
  if (subnav && 'IntersectionObserver' in window) {
    var links = $all('a', subnav);
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        links.forEach(function (a) { a.classList.toggle('on', a.getAttribute('href') === '#' + entry.target.id); });
      });
    }, { rootMargin: '-40% 0px -55% 0px' });
    links.forEach(function (a) {
      var sec = doc.getElementById(a.getAttribute('href').slice(1));
      if (sec) io.observe(sec);
    });
  }

  /* ---------- máscaras ---------- */
  var masks = {
    cpf: function (d) { d = d.slice(0, 11); return d.replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d{1,2})$/, '.$1-$2'); },
    cnpj: function (d) { d = d.slice(0, 14); return d.replace(/^(\d{2})(\d)/, '$1.$2').replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3').replace(/\.(\d{3})(\d)/, '.$1/$2').replace(/(\d{4})(\d)/, '$1-$2'); },
    phone: function (d) {
      d = d.slice(0, 11);
      if (d.length <= 2) return d.length ? '(' + d : '';
      if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
      if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
      return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
    },
    money: function (d) {
      if (!d) return '';
      d = d.replace(/^0+(?=\d)/, '').slice(0, 9);
      while (d.length < 3) d = '0' + d;
      var reais = d.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      return reais + ',' + d.slice(-2);
    }
  };
  doc.addEventListener('input', function (e) {
    var el = e.target;
    var kind = el.getAttribute && el.getAttribute('data-mask');
    if (!kind || !masks[kind]) return;
    var digits = el.value.replace(/\D/g, '');
    el.value = masks[kind](digits);
  });

  /* ---------- mostrar senha ---------- */
  doc.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-password-toggle]');
    if (!btn) return;
    var input = doc.getElementById(btn.getAttribute('data-password-toggle'));
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    btn.setAttribute('aria-label', show ? 'Ocultar senha' : 'Mostrar senha');
  });

  /* ---------- confirmações e estado de envio ---------- */
  doc.addEventListener('submit', function (e) {
    var form = e.target;
    var msg = form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
      e.preventDefault();
      return;
    }
    if (form.matches('[data-loading-form], [data-checkout]') && !e.defaultPrevented) {
      var btn = e.submitter || form.querySelector('[type=submit]');
      if (btn) setTimeout(function () { setLoading(btn, true); }, 0);
    }
  });

  /* ---------- checkout: pessoa física x empresa ---------- */
  var checkout = $('[data-checkout]');
  if (checkout) {
    var applyBuyer = function () {
      var pj = (checkout.querySelector('[data-buyer-type]:checked') || {}).value === 'pj';
      $all('[data-pj]', checkout).forEach(function (el) { el.hidden = !pj; });
      $all('[data-pf]', checkout).forEach(function (el) { el.hidden = pj; });
      $all('[data-pj-required]', checkout).forEach(function (el) { el.required = pj; });
      $all('[data-pf-required]', checkout).forEach(function (el) { el.required = !pj; });
      var wrap = $('[data-name-wrap]', checkout);
      if (wrap) wrap.className = pj ? '' : 'full';
      var label = $('label[for="f-buyer-name"]', checkout);
      if (label) label.textContent = pj ? 'Responsável pela compra' : 'Nome completo';
    };
    $all('[data-buyer-type]', checkout).forEach(function (r) { r.addEventListener('change', applyBuyer); });
    applyBuyer();
  }

  /* ---------- NRs: filtra os cartões ao digitar (número da norma ou parte do nome) ---------- */
  var nrFilter = $('[data-nr-filter]');
  if (nrFilter) {
    var nrItems = $all('[data-nr-item]');
    var nrEmpty = $('[data-nr-empty]');
    var nrSearchLink = $('[data-nr-search-link]');
    var plain = function (s) { return String(s).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase().trim(); };
    var visibleNrs = function () { return nrItems.filter(function (el) { return !el.hidden; }); };
    var filterNrs = function () {
      var q = plain(nrFilter.value).replace(/^nr\s*/, '');
      var numeric = /^\d+$/.test(q);
      nrItems.forEach(function (el) {
        el.hidden = q !== '' && !(numeric ? el.getAttribute('data-nr').indexOf(q) === 0 : el.getAttribute('data-name').indexOf(q) !== -1);
      });
      if (nrEmpty) nrEmpty.hidden = visibleNrs().length > 0;
      if (nrSearchLink) nrSearchLink.href = nrFilter.form.action + '?q=' + encodeURIComponent(nrFilter.value.trim());
    };
    nrFilter.addEventListener('input', filterNrs);
    // Enter: com uma NR só na lista, abre ela; sem nenhuma, pesquisa no catálogo
    nrFilter.form.addEventListener('submit', function (e) {
      var shown = visibleNrs();
      if (shown.length === 0 && nrFilter.value.trim() !== '') return;
      e.preventDefault();
      if (shown.length === 1) window.location.href = shown[0].href;
    });
    if (nrFilter.value) filterNrs();
  }

  /* ---------- cadastro: requisitos da senha e a confirmação conferidos enquanto a pessoa digita ---------- */
  var pwRules = $('[data-pw-rules]');
  var pwInput = pwRules && doc.getElementById(pwRules.getAttribute('data-pw-rules'));
  if (pwInput) {
    var pwMatch = $('[data-pw-match]');
    var pwConfirm = pwMatch && doc.getElementById(pwMatch.getAttribute('data-pw-match'));
    // Mesmas regras do servidor (Validator: password)
    var pwTests = {
      len: function (v) { return v.length >= 8; },
      letter: function (v) { return /[A-Za-z]/.test(v); },
      digit: function (v) { return /\d/.test(v); }
    };
    var checkPassword = function () {
      var v = pwInput.value;
      $all('[data-rule]', pwRules).forEach(function (li) { li.classList.toggle('ok', pwTests[li.getAttribute('data-rule')](v)); });
      if (!pwConfirm) return;
      var c = pwConfirm.value;
      var same = c !== '' && c === v;
      pwMatch.hidden = c === '';
      pwMatch.className = 'pw-match ' + (same ? 'ok' : 'no');
      pwMatch.innerHTML = svg(same ? 'check' : 'alert') + (same ? 'As senhas conferem' : 'As senhas ainda não conferem');
    };
    pwInput.addEventListener('input', checkPassword);
    if (pwConfirm) pwConfirm.addEventListener('input', checkPassword);
    checkPassword();
  }

  /* ---------- código de verificação: seis casas (adaptado do componente OtpInput em React) ----------
     O campo original vira oculto e guarda o valor; digitar avança, colar preenche tudo, Backspace volta,
     setas/Home/End navegam e, com data-otp-autosubmit, o formulário é enviado ao completar. */
  $all('[data-otp]').forEach(function (root) {
    var native = $('[data-otp-input]', root);
    if (!native) return;
    var length = parseInt(native.getAttribute('maxlength'), 10) || 6;
    var groupEvery = 3;
    var label = root.getAttribute('data-otp-label') || 'Código de verificação';
    var form = native.form;
    var msg = root.parentNode && $('[data-otp-msg]', root.parentNode);
    var chars = [];
    var initial = (native.value || '').replace(/\D/g, '');
    for (var n = 0; n < length; n++) chars.push(initial.charAt(n));
    var cells = [];
    var slots = [];
    var glyphs = [];
    var focused = -1;
    var submitted = false;

    var group = doc.createElement('div');
    group.className = 'otp-cells';
    group.setAttribute('role', 'group');
    group.setAttribute('aria-label', label);
    for (var i = 0; i < length; i++) {
      var slot = doc.createElement('div');
      slot.className = 'otp-slot' + (i > 0 && i % groupEvery === 0 ? ' gap' : '');
      var cell = doc.createElement('input');
      cell.type = 'text';
      cell.className = 'otp-cell';
      cell.inputMode = 'numeric';
      cell.autocomplete = i === 0 ? 'one-time-code' : 'off';
      cell.setAttribute('autocorrect', 'off');
      cell.setAttribute('autocapitalize', 'off');
      cell.spellcheck = false;
      cell.setAttribute('aria-label', label + ', dígito ' + (i + 1) + ' de ' + length);
      if (native.getAttribute('aria-describedby')) cell.setAttribute('aria-describedby', native.getAttribute('aria-describedby'));
      if (native.getAttribute('aria-invalid')) cell.setAttribute('aria-invalid', 'true');
      var glyph = doc.createElement('span');
      glyph.className = 'otp-glyph';
      glyph.setAttribute('aria-hidden', 'true');
      var caret = doc.createElement('span');
      caret.className = 'otp-caret';
      caret.setAttribute('aria-hidden', 'true');
      slot.appendChild(cell);
      slot.appendChild(glyph);
      slot.appendChild(caret);
      group.appendChild(slot);
      cells.push(cell);
      slots.push(slot);
      glyphs.push(glyph);
    }
    // O campo original fica oculto (envia o valor); o rótulo passa a apontar para a primeira casa
    var lab = native.id && doc.querySelector('label[for="' + native.id + '"]');
    cells[0].id = native.id + '-1';
    if (lab) lab.htmlFor = cells[0].id;
    var wantsFocus = native.hasAttribute('autofocus');
    native.type = 'hidden';
    native.removeAttribute('autofocus');
    root.appendChild(group);

    var keep = function (text) { return String(text).replace(/\D/g, ''); };
    var render = function () {
      for (var k = 0; k < length; k++) {
        var c = chars[k] || '';
        if (cells[k].value !== c) cells[k].value = c;
        if (glyphs[k].textContent !== c) {
          glyphs[k].textContent = c;
          glyphs[k].classList.remove('pop');
          if (c && !reduceMotion) { void glyphs[k].offsetWidth; glyphs[k].classList.add('pop'); }
        }
        slots[k].classList.toggle('filled', c !== '');
        slots[k].classList.toggle('active', focused === k);
      }
    };
    var clearError = function () {
      if (!root.classList.contains('is-error')) return;
      root.classList.remove('is-error', 'shake');
      cells.forEach(function (c) { c.removeAttribute('aria-invalid'); });
      if (msg) {
        var hint = msg.getAttribute('data-hint');
        msg.classList.remove('is-error');
        msg.innerHTML = hint ? '<span>' + escapeHtml(hint) + '</span>' : '';
      }
    };
    var commit = function (next) {
      chars = next;
      native.value = chars.join('');
      clearError();
      render();
      if (chars.every(function (c) { return c !== ''; }) && form && root.hasAttribute('data-otp-autosubmit') && !submitted) {
        submitted = true;
        setTimeout(function () {
          if (form.requestSubmit) form.requestSubmit(); else form.submit();
        }, 140);
      }
    };
    var focusAt = function (index) {
      var el = cells[Math.max(0, Math.min(length - 1, index))];
      el.focus();
      el.select();
    };
    var fillFrom = function (index, text) {
      var incoming = keep(text);
      if (!incoming) return;
      var next = chars.slice();
      var cursor = index;
      for (var k = 0; k < incoming.length && cursor < length; k++) next[cursor++] = incoming.charAt(k);
      commit(next);
      focusAt(cursor);
    };

    cells.forEach(function (cell, index) {
      cell.addEventListener('input', function () {
        var previous = chars[index] || '';
        var raw = cell.value;
        var trimmed = raw.length > 1 && previous && raw.indexOf(previous) === 0 ? raw.slice(previous.length) : raw;
        var incoming = keep(trimmed);
        if (!incoming) {
          if (raw === '' && previous) {
            var cleared = chars.slice();
            cleared[index] = '';
            commit(cleared);
          }
          cell.value = chars[index] || '';
          return;
        }
        if (incoming.length === 1) {
          var next = chars.slice();
          next[index] = incoming;
          commit(next);
          if (index < length - 1) focusAt(index + 1);
          return;
        }
        fillFrom(index, incoming);
      });
      cell.addEventListener('keydown', function (e) {
        var next;
        if (e.key === 'Backspace') {
          e.preventDefault();
          next = chars.slice();
          if (chars[index]) { next[index] = ''; commit(next); return; }
          if (index > 0) { next[index - 1] = ''; commit(next); focusAt(index - 1); }
          return;
        }
        if (e.key === 'Delete') { e.preventDefault(); next = chars.slice(); next[index] = ''; commit(next); return; }
        if (e.key === 'ArrowLeft') { e.preventDefault(); focusAt(index - 1); return; }
        if (e.key === 'ArrowRight') { e.preventDefault(); focusAt(index + 1); return; }
        if (e.key === 'Home') { e.preventDefault(); focusAt(0); return; }
        if (e.key === 'End') { e.preventDefault(); focusAt(length - 1); }
      });
      cell.addEventListener('paste', function (e) {
        e.preventDefault();
        var text = keep((e.clipboardData || window.clipboardData).getData('text'));
        fillFrom(text.length >= length ? 0 : index, text);
      });
      cell.addEventListener('focus', function () {
        cell.select();
        var firstEmpty = chars.indexOf('');
        if (firstEmpty !== -1 && firstEmpty < index) { focusAt(firstEmpty); return; }
        focused = index;
        render();
      });
      cell.addEventListener('blur', function (e) {
        if (e.relatedTarget && cells.indexOf(e.relatedTarget) !== -1) return;
        focused = -1;
        render();
      });
    });
    if (form) form.addEventListener('submit', function () { submitted = true; });

    render();
    // Código errado (o servidor devolve a página com o erro): treme e volta para a primeira casa
    if (root.classList.contains('is-error')) {
      if (!reduceMotion) root.classList.add('shake');
      focusAt(0);
    } else if (wantsFocus) {
      focusAt(0);
    }
  });

  /* ---------- copiar e baixar texto (chave da verificação, códigos de recuperação) ---------- */
  $all('[data-copy]').forEach(function (btn) {
    if (!navigator.clipboard) return;
    btn.hidden = false;
    btn.addEventListener('click', function () {
      var src = doc.getElementById(btn.getAttribute('data-copy'));
      if (!src) return;
      navigator.clipboard.writeText(src.getAttribute('data-copy-text') || src.textContent.trim()).then(function () {
        toast(btn.getAttribute('data-copied') || 'Copiado.');
      });
    });
  });
  $all('[data-download-text]').forEach(function (btn) {
    btn.hidden = false;
    btn.addEventListener('click', function () {
      var url = URL.createObjectURL(new Blob([btn.getAttribute('data-download-text')], { type: 'text/plain;charset=utf-8' }));
      var a = doc.createElement('a');
      a.href = url;
      a.download = btn.getAttribute('data-filename') || 'arquivo.txt';
      doc.body.appendChild(a);
      a.click();
      a.remove();
      setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
    });
  });

  /* ---------- páginas legais: índice marca a seção visível; botão de imprimir ---------- */
  var tocLinks = $all('[data-toc-link]');
  if (tocLinks.length && 'IntersectionObserver' in window) {
    var tocById = {};
    tocLinks.forEach(function (a) { tocById[a.getAttribute('href').slice(1)] = a; });
    var tocSpy = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        tocLinks.forEach(function (a) { a.classList.toggle('on', a === tocById[entry.target.id]); });
      });
    }, { rootMargin: '-100px 0px -60% 0px' });
    Object.keys(tocById).forEach(function (id) { var el = doc.getElementById(id); if (el) tocSpy.observe(el); });
  }
  $all('[data-print]').forEach(function (btn) {
    btn.hidden = false;
    btn.addEventListener('click', function () { window.print(); });
  });

  /* ---------- pedido: abre o Mercado Pago logo após o checkout ---------- */
  var autopay = $('[data-autopay="1"]');
  if (autopay) {
    setTimeout(function () { window.location.href = autopay.href; }, 1200);
  }
})();
