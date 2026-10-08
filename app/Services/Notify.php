<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Order;
use App\Services\Payments\Payments;

/** E-mails transacionais da loja. Falha de envio nunca interrompe o fluxo (ver Mailer). */
final class Notify
{
    /** Endereço da equipe para avisos internos (MAIL_ADMIN_ADDRESS ou e-mail de contato da loja). */
    public static function teamAddress(): ?string
    {
        $address = env('MAIL_ADMIN_ADDRESS') ?: Settings::get('business.email');
        return $address ? (string) $address : null;
    }

    public static function welcome(array $user): void
    {
        Mailer::send($user['email'], 'Sua conta na ' . Settings::businessName() . ' foi criada', 'welcome', [
            'name' => $user['name'],
            'url' => absolute_url('/minha-conta'),
        ]);
    }

    public static function passwordReset(array $user, string $url, int $minutes): void
    {
        Mailer::send($user['email'], 'Criar nova senha', 'reset-password', ['name' => $user['name'], 'url' => $url, 'minutes' => $minutes]);
    }

    public static function twoFactorChanged(array $user, bool $enabled, bool $byTeam = false): void
    {
        Mailer::send($user['email'], $enabled ? 'Verificação em duas etapas ativada' : 'Verificação em duas etapas desativada', 'two-factor', [
            'name' => $user['name'],
            'enabled' => $enabled,
            'byTeam' => $byTeam,
            'url' => absolute_url('/minha-conta/dados') . '#duas-etapas',
        ]);
    }

    public static function orderCreated(array $order): void
    {
        $items = Order::items((int) $order['id']);
        Mailer::send($order['buyer_email'], 'Pedido ' . $order['number'] . ' recebido', 'order-created', [
            'order' => $order,
            'items' => $items,
            'online' => Payments::isOnline(),
            'url' => absolute_url('/pedido/' . $order['number']),
        ]);
        if ($team = self::teamAddress()) {
            Mailer::send($team, 'Novo pedido ' . $order['number'] . ' — ' . money($order['total']), 'team-order', [
                'order' => $order,
                'items' => $items,
                'headline' => 'Novo pedido aguardando pagamento',
                'url' => absolute_url('/admin/pedidos/' . $order['id']),
            ]);
        }
    }

    public static function orderPaid(array $order): void
    {
        $items = Order::items((int) $order['id']);
        $seats = Enrollment::forOrder((int) $order['id']);
        $awaiting = array_filter($seats, static fn ($e) => $e['status'] === 'awaiting_participant');
        $manual = array_filter($seats, static fn ($e) => $e['status'] === 'processing');
        // Vaga do próprio comprador já liberada (curso na loja): o e-mail leva direto ao curso.
        $ready = array_values(array_filter($seats, static fn ($e) => $e['status'] === 'active' && $e['has_content'] && mb_strtolower((string) $e['participant_email']) === mb_strtolower($order['buyer_email'])));
        Mailer::send($order['buyer_email'], 'Pagamento confirmado — pedido ' . $order['number'], 'order-paid', [
            'order' => $order,
            'items' => $items,
            'awaiting' => count($awaiting),
            'ready' => $ready ? absolute_url(Enrollment::studyPath($ready[0])) : null,
            'readyCount' => count($ready),
            'url' => absolute_url('/minha-conta/pedidos/' . $order['number']),
        ]);
        if ($team = self::teamAddress()) {
            Mailer::send($team, 'Pedido ' . $order['number'] . ' pago' . ($manual ? ' — liberar acessos' : ''), 'team-order', [
                'order' => $order,
                'items' => $items,
                'headline' => $manual
                    ? 'Pagamento confirmado: cadastre os participantes na plataforma de ensino'
                    : ($awaiting ? 'Pagamento confirmado: aguardando o comprador indicar os participantes' : 'Pagamento confirmado: acesso liberado automaticamente (curso na loja)'),
                'url' => absolute_url($manual ? '/admin/matriculas?status=processing' : '/admin/pedidos/' . $order['id']),
            ]);
        }
    }

    public static function accessReleased(?array $enrollment): void
    {
        if (!$enrollment || !$enrollment['participant_email']) {
            return;
        }
        Mailer::send($enrollment['participant_email'], 'Seu acesso ao treinamento foi liberado', 'access-released', [
            'enrollment' => $enrollment,
            'accessUrl' => Enrollment::accessUrl($enrollment),
            'internal' => (bool) $enrollment['has_content'],
            'accountUrl' => absolute_url('/minha-conta/cursos'),
        ]);
    }

    /** Parte on-line de um curso SCORM concluída: a equipe emite o certificado ou agenda a prática. */
    public static function onlinePartDone(?array $enrollment, ?array $summary, bool $practical): void
    {
        $team = self::teamAddress();
        if (!$enrollment || !$team) {
            return;
        }
        $who = (string) ($enrollment['participant_name'] ?: $enrollment['participant_email']);
        Mailer::send($team, ($practical ? 'Agendar prática — ' : 'Emitir certificado — ') . $who, 'team-online-done', [
            'enrollment' => $enrollment,
            'summary' => $summary,
            'practical' => $practical,
            'url' => absolute_url('/admin/matriculas/' . $enrollment['id']),
        ]);
    }

    public static function certificateIssued(?array $enrollment): void
    {
        if (!$enrollment || !$enrollment['participant_email']) {
            return;
        }
        Mailer::send($enrollment['participant_email'], 'Seu certificado está disponível', 'certificate', [
            'enrollment' => $enrollment,
            'url' => absolute_url('/minha-conta/certificados'),
        ]);
    }

    public static function contactReceived(array $contact, ?array $course): void
    {
        if ($team = self::teamAddress()) {
            Mailer::send($team, 'Novo contato pelo site — ' . $contact['name'], 'team-contact', [
                'contact' => $contact,
                'course' => $course,
                'url' => absolute_url('/admin/contatos'),
            ]);
        }
    }
}
