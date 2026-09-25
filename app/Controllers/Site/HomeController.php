<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Response;
use App\Models\Category;
use App\Models\Course;
use App\Services\Seo;
use App\Services\Settings;

final class HomeController extends Controller
{
    public function index(): Response
    {
        $all = Course::allActive();
        $faq = Settings::faq();
        $nrIndex = Course::nrIndex();

        $jsonLd = [Seo::organization(), Seo::website()];
        if ($faq) {
            $jsonLd[] = Seo::faq($faq);
        }

        return $this->view('site/home', [
            'nav' => 'inicio',
            'canonical' => '/',
            'jsonLd' => $jsonLd,
            'total' => count($all),
            'categories' => Category::active(),
            'featured' => Course::featured(8),
            'bestsellers' => Course::bestsellers(4),
            'nrIndex' => $nrIndex,
            'heroCourse' => Course::findActiveBySlug('nr-33-espacos-confinados-trabalhador-e-vigia'),
            'certCourse' => Course::findActiveBySlug('nr-10-seguranca-em-instalacoes-e-servicos-com-eletricidade-basico'),
            'stats' => array_merge([
                ['n' => (string) count($all), 'label' => 'Treinamentos no catálogo'],
                ['n' => (string) count($nrIndex), 'label' => 'Normas Regulamentadoras atendidas'],
            ], Settings::stats()),
            'about' => Settings::get('content.about'),
            'faq' => $faq,
        ]);
    }
}
