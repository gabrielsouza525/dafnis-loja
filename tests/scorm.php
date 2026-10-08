<?php
declare(strict_types=1);

/**
 * Cursos próprios em SCORM 1.2: importação (comando e painel), página do curso, arquivos protegidos,
 * gravação do andamento, aviso de presença e o que a equipe vê. Usa o pacote de tests/fixtures/scorm-demo.
 * Requer os dados de demonstração (db:fresh --demo) e o servidor rodando; acessa o banco do .env.
 *
 *   php tests/scorm.php http://localhost:8000
 */

require __DIR__ . '/lib.php';
require dirname(__DIR__) . '/app/bootstrap.php';
restore_exception_handler();

use App\Core\Database;

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

/** .zip de uma pasta (o leitor de zip do Phar vem no PHP, sem depender da extensão zip). */
function make_zip(string $dir, array $extra = []): string
{
    $zip = sys_get_temp_dir() . '/dafnis-scorm-' . bin2hex(random_bytes(4)) . '.zip';
    $phar = new PharData($zip, 0, null, Phar::ZIP);
    $phar->buildFromDirectory($dir);
    foreach ($extra as $name => $content) {
        $phar->addFromString($name, $content);
    }
    unset($phar);
    return $zip;
}

function config_of(string $html): ?array
{
    return preg_match('#<script type="application/json" id="scorm-config">(.*?)</script>#s', $html, $m) ? json_decode($m[1], true) : null;
}

/** Endereço do app (sem a subpasta do Apache), como os testes pedem as páginas. */
function app_path(string $url): string
{
    $prefix = rtrim((string) parse_url((string) env('APP_URL', ''), PHP_URL_PATH), '/');
    $path = (string) parse_url($url, PHP_URL_PATH);
    return $prefix !== '' && str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
}

$fixture = __DIR__ . '/fixtures/scorm-demo';
$slug = 'nr-10-seguranca-em-instalacoes-e-servicos-com-eletricidade-basico';
$courseId = (int) Database::value('SELECT id FROM courses WHERE slug = :s', ['s' => $slug]);
$enrollmentId = (int) Database::value(
    "SELECT e.id FROM enrollments e WHERE e.participant_email = 'ana@example.com' AND e.course_id = :c AND e.status = 'active'",
    ['c' => $courseId]
);
if (!$courseId || !$enrollmentId) {
    fwrite(STDERR, "Rode php bin/console db:fresh --demo antes (falta a matrícula de NR 10 da Ana).\n");
    exit(1);
}
// Começa sem conteúdo no curso (apaga também as pastas de execuções anteriores).
Database::query('DELETE FROM course_packages WHERE course_id = :c', ['c' => $courseId]);
$old = BASE_PATH . '/storage/scorm/' . $courseId;
if (is_dir($old)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($old, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($old);
}

echo "Dafnis — cursos SCORM em $base\n\nImportação pelo comando\n";
$zip = make_zip($fixture);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/console') . ' scorm:import --course=' . $slug . ' --file=' . escapeshellarg($zip) . ' 2>&1', $output, $code);
check('scorm:import importa o pacote', $code === 0 && str_contains(implode("\n", $output), 'Versão 1 importada'), implode(' ', $output));
$package = Database::first('SELECT * FROM course_packages WHERE course_id = :c AND is_current = 1', ['c' => $courseId]);
check('manifesto lido: título, abertura e nota mínima', $package && $package['title'] === 'Curso de demonstração' && $package['launch_path'] === 'content/index.html' && (int) $package['mastery_score'] === 70);

$bad = sys_get_temp_dir() . '/dafnis-nao-zip.zip';
file_put_contents($bad, 'isto não é um zip');
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/console') . ' scorm:import --course=' . $courseId . ' --file=' . escapeshellarg($bad) . ' 2>&1', $output2, $code2);
check('arquivo que não é zip é recusado', $code2 !== 0);
$evil = make_zip($fixture, ['content/shell.php' => '<?php echo 1;']);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/console') . ' scorm:import --course=' . $courseId . ' --file=' . escapeshellarg($evil) . ' 2>&1', $output3, $code3);
check('pacote com .php é recusado', $code3 !== 0 && str_contains(implode(' ', $output3), 'não aceito'), implode(' ', $output3));
$noManifest = sys_get_temp_dir() . '/dafnis-sem-manifesto-' . bin2hex(random_bytes(3));
mkdir($noManifest . '/x', 0777, true);
file_put_contents($noManifest . '/x/index.html', '<p>oi</p>');
$zipNoManifest = make_zip($noManifest);
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/bin/console') . ' scorm:import --course=' . $courseId . ' --file=' . escapeshellarg($zipNoManifest) . ' 2>&1', $output4, $code4);
check('pacote sem imsmanifest.xml é recusado', $code4 !== 0 && str_contains(implode(' ', $output4), 'imsmanifest'), implode(' ', $output4));
check('importações recusadas não deixam versão nem pasta', (int) Database::value('SELECT COUNT(*) FROM course_packages WHERE course_id = :c', ['c' => $courseId]) === 1
    && count(glob(BASE_PATH . '/storage/scorm/' . $courseId . '/*', GLOB_ONLYDIR) ?: []) === 1);

echo "\nAluna\n";
$guest = new Client($base);
$r = $guest->request('GET', "/minha-conta/cursos/$enrollmentId/estudar");
check('visitante vai para o login', $r['status'] === 302 && str_contains($r['location'], '/login'));

$ana = new Client($base);
check('login da Ana', $ana->login('ana@example.com', 'dafnis123'));
$r = $ana->request('GET', '/minha-conta/cursos');
check('Meus cursos leva ao curso na loja', str_contains($r['body'], "/minha-conta/cursos/$enrollmentId/estudar"));

$r = $ana->request('GET', "/minha-conta/cursos/$enrollmentId/estudar");
$cfg = config_of($r['body']);
check('página do curso abre', $r['status'] === 200 && $cfg !== null, 'status ' . $r['status']);
check('página do curso pode abrir o pacote num iframe', str_contains((string) Client::header($r, 'Content-Security-Policy'), "frame-src 'self'"));
check('primeira vez: entry ab-initio e nome no formato do SCORM', ($cfg['cmi']['cmi.core.entry'] ?? '') === 'ab-initio' && ($cfg['cmi']['cmi.core.student_name'] ?? '') === 'Souza, Ana');
check('nota mínima do manifesto vai para o curso', ($cfg['cmi']['cmi.student_data.mastery_score'] ?? '') === '70');
$session = (int) ($cfg['session'] ?? 0);
$launch = app_path((string) ($cfg['launch'] ?? ''));
$packageBase = substr($launch, 0, (int) strrpos($launch, '/content/'));

$r = $ana->request('GET', $launch);
check('arquivo de abertura é entregue', $r['status'] === 200 && str_contains((string) Client::header($r, 'Content-Type'), 'text/html') && str_contains($r['body'], 'Curso de demonstração'));
check('pacote só abre dentro da loja', Client::header($r, 'X-Frame-Options') === 'SAMEORIGIN' && str_contains((string) Client::header($r, 'Content-Security-Policy'), "frame-ancestors 'self'"));
$r = $ana->request('GET', $packageBase . '/content/demo.js', [], ['Range: bytes=0-9']);
check('vídeos e áudios: pedido com Range responde 206', $r['status'] === 206 && strlen($r['body']) === 10 && str_starts_with((string) Client::header($r, 'Content-Range'), 'bytes 0-9/'));
$r = $ana->request('GET', $packageBase . '/content/%2e%2e/%2e%2e/%2e%2e/%2e%2e/.env');
check('caminho com .. não sai da pasta do pacote', $r['status'] === 404);
$r = $ana->request('GET', $packageBase . '/content/nao-existe.html');
check('arquivo inexistente → 404', $r['status'] === 404);

$r = $ana->json("/minha-conta/cursos/$enrollmentId/scorm", ['session' => $session, 'status' => 'incomplete', 'location' => 'pagina-2', 'suspend' => 'parou-na-pagina-2', 'session_time' => '0000:05:00.00']);
check('curso grava o andamento (LMSCommit)', $r['status'] === 200 && ($r['json']['status'] ?? '') === 'incomplete', 'status ' . $r['status'] . ' ' . substr($r['body'], 0, 200));
$r = $ana->json("/minha-conta/cursos/$enrollmentId/scorm", ['session' => $session, 'status' => 'incomplete'], ['X-CSRF-Token: errado']);
check('gravação sem o token CSRF é recusada', $r['status'] === 419);

$r = $ana->json("/minha-conta/cursos/$enrollmentId/presenca", ['session' => $session, 'active' => true]);
check('aviso de presença aceito', $r['status'] === 200 && ($r['json']['ok'] ?? false) === true);
Database::query('UPDATE study_sessions SET last_seen_at = NOW() - INTERVAL 60 SECOND WHERE id = :id', ['id' => $session]);
$ana->json("/minha-conta/cursos/$enrollmentId/presenca", ['session' => $session, 'active' => true]);
$active = (int) Database::value('SELECT active_seconds FROM study_sessions WHERE id = :id', ['id' => $session]);
check('presença soma o tempo de estudo desde o último aviso', $active >= 59 && $active <= 62, "active_seconds=$active");
Database::query('UPDATE study_sessions SET last_seen_at = NOW() - INTERVAL 600 SECOND WHERE id = :id', ['id' => $session]);
$ana->json("/minha-conta/cursos/$enrollmentId/presenca", ['session' => $session, 'active' => true]);
$active2 = (int) Database::value('SELECT active_seconds FROM study_sessions WHERE id = :id', ['id' => $session]);
check('intervalo longo sem aviso conta no máximo 90 s', $active2 - $active <= 91, 'somou ' . ($active2 - $active));
Database::query('UPDATE study_sessions SET last_seen_at = NOW() - INTERVAL 60 SECOND WHERE id = :id', ['id' => $session]);
$ana->json("/minha-conta/cursos/$enrollmentId/presenca", ['session' => $session, 'active' => false], ['X-Background: 1']);
$active3 = (int) Database::value('SELECT active_seconds FROM study_sessions WHERE id = :id', ['id' => $session]);
check('sem atividade (aba oculta ou parada) não conta tempo', $active3 === $active2);

$r = $ana->request('GET', "/minha-conta/cursos/$enrollmentId/estudar");
$cfg2 = config_of($r['body']);
$session2 = (int) ($cfg2['session'] ?? 0);
check('ao voltar: entry resume, ponto de parada e suspend_data', ($cfg2['cmi']['cmi.core.entry'] ?? '') === 'resume'
    && ($cfg2['cmi']['cmi.core.lesson_location'] ?? '') === 'pagina-2' && ($cfg2['cmi']['cmi.suspend_data'] ?? '') === 'parou-na-pagina-2');
check('tempo total informado pelo curso volta em cmi.core.total_time', ($cfg2['cmi']['cmi.core.total_time'] ?? '') === '0000:05:00.00');
check('cada abertura é um acesso registrado', $session2 > $session);

// Duas abas: a aberta primeiro, em uso, é a única que conta.
Database::query('UPDATE study_sessions SET last_active_at = NOW() WHERE id = :id', ['id' => $session]);
Database::query('UPDATE study_sessions SET last_seen_at = NOW() - INTERVAL 60 SECOND WHERE id = :id', ['id' => $session2]);
$ana->json("/minha-conta/cursos/$enrollmentId/presenca", ['session' => $session2, 'active' => true]);
check('curso aberto em duas abas não conta o tempo em dobro', (int) Database::value('SELECT active_seconds FROM study_sessions WHERE id = :id', ['id' => $session2]) === 0);

$r = $ana->json("/minha-conta/cursos/$enrollmentId/scorm", [
    'session' => $session2, 'status' => 'passed', 'score' => ['raw' => '90', 'max' => '100'], 'session_time' => '0000:12:30.00',
    'interactions' => ['0' => ['id' => 'questao-1', 'type' => 'choice', 'student_response' => 'b', 'result' => 'correct', 'correct_responses' => ['b']]],
]);
check('aprovação gravada', ($r['json']['status'] ?? '') === 'passed');
$e = Database::first('SELECT * FROM enrollments WHERE id = :id', ['id' => $enrollmentId]);
check('parte on-line concluída: progresso 100%', (int) $e['progress'] === 100);
check('curso com prática obrigatória continua "Em andamento" até a parte presencial', $e['status'] === 'active');
$r = $ana->json("/minha-conta/cursos/$enrollmentId/scorm", ['session' => $session2, 'status' => 'failed', 'score' => ['raw' => '40']]);
$attempt = Database::first('SELECT * FROM scorm_attempts WHERE enrollment_id = :e', ['e' => $enrollmentId]);
check('refazer a prova depois de aprovado não tira a aprovação nem a nota', $attempt['lesson_status'] === 'passed' && (float) $attempt['score_raw'] === 90.0);
check('respostas da prova guardadas', str_contains((string) $attempt['interactions'], 'questao-1'));
$r = $ana->request('GET', "/minha-conta/cursos/$enrollmentId/estudar");
check('depois de aprovado o curso abre para revisão', (config_of($r['body'])['cmi']['cmi.core.lesson_mode'] ?? '') === 'review');
check('respostas anteriores numeram as novas (cmi.interactions._count)', (config_of($r['body'])['cmi']['cmi.interactions._count'] ?? '') === '1');
$r = $ana->request('GET', '/minha-conta/cursos');
check('Meus cursos avisa da prática presencial', str_contains($r['body'], 'Parte on-line concluída'));

echo "\nOutras pessoas\n";
$rh = new Client($base);
$rh->login('rh@example.com', 'dafnis123');
check('outra conta não abre o curso da Ana', $rh->request('GET', "/minha-conta/cursos/$enrollmentId/estudar")['status'] === 404);
check('outra conta não baixa os arquivos do curso', $rh->request('GET', $launch)['status'] === 404);
check('outra conta não grava andamento', $rh->json("/minha-conta/cursos/$enrollmentId/scorm", ['status' => 'passed'])['status'] === 404);

echo "\nEquipe\n";
$admin = new Client($base);
check('login da equipe', $admin->login('admin@dafnis.test', 'dafnis123', team_2fa_required() ? DEMO_ADMIN_TOTP : null));
$r = $admin->request('GET', "/admin/matriculas/$enrollmentId");
check('matrícula mostra o curso on-line, a situação e o tempo', $r['status'] === 200 && str_contains($r['body'], 'Curso on-line na loja') && str_contains($r['body'], 'Aprovado') && str_contains($r['body'], 'Tempo de estudo'));
check('matrícula lista os acessos', substr_count($r['body'], '<td class="mono">') >= 2);
check('aprovação com tempo abaixo da carga on-line pede conferência', str_contains($r['body'], 'Confira antes do certificado') && str_contains($r['body'], 'abaixo das 32 h'));
$r = $admin->request('GET', "/admin/cursos/$courseId/editar");
check('curso mostra as versões do conteúdo', str_contains($r['body'], 'Conteúdo on-line próprio') && str_contains($r['body'], 'Curso de demonstração'));
$r = $admin->request('GET', "/admin/cursos/$courseId/pacotes/{$package['id']}/previa");
$preview = config_of($r['body']);
check('pré-visualização abre sem gravar nada', $r['status'] === 200 && ($preview['preview'] ?? false) === true && !isset($preview['commit']));
check('pré-visualização entrega os arquivos', $admin->request('GET', app_path((string) $preview['launch']))['status'] === 200);
check('aluno não abre a pré-visualização', $ana->request('GET', "/admin/cursos/$courseId/pacotes/{$package['id']}/previa")['status'] !== 200);

$r = $admin->request('POST', "/admin/cursos/$courseId/pacotes", ['package' => new CURLFile($zip, 'application/zip', 'curso-v2.zip')]);
$v2 = Database::first('SELECT * FROM course_packages WHERE course_id = :c AND version = 2', ['c' => $courseId]);
check('envio pelo painel cria a versão 2 fora de uso (caixa desmarcada)', $r['status'] === 302 && $v2 && !$v2['is_current']);
$admin->request('GET', "/admin/cursos/$courseId/editar");
$admin->request('POST', "/admin/cursos/$courseId/pacotes/{$v2['id']}/usar");
check('versão 2 em uso', (int) Database::value('SELECT id FROM course_packages WHERE course_id = :c AND is_current = 1', ['c' => $courseId]) === (int) $v2['id']);
$r = $ana->request('GET', "/minha-conta/cursos/$enrollmentId/estudar");
check('quem já começou continua na versão em que começou', str_contains((string) (config_of($r['body'])['launch'] ?? ''), '/pacote/' . $package['id'] . '/'));
$admin->request('GET', "/admin/cursos/$courseId/editar");
$r = $admin->request('POST', "/admin/cursos/$courseId/pacotes/{$package['id']}/excluir");
check('versão com participantes não pode ser excluída', (bool) Database::first('SELECT id FROM course_packages WHERE id = :id', ['id' => $package['id']]));
$admin->request('GET', "/admin/cursos/$courseId/editar");
$admin->request('POST', "/admin/cursos/$courseId/pacotes/{$package['id']}/usar");
$admin->request('GET', "/admin/cursos/$courseId/editar");
$admin->request('POST', "/admin/cursos/$courseId/pacotes/{$v2['id']}/excluir");
check('versão sem participantes e fora de uso é excluída com os arquivos', !Database::first('SELECT id FROM course_packages WHERE id = :id', ['id' => $v2['id']]) && !is_dir(BASE_PATH . '/storage/scorm/' . $v2['directory']));
copy($zip, BASE_PATH . '/storage/scorm/entrada/curso-grande.zip');
$r = $admin->request('GET', "/admin/cursos/$courseId/editar");
check('painel lista os pacotes da pasta de entrada do servidor', str_contains($r['body'], 'curso-grande.zip') && str_contains($r['body'], 'Importar do servidor'));
$r = $admin->request('POST', "/admin/cursos/$courseId/pacotes/servidor", ['arquivo' => 'curso-grande.zip']);
$fromInbox = Database::first('SELECT * FROM course_packages WHERE course_id = :c AND original_name = :n', ['c' => $courseId, 'n' => 'curso-grande.zip']);
check('pacote da pasta de entrada é importado (fora de uso) e sai da pasta', $r['status'] === 302 && $fromInbox && !$fromInbox['is_current'] && !is_file(BASE_PATH . '/storage/scorm/entrada/curso-grande.zip'));
$admin->request('GET', "/admin/cursos/$courseId/editar");
$r = $admin->request('POST', "/admin/cursos/$courseId/pacotes/servidor", ['arquivo' => '../../.env']);
check('pasta de entrada não aceita caminho fora dela', $r['status'] === 302 && is_file(BASE_PATH . '/.env'));
if ($fromInbox) {
    App\Services\Scorm\Packages::delete($fromInbox);
}
$admin->request('GET', "/admin/cursos/$courseId/editar");
$r = $admin->request('POST', "/admin/cursos/$courseId/pacotes", ['package' => new CURLFile($bad, 'application/zip', 'quebrado.zip'), 'make_current' => '1']);
$r = $admin->request('GET', "/admin/cursos/$courseId/editar");
check('pacote inválido pelo painel volta com o erro', str_contains($r['body'], 'não é um .zip válido'));

echo "
Andamento do Rise 360
";
/** Comprime como o Rise (LZW com dicionário inicial de 256 caracteres) para montar um suspend_data. */
function rise_suspend(array $data): string
{
    $text = (string) json_encode($data);
    $dict = [];
    for ($i = 0; $i < 256; $i++) {
        $dict[chr($i)] = $i;
    }
    $next = 256;
    $w = '';
    $codes = [];
    foreach (str_split($text) as $c) {
        if (isset($dict[$w . $c])) {
            $w .= $c;
            continue;
        }
        $codes[] = $dict[$w];
        $dict[$w . $c] = $next++;
        $w = $c;
    }
    $codes[] = $dict[$w];
    return (string) json_encode(['v' => 3, 'd' => $codes]);
}
$suspend = rise_suspend(['cpv' => 'x', 'progress' => ['lessons' => ['0' => ['p' => 100, 'i' => []], '1' => ['p' => 50, 'i' => []], '7' => ['p' => 30]]]]);
check('suspend_data do Rise vira percentual (100 + 50 + 30 em 8 lições = 22%)', App\Services\Scorm\RiseProgress::fromSuspendData($suspend, 8) === 22);
check('lição fora do total é ignorada (índice 7 com 4 lições: 150 / 4 = 37%)', App\Services\Scorm\RiseProgress::fromSuspendData($suspend, 4) === 37);
check('player próprio da Dafnis informa o percentual direto', App\Services\Scorm\RiseProgress::fromSuspendData('{"dafnis":1,"progress":36,"done":{}}', null) === 36);
check('formato desconhecido não inventa percentual', App\Services\Scorm\RiseProgress::fromSuspendData('parou-na-pagina-2', 4) === null && App\Services\Scorm\RiseProgress::fromSuspendData($suspend, null) === null);
$riseDir = sys_get_temp_dir() . '/dafnis-rise-' . bin2hex(random_bytes(3));
mkdir($riseDir . '/scormcontent', 0777, true);
$runtime = ['course' => ['lessons' => [['type' => 'blocks'], ['type' => 'section'], ['type' => 'blocks'], ['type' => 'quiz']]]];
file_put_contents($riseDir . '/scormcontent/runtime-data.js', '__jsonp("runtime-data.js","' . base64_encode((string) json_encode($runtime)) . '");');
check('total de lições lido do pacote do Rise, sem os títulos de seção', App\Services\Scorm\RiseProgress::lessonCount($riseDir) === 3);
@unlink($riseDir . '/scormcontent/runtime-data.js');
@rmdir($riseDir . '/scormcontent');
@rmdir($riseDir);

foreach ([$zip, $bad, $evil, $zipNoManifest] as $f) {
    @unlink($f);
}
echo "\n" . ($failures ? "$failures de $checks verificações falharam." : "Todas as $checks verificações passaram.") . PHP_EOL;
exit($failures ? 1 : 0);
