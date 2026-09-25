<?php
declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Database;
use App\Services\Orders;

/**
 * Dados FICTÍCIOS só para desenvolvimento e testes (bloqueado em produção).
 * E-mails @example.com e @dafnis.test, senha "dafnis123".
 */
final class DemoSeeder
{
    public const PASSWORD = 'dafnis123';

    public static function run(?callable $out = null): void
    {
        $out ??= static fn () => null;
        $hash = password_hash(self::PASSWORD, PASSWORD_DEFAULT);

        self::user('Equipe Dafnis', 'admin@dafnis.test', 'admin', $hash, '18900000001');
        $ana = self::user('Ana Souza', 'ana@example.com', 'student', $hash, '18900000002', '52998224725');
        $rh = self::user('Carlos Lima', 'rh@example.com', 'student', $hash, '18900000003');
        $out('Usuários de teste: admin@dafnis.test, ana@example.com, rh@example.com (senha ' . self::PASSWORD . ').');

        $nr10 = self::course('nr-10-seguranca-em-instalacoes-e-servicos-com-eletricidade-basico');
        $nr33 = self::course('nr-33-espacos-confinados-trabalhador-e-vigia');
        $ps = self::course('primeiros-socorros-lei-lucas-lei-n-13-722');
        $nr6 = self::course('nr-6-epi-e-epc-equipamentos-de-protecao-individual-e-coletiva');

        // Ana (pessoa física): NR 10 pago e em andamento, NR 6 concluído, Primeiros Socorros aguardando pagamento.
        $o1 = self::order($ana, 'pf', [[$nr10, 1]], 'pix', '52998224725');
        Orders::markPaid($o1, 'admin');
        Database::query("UPDATE enrollments SET status = 'active', progress = 45, released_at = NOW(), access_url = 'https://example.com/plataforma' WHERE order_id = :o", ['o' => $o1]);

        $o2 = self::order($ana, 'pf', [[$nr6, 1]], 'card', '52998224725');
        Orders::markPaid($o2, 'admin');
        $e2 = (int) Database::value('SELECT id FROM enrollments WHERE order_id = :o', ['o' => $o2]);
        Database::query("UPDATE enrollments SET status = 'completed', progress = 100, released_at = NOW(), completed_at = NOW() WHERE id = :id", ['id' => $e2]);
        $pdf = self::demoPdf();
        Database::insert('certificates', ['enrollment_id' => $e2, 'code' => 'DF-DEMO0001', 'file_path' => $pdf, 'issued_at' => date('Y-m-d')]);

        self::order($ana, 'pf', [[$ps, 1]], 'boleto', '52998224725');

        // Empresa: 3 vagas de NR 33 pagas (1 participante indicado) e um pedido aguardando pagamento.
        $o4 = self::order($rh, 'pj', [[$nr33, 3], [$ps, 2]], 'pix', '11222333000181', 'Metalúrgica Exemplo Ltda');
        Orders::markPaid($o4, 'admin');
        $seat = (int) Database::value('SELECT id FROM enrollments WHERE order_id = :o ORDER BY id LIMIT 1', ['o' => $o4]);
        Database::update('enrollments', ['participant_name' => 'Bruno Alves', 'participant_email' => 'bruno@example.com', 'participant_document' => '11144477735', 'status' => 'processing'], ['id' => $seat]);
        self::order($rh, 'pj', [[$nr10, 2]], 'boleto', '11222333000181', 'Metalúrgica Exemplo Ltda');
        $out('Pedidos de teste criados em todas as situações.');

        Database::query("INSERT IGNORE INTO coupons (code, description, type, value, is_active) VALUES ('BEMVINDO10', 'Cupom de teste (desenvolvimento)', 'percent', 10, 1)");
        Database::insert('contact_requests', [
            'subject' => 'empresas', 'name' => 'Paula Mendes', 'email' => 'paula@example.com', 'phone' => '18900000004',
            'company' => 'Construtora Exemplo', 'participants' => 25, 'message' => 'Precisamos de NR 18 e NR 35 para a equipe da obra.',
        ]);
        $out('Cupom BEMVINDO10 e um contato de exemplo criados.');
    }

    private static function user(string $name, string $email, string $role, string $hash, string $phone, ?string $doc = null): array
    {
        $id = Database::value('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        if (!$id) {
            $id = Database::insert('users', ['name' => $name, 'email' => $email, 'role' => $role, 'password_hash' => $hash, 'phone' => $phone, 'document' => $doc]);
        }
        return Database::first('SELECT * FROM users WHERE id = :id', ['id' => $id]);
    }

    private static function course(string $slug): array
    {
        $c = Database::first('SELECT * FROM courses WHERE slug = :s', ['s' => $slug]);
        if (!$c) {
            throw new \RuntimeException("Curso $slug não encontrado — rode o seed antes.");
        }
        return $c;
    }

    private static function order(array $user, string $type, array $lines, string $method, string $doc, ?string $company = null): int
    {
        $subtotal = 0.0;
        foreach ($lines as [$c, $qty]) {
            $subtotal += (float) $c['price'] * $qty;
        }
        $id = Database::insert('orders', [
            'user_id' => $user['id'], 'status' => 'pending', 'buyer_type' => $type, 'buyer_name' => $user['name'],
            'buyer_document' => $doc, 'buyer_email' => $user['email'], 'buyer_phone' => $user['phone'], 'company_name' => $company,
            'subtotal' => $subtotal, 'discount' => 0, 'total' => $subtotal, 'payment_method' => $method, 'gateway' => 'manual',
            'terms_accepted_at' => date('Y-m-d H:i:s'),
        ]);
        Database::update('orders', ['number' => Orders::numberFor($id)], ['id' => $id]);
        foreach ($lines as [$c, $qty]) {
            Database::insert('order_items', [
                'order_id' => $id, 'course_id' => $c['id'], 'course_title' => $c['title'],
                'course_code' => $c['nr_number'] ? ($c['code'] ?: 'NR ' . $c['nr_number']) : null, 'course_hours' => $c['hours'],
                'list_price' => $c['price'], 'unit_price' => $c['price'], 'quantity' => $qty, 'line_total' => (float) $c['price'] * $qty,
            ]);
        }
        return $id;
    }

    /** PDF mínimo válido, só para testar o download do certificado. */
    private static function demoPdf(): string
    {
        $dir = BASE_PATH . '/storage/uploads/certificados';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $text = 'Certificado de teste - Dafnis Treinamentos';
        $stream = "BT /F1 18 Tf 72 720 Td ($text) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($stream) . " >>\nstream\n$stream\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n$obj\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF\n";
        file_put_contents("$dir/demo-certificado.pdf", $pdf);
        return 'certificados/demo-certificado.pdf';
    }
}
