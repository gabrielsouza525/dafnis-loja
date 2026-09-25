<?php
declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\Enrollment;
use App\Models\Order;
use App\Services\Activity;
use App\Services\Auth;
use App\Services\Enrollments;
use App\Services\Payments\Payments;
use App\Services\Uploads;

/** Área do aluno: cursos, certificados, pedidos, vagas da equipe e dados pessoais. */
final class AccountController extends Controller
{
    public function dashboard(): Response
    {
        $user = Auth::user();
        $mine = Enrollment::forParticipant($user['email']);
        $seats = Enrollment::seatsBoughtBy((int) $user['id']);
        return $this->account('account/dashboard', 'painel', [
            'title' => 'Minha conta',
            'kpis' => [
                'courses' => count($mine),
                'active' => count(array_filter($mine, static fn ($e) => $e['status'] === 'active')),
                'completed' => count(array_filter($mine, static fn ($e) => $e['status'] === 'completed')),
                'certificates' => count(array_filter($mine, static fn ($e) => $e['certificate_id'])),
            ],
            'current' => array_slice(array_values(array_filter($mine, static fn ($e) => $e['status'] !== 'completed')), 0, 4),
            'orders' => Order::forUser((int) $user['id'], 4),
            'awaiting' => count(array_filter($seats, static fn ($s) => $s['status'] === 'awaiting_participant')),
            'teamSeats' => count(array_filter($seats, static fn ($s) => $s['participant_email'] !== $user['email'])),
        ]);
    }

    public function courses(): Response
    {
        $user = Auth::user();
        return $this->account('account/courses', 'cursos', [
            'title' => 'Meus cursos',
            'enrollments' => Enrollment::forParticipant($user['email']),
        ]);
    }

    public function certificates(): Response
    {
        $user = Auth::user();
        $mine = array_filter(Enrollment::forParticipant($user['email']), static fn ($e) => $e['status'] === 'completed');
        // Quem comprou para a equipe também vê os certificados dos colaboradores.
        $team = array_filter(Enrollment::seatsBoughtBy((int) $user['id']), static fn ($e) => $e['status'] === 'completed' && $e['participant_email'] !== $user['email']);
        return $this->account('account/certificates', 'certificados', [
            'title' => 'Certificados',
            'mine' => array_values($mine),
            'team' => array_values($team),
        ]);
    }

    public function downloadCertificate(int $id): Response
    {
        $user = Auth::user();
        $cert = Database::first(
            'SELECT c.*, e.participant_email, e.buyer_user_id, e.participant_name, i.course_title
               FROM certificates c JOIN enrollments e ON e.id = c.enrollment_id JOIN order_items i ON i.id = e.order_item_id
              WHERE c.id = :id',
            ['id' => $id]
        );
        $allowed = $cert && ($cert['participant_email'] === $user['email'] || (int) $cert['buyer_user_id'] === (int) $user['id']);
        if (!$allowed) {
            $this->notFound('Certificado não encontrado.');
        }
        if ($path = Uploads::privatePath($cert['file_path'])) {
            $name = 'certificado-' . slugify($cert['course_title']) . '-' . slugify((string) $cert['participant_name']) . '.pdf';
            return \App\Core\Response::file($path, 'application/pdf', [
                'Content-Disposition' => 'attachment; filename="' . $name . '"',
                'Cache-Control' => 'no-store, private',
            ]);
        }
        if ($cert['external_url']) {
            return Response::redirect($cert['external_url']);
        }
        $this->notFound('O arquivo deste certificado ainda não foi anexado.');
    }

    public function orders(): Response
    {
        return $this->account('account/orders', 'pedidos', [
            'title' => 'Meus pedidos',
            'orders' => Order::forUser((int) Auth::id()),
        ]);
    }

    public function order(string $number): Response
    {
        $order = Order::findByNumber($number);
        if (!$order || (int) $order['user_id'] !== Auth::id()) {
            $this->notFound('Pedido não encontrado.');
        }
        return $this->account('account/order', 'pedidos', [
            'title' => 'Pedido ' . $order['number'],
            'order' => $order,
            'items' => Order::items((int) $order['id']),
            'seats' => Enrollment::forOrder((int) $order['id']),
            'online' => Payments::isOnline(),
        ]);
    }

    public function assignSeat(int $id): Response
    {
        $seat = Enrollment::find($id);
        if (!$seat || (int) $seat['buyer_user_id'] !== Auth::id()) {
            $this->notFound('Vaga não encontrada.');
        }
        $data = $this->validate([
            'participant_name' => 'required|min:3|max:120',
            'participant_email' => 'required|email',
            'participant_document' => 'required|cpf',
        ], ['participant_name' => 'nome do participante', 'participant_email' => 'e-mail do participante', 'participant_document' => 'CPF do participante']);
        Enrollments::assignParticipant($seat, $data);
        return $this->success('Participante salvo. Vamos liberar o acesso e avisar ' . first_name($data['participant_name']) . ' por e-mail.', '/minha-conta/pedidos/' . $seat['order_number'] . '#vaga-' . $id);
    }

    public function profile(): Response
    {
        return $this->account('account/profile', 'dados', ['title' => 'Meus dados', 'user' => Auth::user()]);
    }

    public function updateProfile(): Response
    {
        $user = Auth::user();
        $data = $this->validate([
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|unique:users,email,' . $user['id'],
            'phone' => 'nullable|phone',
            'document' => 'nullable|cpf',
        ], ['name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'document' => 'CPF']);

        Database::transaction(static function () use ($user, $data) {
            Database::update('users', $data, ['id' => $user['id']]);
            if ($data['email'] !== $user['email']) {
                // Os cursos são ligados ao e-mail do participante: acompanham a troca.
                Database::update('enrollments', ['participant_email' => $data['email']], ['participant_email' => $user['email']]);
            }
        });
        Activity::log('profile.updated', 'user', (int) $user['id']);
        Auth::reset();
        return $this->success('Dados atualizados.', '/minha-conta/dados');
    }

    public function updatePassword(): Response
    {
        $user = Database::first('SELECT * FROM users WHERE id = :id', ['id' => Auth::id()]);
        if (!password_verify((string) $this->request->input('current_password'), $user['password_hash'])) {
            throw ValidationException::with('current_password', 'A senha atual não confere.');
        }
        $this->validate(['password' => 'required|password|confirmed'], ['password' => 'nova senha']);
        Database::update('users', [
            'password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT),
            'password_changed_at' => date('Y-m-d H:i:s'),
        ], ['id' => $user['id']]);
        Auth::forgetAllDevices((int) $user['id']);
        Session::regenerate();
        Activity::log('password.changed', 'user', (int) $user['id']);
        return $this->success('Senha alterada.', '/minha-conta/dados');
    }

    private function account(string $view, string $active, array $data): Response
    {
        $user = Auth::user();
        $data['user'] ??= $user;
        $data['active'] = $active;
        $data['nav'] = 'conta';
        $data['noindex'] = true;
        $data['awaitingSeats'] = (int) Database::value(
            "SELECT COUNT(*) FROM enrollments WHERE buyer_user_id = :u AND status = 'awaiting_participant'",
            ['u' => $user['id']]
        );
        return $this->view($view, $data);
    }
}
