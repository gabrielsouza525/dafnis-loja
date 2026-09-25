<?php
/** @var array $f @var array $result */
?>
<?php foreach ($result['groups'] as $g): if (!$g['options']) { continue; } ?>
<fieldset class="f-group">
<legend class="f-title"><?= e($g['title']) ?></legend>
<div class="f-opts<?= $g['cols'] ? ' cols' : '' ?>">
<?php foreach ($g['options'] as $o): $id = 'f-' . $g['key'] . '-' . preg_replace('/[^a-z0-9]+/i', '-', $o['value']); ?>
<label class="f-opt<?= $o['dim'] ? ' dim' : '' ?>" for="<?= e($id) ?>"><input type="checkbox" id="<?= e($id) ?>" name="<?= e($g['key']) ?>[]" value="<?= e($o['value']) ?>"<?= checked($o['checked']) ?>><span><?= e($o['label']) ?></span><span class="f-count" aria-label="<?= e(pluralize($o['count'], 'curso', 'cursos')) ?>"><?= (int) $o['count'] ?></span></label>
<?php endforeach; ?>
</div>
</fieldset>
<?php endforeach; ?>
