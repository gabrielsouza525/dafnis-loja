<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Database;
use App\Core\Response;
use App\Core\ValidationException;
use App\Core\Validator;
use App\Models\Coupon;
use App\Services\Activity;

final class CouponController extends AdminController
{
    public function index(): Response
    {
        return $this->view('admin/coupons/index', [
            'title' => 'Cupons',
            'section' => 'cupons',
            'coupons' => Database::select('SELECT * FROM coupons ORDER BY is_active DESC, id DESC'),
        ]);
    }

    public function create(): Response
    {
        return $this->view('admin/coupons/form', ['title' => 'Novo cupom', 'section' => 'cupons', 'coupon' => null]);
    }

    public function edit(int $id): Response
    {
        $coupon = $this->findOr404(Database::first('SELECT * FROM coupons WHERE id = :id', ['id' => $id]), 'Cupom não encontrado.');
        return $this->view('admin/coupons/form', ['title' => 'Editar cupom', 'section' => 'cupons', 'coupon' => $coupon]);
    }

    public function store(): Response
    {
        $data = $this->validated(null);
        $id = Database::insert('coupons', $data);
        Activity::log('coupon.created', 'coupon', $id, $data['code']);
        return $this->success('Cupom criado.', '/admin/cupons');
    }

    public function update(int $id): Response
    {
        $this->findOr404(Database::first('SELECT id FROM coupons WHERE id = :id', ['id' => $id]), 'Cupom não encontrado.');
        $data = $this->validated($id);
        Database::update('coupons', $data, ['id' => $id]);
        Activity::log('coupon.updated', 'coupon', $id, $data['code']);
        return $this->success('Cupom salvo.', '/admin/cupons');
    }

    public function destroy(int $id): Response
    {
        $coupon = $this->findOr404(Database::first('SELECT * FROM coupons WHERE id = :id', ['id' => $id]), 'Cupom não encontrado.');
        if ((int) $coupon['uses'] > 0) {
            Database::update('coupons', ['is_active' => 0], ['id' => $id]);
            return $this->success('Este cupom já foi usado, então foi desativado em vez de excluído.', '/admin/cupons');
        }
        Database::delete('coupons', ['id' => $id]);
        return $this->success('Cupom excluído.', '/admin/cupons');
    }

    private function validated(?int $id): array
    {
        $input = $this->request->all();
        $input['code'] = Coupon::normalize((string) ($input['code'] ?? ''));
        $data = Validator::validate($input, [
            'code' => 'required|min:3|max:40',
            'description' => 'nullable|max:160',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|decimal|positive',
            'min_subtotal' => 'nullable|decimal|minval:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'max_uses' => 'nullable|integer',
            'is_active' => 'bool',
        ], ['code' => 'código', 'value' => 'valor', 'min_subtotal' => 'compra mínima', 'starts_at' => 'início', 'ends_at' => 'fim', 'max_uses' => 'limite de usos']);
        if ($data['type'] === 'percent' && $data['value'] > 100) {
            throw ValidationException::with('value', 'O desconto percentual vai até 100%.');
        }
        $data['starts_at'] = $data['starts_at'] ? $data['starts_at'] . ' 00:00:00' : null;
        $data['ends_at'] = $data['ends_at'] ? $data['ends_at'] . ' 23:59:59' : null;
        $taken = (int) Database::value('SELECT COUNT(*) FROM coupons WHERE code = :c' . ($id ? ' AND id <> :id' : ''), array_filter(['c' => $data['code'], 'id' => $id]));
        if ($taken) {
            throw ValidationException::with('code', 'Já existe um cupom com este código.');
        }
        return $data;
    }
}
