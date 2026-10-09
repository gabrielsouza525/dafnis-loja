<?php
declare(strict_types=1);

/**
 * Certificados gerados pela loja: o PDF no modelo da Dafnis, a geração pela equipe (com a prática
 * presencial), a emissão automática ao concluir o curso na loja, o CPF pedido ao participante, o
 * download, a validação pública e o cadastro de quem assina.
 * Requer os dados de demonstração (db:fresh --demo) e o servidor rodando; acessa o banco do .env.
 *
 *   php tests/certificates.php http://localhost:8000
 */

require __DIR__ . '/lib.php';
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

use App\Core\Database;
use App\Services\Certificates;
use App\Services\Pdf\Document;
use App\Services\Settings;

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
$failures = 0;
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures++;
    }
    echo ($ok ? '  ok   ' : '  FAIL ') . $label . ($detail !== '' && !$ok ? "  → $detail" : '') . PHP_EOL;
}

/** Arquivos de e-mail já gravados (MAIL_DRIVER=log), para achar só os novos depois de uma ação. */
function mail_files(): array
{
    return glob(BASE_PATH . '/storage/mail/*.html') ?: [];
}

/** E-mails gravados depois do retrato $before, com o assunto começando por $subject. */
function mails(array $before, string $subject, ?string $to = null): array
{
    $found = [];
    foreach (array_diff(mail_files(), $before) as $file) {
        $head = (string) file_get_contents($file, false, null, 0, 600);
        if (str_contains($head, 'Assunto: ' . $subject) && ($to === null || str_contains($head, 'Para: ' . $to))) {
            $found[] = $file;
        }
    }
    return $found;
}

function png_rgba(int $w, int $h): string
{
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));
    imageline($img, 2, $h - 4, $w - 3, 3, imagecolorallocatealpha($img, 20, 30, 120, 0));
    ob_start();
    imagepng($img);
    return (string) ob_get_clean();
}

/** Matrícula extra da Ana no mesmo pedido, para simular outra conclusão do curso. */
function clone_enrollment(int $from, ?string $document): int
{
    $e = Database::first('SELECT * FROM enrollments WHERE id = :id', ['id' => $from]);
    return Database::insert('enrollments', [
        'order_id' => $e['order_id'], 'order_item_id' => $e['order_item_id'], 'course_id' => $e['course_id'], 'buyer_user_id' => $e['buyer_user_id'],
        'participant_name' => 'Ana Souza', 'participant_email' => 'ana@example.com', 'participant_document' => $document,
        'status' => 'active', 'released_at' => date('Y-m-d H:i:s'),
    ]);
}

function config_of(string $html): ?array
{
    return preg_match('#<script type="application/json" id="scorm-config">(.*?)</script>#s', $html, $m) ? json_decode($m[1], true) : null;
}

$slug = 'nr-10-seguranca-em-instalacoes-e-servicos-com-eletricidade-basico';
$courseId = (int) Database::value('SELECT id FROM courses WHERE slug = :s', ['s' => $slug]);
$enrollmentId = (int) Database::value("SELECT id FROM enrollments WHERE participant_email = 'ana@example.com' AND course_id = :c ORDER BY id LIMIT 1", ['c' => $courseId]);
if (!$courseId || !$enrollmentId) {
    fwrite(STDERR, "Rode php bin/console db:fresh --demo antes (falta a matrícula de NR 10 da Ana).\n");
    exit(1);
}
if (!Database::value('SELECT id FROM course_packages WHERE course_id = :c AND is_current = 1', ['c' => $courseId])) {
    $zip = sys_get_temp_dir() . '/dafnis-cert-' . bin2hex(random_bytes(4)) . '.zip';
    $phar = new PharData($zip, 0, null, Phar::ZIP);
    $phar->buildFromDirectory(__DIR__ . '/fixtures/scorm-demo');
    unset($phar);
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/console') . ' scorm:import --course=' . $courseId . ' --file=' . escapeshellarg($zip) . ' 2>&1');
}

echo "Dafnis — certificados em $base\n\nPDF\n";
$doc = new Document(300, 200, 'Teste de acentuação');
$doc->font('Times-Bold', 12);
$doc->text(10, 20, 'Ação – teste (parênteses)');
$doc->image(png_rgba(40, 20), 10, 40, 80, 40);
$pdf = $doc->output();
check('documento começa e termina como PDF', str_starts_with($pdf, '%PDF-1.4') && str_ends_with(rtrim($pdf), '%%EOF'));
check('tabela de referências no lugar indicado', preg_match('/startxref\n(\d+)\n/', $pdf, $m) === 1 && substr($pdf, (int) $m[1], 4) === 'xref');
check('PNG com transparência entra com máscara', str_contains($pdf, '/SMask'));
check('largura do texto pelas métricas da fonte (Helvetica: "ação")', abs((new Document(10, 10))->font('Helvetica', 10)->textWidth('ação') - 21.68) < 0.001);
check('quebra de linha respeita a largura', count((new Document(10, 10))->font('Times-Roman', 10)->wrap(str_repeat('palavra ', 30), 100)) > 5);
check('período no mesmo mês', Certificates::periodText('2026-09-07', '2026-09-11') === 'de 07 a 11/09/2026');
check('período num dia só', Certificates::periodText('2026-09-11', '2026-09-11') === 'em 11/09/2026');
check('período entre meses e entre anos', Certificates::periodText('2026-08-28', '2026-09-03') === 'de 28/08 a 03/09/2026' && Certificates::periodText('2025-12-28', '2026-01-03') === 'de 28/12/2025 a 03/01/2026');

// Modelo: CNPJ da empresa, quem assina e o conteúdo programático do curso.
Settings::set('business.cnpj', '11222333000181');
if (!env('MAIL_ADMIN_ADDRESS') && !Settings::get('business.email')) {
    Settings::set('business.email', 'equipe@example.com');
}
Settings::set('certificate.auto', '1');
Settings::set('certificate.city', 'Araçatuba/SP');
Settings::set('certificate.signers', json_encode([
    ['id' => 'a1b2c3d4', 'name' => 'Instrutora Exemplo', 'role' => 'Téc. em Segurança do Trabalho / Instrutora', 'registry' => 'MTE/SP 0000000', 'default' => true, 'signature' => null],
    ['id' => 'e5f6a7b8', 'name' => 'Engenheiro Exemplo', 'role' => 'Engenheiro Eletricista / Resp. Técnico', 'registry' => 'CREA/SP 0000000000', 'default' => false, 'signature' => null],
], JSON_UNESCAPED_UNICODE));
Database::update('courses', [
    'cert_name' => 'NR 10 – Segurança em Instalações e Serviços em Eletricidade',
    'cert_syllabus' => "1. Introdução à segurança com eletricidade.\n2. Riscos em instalações e serviços com eletricidade:\na) o choque elétrico, mecanismos e efeitos;\nb) arcos elétricos; queimaduras e quedas.",
    'cert_signers' => json_encode(['a1b2c3d4', 'e5f6a7b8']),
    'practical_required' => 1,
], ['id' => $courseId]);
Database::query('DELETE FROM certificates WHERE enrollment_id = :e', ['e' => $enrollmentId]);
Database::update('enrollments', ['status' => 'active', 'participant_document' => '52998224725', 'practical_done_at' => null, 'practical_location' => null], ['id' => $enrollmentId]);

$course = Database::first('SELECT * FROM courses WHERE id = :id', ['id' => $courseId]);
check('curso escolhe quem assina, na ordem marcada', array_column(Certificates::signersFor($course), 'id') === ['a1b2c3d4', 'e5f6a7b8']);
check('sem escolha no curso, assinam os marcados como padrão', array_column(Certificates::signersFor(['cert_signers' => null]), 'id') === ['a1b2c3d4']);
check('falta de CPF impede a geração', in_array('o CPF do participante', Certificates::missing(['participant_name' => 'Ana Souza', 'participant_document' => null], $course), true));
$sample = Certificates::sample($course);
check('PDF de exemplo com frente e verso', str_starts_with($sample, '%PDF') && substr_count($sample, '/Type /Page ') === 2);

echo "\nEquipe: prática presencial e geração\n";
$admin = new Client($base);
check('login da equipe', $admin->login('admin@dafnis.test', 'dafnis123', team_2fa_required() ? DEMO_ADMIN_TOTP : null));
$r = $admin->request('GET', "/admin/matriculas/$enrollmentId");
check('matrícula com prática oferece registrar a prática e gerar', str_contains($r['body'], 'Registrar a prática e gerar o certificado') && str_contains($r['body'], 'Local da parte presencial'));
$r = $admin->request('POST', "/admin/matriculas/$enrollmentId/certificado/gerar", ['_scope' => 'gerar', 'start' => '2026-09-07', 'end' => '2026-09-11', 'practical_location' => '', 'issued_at' => '']);
check('sem o local da prática não gera', !Database::value('SELECT id FROM certificates WHERE enrollment_id = :e', ['e' => $enrollmentId]));
$admin->request('GET', "/admin/matriculas/$enrollmentId");
$t = mail_files();
$r = $admin->request('POST', "/admin/matriculas/$enrollmentId/certificado/gerar", ['_scope' => 'gerar', 'start' => '2026-09-07', 'end' => '2026-09-11', 'practical_location' => 'Araçatuba/SP', 'issued_at' => '']);
$cert = Database::first('SELECT * FROM certificates WHERE enrollment_id = :e', ['e' => $enrollmentId]);
$data = json_decode((string) ($cert['data'] ?? ''), true) ?: [];
$e = Database::first('SELECT * FROM enrollments WHERE id = :id', ['id' => $enrollmentId]);
check('certificado gerado com código de validação', $r['status'] === 302 && $cert && preg_match('/^DF-[A-Z2-9]{8}$/', $cert['code']) === 1, 'status ' . $r['status']);
check('matrícula concluída, com a data e o local da prática', $e['status'] === 'completed' && $e['practical_done_at'] === '2026-09-11' && $e['practical_location'] === 'Araçatuba/SP');
check('texto com CPF, curso, período e carga horária', str_contains($data['statement'] ?? '', 'Titular do CPF: 529.982.247-25, concluiu o Treinamento de Formação em NR 10 – SEGURANÇA EM INSTALAÇÕES')
    && str_contains($data['statement'], 'realizado de 07 a 11/09/2026, com carga horária de 40 horas') && str_contains($data['statement'], 'CNPJ: 11.222.333/0001-81'));
check('data do certificado: término da prática, por extenso', ($data['issued_at'] ?? '') === '2026-09-11' && ($data['city_date'] ?? '') === 'Araçatuba/SP, 11 de Setembro de 2026');
check('local de realização com a parte on-line e a prática', str_contains($data['location'] ?? '', 'parte prática presencial em Araçatuba/SP, em 11/09/2026'));
check('verso com quem assina e o conteúdo programático', count($data['signers'] ?? []) === 2 && str_contains($data['syllabus'] ?? '', 'a) o choque elétrico'));
check('participante recebe o aviso por e-mail', count(mails($t, 'Seu certificado está disponível', 'ana@example.com')) === 1);
$r = $admin->request('GET', "/admin/matriculas/$enrollmentId/certificado");
check('equipe abre o PDF', $r['status'] === 200 && str_starts_with($r['body'], '%PDF') && str_contains((string) Client::header($r, 'Content-Type'), 'application/pdf'));
$t = mail_files();
$admin->request('GET', "/admin/matriculas/$enrollmentId");
$admin->request('POST', "/admin/matriculas/$enrollmentId/certificado/gerar", ['_scope' => 'gerar', 'start' => '2026-09-08', 'end' => '2026-09-12', 'practical_location' => 'Araçatuba/SP', 'issued_at' => '2026-09-15']);
$again = Database::first('SELECT * FROM certificates WHERE enrollment_id = :e', ['e' => $enrollmentId]);
clearstatcache();
check('gerar de novo mantém o código e atualiza os dados', $again['code'] === $cert['code'] && $again['issued_at'] === '2026-09-15' && str_contains((string) $again['data'], 'de 08 a 12/09/2026'));
check('gerar de novo não manda outro e-mail', mails($t, 'Seu certificado está disponível', 'ana@example.com') === []);
check('PDF antigo apagado ao gerar de novo', !is_file(BASE_PATH . '/storage/uploads/' . $cert['file_path']) && is_file(BASE_PATH . '/storage/uploads/' . $again['file_path']));

echo "\nParticipante\n";
$ana = new Client($base);
check('login da Ana', $ana->login('ana@example.com', 'dafnis123'));
$r = $ana->request('GET', '/minha-conta/certificados');
check('certificado aparece para baixar', str_contains($r['body'], $again['code']) && str_contains($r['body'], "/minha-conta/certificados/{$again['id']}/baixar"));
$r = $ana->request('GET', "/minha-conta/certificados/{$again['id']}/baixar");
check('download do PDF', $r['status'] === 200 && str_starts_with($r['body'], '%PDF') && str_contains((string) Client::header($r, 'Content-Disposition'), 'attachment'));
@unlink(BASE_PATH . '/storage/uploads/' . $again['file_path']);
$r = $ana->request('GET', "/minha-conta/certificados/{$again['id']}/baixar");
check('arquivo apagado do servidor: o PDF é refeito com os dados guardados', $r['status'] === 200 && str_starts_with($r['body'], '%PDF'));
$rh = new Client($base);
$rh->login('rh@example.com', 'dafnis123');
check('outra conta não baixa o certificado da Ana', $rh->request('GET', "/minha-conta/certificados/{$again['id']}/baixar")['status'] === 404);

echo "\nValidação pública\n";
$guest = new Client($base);
$r = $guest->request('GET', '/certificados/' . $again['code']);
check('código válido mostra o certificado', $r['status'] === 200 && str_contains($r['body'], 'Certificado válido') && str_contains($r['body'], 'ANA SOUZA'));
check('CPF aparece mascarado', str_contains($r['body'], '***.982.247-**') && !str_contains($r['body'], '529.982.247-25'));
check('página de validação fora do Google', str_contains($r['body'], 'noindex'));
$r = $guest->request('GET', '/certificados?codigo=' . strtolower($again['code']));
check('busca pelo código (minúsculas) leva à página do certificado', $r['status'] === 302 && str_contains($r['location'], '/certificados/' . $again['code']));
$r = $guest->request('GET', '/certificados/DF-ZZZZZZZZ');
check('código inexistente avisa que não encontrou', $r['status'] === 200 && str_contains($r['body'], 'Nenhum certificado encontrado'));
check('o QR code do PDF aponta para a validação', str_ends_with((string) (json_decode((string) $again['data'], true)['verify_url'] ?? ''), '/certificados/' . $again['code']));

echo "\nEmissão automática (curso na loja sem prática)\n";
Database::update('courses', ['practical_required' => 0], ['id' => $courseId]);
$auto = clone_enrollment($enrollmentId, '52998224725');
$t = mail_files();
$r = $ana->request('GET', "/minha-conta/cursos/$auto/estudar");
$cfg = config_of($r['body']);
$ana->json("/minha-conta/cursos/$auto/scorm", ['session' => (int) ($cfg['session'] ?? 0), 'status' => 'passed', 'score' => ['raw' => '85']]);
$autoCert = Database::first('SELECT * FROM certificates WHERE enrollment_id = :e', ['e' => $auto]);
check('aprovação gera o certificado na hora', $autoCert !== null && is_file(BASE_PATH . '/storage/uploads/' . $autoCert['file_path']));
check('matrícula fica "Concluído"', Database::value('SELECT status FROM enrollments WHERE id = :id', ['id' => $auto]) === 'completed');
check('período começa no primeiro acesso e termina na aprovação', str_contains((string) $autoCert['data'], 'realizado em ' . date('d/m/Y')));
check('local: on-line, na plataforma da empresa', str_contains((string) $autoCert['data'], '"location":"on-line (EAD), na plataforma de ensino da'));
check('participante recebe o aviso e a equipe sabe que já saiu', count(mails($t, 'Seu certificado está disponível', 'ana@example.com')) === 1 && count(mails($t, 'Certificado emitido — ')) === 1);

$noDoc = clone_enrollment($enrollmentId, null);
$t = mail_files();
$r = $ana->request('GET', "/minha-conta/cursos/$noDoc/estudar");
$cfg = config_of($r['body']);
$ana->json("/minha-conta/cursos/$noDoc/scorm", ['session' => (int) ($cfg['session'] ?? 0), 'status' => 'passed', 'score' => ['raw' => '85']]);
check('sem CPF o certificado espera', !Database::value('SELECT id FROM certificates WHERE enrollment_id = :e', ['e' => $noDoc]));
check('participante recebe o pedido do CPF e a equipe o aviso', count(mails($t, 'Falta o seu CPF para emitir o certificado', 'ana@example.com')) === 1 && count(mails($t, 'Emitir certificado — ')) === 1);
$r = $ana->request('GET', '/minha-conta/certificados');
check('Certificados pede o CPF', str_contains($r['body'], "/minha-conta/certificados/$noDoc/cpf"));
$ana->request('POST', "/minha-conta/certificados/$noDoc/cpf", ['_scope' => 'cpf-' . $noDoc, 'document' => '123.456.789-00']);
check('CPF inválido é recusado', !Database::value('SELECT participant_document FROM enrollments WHERE id = :id', ['id' => $noDoc]));
$ana->request('GET', '/minha-conta/certificados');
$r = $ana->request('POST', "/minha-conta/certificados/$noDoc/cpf", ['_scope' => 'cpf-' . $noDoc, 'document' => '529.982.247-25']);
check('com o CPF, o certificado sai na hora', $r['status'] === 302 && (bool) Database::value('SELECT id FROM certificates WHERE enrollment_id = :e', ['e' => $noDoc]));
check('outra pessoa não informa CPF na matrícula da Ana', $rh->request('POST', "/minha-conta/certificados/$noDoc/cpf", ['document' => '52998224725'])['status'] === 404);

Settings::set('certificate.auto', '0');
$off = clone_enrollment($enrollmentId, '52998224725');
$r = $ana->request('GET', "/minha-conta/cursos/$off/estudar");
$cfg = config_of($r['body']);
$ana->json("/minha-conta/cursos/$off/scorm", ['session' => (int) ($cfg['session'] ?? 0), 'status' => 'passed', 'score' => ['raw' => '85']]);
check('emissão automática desligada: a equipe emite', !Database::value('SELECT id FROM certificates WHERE enrollment_id = :e', ['e' => $off]));
Settings::set('certificate.auto', '1');
Database::update('courses', ['practical_required' => 1], ['id' => $courseId]);

echo "\nEquipe: modelo do certificado\n";
$r = $admin->request('GET', '/admin/certificados');
check('tela do modelo abre', $r['status'] === 200 && str_contains($r['body'], 'Quem assina') && str_contains($r['body'], 'Instrutora Exemplo'));
$sig = sys_get_temp_dir() . '/dafnis-assinatura-' . bin2hex(random_bytes(3)) . '.png';
file_put_contents($sig, png_rgba(300, 90));
$r = $admin->request('POST', '/admin/certificados', [
    'certificate_auto' => '1', 'certificate_company' => 'Dafnis HSE Compliance', 'certificate_legal_name' => 'Dafnis HSE Compliance Ltda', 'certificate_city' => 'Araçatuba/SP',
    'signer_id[0]' => 'a1b2c3d4', 'signer_name[0]' => 'Instrutora Exemplo', 'signer_role[0]' => 'Instrutora', 'signer_registry[0]' => '', 'signer_default[0]' => '1', 'signer_remove_signature[0]' => '0',
    'signer_signature[0]' => new CURLFile($sig, 'image/png', 'assinatura.png'),
    'signer_id[1]' => 'e5f6a7b8', 'signer_name[1]' => 'Engenheiro Exemplo', 'signer_role[1]' => 'Resp. Técnico', 'signer_registry[1]' => 'CREA/SP 1', 'signer_default[1]' => '0', 'signer_remove_signature[1]' => '0',
    'signer_id[2]' => '', 'signer_name[2]' => 'Pessoa Nova', 'signer_role[2]' => 'Instrutor', 'signer_registry[2]' => '', 'signer_default[2]' => '0', 'signer_remove_signature[2]' => '0',
]);
Settings::flush();
$signers = Certificates::signers();
check('modelo salvo com as três pessoas', $r['status'] === 302 && count($signers) === 3 && $signers[0]['id'] === 'a1b2c3d4' && $signers[1]['id'] === 'e5f6a7b8' && preg_match('/^[a-f0-9]{8}$/', $signers[2]['id']) === 1);
check('razão social em maiúsculas e cidade da data', Settings::get('certificate.legal_name') === 'DAFNIS HSE COMPLIANCE LTDA' && Settings::get('certificate.city') === 'Araçatuba/SP');
check('imagem da assinatura guardada fora da pasta pública', !empty($signers[0]['signature']) && is_file(BASE_PATH . '/storage/uploads/' . $signers[0]['signature']));
$r = $admin->request('GET', '/admin/certificados/assinaturas/a1b2c3d4');
check('equipe confere a assinatura no painel', $r['status'] === 200 && str_contains((string) Client::header($r, 'Content-Type'), 'image/png'));
check('assinatura não abre fora do painel', $guest->request('GET', '/admin/certificados/assinaturas/a1b2c3d4')['status'] !== 200);
$course = Database::first('SELECT * FROM courses WHERE id = :id', ['id' => $courseId]);
check('PDF com a imagem da assinatura', str_contains(Certificates::sample($course), '/SMask'));
$r = $admin->request('GET', "/admin/cursos/$courseId/certificado-exemplo");
check('exemplo do curso em PDF pelo painel', $r['status'] === 200 && str_starts_with($r['body'], '%PDF'));
$r = $admin->request('GET', "/admin/cursos/$courseId/editar");
check('cadastro do curso tem o quadro do certificado', str_contains($r['body'], 'Conteúdo programático (verso)') && str_contains($r['body'], 'name="cert_signers[]"'));
$oldSignature = $signers[0]['signature'];
$admin->request('GET', '/admin/certificados');
$admin->request('POST', '/admin/certificados', [
    'certificate_auto' => '1',
    'signer_id[0]' => 'a1b2c3d4', 'signer_name[0]' => 'Instrutora Exemplo', 'signer_role[0]' => 'Instrutora', 'signer_registry[0]' => '', 'signer_default[0]' => '1', 'signer_remove_signature[0]' => '1',
]);
Settings::flush();
$left = Certificates::signers();
clearstatcache();
check('remover a pessoa e a assinatura apaga a imagem', count($left) === 1 && empty($left[0]['signature']) && !is_file(BASE_PATH . '/storage/uploads/' . $oldSignature), json_encode($left) . ' ' . $oldSignature);
@unlink($sig);

echo "\n" . ($failures ? "$failures de $checks verificações falharam." : "Tudo certo: $checks verificações.") . "\n";
exit($failures ? 1 : 0);
