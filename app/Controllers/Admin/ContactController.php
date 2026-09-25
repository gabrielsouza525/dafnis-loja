<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;

final class ContactController extends AdminController
{
    public function index(): Response
    {
        $status = $this->request->query('status') === 'atendidos' ? 'handled' : 'new';
        $page = $this->paginate(
            'SELECT r.*, c.title AS course_title, c.code AS course_code, c.nr_number
               FROM contact_requests r LEFT JOIN courses c ON c.id = r.course_id
              WHERE r.status = :s ORDER BY r.id DESC',
            ['s' => $status]
        );
        return $this->view('admin/contacts', ['title' => 'Contatos', 'section' => 'contatos', 'page' => $page, 'status' => $status]);
    }

    public function toggle(int $id): Response
    {
        $row = $this->findOr404(Database::first('SELECT * FROM contact_requests WHERE id = :id', ['id' => $id]), 'Contato não encontrado.');
        Database::update('contact_requests', ['status' => $row['status'] === 'new' ? 'handled' : 'new'], ['id' => $id]);
        return $this->success($row['status'] === 'new' ? 'Marcado como atendido.' : 'Contato reaberto.', '/admin/contatos' . ($row['status'] === 'new' ? '' : '?status=atendidos'));
    }
}
