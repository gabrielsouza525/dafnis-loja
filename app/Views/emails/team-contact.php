<?php /** @var array $contact @var array|null $course @var string $url */ ?>
<p style="margin:0 0 12px"><strong>Novo contato pelo site</strong> — <?= e(App\Controllers\Site\PageController::SUBJECTS[$contact['subject']] ?? $contact['subject']) ?></p>
<p style="margin:0 0 12px"><?= e($contact['name']) ?><?= $contact['company'] ? ' · ' . e($contact['company']) : '' ?><br><?= e($contact['email']) ?><?= $contact['phone'] ? ' · ' . e(phone_display($contact['phone'])) : '' ?></p>
<?php if ($course): ?><p style="margin:0 0 12px">Treinamento: <?= e($course['display_title']) ?></p><?php endif; ?>
<?php if ($contact['participants']): ?><p style="margin:0 0 12px">Participantes: <?= (int) $contact['participants'] ?></p><?php endif; ?>
<?php if ($contact['message']): ?><p style="margin:0 0 12px;white-space:pre-line;background:#F5F7FA;padding:12px;border-radius:8px"><?= e($contact['message']) ?></p><?php endif; ?>
<?= App\Core\View::file('emails/button', ['url' => $url, 'label' => 'Ver contatos', 'color' => '#0B2545']) ?>
