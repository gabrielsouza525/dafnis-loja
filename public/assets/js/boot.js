// Marca que o JavaScript está ativo antes da pintura (evita piscar o layout sem JS).
document.documentElement.classList.add('js');

// Tema da loja, também antes da pintura: vale a escolha salva no botão; sem escolha, segue o sistema.
// Só nas páginas com o botão de tema (a loja); o painel da equipe fica sempre claro.
(function (root) {
  if (!root.hasAttribute('data-themeable')) return;
  var saved = null;
  try { saved = localStorage.getItem('dafnis-theme'); } catch (e) {}
  var dark = saved === 'dark' || saved === 'light' ? saved === 'dark' : !!(window.matchMedia && matchMedia('(prefers-color-scheme: dark)').matches);
  root.setAttribute('data-theme', dark ? 'dark' : 'light');
})(document.documentElement);
