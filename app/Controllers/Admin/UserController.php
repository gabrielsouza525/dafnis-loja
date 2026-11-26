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
use App\Services\Notify;
use App\Services\TwoFactor;

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
            "SELECT u.*, (SELECT COUNT(*) FROM orders o WHERE o.user_id = u.id AND o.status IN ('paid','invoiced')) AS paid_orders,
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

    /** Para quem perdeu o celular e os códigos de recuperação: a equipe desativa e a pessoa ativa de novo. */
    public function disableTwoFactor(int $id): Response
    {
        $user = $this->findOr404(Database::first('SELECT * FROM users WHERE id = :id', ['id' => $id]), 'Usuário não encontrado.');
        if (TwoFactor::enabled($user)) {
            TwoFactor::disable($user, true);
        }
        return $this->success('Verificação em duas etapas desativada. A pessoa recebeu um aviso por e-mail.', '/admin/usuarios/' . $id);
    }

    /**
     * Fatura mensal: a conta passa a ver "Fatura mensal" no checkout, só para o CNPJ informado.
     * O pedido faturado libera o acesso na hora e é cobrado no fechamento do mês (Faturamento).
     */
    public function invoice(int $id): Response
    {
        $user = $this->findOr404(Database::first('SELECT * FROM users WHERE id = :id', ['id' => $id]), 'Usuário não encontrado.');
        $enabled = (string) $this->request->input('invoice_enabled', '') === '1';
        $cnpj = null;
        if ($enabled) {
            $data = $this->validate(['invoice_cnpj' => 'required|cnpj'], ['invoice_cnpj' => 'CNPJ da fatura']);
            $cnpj = preg_replace('/\D/', '', (string) $data['invoice_cnpj']);
        }
        Database::update('users', ['invoice_cnpj' => $cnpj], ['id' => $id]);
        Activity::log('user.invoice', 'user', $id, $cnpj ? 'fatura mensal autorizada para o CNPJ ' . document_display($cnpj) : 'fatura mensal desativada');
        return $this->success($cnpj ? 'Fatura mensal autorizada para ' . $user['name'] . ' no CNPJ ' . document_display($cnpj) . '.' : 'Fatura mensal desativada para esta conta.', '/admin/usuarios/' . $id);
    }

    /**
     * Corrigir os dados cadastrais (nome, e-mail, telefone, CPF). Os cursos são ligados ao e-mail
     * do participante e acompanham a troca; pedidos e certificados guardam os dados da compra e da matrícula.
     */
    public function updateData(int $id): Response
    {
        $user = $this->findOr404(Database::first('SELECT * FROM users WHERE id = :id', ['id' => $id]), 'Usuário não encontrado.');
        $data = $this->validate([
            'name' => 'required|min:3|max:120',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|phone',
            'document' => 'nullable|cpf',
        ], ['name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'document' => 'CPF'], [
            'email.unique' => 'Já existe outra conta com este e-mail.',
        ]);

        $labels = ['name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'document' => 'CPF'];
        $changed = array_keys(array_filter($labels, static fn ($label, $field) => (string) ($data[$field] ?? '') !== (string) ($user[$field] ?? ''), ARRAY_FILTER_USE_BOTH));
        if (!$changed) {
            return $this->success('Nenhum dado foi alterado.', '/admin/usuarios/' . $id);
        }
        $emailChanged = in_array('email', $changed, true);
        $moved = 0;
        Database::transaction(static function () use ($id, $user, $data, $emailChanged, &$moved) {
            Database::update('users', $data, ['id' => $id]);
            if ($emailChanged) {
                $moved = Database::update('enrollments', ['participant_email' => $data['email']], ['participant_email' => $user['email']]);
            }
        });
        $details = implode(', ', array_map(static fn ($field) => $labels[$field], $changed));
        if ($emailChanged) {
            $details .= ' · e-mail ' . $user['email'] . ' → ' . $data['email'];
            Notify::emailChangedByTeam($user, $data['email']);
        }
        Activity::log('user.data', 'user', $id, 'dados alterados pela equipe: ' . $details);
        if ($id === Auth::id()) {
            Auth::reset();
        }
        $message = 'Dados de ' . $data['name'] . ' atualizados.';
        if ($emailChanged) {
            $message .= ' Agora o login é ' . $data['email'] . ($moved ? ' e os cursos foram junto' : '') . '. A pessoa recebeu um aviso nos dois e-mails.';
        }
        return $this->success($message, '/admin/usuarios/' . $id);
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
