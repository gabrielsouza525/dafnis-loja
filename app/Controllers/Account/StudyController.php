<?php
declare(strict_types=1);

namespace App\Controllers\Account;

use App\Controllers\Controller;
use App\Core\App;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\View;
use App\Models\Enrollment;
use App\Services\Auth;
use App\Services\Scorm\Packages;
use App\Services\Scorm\Tracker;

/**
 * Cursos próprios (SCORM 1.2) feitos dentro da loja: a página do curso, os arquivos do pacote,
 * o que o curso grava (LMSCommit) e o aviso de presença que mede o tempo de estudo.
 */
final class StudyController extends Controller
{
    public function show(int $id): Response
    {
        // Link do e-mail aberto com outra conta conectada (por exemplo, a da equipe no mesmo navegador):
        // em vez de um 404 seco, explica e oferece sair e entrar com o e-mail do participante.
        $found = Enrollment::find($id);
        if ($found && $found['status'] !== 'cancelled' && $found['participant_email'] !== mb_strtolower((string) Auth::user()['email'])) {
            return $this->otherAccount($id);
        }
        $enrollment = $this->enrollment($id);
        if (in_array($enrollment['status'], ['awaiting_participant', 'processing'], true)) {
            flash('info', 'O acesso a este curso ainda está sendo liberado. Avisaremos por e-mail.');
            return $this->redirect('/minha-conta/cursos');
        }
        if ($enrollment['expires_at'] && strtotime((string) $enrollment['expires_at']) < time()) {
            flash('warning', 'O prazo de acesso a este curso terminou em ' . date_br($enrollment['expires_at']) . '. Fale com a nossa equipe para renovar.');
            return $this->redirect('/minha-conta/cursos');
        }
        $attempt = Tracker::attemptFor($enrollment);
        $package = $attempt ? Packages::find((int) $attempt['package_id']) : null;
        if (!$package) {
            $external = Enrollment::accessUrl($enrollment);
            flash('info', 'Este curso é feito na plataforma de ensino' . ($external ? '.' : '. O link de acesso chega por e-mail.'));
            return $external ? Response::redirect($external) : $this->redirect('/minha-conta/cursos');
        }
        $session = Tracker::startSession($attempt, $this->request);
        $base = '/minha-conta/cursos/' . $id;

        return $this->player([
            'title' => $enrollment['course_title'],
            'code' => $enrollment['course_code'],
            'backUrl' => url('/minha-conta/cursos'),
            'config' => [
                'launch' => url($base . '/pacote/' . $package['id'] . '/' . $package['launch_path']),
                'commit' => url($base . '/scorm'),
                'heartbeat' => url($base . '/presenca'),
                'login' => url('/login', ['volta' => $base . '/estudar']),
                'session' => $session,
                'preview' => false,
                'cmi' => Tracker::initialData($attempt, $package, $enrollment),
            ],
        ]);
    }

    /** Arquivos do pacote: só para o participante, e só da versão em que ele está. */
    public function asset(int $id, int $package, int|string $path): Response
    {
        $enrollment = $this->enrollment($id);
        $allowed = in_array($enrollment['status'], ['active', 'completed'], true) && (int) Database::value(
            'SELECT COUNT(*) FROM scorm_attempts WHERE enrollment_id = :e AND package_id = :p',
            ['e' => $id, 'p' => $package]
        ) > 0;
        $row = $allowed ? Packages::find($package) : null;
        if (!$row) {
            throw new HttpException(404, 'Arquivo do curso não encontrado.');
        }
        // O navegador busca dezenas de arquivos ao mesmo tempo: libera a sessão para não enfileirá-los.
        session_write_close();
        return Packages::fileResponse($row, (string) $path, $this->request->header('Range'));
    }

    public function commit(int $id): Response
    {
        [$enrollment, $attempt] = $this->tracking($id);
        $session = $this->request->int('session') ?: null;
        $data = $this->request->json() ?? [];
        $attempt = Tracker::commit($attempt, $enrollment, $session, $data);
        return Response::json([
            'ok' => true,
            'status' => $attempt['lesson_status'],
            'label' => Tracker::STATUS[$attempt['lesson_status']] ?? $attempt['lesson_status'],
        ]);
    }

    public function heartbeat(int $id): Response
    {
        [, $attempt] = $this->tracking($id);
        $session = Tracker::session($attempt, $this->request->int('session'));
        if (!$session) {
            throw new HttpException(404, 'Sessão de estudo não encontrada.');
        }
        Tracker::heartbeat($session, $this->request->bool('active'));
        return Response::json(['ok' => true]);
    }

    /** Matrícula do usuário logado como participante (404 para qualquer outra). */
    /** A matrícula é de outro e-mail: página que orienta a trocar de conta (sem revelar de quem é). */
    private function otherAccount(int $id): Response
    {
        $user = Auth::user();
        $html = View::render('account/study-other-account', [
            'title' => 'Treinamento em outra conta',
            'noindex' => true,
            'nav' => 'conta',
            'active' => 'cursos',
            'user' => $user,
            'awaitingSeats' => (int) Database::value(
                "SELECT COUNT(*) FROM enrollments WHERE buyer_user_id = :u AND status = 'awaiting_participant'",
                ['u' => $user['id']]
            ),
            'volta' => Enrollment::studyPath(['id' => $id]),
            'adminUrl' => Auth::isAdmin() ? url('/admin/matriculas/' . $id) : null,
        ], $this->layout);
        return Response::html($html, 404);
    }

    private function enrollment(int $id): array
    {
        $enrollment = Enrollment::find($id);
        $user = Auth::user();
        if (!$enrollment || $enrollment['participant_email'] !== mb_strtolower((string) $user['email']) || $enrollment['status'] === 'cancelled') {
            $this->notFound('Curso não encontrado.');
        }
        return $enrollment;
    }

    /** @return array{0:array,1:array} matrícula e tentativa, para gravar andamento */
    private function tracking(int $id): array
    {
        $enrollment = $this->enrollment($id);
        session_write_close();
        $attempt = in_array($enrollment['status'], ['active', 'completed'], true)
            ? Database::first('SELECT * FROM scorm_attempts WHERE enrollment_id = :e ORDER BY updated_at DESC, id DESC LIMIT 1', ['e' => $id])
            : null;
        if (!$attempt) {
            throw new HttpException(404, 'Curso não encontrado.');
        }
        return [$enrollment, $attempt];
    }

    /** A página do curso, sem o cabeçalho da loja: barra fina e o pacote ocupando o resto da tela. */
    public static function playerResponse(array $data): Response
    {
        return Response::html(View::render('account/study', $data + ['noindex' => true]))
            ->withHeader('Content-Security-Policy', App::csp(['frame-src' => "'self'"]))
            ->withHeader('Cache-Control', 'no-store, private');
    }

    private function player(array $data): Response
    {
        return self::playerResponse($data);
    }
}
