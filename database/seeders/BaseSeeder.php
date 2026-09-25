<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Models\Course;

/**
 * Dados reais e seguros para produção: categorias, catálogo da planilha e configurações.
 * Pode rodar várias vezes: só cria o que falta e nunca sobrescreve o que foi editado no painel.
 */
final class BaseSeeder
{
    public const CATEGORIES = [
        'nr' => ['normas-regulamentadoras', 'Normas Regulamentadoras', 'Treinamentos organizados por NR para as demandas da sua operação.', 'clipboard', 'a'],
        'seg' => ['seguranca-do-trabalho', 'Segurança do Trabalho', 'Prevenção, análise de riscos e boas práticas no dia a dia.', 'hardhat', 'b'],
        'ps' => ['primeiros-socorros', 'Primeiros Socorros', 'Preparação para agir em situações de emergência.', 'aid', 'c'],
        'brig' => ['brigada-de-incendio', 'Brigada de Incêndio', 'Prevenção e ações iniciais diante de princípios de incêndio.', 'flame', 'b'],
        'maq' => ['operacao-de-maquinas', 'Operação de Máquinas', 'Segurança na operação de máquinas, equipamentos e empilhadeiras.', 'cog', 'd'],
        'saude' => ['saude-ocupacional', 'Saúde Ocupacional', 'Ergonomia e cuidados com a saúde no ambiente de trabalho.', 'pulse', 'c'],
        'sim' => ['simuladores-e-jogos', 'Simuladores e Jogos', 'Simuladores e jogos para praticar a tomada de decisão com segurança.', 'game', 'd'],
        'corp' => ['treinamentos-corporativos', 'Treinamentos Corporativos', 'Direção defensiva, LGPD e outros temas para equipes.', 'briefcase', 'd'],
    ];

    public const FAQ = [
        ['q' => 'Como funciona a compra?', 'a' => 'Escolha o treinamento, defina quantos participantes vão fazer o curso e finalize pelo carrinho. Depois da confirmação do pagamento, você indica os participantes (quando a compra é para a equipe) e nós liberamos o acesso.'],
        ['q' => 'Como acesso meu treinamento?', 'a' => 'Os treinamentos são realizados na nossa plataforma de ensino online. Quando o acesso é liberado, o participante recebe o link por e-mail e também encontra o curso em Minha conta › Meus cursos.'],
        ['q' => 'Como funciona a certificação?', 'a' => 'Ao concluir o treinamento e cumprir os critérios de aprovação do curso, o certificado de conclusão fica disponível para download em Minha conta › Certificados.'],
        ['q' => 'Posso comprar para minha empresa?', 'a' => 'Sim. Escolha a quantidade de participantes em cada treinamento e finalize como empresa, com CNPJ. Depois do pagamento, você informa nome, e-mail e CPF de cada participante. Para equipes grandes, fale com a nossa equipe.'],
        ['q' => 'Quais formas de pagamento são aceitas?', 'a' => 'Pix, cartão de crédito ou boleto bancário, pelo ambiente seguro do Mercado Pago. Os dados do cartão não passam pela nossa loja.'],
        ['q' => 'Os treinamentos têm parte prática?', 'a' => 'Alguns, por exigência da norma, têm carga horária prática presencial. Eles aparecem como "Semipresencial" e a página do curso informa a carga prática. Fale com a nossa equipe para combinar a prática.'],
        ['q' => 'Como encontro uma NR específica?', 'a' => 'Digite o número da NR na busca (por exemplo, "NR 10") ou abra o menu NRs no topo da página para ver a lista completa.'],
    ];

    public static function run(?callable $out = null): void
    {
        $out ??= static fn () => null;

        $catIds = [];
        $order = 1;
        foreach (self::CATEGORIES as $key => [$slug, $name, $description, $icon, $tone]) {
            $id = Database::value('SELECT id FROM categories WHERE slug = :s', ['s' => $slug]);
            if (!$id) {
                $id = Database::insert('categories', [
                    'slug' => $slug, 'name' => $name, 'description' => $description,
                    'icon' => $icon, 'tone' => $tone, 'sort_order' => $order,
                ]);
            }
            $catIds[$key] = (int) $id;
            $order++;
        }
        $out(count($catIds) . ' categorias prontas.');

        $file = BASE_PATH . '/database/data/cursos.json';
        $courses = json_decode((string) file_get_contents($file), true) ?: [];
        $created = 0;
        foreach ($courses as $c) {
            if (Database::value('SELECT id FROM courses WHERE slug = :s', ['s' => $c['slug']])) {
                continue;
            }
            Database::insert('courses', [
                'slug' => $c['slug'],
                'category_id' => $catIds[$c['category']] ?? null,
                'nr_number' => $c['nr'],
                'code' => $c['code'] && $c['code'] !== 'NR ' . $c['nr'] ? $c['code'] : null,
                'title' => $c['title'],
                'short_title' => $c['short_title'],
                'audience' => $c['audience'],
                'hours' => (int) $c['hours'],
                'hours_note' => $c['hours_note'],
                'modality' => $c['modality'],
                'training_type' => $c['training_type'],
                'practical_required' => $c['practical_required'] ? 1 : 0,
                'practical_hours' => $c['practical_hours'],
                'practical_note' => $c['practical_note'],
                'price' => $c['price'],
                'icon' => $c['icon'],
                'keywords' => $c['keywords'],
                'featured_order' => $c['featured'],
                'source_ref' => 'Planilha EAD 2025, linha ' . $c['source_row'],
            ]);
            $created++;
        }
        Course::flushCache();
        $out($created . ' curso(s) importado(s) da planilha (' . count($courses) . ' no arquivo).');

        $defaults = [
            'business.name' => 'Dafnis Soluções em EPI',
            'business.city' => 'Araçatuba',
            'business.state' => 'SP',
            'content.faq' => json_encode(self::FAQ, JSON_UNESCAPED_UNICODE),
        ];
        foreach ($defaults as $key => $value) {
            Database::query('INSERT IGNORE INTO settings (`key`, `value`) VALUES (:k, :v)', ['k' => $key, 'v' => $value]);
        }
        $out('Configurações padrão prontas.');
    }
}
