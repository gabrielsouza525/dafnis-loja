<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Order;
use App\Services\Auth;
use App\Services\Cart;
use App\Services\Orders;
use App\Services\Payments\Payments;
use App\Services\RateLimiter;

final class CheckoutController extends Controller
{
    public function show(): Response
    {
        $lines = Cart::lines();
        if (!$lines) {
            flash('info', 'Seu carrinho está vazio. Escolha um treinamento para continuar.');
            return $this->redirect('/carrinho');
        }
        $user = Auth::user();
        // Reaproveita os dados da última compra (útil para empresas que compram sempre).
        $last = Database::first('SELECT * FROM orders WHERE user_id = :u ORDER BY id DESC LIMIT 1', ['u' => $user['id']]);

        return $this->view('site/checkout', [
            'title' => 'Finalizar compra',
            'noindex' => true,
            'lines' => $lines,
            'totals' => Cart::totals($lines),
            'buyer' => [
                'buyer_type' => $last['buyer_type'] ?? 'pf',
                'buyer_name' => $user['name'],
                'buyer_document' => $user['document'] ? document_display($user['document']) : '',
                'buyer_email' => $user['email'],
                'buyer_phone' => $user['phone'] ? phone_display($user['phone']) : '',
                'company_name' => $last['company_name'] ?? '',
                'company_document' => ($last['buyer_type'] ?? '') === 'pj' ? document_display($last['buyer_document']) : '',
                'payment_method' => $last['payment_method'] ?? 'pix',
            ],
            'online' => Payments::isOnline(),
            'methods' => Order::METHODS,
        ]);
    }

    public function place(): Response
    {
        $user = Auth::user();
        RateLimiter::check('checkout', (string) $user['id'], $this->request->ip(), 10, 30, 10);

        $type = $this->request->input('buyer_type') === 'pj' ? 'pj' : 'pf';
        $rules = [
            'buyer_name' => 'required|min:3|max:120',
            'buyer_email' => 'required|email',
            'buyer_phone' => 'required|phone',
            'payment_method' => 'required|in:pix,card,boleto',
            'accept_terms' => 'required',
        ];
        $labels = ['buyer_name' => 'nome', 'buyer_email' => 'e-mail', 'buyer_phone' => 'telefone', 'payment_method' => 'forma de pagamento'];
        $messages = ['accept_terms.required' => 'Para finalizar, aceite os termos de uso e a política de privacidade.'];
        if ($type === 'pj') {
            $rules['company_name'] = 'required|min:2|max:160';
            $rules['company_document'] = 'required|cnpj';
            $labels += ['company_name' => 'razão social', 'company_document' => 'CNPJ', 'buyer_name' => 'responsável pela compra'];
        } else {
            $rules['buyer_document'] = 'required|cpf';
            $labels['buyer_document'] = 'CPF';
        }
        $data = Validator::validate($this->request->all(), $rules, $labels, $messages);

        $order = Orders::createFromCart([
            'buyer_type' => $type,
            'buyer_name' => $data['buyer_name'],
            'buyer_document' => $type === 'pj' ? $data['company_document'] : $data['buyer_document'],
            'buyer_email' => $data['buyer_email'],
            'buyer_phone' => $data['buyer_phone'],
            'company_name' => $type === 'pj' ? $data['company_name'] : null,
        ], (int) $user['id'], $data['payment_method'], $this->request->ip());

        // Completa o cadastro com o que o cliente acabou de informar, sem sobrescrever nada.
        $profile = [];
        if (!$user['phone']) {
            $profile['phone'] = $data['buyer_phone'];
        }
        if ($type === 'pf' && !$user['document']) {
            $profile['document'] = $data['buyer_document'];
        }
        if ($profile) {
            Database::update('users', $profile, ['id' => $user['id']]);
        }

        Cart::clear();
        return $this->redirect('/pedido/' . $order['number'] . (Payments::isOnline() ? '?pagar=1' : ''));
    }
}
