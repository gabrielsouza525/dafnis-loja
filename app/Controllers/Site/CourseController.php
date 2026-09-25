<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Response;
use App\Models\Course;
use App\Services\Seo;
use App\Services\Settings;

final class CourseController extends Controller
{
    public function show(string $slug): Response
    {
        $course = Course::findActiveBySlug($slug);
        if (!$course) {
            $this->notFound('Este treinamento não existe ou não está mais disponível.');
        }

        $crumbs = [['Início', '/'], ['Cursos', '/cursos']];
        if ($course['nr_number'] && !$course['is_simulator']) {
            $crumbs[] = [$course['code_label'], '/nr/' . $course['nr_number']];
        } elseif ($course['category_slug']) {
            $crumbs[] = [$course['category_name'], '/categorias/' . $course['category_slug']];
        }
        $crumbs[] = [$course['title'], '/cursos/' . $slug];

        $summary = $course['summary'] ?: Course::shortSummary($course);

        return $this->view('site/course', [
            'nav' => 'cursos',
            'course' => $course,
            'summary' => $summary,
            'syllabus' => Course::syllabus($course),
            'related' => Course::related($course, 4),
            'practicalNote' => Settings::get('content.practical_note', 'Fale com a nossa equipe para combinar a parte prática.'),
            'crumbs' => $crumbs,
            'title' => $course['meta_title'] ?: $course['display_title'],
            'description' => $course['meta_description'] ?: str_limit($summary, 158),
            'canonical' => '/cursos/' . $slug,
            'jsonLd' => [Seo::course($course), Seo::breadcrumbs($crumbs)],
            'bodyClass' => 'has-bar-page',
        ]);
    }

    /** /curso/{slug} (singular) → /cursos/{slug} */
    public function legacy(string $slug): Response
    {
        return Response::redirect('/cursos/' . $slug, 301);
    }
}
