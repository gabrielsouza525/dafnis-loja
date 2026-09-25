<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Models\Category;
use App\Models\Course;
use App\Services\Auth;
use App\Services\Notify;
use App\Services\RateLimiter;
use App\Services\Settings;

final class PageController extends Controller
{
    public const SUBJECTS = [
        'empresas' => 'Treinamento para empresa',
        'curso' => 'Dúvida sobre um treinamento',
        'conteudo' => 'Conteúdo programático',
        'duvida' => 'Outra dúvida',
    ];

    public function contact(): Response
    {
        $subject = (string) $this->request->query('assunto', 'duvida');
        $course = ($slug = $this->request->query('curso')) ? Course::findActiveBySlug((string) $slug) : null;
        $user = Auth::user();
        return $this->view('site/contact', [
            'title' => 'Fale com a nossa equipe',
            'description' => 'Tire dúvidas, peça o conteúdo programático ou solicite uma proposta de treinamentos para a sua empresa.',
            'canonical' => '/contato',
            'subject' => array_key_exists($subject, self::SUBJECTS) ? $subject : 'duvida',
            'course' => $course,
            'message' => (string) $this->request->query('mensagem', ''),
            'user' => $user,
            'whatsapp' => Settings::get('business.whatsapp'),
            'phone' => Settings::get('business.phone'),
            'email' => Settings::get('business.email'),
        ]);
    }

    public function sendContact(): Response
    {
        // Campo invisível: robôs preenchem, pessoas não.
        if (trim((string) $this->request->input('website', '')) !== '') {
            return $this->redirect('/contato?enviado=1');
        }
        RateLimiter::check('contact', $this->request->ip(), $this->request->ip(), 5, 5, 30);
        $data = $this->validate([
            'subject' => 'required|in:' . implode(',', array_keys(self::SUBJECTS)),
            'name' => 'required|min:3|max:120',
            'email' => 'required|email',
            'phone' => 'nullable|phone',
            'company' => 'nullable|max:160',
            'participants' => 'nullable|integer',
            'course_slug' => 'nullable|max:120',
            'message' => 'nullable|max:3000',
        ], ['name' => 'nome', 'email' => 'e-mail', 'phone' => 'telefone', 'company' => 'empresa', 'participants' => 'número de participantes', 'message' => 'mensagem']);

        $course = $data['course_slug'] ? Course::findActiveBySlug($data['course_slug']) : null;
        $row = [
            'subject' => $data['subject'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'company' => $data['company'],
            'participants' => $data['participants'] !== null ? max(1, min(65000, (int) $data['participants'])) : null,
            'course_id' => $course['id'] ?? null,
            'message' => $data['message'],
            'ip' => $this->request->ip(),
        ];
        $row['id'] = Database::insert('contact_requests', $row);
        RateLimiter::hit('contact', $this->request->ip(), $this->request->ip());
        Notify::contactReceived($row, $course);

        return $this->success('Mensagem enviada! Nossa equipe responde pelo e-mail ou telefone informado.', '/contato?enviado=1');
    }

    public function terms(): Response
    {
        return $this->view('site/terms', ['title' => 'Termos de uso', 'canonical' => '/termos-de-uso']);
    }

    public function privacy(): Response
    {
        return $this->view('site/privacy', ['title' => 'Política de privacidade', 'canonical' => '/politica-de-privacidade']);
    }

    public function sitemap(): Response
    {
        $urls = [['/', '1.0'], ['/cursos', '0.9'], ['/nrs', '0.8'], ['/contato', '0.5'], ['/termos-de-uso', '0.2'], ['/politica-de-privacidade', '0.2']];
        foreach (Category::active() as $cat) {
            if ($cat['course_count'] > 0) {
                $urls[] = ['/categorias/' . $cat['slug'], '0.8'];
            }
        }
        foreach (Course::nrIndex() as $nr) {
            $urls[] = ['/nr/' . $nr['nr'], '0.8'];
        }
        $updated = [];
        foreach (Database::select('SELECT slug, updated_at FROM courses WHERE is_active = 1') as $row) {
            $updated[$row['slug']] = $row['updated_at'];
            $urls[] = ['/cursos/' . $row['slug'], '0.7'];
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$path, $priority]) {
            $slug = str_starts_with($path, '/cursos/') ? substr($path, 8) : null;
            $xml .= '  <url><loc>' . e(absolute_url($path)) . '</loc>'
                . ($slug && isset($updated[$slug]) ? '<lastmod>' . date('Y-m-d', strtotime($updated[$slug])) . '</lastmod>' : '')
                . '<priority>' . $priority . '</priority></url>' . "\n";
        }
        return Response::text($xml . '</urlset>', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /minha-conta',
            'Disallow: /carrinho',
            'Disallow: /checkout',
            'Disallow: /pedido/',
            'Disallow: /login',
            'Disallow: /cadastro',
            'Disallow: /instalar',
            'Disallow: /*?*q=',
            '',
            'Sitemap: ' . absolute_url('/sitemap.xml'),
        ];
        if (env('APP_ENV', 'local') !== 'production' || static_demo()) {
            $lines = ['User-agent: *', 'Disallow: /'];
        }
        return Response::text(implode("\n", $lines) . "\n");
    }
}
