<?php
declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\Auth;
use App\Services\Cart;
use App\Services\Notify;
use App\Services\RateLimiter;
use App\Services\TwoFactor;

final class AuthController extends Controller
{
    public function loginForm(): Response
    {
        TwoFactor::forgetPendingLogin(); // "Voltar para o login" na segunda etapa recomeça
        return $this->view('auth/login', [
            'title' => 'Entrar',
            'noindex' => true,
            'volta' => Auth::safeReturnPath($this->request->query('volta')),
            'fromCheckout' => Auth::safeReturnPath($this->request->query('volta')) === '/checkout',
        ]);
    }

    public function login(): Response
    {
        $data = $this->validate(['email' => 'required|email', 'password' => 'required'], ['email' => 'e-mail', 'password' => 'senha']);
        $user = Auth::verifyCredentials($data['email'], (string) $this->request->input('password'), $this->request);
        $remember = $this->request->bool('remember');
        $volta = Auth::safeReturnPath($this->request->input('volta'));
        if (TwoFactor::enabled($user)) {
            TwoFactor::beginLogin($user, $remember, $volta);
            return $this->redirect('/login/verificacao');
        }
        Auth::login((int) $user['id'], $remember, $this->request);
        return $this->afterLogin($volta);
    }

    /** Segunda etapa: o código do aplicativo autenticador (ou um de recuperação). */
    public function twoFactorForm(): Response
    {
        $pending = TwoFactor::pendingLogin();
        if (!$pending) {
            flash('error', 'Por segurança, entre de novo com o seu e-mail e a senha.');
            return $this->redirect('/login');
        }
        $email = (string) Database::value('SELECT email FROM users WHERE id = :id', ['id' => $pending['user_id']]);
        return $this->view('auth/two-factor', [
            'title' => 'Verificação em duas etapas',
            'noindex' => true,
            'email' => mask_email($email),
        ]);
    }

    public function twoFactor(): Response
    {
        $pending = TwoFactor::pendingLogin();
        if (!$pending) {
            flash('error', 'Por segurança, entre de novo com o seu e-mail e a senha.');
            return $this->redirect('/login');
        }
        $key = 'user:' . $pending['user_id'];
        RateLimiter::check('2fa', $key, $this->request->ip(), 5, 20, 15);
        $row = Database::first('SELECT * FROM users WHERE id = :id AND is_active = 1', ['id' => $pending['user_id']]);
        $recovery = trim((string) $this->request->input('recovery_code', ''));
        $ok = $row && TwoFactor::enabled($row) && ($recovery !== ''
            ? TwoFactor::useRecoveryCode($row, $recovery)
            : TwoFactor::verifyApp($row, (string) $this->request->input('code', '')));
        if (!$ok) {
            RateLimiter::hit('2fa', $key, $this->request->ip());
            throw $recovery !== ''
                ? ValidationException::with('recovery_code', 'Código de recuperação inválido ou já usado.')
                : ValidationException::with('code', 'Código incorreto. Digite o código que aparece agora no aplicativo.');
        }
        RateLimiter::clear('2fa', $key);
        TwoFactor::forgetPendingLogin();
        Auth::login((int) $row['id'], (bool) $pending['remember'], $this->request);
        if ($recovery !== '') {
            $left = TwoFactor::recoveryLeft(Database::first('SELECT two_factor_recovery FROM users WHERE id = :id', ['id' => $row['id']]) ?? []);
            flash('success', 'Você entrou com um código de recuperação. ' . ($left > 0 ? 'Restam ' . pluralize($left, 'código', 'códigos') . '; se perdeu o celular, gere novos aqui.' : 'Não restam códigos: gere novos aqui.'));
            return $this->redirect('/minha-conta/dados#duas-etapas');
        }
        return $this->afterLogin($pending['volta'] ?? null);
    }

    private function afterLogin(?string $volta): Response
    {
        $to = $volta ?? Auth::homePath();
        if (Auth::isAdmin() && $to === '/minha-conta') {
            $to = '/admin';
        }
        flash('success', 'Olá, ' . first_name(Auth::user()['name']) . '!');
        return $this->redirect($to);
    }

    /** Endereço antigo (/entrar) — mantém links salvos funcionando. */
    public function legacyLogin(): Response
    {
        return Response::redirect('/login' . (($v = $this->request->query('volta')) ? '?volta=' . rawurlencode((string) $v) : ''), 301);
    }

    public function registerForm(): Response
    {
        return $this->view('auth/register', [
            'title' => 'Criar conta',
            'noindex' => true,
            'volta' => Auth::safeReturnPath($this->request->query('volta')),
            'fromCheckout' => Auth::safeReturnPath($this->request->query('volta')) === '/checkout',
        ]);
    }

    public function register(): Response
    {
        RateLimiter::check('register', $this->request->ip(), $this->request->ip(), 8, 8, 60);
        $data = $this->validate([
            'name' => 'required|min:3|max:120',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'password' => 'required|password|confirmed',
            'accept_terms' => 'required',
        ], ['name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'password' => 'senha'], [
            'accept_terms.required' => 'Para criar a conta, aceite os termos de uso e a política de privacidade.',
        ]);
        if (!str_contains(trim($data['name']), ' ')) {
            throw ValidationException::with('name', 'Informe nome e sobrenome.');
        }
        if ((int) Database::value('SELECT COUNT(*) FROM users WHERE email = :e', ['e' => $data['email']]) > 0) {
            RateLimiter::hit('register', $this->request->ip(), $this->request->ip());
            throw ValidationException::with('email', 'Já existe uma conta com este e-mail. Entre ou recupere a senha.');
        }

        $id = Database::insert('users', [
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'role' => 'student',
            'password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT),
            'password_changed_at' => date('Y-m-d H:i:s'),
        ]);
        RateLimiter::hit('register', $this->request->ip(), $this->request->ip(), true);
        Auth::login($id, false, $this->request);
        Notify::welcome(['name' => $data['name'], 'email' => $data['email']]);

        $to = Auth::safeReturnPath($this->request->input('volta')) ?? (Cart::count() > 0 ? '/checkout' : '/minha-conta');
        flash('success', 'Conta criada! Bem-vindo(a), ' . first_name($data['name']) . '.');
        return $this->redirect($to);
    }

    public function logout(): Response
    {
        Auth::logout($this->request);
        Session::flash('toast', ['type' => 'info', 'message' => 'Você saiu da sua conta.']);
        return $this->redirect('/');
    }
}
