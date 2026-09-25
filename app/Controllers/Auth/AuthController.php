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

final class AuthController extends Controller
{
    public function loginForm(): Response
    {
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
        Auth::attempt($data['email'], (string) $this->request->input('password'), $this->request->bool('remember'), $this->request);
        $to = Auth::safeReturnPath($this->request->input('volta')) ?? Auth::homePath();
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
