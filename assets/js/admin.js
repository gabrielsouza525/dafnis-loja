/* Painel da equipe: menu no celular, listas repetíveis (módulos, FAQ) e slug automático. */
(function () {
  'use strict';

  var doc = document;

  var menuBtn = doc.querySelector('[data-admin-menu]');
  var side = doc.getElementById('adm-side');
  if (menuBtn && side) {
    menuBtn.addEventListener('click', function () {
      var open = !side.classList.contains('open');
      side.classList.toggle('open', open);
      menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    doc.addEventListener('click', function (e) {
      if (side.classList.contains('open') && !side.contains(e.target) && !menuBtn.contains(e.target)) {
        side.classList.remove('open');
        menuBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  doc.addEventListener('click', function (e) {
    var add = e.target.closest('[data-repeater-add]');
    if (add) {
      var list = doc.querySelector('[data-repeater="' + add.getAttribute('data-repeater-add') + '"]');
      var rows = list ? list.querySelectorAll('[data-repeater-row]') : [];
      if (!rows.length) return;
      var clone = rows[rows.length - 1].cloneNode(true);
      Array.prototype.forEach.call(clone.querySelectorAll('input, textarea'), function (el) { el.value = ''; });
      list.appendChild(clone);
      var first = clone.querySelector('input, textarea');
      if (first) first.focus();
      return;
    }
    var remove = e.target.closest('[data-repeater-remove]');
    if (remove) {
      var row = remove.closest('[data-repeater-row]');
      var parent = row.parentNode;
      if (parent.querySelectorAll('[data-repeater-row]').length > 1) {
        row.remove();
      } else {
        Array.prototype.forEach.call(row.querySelectorAll('input, textarea'), function (el) { el.value = ''; });
      }
    }
  });

  var source = doc.querySelector('[data-slug-source]');
  var target = doc.querySelector('[data-slug-target]');
  if (source && target) {
    var auto = target.value === '';
    var slugify = function (s) {
      return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
    };
    target.addEventListener('input', function () { auto = target.value === ''; });
    source.addEventListener('input', function () {
      if (auto) target.placeholder = slugify(source.value);
    });
  }
})();
