<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Response;
use App\Core\Validator;
use App\Services\Activity;
use App\Services\Payments\Payments;
use App\Services\Settings;

final class SettingsController extends AdminController
{
    /** Campos simples: chave => [rótulo, regra] */
    private const FIELDS = [
        'business.name' => ['Nome da empresa', 'required|max:120'],
        'business.cnpj' => ['CNPJ', 'nullable|cnpj'],
        'business.email' => ['E-mail de contato', 'nullable|email'],
        'business.phone' => ['Telefone', 'nullable|phone'],
        'business.whatsapp' => ['WhatsApp', 'nullable|phone'],
        'business.address' => ['Endereço', 'nullable|max:200'],
        'business.city' => ['Cidade', 'nullable|max:80'],
        'business.state' => ['UF', 'nullable|uf'],
        'business.instagram' => ['Instagram (usuário)', 'nullable|max:60'],
        'business.linkedin' => ['LinkedIn (link)', 'nullable|url'],
        'business.youtube' => ['YouTube (link)', 'nullable|url'],
        'stats.companies' => ['Empresas atendidas', 'nullable|max:20'],
        'stats.professionals' => ['Profissionais capacitados', 'nullable|max:20'],
        'stats.years' => ['Anos de atuação', 'nullable|max:20'],
        'lms.url' => ['Link da plataforma de ensino', 'nullable|url'],
        'notice.text' => ['Aviso no topo da loja', 'nullable|max:200'],
        'content.about' => ['Texto institucional', 'nullable|max:1200'],
        'content.practical_note' => ['Aviso da parte prática', 'nullable|max:255'],
    ];

    public function edit(): Response
    {
        $values = [];
        foreach (array_keys(self::FIELDS) as $key) {
            $values[$key] = Settings::get($key, '');
        }
        return $this->view('admin/settings', [
            'title' => 'Configurações',
            'section' => 'configuracoes',
            'values' => $values,
            'faq' => Settings::json('content.faq'),
            'online' => Payments::isOnline(),
            'gatewayName' => Payments::gateway()->name(),
            'webhookUrl' => absolute_url('/webhooks/mercadopago'),
        ]);
    }

    public function update(): Response
    {
        $input = [];
        $rules = [];
        $labels = [];
        foreach (self::FIELDS as $key => [$label, $rule]) {
            $field = str_replace('.', '__', $key);
            $input[$field] = $this->request->input($field);
            $rules[$field] = $rule;
            $labels[$field] = mb_strtolower($label);
        }
        $data = Validator::validate($input, $rules, $labels);
        foreach (self::FIELDS as $key => $_) {
            $value = $data[str_replace('.', '__', $key)];
            if ($key === 'business.instagram' && $value) {
                $value = ltrim(preg_replace('#^https?://(www\.)?instagram\.com/#', '', (string) $value), '@/');
                $value = rtrim($value, '/');
            }
            Settings::set($key, $value !== null ? (string) $value : null);
        }

        $questions = (array) $this->request->input('faq_q', []);
        $answers = (array) $this->request->input('faq_a', []);
        $faq = [];
        foreach ($questions as $i => $q) {
            $q = trim((string) $q);
            $a = trim((string) ($answers[$i] ?? ''));
            if ($q !== '' && $a !== '') {
                $faq[] = ['q' => mb_substr($q, 0, 200), 'a' => mb_substr($a, 0, 1500)];
            }
        }
        Settings::set('content.faq', json_encode($faq, JSON_UNESCAPED_UNICODE));
        Activity::log('settings.updated', 'settings', null);
        return $this->success('Configurações salvas.', '/admin/configuracoes');
    }
}
