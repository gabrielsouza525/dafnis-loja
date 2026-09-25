<?php
/**
 * Layout da loja: aviso, cabeçalho (menu de NRs e gaveta no celular), conteúdo e rodapé.
 * @var string $content
 * @var string|null $nav  item ativo: inicio|cursos|conta
 */
use App\Models\Category;
use App\Models\Course;
use App\Services\Auth;
use App\Services\Cart;
use App\Services\Payments\Payments;
use App\Services\Settings;

$user = Auth::user();
$cartCount = Cart::count();
$nrIndex = Course::nrIndex();
$nav = $nav ?? null;
$notice = Settings::get('notice.text');
if (static_demo()) {
    $notice = '<strong>Prévia da loja para aprovação.</strong> Cursos e preços reais do catálogo; contas e pedidos são de exemplo e nenhuma compra é realizada.';
    $noticeHtml = true;
} elseif ($notice === null && !Payments::isOnline()) {
    $notice = '<strong>Pagamento online em ativação.</strong> Seu pedido é registrado e a nossa equipe envia as instruções de pagamento.';
    $noticeHtml = true;
}
$accountLabel = $user ? (Auth::isAdmin() ? 'Painel' : 'Minha conta') : 'Entrar';
$accountUrl = $user ? url(Auth::homePath()) : url('/login');
$whatsapp = Settings::get('business.whatsapp');
$phone = Settings::get('business.phone');
$email = Settings::get('business.email');
$socials = array_filter([
    'Instagram' => ($ig = Settings::get('business.instagram')) ? 'https://www.instagram.com/' . ltrim((string) $ig, '@') . '/' : null,
    'LinkedIn' => Settings::get('business.linkedin'),
    'YouTube' => Settings::get('business.youtube'),
]);
$categories = Category::active();
$catUrl = static function (string $slug) use ($categories): ?string {
    foreach ($categories as $c) {
        if ($c['slug'] === $slug && $c['course_count'] > 0) {
            return $c['url'];
        }
    }
    return null;
};
?>
<!doctype html>
<html lang="pt-BR">
<head>
<?= partial('head', get_defined_vars()) ?>
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<?php if ($notice): ?>
<div class="demobar" role="note"><?= icon('info', 'ic-sm') ?><span><?= !empty($noticeHtml) ? $notice : e($notice) ?></span></div>
<?php endif; ?>

<header class="hdr" data-header>
<div class="wrap hdr-in">
<a class="logo" href="<?= e(url('/')) ?>" aria-label="Dafnis Treinamentos — página inicial"><?= partial('logo') ?></a>
<nav class="nav" aria-label="Principal">
<a class="nav-link<?= $nav === 'inicio' ? ' on" aria-current="page' : '' ?>" href="<?= e(url('/')) ?>">Início</a>
<a class="nav-link<?= $nav === 'cursos' ? ' on" aria-current="page' : '' ?>" href="<?= e(url('/cursos')) ?>">Cursos</a>
<a class="nav-link<?= $nav === 'nrs' ? ' on' : '' ?>" href="<?= e(url('/nrs')) ?>" data-mega-toggle aria-controls="mega-nr" aria-expanded="false">NRs<?= icon('chevD', 'ic-sm') ?></a>
<a class="nav-link" href="<?= e(url('/#categorias')) ?>">Categorias</a>
<a class="nav-link" href="<?= e(url('/#empresas')) ?>">Empresas</a>
<a class="nav-link" href="<?= e(url('/#sobre')) ?>">Sobre nós</a>
</nav>
<div class="hdr-actions">
<a class="icon-btn" href="<?= e(url('/cursos#busca')) ?>" aria-label="Pesquisar treinamentos" data-open-search><?= icon('search') ?></a>
<a class="link-btn hide-t" href="<?= e($accountUrl) ?>"><?= icon('user') ?><?= e($accountLabel) ?></a>
<a class="icon-btn" href="<?= e(url('/carrinho')) ?>" data-cart-link aria-label="<?= e('Carrinho, ' . pluralize($cartCount, 'item', 'itens')) ?>"><?= icon('cart') ?><span class="cart-count" data-cart-count<?= $cartCount ? '' : ' hidden' ?>><?= $cartCount ?></span></a>
<a class="btn btn-navy btn-sm hdr-cta" href="<?= e(url('/cursos')) ?>">Ver cursos</a>
<button class="icon-btn show-c" type="button" aria-label="Abrir menu" aria-controls="menu-drawer" aria-expanded="false" data-drawer-open="menu-drawer"><?= icon('menu') ?></button>
</div>
</div>
<div class="mega" id="mega-nr" hidden>
<div class="wrap mega-in">
<div class="mega-aside">
<div class="kicker">Acesso rápido</div>
<h3>Normas Regulamentadoras</h3>
<p>Selecione uma NR para ver todos os treinamentos relacionados.</p>
<a class="text-link" href="<?= e(url('/nrs')) ?>">Ver todas as NRs<?= icon('arrowR', 'ic-sm') ?></a>
</div>
<div class="mega-grid">
<?php foreach ($nrIndex as $m): ?>
<a class="mega-item" href="<?= e($m['url']) ?>"><span class="mega-code"><?= e($m['code']) ?></span><span class="mega-name"><?= e($m['name']) ?><small><?= e(pluralize($m['count'], 'treinamento', 'treinamentos')) ?></small></span></a>
<?php endforeach; ?>
</div>
</div>
</div>
</header>

<main id="conteudo" tabindex="-1">
<?= $content ?>
</main>

<footer class="ftr">
<div class="wrap">
<div class="ftr-top">
<div class="ftr-brand">
<a class="logo inv" href="<?= e(url('/')) ?>" aria-label="Dafnis — página inicial"><?= partial('logo') ?></a>
<p class="ftr-tag">Treinamentos profissionais para pessoas e empresas.</p>
</div>
<div><h2>Cursos</h2><div class="ftr-links">
<a class="ftr-link" href="<?= e(url('/nrs')) ?>">NRs</a>
<?php foreach (['seguranca-do-trabalho' => 'Segurança do Trabalho', 'primeiros-socorros' => 'Primeiros Socorros', 'treinamentos-corporativos' => 'Treinamentos Corporativos'] as $slug => $label): ?>
<?php if ($u = $catUrl($slug)): ?><a class="ftr-link" href="<?= e($u) ?>"><?= e($label) ?></a><?php endif; ?>
<?php endforeach; ?>
<a class="ftr-link" href="<?= e(url('/cursos')) ?>">Todos os treinamentos</a>
</div></div>
<div><h2>Empresa</h2><div class="ftr-links">
<a class="ftr-link" href="<?= e(url('/#sobre')) ?>">Sobre nós</a>
<a class="ftr-link" href="<?= e(url('/#empresas')) ?>">Empresas</a>
<a class="ftr-link" href="<?= e(url('/contato')) ?>">Contato</a>
</div></div>
<div><h2>Suporte</h2><div class="ftr-links">
<a class="ftr-link" href="<?= e(url('/#faq')) ?>">FAQ</a>
<a class="ftr-link" href="<?= e($accountUrl) ?>"><?= $user ? e($accountLabel) : 'Área do aluno' ?></a>
<a class="ftr-link" href="<?= e(url('/termos-de-uso')) ?>">Termos de uso</a>
<a class="ftr-link" href="<?= e(url('/politica-de-privacidade')) ?>">Política de privacidade</a>
</div></div>
<div class="ftr-social">
<?php if ($socials): ?>
<h2>Redes sociais</h2>
<div class="socials">
<?php foreach ($socials as $label => $href): ?><a class="social" href="<?= e($href) ?>" target="_blank" rel="noopener"><?= e($label) ?></a><?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($whatsapp || $phone || $email): ?>
<h2<?= $socials ? ' style="margin-top:24px"' : '' ?>>Atendimento</h2>
<div class="ftr-links">
<?php if ($whatsapp): ?><a class="ftr-link ftr-contact" href="<?= e(wa_link($whatsapp, 'Olá! Vim pelo site da Dafnis Treinamentos.')) ?>" target="_blank" rel="noopener"><?= icon('whatsapp') ?><?= e(phone_display($whatsapp)) ?></a><?php endif; ?>
<?php if ($phone && $phone !== $whatsapp): ?><a class="ftr-link ftr-contact" href="<?= e(tel_link($phone)) ?>"><?= icon('phone') ?><?= e(phone_display($phone)) ?></a><?php endif; ?>
<?php if ($email): ?><a class="ftr-link ftr-contact" href="mailto:<?= e($email) ?>"><?= icon('mail') ?><?= e($email) ?></a><?php endif; ?>
</div>
<?php elseif (!$socials): ?>
<h2>Atendimento</h2>
<div class="ftr-links"><a class="ftr-link" href="<?= e(url('/contato')) ?>">Fale com a nossa equipe</a></div>
<?php endif; ?>
</div>
</div>
<div class="ftr-bot"><span>© <?= date('Y') ?> <?= e(Settings::businessName()) ?>. Todos os direitos reservados.</span><span><?php
$bits = array_filter([
    ($cnpj = Settings::get('business.cnpj')) ? 'CNPJ ' . document_display($cnpj) : null,
    Settings::cityLine(),
]);
echo e(implode(' · ', $bits));
?></span></div>
</div>
</footer>

<div class="drawer-root" id="menu-drawer" hidden>
<button class="backdrop" type="button" aria-label="Fechar menu" data-drawer-close tabindex="-1"></button>
<div class="drawer" role="dialog" aria-modal="true" aria-label="Menu">
<div class="drawer-head"><span class="logo"><?= partial('logo', ['sub' => 'Treinamentos']) ?></span><button class="icon-btn" type="button" aria-label="Fechar menu" data-drawer-close><?= icon('close') ?></button></div>
<div class="drawer-body">
<a class="d-link<?= $nav === 'inicio' ? ' on' : '' ?>" href="<?= e(url('/')) ?>">Início<?= icon('chevR') ?></a>
<a class="d-link<?= $nav === 'cursos' ? ' on' : '' ?>" href="<?= e(url('/cursos')) ?>">Cursos<?= icon('chevR') ?></a>
<a class="d-link<?= $nav === 'nrs' ? ' on' : '' ?>" href="<?= e(url('/nrs')) ?>">NRs<?= icon('chevR') ?></a>
<a class="d-link" href="<?= e(url('/#categorias')) ?>" data-drawer-close-on-click>Categorias<?= icon('chevR') ?></a>
<a class="d-link" href="<?= e(url('/#empresas')) ?>" data-drawer-close-on-click>Empresas<?= icon('chevR') ?></a>
<a class="d-link" href="<?= e(url('/#sobre')) ?>" data-drawer-close-on-click>Sobre nós<?= icon('chevR') ?></a>
<a class="d-link" href="<?= e(url('/contato')) ?>">Contato<?= icon('chevR') ?></a>
<div class="d-sub">
<div class="kicker">Acesso rápido por NR</div>
<div class="quick">
<?php foreach ($nrIndex as $m): ?><a class="chip" href="<?= e($m['url']) ?>"><?= e($m['code']) ?></a><?php endforeach; ?>
</div>
</div>
</div>
<div class="drawer-foot"><a class="btn btn-outline" href="<?= e($accountUrl) ?>"><?= icon('user') ?><?= e($accountLabel) ?></a><a class="btn btn-navy" href="<?= e(url('/cursos')) ?>">Ver cursos</a></div>
</div>
</div>

<?= partial('flash') ?>
</body>
</html>
