<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Services\Activity;
use App\Services\Auth;
use App\Services\Notify;
use App\Services\RateLimiter;

/**
 * Recuperação de senha por link de uso único (o banco guarda só o hash do token).
 * A resposta é a mesma exista ou não a conta, para não revelar e-mails cadastrados.
 */
final class PasswordController extends Controller
{
    private const MINUTES = 60;

    public function forgotForm(): Response
    {
        return $this->view('auth/forgot', ['title' => 'Recuperar senha', 'noindex' => true, 'sent' => $this->request->query('enviado') === '1']);
    }

    public function sendLink(): Response
    {
        $data = $this->validate(['email' => 'required|email'], ['email' => 'e-mail']);
        RateLimiter::check('reset', $data['email'], $this->request->ip(), 3, 10, 30);
        RateLimiter::hit('reset', $data['email'], $this->request->ip());

        $user = Database::first('SELECT id, name, email FROM users WHERE email = :e AND is_active = 1', ['e' => $data['email']]);
        if ($user) {
            $token = random_token(32);
            Database::query('DELETE FROM password_resets WHERE user_id = :u', ['u' => $user['id']]);
            Database::insert('password_resets', [
                'user_id' => $user['id'],
                'token_hash' => hash('sha256', $token),
                'expires_at' => date('Y-m-d H:i:s', time() + self::MINUTES * 60),
            ]);
            Notify::passwordReset($user, absolute_url('/redefinir-senha/' . $token), self::MINUTES);
        }
        return $this->redirect('/esqueci-senha?enviado=1');
    }

    public function resetForm(string $token): Response
    {
        return $this->view('auth/reset', [
            'title' => 'Criar nova senha',
            'noindex' => true,
            'token' => $token,
            'valid' => $this->findReset($token) !== null,
        ]);
    }

    public function reset(): Response
    {
        $token = (string) $this->request->input('token', '');
        $row = $this->findReset($token);
        if (!$row) {
            throw ValidationException::with('password', 'Este link expirou ou já foi usado. Peça um novo.');
        }
        $this->validate(['password' => 'required|password|confirmed'], ['password' => 'senha']);

        Database::transaction(function () use ($row) {
            Database::update('users', [
                'password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT),
                'password_changed_at' => date('Y-m-d H:i:s'),
            ], ['id' => $row['user_id']]);
            Database::update('password_resets', ['used_at' => date('Y-m-d H:i:s')], ['id' => $row['id']]);
        });
        Auth::forgetAllDevices((int) $row['user_id']);
        Activity::log('password.reset', 'user', (int) $row['user_id']);
        Auth::login((int) $row['user_id'], false, $this->request);
        flash('success', 'Senha alterada. Você já está conectado.');
        return $this->redirect(Auth::homePath());
    }

    private function findReset(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $token)) {
            return null;
        }
        return Database::first(
            'SELECT r.* FROM password_resets r JOIN users u ON u.id = r.user_id
              WHERE r.token_hash = :h AND r.used_at IS NULL AND r.expires_at > NOW() AND u.is_active = 1',
            ['h' => hash('sha256', $token)]
        );
    }
}
