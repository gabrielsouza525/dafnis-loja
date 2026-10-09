<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Response;
use App\Services\Certificates;

/** Validação pública dos certificados: o QR code e o código impressos levam para cá. */
final class CertificateController extends Controller
{
    public function verify(): Response
    {
        $code = strtoupper(trim((string) $this->request->query('codigo', '')));
        if ($code !== '' && preg_match('/^DF-?[A-Z0-9]{8}$/', $code)) {
            $code = str_starts_with($code, 'DF-') ? $code : 'DF-' . substr($code, 2);
            return Response::redirect(url('/certificados/' . $code));
        }
        return $this->page($code, null, $code !== '');
    }

    public function show(string $code): Response
    {
        $code = strtoupper($code);
        return $this->page($code, Certificates::findByCode($code), true);
    }

    private function page(string $code, ?array $certificate, bool $searched): Response
    {
        return $this->view('site/certificate-verify', [
            'title' => $certificate ? 'Certificado ' . $certificate['code'] : 'Validar certificado',
            'description' => 'Confira se um certificado emitido pela Dafnis é verdadeiro, pelo código impresso nele.',
            'noindex' => true,
            'code' => $code,
            'certificate' => $certificate,
            'searched' => $searched,
        ]);
    }
}
