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
        $awaiting = array_filter(Enrollment::forOrder((int) $order['id']), static fn ($e) => $e['status'] === 'awaiting_participant');
        Mailer::send($order['buyer_email'], 'Pagamento confirmado — pedido ' . $order['number'], 'order-paid', [
            'order' => $order,
            'items' => $items,
            'awaiting' => count($awaiting),
            'url' => absolute_url('/minha-conta/pedidos/' . $order['number']),
        ]);
        if ($team = self::teamAddress()) {
            Mailer::send($team, 'Pedido ' . $order['number'] . ' pago — liberar acessos', 'team-order', [
                'order' => $order,
                'items' => $items,
                'headline' => 'Pagamento confirmado: cadastre os participantes na plataforma de ensino',
                'url' => absolute_url('/admin/matriculas?status=processing'),
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
            'accountUrl' => absolute_url('/minha-conta/cursos'),
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
