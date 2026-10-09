<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\Course;
use App\Services\Activity;
use App\Services\Certificates;
use App\Services\Settings;
use App\Services\Uploads;

/** Modelo dos certificados: textos da empresa, quem assina (com a imagem da assinatura) e o PDF de exemplo. */
final class CertificateController extends AdminController
{
    public function edit(): Response
    {
        return $this->view('admin/certificates', [
            'title' => 'Certificados',
            'section' => 'certificados',
            'settings' => Certificates::settings(),
            'values' => [
                'certificate.company' => Settings::get('certificate.company', ''),
                'certificate.legal_name' => Settings::get('certificate.legal_name', ''),
                'certificate.city' => Settings::get('certificate.city', ''),
            ],
            'signers' => Certificates::signers(),
            'courses' => \App\Core\Database::select(
                "SELECT id, title, code, nr_number FROM courses
                  WHERE id IN (SELECT course_id FROM course_packages WHERE is_current = 1) OR cert_syllabus IS NOT NULL
                  ORDER BY nr_number IS NULL, nr_number, title"
            ),
        ]);
    }

    public function update(): Response
    {
        $data = Validator::validate($this->request->all(), [
            'certificate_company' => 'nullable|max:120',
            'certificate_legal_name' => 'nullable|max:160',
            'certificate_city' => 'nullable|max:80',
        ], ['certificate_company' => 'nome da empresa no texto', 'certificate_legal_name' => 'razão social', 'certificate_city' => 'cidade']);
        Settings::set('certificate.auto', $this->request->bool('certificate_auto') ? '1' : '0');
        Settings::set('certificate.company', $data['certificate_company']);
        Settings::set('certificate.legal_name', $data['certificate_legal_name'] !== null ? mb_strtoupper((string) $data['certificate_legal_name']) : null);
        Settings::set('certificate.city', $data['certificate_city']);

        // Signatários: uma linha do formulário por pessoa; o id mantém a escolha feita nos cursos.
        $current = array_column(Certificates::signers(), null, 'id');
        $ids = (array) $this->request->input('signer_id', []);
        $names = (array) $this->request->input('signer_name', []);
        $roles = (array) $this->request->input('signer_role', []);
        $registries = (array) $this->request->input('signer_registry', []);
        $defaults = (array) $this->request->input('signer_default', []);
        $removeSignature = (array) $this->request->input('signer_remove_signature', []);
        $files = $this->request->files('signer_signature');
        $signers = [];
        foreach ($names as $i => $name) {
            $name = trim((string) $name);
            $id = preg_replace('/[^a-z0-9]/', '', (string) ($ids[$i] ?? ''));
            $before = $current[$id] ?? null;
            if ($name === '') {
                if ($before && !empty($before['signature'])) {
                    Uploads::deletePrivate($before['signature']);
                }
                continue;
            }
            $signature = $before['signature'] ?? null;
            if (($removeSignature[$i] ?? '') === '1' && $signature) {
                Uploads::deletePrivate($signature);
                $signature = null;
            }
            if (isset($files[$i])) {
                $new = Uploads::signature($files[$i], 'signer_signature');
                if ($signature) {
                    Uploads::deletePrivate($signature);
                }
                $signature = $new;
            }
            $signers[] = [
                'id' => $before ? $id : bin2hex(random_bytes(4)),
                'name' => mb_substr($name, 0, 120),
                'role' => mb_substr(trim((string) ($roles[$i] ?? '')), 0, 120),
                'registry' => mb_substr(trim((string) ($registries[$i] ?? '')), 0, 60),
                'default' => ($defaults[$i] ?? '') === '1',
                'signature' => $signature,
            ];
        }
        $kept = array_column($signers, 'id');
        foreach ($current as $id => $before) {
            if (!in_array($id, $kept, true) && !empty($before['signature'])) {
                Uploads::deletePrivate($before['signature']);
            }
        }
        Settings::set('certificate.signers', json_encode($signers, JSON_UNESCAPED_UNICODE));
        Activity::log('settings.certificates', 'settings', null, count($signers) . ' signatário(s)');
        return $this->success('Modelo do certificado salvo.', '/admin/certificados');
    }

    /** Imagem da assinatura, para conferir no painel. */
    public function signature(string $id): Response
    {
        foreach (Certificates::signers() as $s) {
            if ($s['id'] === $id && ($path = Uploads::privatePath($s['signature'] ?? null))) {
                return Response::file($path, str_ends_with($path, '.png') ? 'image/png' : 'image/jpeg', ['Cache-Control' => 'no-store, private']);
            }
        }
        $this->notFound('Assinatura não encontrada.');
    }

    /** PDF de exemplo com os dados do curso e um participante fictício. */
    public function sample(int $id): Response
    {
        $course = $this->findOr404(Course::find($id), 'Curso não encontrado.');
        try {
            $pdf = Certificates::sample($course);
        } catch (\RuntimeException $e) {
            throw ValidationException::with('certificate', 'Não foi possível gerar o exemplo: ' . $e->getMessage());
        }
        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="certificado-exemplo-' . $course['slug'] . '.pdf"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
