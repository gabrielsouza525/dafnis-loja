<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\Category;
use App\Models\Course;
use App\Services\Activity;
use App\Services\Uploads;

final class CourseController extends AdminController
{
    public function index(): Response
    {
        $q = trim((string) $this->request->query('q', ''));
        $status = (string) $this->request->query('status', 'todos');
        $category = $this->request->int('categoria');
        $where = ['1 = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = "(c.title LIKE :q OR c.code LIKE :q2 OR c.slug LIKE :q3 OR CONCAT('NR ', c.nr_number) = :qnr)";
            $params += ['q' => $this->likeTerm($q), 'q2' => $this->likeTerm($q), 'q3' => $this->likeTerm($q), 'qnr' => strtoupper(preg_replace('/^nr\s*/i', 'NR ', $q))];
        }
        if ($status === 'ativos') {
            $where[] = 'c.is_active = 1';
        } elseif ($status === 'inativos') {
            $where[] = 'c.is_active = 0';
        } elseif ($status === 'sem-preco') {
            $where[] = 'c.price IS NULL';
        } elseif ($status === 'destaques') {
            $where[] = '(c.featured_order IS NOT NULL OR c.is_bestseller = 1)';
        }
        if ($category) {
            $where[] = 'c.category_id = :cat';
            $params['cat'] = $category;
        }
        $page = $this->paginate(
            "SELECT c.*, k.name AS category_name,
                    (SELECT COUNT(*) FROM order_items i JOIN orders o ON o.id = i.order_id WHERE i.course_id = c.id AND o.status = 'paid') AS sold
               FROM courses c LEFT JOIN categories k ON k.id = c.category_id
              WHERE " . implode(' AND ', $where) . '
              ORDER BY c.nr_number IS NULL, c.nr_number, c.title',
            $params,
            40
        );
        return $this->view('admin/courses/index', [
            'title' => 'Cursos',
            'section' => 'cursos',
            'page' => $page,
            'q' => $q,
            'status' => $status,
            'category' => $category,
            'categories' => Category::options(),
        ]);
    }

    public function create(): Response
    {
        return $this->form(null);
    }

    public function edit(int $id): Response
    {
        return $this->form($this->findOr404(Course::find($id), 'Curso não encontrado.'));
    }

    public function store(): Response
    {
        $data = $this->validated(null);
        $id = Database::insert('courses', $data);
        $this->handleImage($id, $data['slug'], null);
        Activity::log('course.created', 'course', $id, $data['title']);
        return $this->success('Curso criado.', '/admin/cursos/' . $id . '/editar');
    }

    public function update(int $id): Response
    {
        $course = $this->findOr404(Course::find($id), 'Curso não encontrado.');
        $data = $this->validated($course);
        Database::update('courses', $data, ['id' => $id]);
        $this->handleImage($id, $data['slug'], $course);
        Activity::log('course.updated', 'course', $id, $data['title'] . ' · ' . ($data['price'] !== null ? money($data['price']) : 'sob consulta'));
        return $this->success('Curso salvo.', '/admin/cursos/' . $id . '/editar');
    }

    public function toggle(int $id): Response
    {
        $course = $this->findOr404(Course::find($id), 'Curso não encontrado.');
        $field = $this->request->input('campo') === 'mais-vendido' ? 'is_bestseller' : 'is_active';
        $value = $course[$field] ? 0 : 1;
        Database::update('courses', [$field => $value], ['id' => $id]);
        $label = $field === 'is_active' ? ($value ? 'Curso ativado.' : 'Curso desativado: não aparece mais na loja.') : ($value ? 'Marcado como "Mais vendido".' : 'Selo "Mais vendido" removido.');
        Activity::log('course.' . $field, 'course', $id, (string) $value);
        return $this->success($label, $this->request->input('volta') === 'lista' ? '/admin/cursos' : '/admin/cursos/' . $id . '/editar');
    }

    public function destroy(int $id): Response
    {
        $course = $this->findOr404(Course::find($id), 'Curso não encontrado.');
        $sold = (int) Database::value('SELECT COUNT(*) FROM order_items WHERE course_id = :id', ['id' => $id]);
        if ($sold > 0) {
            // Pedidos antigos guardam uma cópia dos dados, mas o histórico fica mais claro com o curso inativo.
            Database::update('courses', ['is_active' => 0], ['id' => $id]);
            return $this->success('Este curso já tem pedidos, então foi desativado em vez de excluído.', '/admin/cursos');
        }
        Database::delete('courses', ['id' => $id]);
        Uploads::deletePublic($course['image_path']);
        Activity::log('course.deleted', 'course', $id, $course['title']);
        return $this->success('Curso excluído.', '/admin/cursos');
    }

    private function form(?array $course): Response
    {
        return $this->view('admin/courses/form', [
            'title' => $course ? 'Editar curso' : 'Novo curso',
            'section' => 'cursos',
            'course' => $course,
            'categories' => Category::options(),
            'syllabus' => $course ? Course::syllabus($course) : [],
            'scripts' => [],
        ]);
    }

    private function validated(?array $course): array
    {
        $input = $this->request->all();
        $input['slug'] = slugify((string) (($input['slug'] ?? '') !== '' ? $input['slug'] : ($input['title'] ?? '')));
        $data = Validator::validate($input, [
            'title' => 'required|min:3|max:190',
            'slug' => 'required|max:120',
            'category_id' => 'nullable|exists:categories,id',
            'nr_number' => 'nullable|integer',
            'code' => 'nullable|max:20',
            'short_title' => 'nullable|max:60',
            'summary' => 'nullable|max:400',
            'description' => 'nullable|max:8000',
            'audience' => 'nullable|max:4000',
            'objectives' => 'nullable|max:4000',
            'requirements' => 'nullable|max:255',
            'hours' => 'required|integer',
            'hours_note' => 'nullable|max:120',
            'modality' => 'required|in:online,semipresencial,presencial',
            'training_type' => 'required|in:inicial,periodico',
            'practical_required' => 'bool',
            'practical_hours' => 'nullable|max:160',
            'practical_note' => 'nullable|max:255',
            'price' => 'nullable|decimal|minval:0',
            'promo_price' => 'nullable|decimal|minval:0',
            'certificate' => 'bool',
            'access_days' => 'nullable|integer',
            'access_url' => 'nullable|url',
            'icon' => 'required|in:' . implode(',', array_keys(self::ICON_CHOICES)),
            'keywords' => 'nullable|max:255',
            'is_active' => 'bool',
            'is_bestseller' => 'bool',
            'is_new' => 'bool',
            'featured_order' => 'nullable|integer',
            'meta_title' => 'nullable|max:160',
            'meta_description' => 'nullable|max:255',
        ], [
            'title' => 'título', 'slug' => 'endereço (slug)', 'category_id' => 'categoria', 'nr_number' => 'número da NR',
            'hours' => 'carga horária', 'price' => 'preço', 'promo_price' => 'preço promocional', 'access_url' => 'link de acesso',
            'access_days' => 'prazo de acesso', 'featured_order' => 'ordem no destaque',
        ]);

        $taken = (int) Database::value('SELECT COUNT(*) FROM courses WHERE slug = :s' . ($course ? ' AND id <> :id' : ''), array_filter(['s' => $data['slug'], 'id' => $course['id'] ?? null]));
        if ($taken) {
            throw ValidationException::with('slug', 'Já existe um curso com este endereço. Ajuste o slug.');
        }
        if ($data['nr_number'] !== null && ($data['nr_number'] < 1 || $data['nr_number'] > 99)) {
            throw ValidationException::with('nr_number', 'Informe um número de NR entre 1 e 99.');
        }
        if ($data['hours'] < 0 || $data['hours'] > 2000) {
            throw ValidationException::with('hours', 'Carga horária inválida.');
        }
        if ($data['promo_price'] !== null && ($data['price'] === null || $data['promo_price'] >= $data['price'])) {
            throw ValidationException::with('promo_price', 'O preço promocional precisa ser menor que o preço normal.');
        }
        $data['syllabus'] = $this->syllabusFromInput();
        return $data;
    }

    private function syllabusFromInput(): ?string
    {
        $titles = (array) $this->request->input('module_title', []);
        $hours = (array) $this->request->input('module_hours', []);
        $topics = (array) $this->request->input('module_topics', []);
        $modules = [];
        foreach ($titles as $i => $title) {
            $title = trim((string) $title);
            if ($title === '') {
                continue;
            }
            $modules[] = [
                'title' => mb_substr($title, 0, 160),
                'hours' => mb_substr(trim((string) ($hours[$i] ?? '')), 0, 20),
                'topics' => mb_substr(trim((string) ($topics[$i] ?? '')), 0, 2000),
            ];
        }
        return $modules ? json_encode($modules, JSON_UNESCAPED_UNICODE) : null;
    }

    private function handleImage(int $id, string $slug, ?array $course): void
    {
        if ($this->request->bool('remove_image') && $course && $course['image_path']) {
            Uploads::deletePublic($course['image_path']);
            Database::update('courses', ['image_path' => null], ['id' => $id]);
        }
        if ($file = $this->request->file('image')) {
            $path = Uploads::courseImage($file, $slug);
            if ($course && $course['image_path']) {
                Uploads::deletePublic($course['image_path']);
            }
            Database::update('courses', ['image_path' => $path], ['id' => $id]);
        }
    }

    public const ICON_CHOICES = [
        'clipboard' => 'Prancheta', 'clipcheck' => 'Checklist', 'hardhat' => 'Capacete', 'bolt' => 'Eletricidade', 'box' => 'Espaço confinado',
        'forklift' => 'Empilhadeira', 'crane' => 'Guindaste / obra', 'cog' => 'Máquinas', 'gauge' => 'Pressão', 'drop' => 'Inflamáveis',
        'flame' => 'Incêndio', 'aid' => 'Primeiros socorros', 'pulse' => 'Saúde', 'hospital' => 'Serviços de saúde', 'chair' => 'Ergonomia',
        'sign' => 'Sinalização', 'spark' => 'Trabalho a quente', 'leaf' => 'Rural / ambiente', 'users' => 'Pessoas / CIPA', 'car' => 'Direção',
        'lock' => 'Bloqueio / dados', 'game' => 'Jogo / simulador', 'briefcase' => 'Corporativo',
    ];
}
