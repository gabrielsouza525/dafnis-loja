<?php
declare(strict_types=1);

namespace App\Services;

/** Dados estruturados (schema.org), montados só com o que está cadastrado. */
final class Seo
{
    public static function organization(): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'EducationalOrganization',
            '@id' => absolute_url('/') . '#organizacao',
            'name' => Settings::businessName(),
            'url' => absolute_url('/'),
            'description' => 'Treinamentos de Normas Regulamentadoras, segurança do trabalho e cursos complementares para profissionais e empresas.',
            'logo' => absolute_url('/favicon.svg'),
        ];
        if ($phone = Settings::get('business.phone') ?: Settings::get('business.whatsapp')) {
            $data['telephone'] = '+55' . preg_replace('/\D/', '', (string) $phone);
        }
        if ($email = Settings::get('business.email')) {
            $data['email'] = $email;
        }
        if ($city = Settings::get('business.city')) {
            $data['address'] = array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => Settings::get('business.address'),
                'addressLocality' => $city,
                'addressRegion' => Settings::get('business.state'),
                'addressCountry' => 'BR',
            ]);
        }
        $sameAs = array_values(array_filter([
            ($ig = Settings::get('business.instagram')) ? 'https://www.instagram.com/' . ltrim((string) $ig, '@') . '/' : null,
            Settings::get('business.linkedin'),
            Settings::get('business.youtube'),
        ]));
        if ($sameAs) {
            $data['sameAs'] = $sameAs;
        }
        return $data;
    }

    public static function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => Settings::businessName() . ' — Treinamentos',
            'url' => absolute_url('/'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => absolute_url('/cursos') . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    public static function course(array $c): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Course',
            'name' => $c['display_title'],
            'description' => $c['summary'] ?: \App\Models\Course::factualSummary($c),
            'url' => absolute_url('/cursos/' . $c['slug']),
            'provider' => ['@type' => 'Organization', 'name' => Settings::businessName(), 'sameAs' => absolute_url('/')],
            'inLanguage' => 'pt-BR',
            'hasCourseInstance' => [
                '@type' => 'CourseInstance',
                'courseMode' => $c['modality'] === 'online' ? 'online' : ($c['modality'] === 'presencial' ? 'onsite' : 'blended'),
                'courseWorkload' => 'PT' . max(1, $c['hours']) . 'H',
            ],
        ];
        if ($c['has_price']) {
            $data['offers'] = [
                '@type' => 'Offer',
                'category' => 'Paid',
                'price' => number_format((float) $c['final_price'], 2, '.', ''),
                'priceCurrency' => 'BRL',
                'availability' => 'https://schema.org/InStock',
                'url' => absolute_url('/cursos/' . $c['slug']),
            ];
        }
        if ($c['image_url']) {
            $data['image'] = absolute_url(parse_url($c['image_url'], PHP_URL_PATH) ?: '/');
        }
        return $data;
    }

    public static function breadcrumbs(array $items): array
    {
        $list = [];
        foreach (array_values($items) as $i => [$name, $path]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => absolute_url($path)];
        }
        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
    }

    public static function itemList(array $courses): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(static fn ($c, $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'url' => absolute_url('/cursos/' . $c['slug']),
                'name' => $c['display_title'],
            ], array_values($courses), array_keys(array_values($courses))),
        ];
    }

    public static function faq(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn ($q) => [
                '@type' => 'Question',
                'name' => $q['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $q['a']],
            ], $items),
        ];
    }
}
