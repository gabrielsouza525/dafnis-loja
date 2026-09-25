<?php
declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\View;
use App\Models\Coupon;
use App\Services\Cart;

/**
 * Carrinho. Toda ação funciona como formulário comum (redirect) e, chamada pelo
 * app.js, devolve JSON com o HTML atualizado e a contagem do cabeçalho.
 */
final class CartController extends Controller
{
    public function show(): Response
    {
        return $this->view('site/cart', array_merge($this->cartData(), [
            'title' => 'Carrinho',
            'noindex' => true,
        ]));
    }

    public function add(): Response
    {
        $qty = max(1, min(Cart::MAX_QTY, $this->request->int('qty', 1)));
        $course = Cart::add($this->request->int('course_id'), $qty);
        $label = $course['nr_number'] && !$course['is_simulator'] ? $course['code_label'] : ($course['short_title'] ?: 'Treinamento');
        $message = $label . ' adicionado ao carrinho';

        if ($this->request->bool('buy_now')) {
            return $this->request->wantsJson()
                ? $this->json(['ok' => true, 'redirect' => url('/carrinho'), 'count' => Cart::count()])
                : $this->redirect('/carrinho');
        }
        if ($this->request->wantsJson()) {
            return $this->json(['ok' => true, 'message' => $message, 'count' => Cart::count(), 'cart_url' => url('/carrinho')]);
        }
        flash('success', $message);
        return $this->back('/carrinho');
    }

    public function update(): Response
    {
        Cart::setQuantity($this->request->int('course_id'), $this->request->int('qty', 1));
        return $this->respond(null);
    }

    public function remove(): Response
    {
        $course = Cart::remove($this->request->int('course_id'));
        return $this->respond($course ? 'Treinamento removido do carrinho' : null);
    }

    public function coupon(): Response
    {
        try {
            $coupon = Cart::applyCoupon((string) $this->request->input('coupon', ''));
        } catch (ValidationException $e) {
            if ($this->request->wantsJson()) {
                return $this->respond(null, ['coupon_error' => $e->getMessage(), 'coupon_input' => (string) $this->request->input('coupon', '')]);
            }
            throw $e;
        }
        return $this->respond('Cupom ' . $coupon['code'] . ' aplicado: ' . Coupon::describe($coupon) . '.');
    }

    public function removeCoupon(): Response
    {
        Cart::removeCoupon();
        return $this->respond('Cupom removido.');
    }

    private function respond(?string $message, array $extra = []): Response
    {
        if ($this->request->wantsJson()) {
            $data = array_merge($this->cartData(), $extra);
            return $this->json([
                'ok' => true,
                'message' => $message,
                'count' => $data['totals']['count'],
                'html' => View::file('site/partials/cart-body', $data),
            ]);
        }
        if ($message) {
            flash('success', $message);
        }
        return $this->redirect('/carrinho');
    }

    private function cartData(): array
    {
        $lines = Cart::lines();
        return ['lines' => $lines, 'totals' => Cart::totals($lines)];
    }
}
