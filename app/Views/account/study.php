<?php
/**
 * Página do curso SCORM: barra fina com a volta e o estado do salvamento; o pacote ocupa o resto.
 * O iframe só recebe o endereço depois que assets/js/scorm-player.js cria a API (window.API).
 * @var string $title @var string|null $code @var string $backUrl @var array $config
 */
$scripts = ['scorm-player.js'];
$preview = !empty($config['preview']);
?>
<!doctype html>
<html lang="pt-BR" data-themeable>
<head>
<?= partial('head', ['title' => $title, 'noindex' => true, 'scripts' => $scripts]) ?>
</head>
<body class="study" data-study>
<header class="study-bar">
<a class="study-back" href="<?= e($backUrl) ?>" data-study-back><?= icon('arrowL', 'ic-sm') ?><span><?= $preview ? 'Voltar ao painel' : 'Meus cursos' ?></span></a>
<div class="study-title"><?php if ($code): ?><span class="nr-tag"><?= e($code) ?></span><?php endif; ?><strong><?= e($title) ?></strong></div>
<p class="study-status" data-study-status role="status" aria-live="polite"><?= $preview ? 'Pré-visualização: nada é gravado' : 'Carregando o curso…' ?></p>
</header>
<div class="study-alert" data-study-alert role="alert" hidden></div>
<main class="study-main">
<iframe class="study-frame" title="<?= e('Conteúdo do curso ' . $title) ?>" data-study-frame allow="fullscreen; autoplay"></iframe>
<noscript><div class="study-noscript"><p>Para fazer o curso, ative o JavaScript do navegador.</p></div></noscript>
<div class="study-idle" data-study-idle hidden>
<div class="study-idle-box" role="dialog" aria-modal="true" aria-labelledby="study-idle-title">
<h2 id="study-idle-title">Você ainda está aí?</h2>
<p>Sem atividade há alguns minutos, o tempo de estudo ficou pausado. Seu andamento está salvo.</p>
<button class="btn btn-primary" type="button" data-study-resume>Continuar estudando</button>
</div>
</div>
</main>
<script type="application/json" id="scorm-config"><?= json_script($config) ?></script>
</body>
</html>
