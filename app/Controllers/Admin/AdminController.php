<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Database;

abstract class AdminController extends Controller
{
    protected string $layout = 'layouts/admin';

    /**
     * Paginação simples: $sql sem LIMIT; devolve linhas e metadados para o rodapé da tabela.
     */
    protected function paginate(string $sql, array $params, int $perPage = 30): array
    {
        $page = $this->pageNumber();
        $total = (int) Database::value('SELECT COUNT(*) FROM (' . $sql . ') t', $params);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $rows = Database::select($sql . ' LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage), $params);
        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'from' => $total ? ($page - 1) * $perPage + 1 : 0,
            'to' => min($total, $page * $perPage),
        ];
    }

    /** Termo de busca pronto para LIKE, com curingas do usuário escapados. */
    protected function likeTerm(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
