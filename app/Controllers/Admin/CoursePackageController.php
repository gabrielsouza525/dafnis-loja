<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Account\StudyController;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\ValidationException;
use App\Models\Course;
use App\Services\Auth;
use App\Services\Scorm\Packages;

/** Conteúdo próprio do curso (pacote SCORM 1.2): envio, versão em uso, exclusão e pré-visualização. */
final class CoursePackageController extends AdminController
{
    public function store(int $id): Response
    {
        $course = $this->findOr404(Course::find($id), 'Curso não encontrado.');
        $file = $this->request->file('package');
        if (!$file) {
            throw ValidationException::with('package', 'Escolha o arquivo .zip exportado em SCORM 1.2.');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw ValidationException::with('package', in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'O arquivo passa do limite de envio pelo painel. Use a importação pelo servidor (php bin/console scorm:import).'
                : 'Falha no envio do arquivo. Tente novamente.');
        }
        if (strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'zip') {
            throw ValidationException::with('package', 'Envie o pacote como .zip, do jeito que a ferramenta exportou.');
        }
        $package = Packages::import($id, $file['tmp_name'], (string) $file['name'], Auth::id(), $this->request->bool('make_current'));
        $message = 'Versão ' . $package['version'] . ' importada: ' . $package['file_count'] . ' arquivos.';
        return $this->success($package['is_current'] ? $message . ' Novos participantes já recebem esta versão.' : $message, '/admin/cursos/' . $course['id'] . '/editar#conteudo');
    }

    /** Pacote copiado para storage/scorm/entrada (pelo cPanel ou FTP), sem o limite de envio do navegador. */
    public function storeFromServer(int $id): Response
    {
        $this->findOr404(Course::find($id), 'Curso não encontrado.');
        $package = Packages::importFromInbox($id, (string) $this->request->input('arquivo', ''), Auth::id(), $this->request->bool('make_current'));
        $message = 'Versão ' . $package['version'] . ' importada da pasta de entrada: ' . $package['file_count'] . ' arquivos.';
        return $this->success($package['is_current'] ? $message . ' Novos participantes já recebem esta versão.' : $message, '/admin/cursos/' . $id . '/editar#conteudo');
    }

    public function activate(int $id, int $package): Response
    {
        $row = $this->package($id, $package);
        Packages::makeCurrent($row);
        return $this->success('Versão ' . $row['version'] . ' em uso para novos participantes.', '/admin/cursos/' . $id . '/editar#conteudo');
    }

    public function destroy(int $id, int $package): Response
    {
        $row = $this->package($id, $package);
        Packages::delete($row);
        return $this->success('Versão ' . $row['version'] . ' excluída.', '/admin/cursos/' . $id . '/editar#conteudo');
    }

    /** O curso como o aluno vê, sem gravar nada. */
    public function preview(int $id, int $package): Response
    {
        $row = $this->package($id, $package);
        $course = Course::find($id);
        return StudyController::playerResponse([
            'title' => $course['title'],
            'code' => $course['code'] ?: ($course['nr_number'] ? 'NR ' . $course['nr_number'] : null),
            'backUrl' => url('/admin/cursos/' . $id . '/editar#conteudo'),
            'config' => [
                'launch' => url('/admin/cursos/' . $id . '/pacotes/' . $row['id'] . '/arquivos/' . $row['launch_path']),
                'preview' => true,
                'cmi' => [
                    'cmi.core.student_id' => 'PREVIA',
                    'cmi.core.student_name' => 'Pré-visualização',
                    'cmi.core.credit' => 'no-credit',
                    'cmi.core.lesson_status' => 'not attempted',
                    'cmi.core.entry' => 'ab-initio',
                    'cmi.core.lesson_mode' => 'browse',
                    'cmi.core.total_time' => '0000:00:00.00',
                    'cmi.student_data.mastery_score' => $row['mastery_score'] !== null ? (string) $row['mastery_score'] : '',
                    'cmi.interactions._count' => '0',
                ],
            ],
        ]);
    }

    public function asset(int $id, int $package, int|string $path): Response
    {
        $row = $this->package($id, $package);
        session_write_close();
        return Packages::fileResponse($row, (string) $path, $this->request->header('Range'));
    }

    private function package(int $courseId, int $packageId): array
    {
        $row = Packages::find($packageId);
        if (!$row || (int) $row['course_id'] !== $courseId) {
            throw new HttpException(404, 'Versão do conteúdo não encontrada.');
        }
        return $row;
    }
}
