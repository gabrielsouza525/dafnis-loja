<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\Category;
use App\Services\Activity;

final class CategoryController extends AdminController
{
    public function index(): Response
    {
        return $this->view('admin/categories', [
            'title' => 'Categorias',
            'section' => 'categorias',
            'categories' => Category::all(),
        ]);
    }

    public function store(): Response
    {
        $data = $this->validated(null);
        $id = Database::insert('categories', $data);
        Activity::log('category.created', 'category', $id, $data['name']);
        return $this->success('Categoria criada.', '/admin/categorias');
    }

    public function update(int $id): Response
    {
        $this->findOr404(Database::first('SELECT * FROM categories WHERE id = :id', ['id' => $id]));
        $data = $this->validated($id);
        Database::update('categories', $data, ['id' => $id]);
        Activity::log('category.updated', 'category', $id, $data['name']);
        return $this->success('Categoria salva.', '/admin/categorias');
    }

    public function destroy(int $id): Response
    {
        $cat = $this->findOr404(Database::first('SELECT * FROM categories WHERE id = :id', ['id' => $id]));
        if ((int) Database::value('SELECT COUNT(*) FROM courses WHERE category_id = :id', ['id' => $id]) > 0) {
            throw ValidationException::with('category', 'Mova os cursos desta categoria antes de excluí-la (ou apenas desative).');
        }
        Database::delete('categories', ['id' => $id]);
        Activity::log('category.deleted', 'category', $id, $cat['name']);
        return $this->success('Categoria excluída.', '/admin/categorias');
    }

    private function validated(?int $id): array
    {
        $input = $this->request->all();
        $input['slug'] = slugify((string) (($input['slug'] ?? '') !== '' ? $input['slug'] : ($input['name'] ?? '')));
        $data = Validator::validate($input, [
            'name' => 'required|min:2|max:80',
            'slug' => 'required|max:80',
            'description' => 'nullable|max:255',
            'icon' => 'required|in:' . implode(',', array_keys(CourseController::ICON_CHOICES)),
            'tone' => 'required|in:a,b,c,d',
            'sort_order' => 'nullable|integer',
            'is_active' => 'bool',
        ], ['name' => 'nome', 'description' => 'descrição', 'sort_order' => 'ordem']);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $taken = (int) Database::value('SELECT COUNT(*) FROM categories WHERE slug = :s' . ($id ? ' AND id <> :id' : ''), array_filter(['s' => $data['slug'], 'id' => $id]));
        if ($taken) {
            throw ValidationException::with('slug', 'Já existe uma categoria com este endereço.');
        }
        return $data;
    }
}
