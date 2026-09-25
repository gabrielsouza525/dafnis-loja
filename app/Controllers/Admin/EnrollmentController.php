<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Enrollment;
use App\Services\Activity;
use App\Services\Enrollments;
use App\Services\Uploads;

final class EnrollmentController extends AdminController
{
    public function index(): Response
    {
        $status = (string) $this->request->query('status', '');
        $q = trim((string) $this->request->query('q', ''));
        $orderNumber = trim((string) $this->request->query('pedido', ''));
        $where = ['1 = 1'];
        $params = [];
        if (isset(Enrollment::STATUS[$status])) {
            $where[] = 'e.status = :st';
            $params['st'] = $status;
        }
        if ($q !== '') {
            $where[] = '(e.participant_name LIKE :q1 OR e.participant_email LIKE :q2 OR i.course_title LIKE :q3)';
            $params += ['q1' => $this->likeTerm($q), 'q2' => $this->likeTerm($q), 'q3' => $this->likeTerm($q)];
        }
        if ($orderNumber !== '') {
            $where[] = 'o.number = :num';
            $params['num'] = $orderNumber;
        }
        $page = $this->paginate(
            'SELECT e.*, i.course_title, i.course_code, o.number AS order_number, o.buyer_name, o.company_name, o.buyer_type,
                    cert.id AS certificate_id
               FROM enrollments e
               JOIN order_items i ON i.id = e.order_item_id
               JOIN orders o ON o.id = e.order_id
               LEFT JOIN certificates cert ON cert.enrollment_id = e.id
              WHERE ' . implode(' AND ', $where) . "
              ORDER BY FIELD(e.status, 'processing', 'awaiting_participant', 'active', 'completed', 'cancelled'), e.updated_at DESC",
            $params
        );
        return $this->view('admin/enrollments', [
            'title' => 'Matrículas e certificados',
            'section' => 'matriculas',
            'page' => $page,
            'status' => $status,
            'q' => $q,
            'orderNumber' => $orderNumber,
            'counts' => array_column(Database::select('SELECT status, COUNT(*) AS n FROM enrollments GROUP BY status'), 'n', 'status'),
        ]);
    }

    public function show(int $id): Response
    {
        $e = $this->findOr404(Enrollment::find($id), 'Matrícula não encontrada.');
        return $this->view('admin/enrollment', [
            'title' => 'Matrícula #' . $id,
            'section' => 'matriculas',
            'e' => $e,
            'order' => Database::first('SELECT * FROM orders WHERE id = :id', ['id' => $e['order_id']]),
            'accessUrl' => Enrollment::accessUrl($e),
            'activity' => Activity::forSubject('enrollment', $id),
        ]);
    }

    public function update(int $id): Response
    {
        $this->findOr404(Enrollment::find($id), 'Matrícula não encontrada.');
        $data = $this->validate([
            'participant_name' => 'nullable|min:3|max:120',
            'participant_email' => 'nullable|email',
            'participant_document' => 'nullable|cpf',
            'status' => 'required|in:' . implode(',', array_keys(Enrollment::STATUS)),
            'progress' => 'nullable|integer',
            'access_url' => 'nullable|url',
        ], ['participant_name' => 'nome', 'participant_email' => 'e-mail', 'participant_document' => 'CPF', 'access_url' => 'link de acesso']);
        Enrollments::adminUpdate($id, $data);
        return $this->success('Matrícula atualizada.', '/admin/matriculas/' . $id);
    }

    public function certificate(int $id): Response
    {
        $this->findOr404(Enrollment::find($id), 'Matrícula não encontrada.');
        $data = Validator::validate($this->request->all(), [
            'issued_at' => 'required|date',
            'external_url' => 'nullable|url',
        ], ['issued_at' => 'data de emissão', 'external_url' => 'link do certificado']);
        $file = $this->request->file('certificate_file');
        $path = $file ? Uploads::certificate($file) : null;
        Enrollments::attachCertificate($id, $path, $data['external_url'], $data['issued_at']);
        return $this->success('Certificado registrado. O participante foi avisado por e-mail.', '/admin/matriculas/' . $id);
    }

    public function downloadCertificate(int $id): Response
    {
        $cert = $this->findOr404(Database::first('SELECT * FROM certificates WHERE enrollment_id = :e', ['e' => $id]), 'Certificado não encontrado.');
        $path = Uploads::privatePath($cert['file_path']);
        if (!$path) {
            $this->notFound('Arquivo do certificado não encontrado.');
        }
        return Response::file($path, 'application/pdf', ['Content-Disposition' => 'inline; filename="certificado-' . $cert['code'] . '.pdf"', 'Cache-Control' => 'no-store, private']);
    }
}
