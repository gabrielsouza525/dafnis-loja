<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Services\Payments\Payments;

final class DashboardController extends AdminController
{
    public function index(): Response
    {
        $monthStart = date('Y-m-01 00:00:00');
        $kpis = Database::first(
            "SELECT (SELECT COUNT(*) FROM orders WHERE status = 'pending') AS pending,
                    (SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'pending') AS pending_total,
                    (SELECT COUNT(*) FROM orders WHERE status = 'paid' AND paid_at >= :m1) AS paid_month,
                    (SELECT COALESCE(SUM(total), 0) FROM orders WHERE status = 'paid' AND paid_at >= :m2) AS revenue_month,
                    (SELECT COUNT(*) FROM enrollments WHERE status = 'processing') AS to_release,
                    (SELECT COUNT(*) FROM enrollments WHERE status = 'awaiting_participant') AS awaiting,
                    (SELECT COUNT(*) FROM courses WHERE is_active = 1) AS courses,
                    (SELECT COUNT(*) FROM users WHERE role = 'student') AS students",
            ['m1' => $monthStart, 'm2' => $monthStart]
        );

        return $this->view('admin/dashboard', [
            'title' => 'Visão geral',
            'section' => 'painel',
            'kpis' => $kpis,
            'orders' => Database::select('SELECT * FROM orders ORDER BY id DESC LIMIT 8'),
            'toRelease' => Database::select(
                "SELECT e.*, i.course_title, i.course_code, o.number AS order_number FROM enrollments e
                   JOIN order_items i ON i.id = e.order_item_id JOIN orders o ON o.id = e.order_id
                  WHERE e.status = 'processing' ORDER BY e.updated_at LIMIT 8"
            ),
            'contacts' => Database::select("SELECT * FROM contact_requests WHERE status = 'new' ORDER BY id DESC LIMIT 5"),
            'online' => Payments::isOnline(),
        ]);
    }
}
