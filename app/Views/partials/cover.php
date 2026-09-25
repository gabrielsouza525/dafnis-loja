<?php
/**
 * Capa do curso: foto enviada no admin ou a "placa" da marca (NR em destaque sobre o grid).
 * @var array $course
 * @var string $variant card|lg|thumb|my
 * @var string|null $tag   a (link) ou div
 * @var bool|null $badge
 */
$variant = $variant ?? 'card';
$tag = $tag ?? 'div';
$classes = 'cover tone-' . e($course['tone']) . ($variant === 'lg' ? ' lg' : '') . ($variant === 'thumb' ? ' thumb' : '');
// A capa repete o link do título: fica fora do Tab e dos leitores de tela.
$attrs = $tag === 'a'
    ? ' href="' . e($course['url']) . '" tabindex="-1" aria-hidden="true"'
    : ($variant === 'lg' ? ' role="img" aria-label="' . e('Ilustração do treinamento ' . $course['display_title']) . '"' : ' aria-hidden="true"');
?>
<<?= $tag ?> class="<?= $classes ?>"<?= $attrs ?>>
<?php if (!empty($badge) && $course['badge']): ?><span class="badge badge-<?= e($course['badge']) ?>"><?= e($course['badge_label']) ?></span><?php endif; ?>
<?php if ($course['image_url'] && $variant !== 'thumb'): ?><img class="cover-img" src="<?= e($course['image_url']) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
<span class="plate"><span class="plate-kicker"><?= e($course['kicker']) ?></span><span class="plate-code<?= $course['plate_is_text'] ? ' is-text' : '' ?>"><?= e($course['plate_big']) ?></span></span>
<?php if (!$course['image_url']): ?><svg class="plate-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="<?= ICONS[$course['icon']] ?? ICONS['clipboard'] ?>"></path></svg><?php endif; ?>
<span class="plate-stripe"></span>
</<?= $tag ?>>
