<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Enrollment;
use App\Models\Order;
use App\Services\Activity;
use App\Services\Auth;

final class UserController extends AdminController
{
    public function index(): Response
    {
        $q = trim((string) $this->request->query('q', ''));
        $role = (string) $this->request->query('perfil', '');
        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = '(u.name LIKE :q1 OR u.email LIKE :q2 OR u.document LIKE :q3)';
            $params += ['q1' => $this->likeTerm($q), 'q2' => $this->likeTerm($q), 'q3' => $this->likeTerm(preg_replace('/\D/', '', $q) ?: $q)];
        }
        if (in_array($role, ['student', 'admin'], true)) {
            $where[] = 'u.role = :r';
            $params['r'] = $role;
        }
        $page = $this->paginate(
            "SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status = 'paid') AS paid_orders,
                    (SELECT COUNT(*) FROM enrollments e WHERE e.participant_email = u.email AND e.status IN ('processing','active','completed')) AS courses
               FROM users u WHERE " . implode(' AND ', $where) . ' ORDER BY u.id DESC',
            $params
        );
        return $this->view('admin/users/index', ['title' => 'Usuários', 'section' => 'usuarios', 'page' => $page, 'q' => $q, 'role' => $role]);
    }

    public function show(int $id): Response
    {
        $user = $this->findOr404(Database::first('SELECT * FROM users WHERE id = :id', ['id' => $id]), 'Usuário não encontrado.');
        return $this->view('admin/users/show', [
            'title' => $user['name'],
            'section' => 'usuarios',
            'u' => $user,
            'orders' => Order::forUser($id),
            'courses' => Enrollment::forParticipant($user['email']),
            'isSelf' => $id === Auth::id(),
        ]);
    }

    public function update(int $id): Response
    {
        $user = $this->findOr404(Database::first('SELECT * FROM users WHERE id = :id', ['id' => $id]), 'Usuário não encontrado.');
        if ($id === Auth::id()) {
            throw ValidationException::with('role', 'Você não pode alterar o próprio perfil nem se desativar.');
        }
        $data = $this->validate(['role' => 'required|in:student,admin', 'is_active' => 'bool'], ['role' => 'perfil']);
        if ($user['role'] === 'admin' && ($data['role'] !== 'admin' || !$data['is_active'])
            && (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1") <= 1) {
            throw ValidationException::with('role', 'A loja precisa de pelo menos um administrador ativo.');
        }
        Database::update('users', $data, ['id' => $id]);
        if (!$data['is_active']) {
            Auth::forgetAllDevices($id);
        }
        Activity::log('user.updated', 'user', $id, 'perfil ' . $data['role'] . ($data['is_active'] ? '' : ' · desativado'));
        return $this->success('Usuário atualizado.', '/admin/usuarios/' . $id);
    }
}
