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

  /* ---------- menu de NRs ---------- */
  var megaToggle = $('[data-mega-toggle]');
  var mega = $('#mega-nr');
  function closeMega() {
    if (!mega || mega.hidden) return;
    mega.hidden = true;
    megaToggle.setAttribute('aria-expanded', 'false');
  }
  if (megaToggle && mega) {
    megaToggle.addEventListener('click', function (e) {
      if (window.matchMedia('(max-width: 940px)').matches) return;
      e.preventDefault();
      var open = mega.hidden;
      mega.hidden = !open;
      megaToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
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

  /* ---------- pedido: abre o Mercado Pago logo após o checkout ---------- */
  var autopay = $('[data-autopay="1"]');
  if (autopay) {
    setTimeout(function () { window.location.href = autopay.href; }, 1200);
  }
})();
