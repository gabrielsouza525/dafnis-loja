/* Prévia estática (GitHub Pages): não há servidor, então os formulários que
   gravariam algo viram navegação entre as telas de exemplo geradas pelo export.
   Carregado só com STATIC_DEMO=true; a loja publicada não usa este arquivo. */
(function () {
  'use strict';

  var doc = document;
  var map = window.DAFNIS_DEMO || {};

  function notice(msg) {
    if (window.dafnisToast) window.dafnisToast(msg, 'info');
  }

  doc.addEventListener('submit', function (e) {
    var form = e.target;
    // Busca da home e filtros do catálogo continuam funcionando (GET e filtro no navegador).
    if (form.id === 'filtros-form' || (form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
    e.preventDefault();
    e.stopImmediatePropagation();

    var action = form.getAttribute('action') || '';
    if (form.matches('[data-add-to-cart]') && map.cart) { location.href = map.cart; return; }
    if (form.matches('[data-checkout]') && map.order) { location.href = map.order; return; }
    if (/\/(login|cadastro)\/?$/.test(action) && map.account) { location.href = map.account; return; }
    if (/\/sair\/?$/.test(action) && map.home) { location.href = map.home; return; }
    notice('Prévia: esta ação funciona na loja publicada, não nesta demonstração.');
  }, true);

  // Entrar / criar conta na prévia leva direto à conta de exemplo, sem exigir preencher.
  Array.prototype.forEach.call(doc.querySelectorAll('form[action$="/login/"], form[action$="/cadastro/"]'), function (form) {
    form.noValidate = true;
  });

  // Contato aberto a partir de um botão ("Fale com nossa equipe", "Solicitar proposta"...).
  var subject = new URLSearchParams(location.search).get('assunto');
  var select = doc.querySelector('select[name="subject"]');
  if (subject && select && select.querySelector('option[value="' + subject + '"]')) select.value = subject;
})();
