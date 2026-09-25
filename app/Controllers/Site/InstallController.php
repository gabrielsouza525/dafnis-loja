<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\View;
use App\Services\Migrator;
use Database\Seeders\BaseSeeder;

/**
 * Instalação pela web para hospedagens sem acesso SSH.
 * Só funciona com INSTALL_TOKEN definido no .env e antes de existir um administrador.
 * Depois de concluída, grava storage/installed.lock e fica desativada.
 */
final class InstallController extends Controller
{
    private const LOCK = '/storage/installed.lock';

    public function form(): Response
    {
        $this->guard();
        return Response::html(View::file('site/install', ['done' => false]));
    }

    public function install(): Response
    {
        $this->guard();
        if (!hash_equals((string) env('INSTALL_TOKEN'), (string) $this->request->input('install_token'))) {
            throw ValidationException::with('install_token', 'Token de instalação incorreto.');
        }
        $data = $this->validate([
            'name' => 'required|min:3|max:120',
            'email' => 'required|email',
            'password' => 'required|password',
        ], ['name' => 'nome', 'email' => 'e-mail', 'password' => 'senha']);

        require_once BASE_PATH . '/database/seeders/BaseSeeder.php';
        Migrator::run();
        BaseSeeder::run();

        if ((int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin'") === 0) {
            Database::insert('users', [
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => 'admin',
                'password_hash' => password_hash((string) $this->request->input('password'), PASSWORD_DEFAULT),
                'password_changed_at' => date('Y-m-d H:i:s'),
            ]);
        }
        file_put_contents(BASE_PATH . self::LOCK, date('c'));
        return Response::html(View::file('site/install', ['done' => true]));
    }

    private function guard(): void
    {
        if ((string) env('INSTALL_TOKEN', '') === '' || is_file(BASE_PATH . self::LOCK)) {
            throw new HttpException(404);
        }
        try {
            $hasAdmin = (int) Database::value("SELECT COUNT(*) FROM users WHERE role = 'admin'") > 0;
        } catch (\Throwable) {
            $hasAdmin = false; // tabelas ainda não existem
        }
        if ($hasAdmin) {
            @file_put_contents(BASE_PATH . self::LOCK, date('c'));
            throw new HttpException(404);
        }
    }
}
