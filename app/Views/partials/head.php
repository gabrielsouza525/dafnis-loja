<?php
/**
 * <head> comum. Variáveis: $title, $description, $canonical, $noindex, $jsonLd (lista), $css (lista), $scripts (lista)
 */
use App\Services\Settings;

$brand = Settings::businessName();
$pageTitle = isset($title) && $title !== '' ? $title . ' | Dafnis Treinamentos' : 'Dafnis Treinamentos — Cursos NR e treinamentos de segurança do trabalho';
$metaDescription = $description ?? 'Treinamentos de Normas Regulamentadoras, segurança do trabalho, primeiros socorros e cursos complementares, com certificado de conclusão. Compre para você ou para sua equipe.';
$canonicalUrl = isset($canonical) ? absolute_url($canonical) : null;
$ogImage = is_file(BASE_PATH . '/public/assets/img/og.png') ? absolute_url('/assets/img/og.png') : null;
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($pageTitle) ?></title>
<meta name="description" content="<?= e($metaDescription) ?>">
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<?php if ($canonicalUrl): ?>
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php endif; ?>
<meta name="theme-color" content="#0B2545">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="<?= e($brand) ?>">
<meta property="og:title" content="<?= e($pageTitle) ?>">
<meta property="og:description" content="<?= e($metaDescription) ?>">
<?php if ($canonicalUrl): ?>
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<?php endif; ?>
<?php if ($ogImage): ?>
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<?php endif; ?>
<link rel="icon" href="<?= e(url('/favicon.svg')) ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="<?= e(url('/apple-touch-icon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Schibsted+Grotesk:wght@500;600;700;800&amp;family=IBM+Plex+Sans:wght@400;500;600&amp;family=IBM+Plex+Mono:wght@500;600&amp;display=swap">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
<?php foreach ($css ?? [] as $sheet): ?>
<link rel="stylesheet" href="<?= e(asset('css/' . $sheet)) ?>">
<?php endforeach; ?>
<?php foreach ($jsonLd ?? [] as $ld): ?>
<script type="application/ld+json"><?= json_script($ld) ?></script>
<?php endforeach; ?>
<script src="<?= e(asset('js/boot.js')) ?>"></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?php foreach ($scripts ?? [] as $script): ?>
<script src="<?= e(asset('js/' . $script)) ?>" defer></script>
<?php endforeach; ?>
