<?php
declare(strict_types=1);

use App\Controllers\Account\AccountController;
use App\Controllers\Admin;
use App\Controllers\Auth\AuthController;
use App\Controllers\Auth\PasswordController;
use App\Controllers\Site\CartController;
use App\Controllers\Site\CatalogController;
use App\Controllers\Site\CheckoutController;
use App\Controllers\Site\CourseController;
use App\Controllers\Site\HomeController;
use App\Controllers\Site\InstallController;
use App\Controllers\Site\OrderController;
use App\Controllers\Site\PageController;
use App\Controllers\Site\WebhookController;
use App\Core\Router;

return static function (Router $r): void {
    // Loja ------------------------------------------------------------------
    $r->get('/', [HomeController::class, 'index']);
    $r->get('/cursos', [CatalogController::class, 'index']);
    $r->get('/cursos/{slug:[a-z0-9-]+}', [CourseController::class, 'show']);
    $r->get('/curso/{slug:[a-z0-9-]+}', [CourseController::class, 'legacy']);
    $r->get('/categorias/{slug:[a-z0-9-]+}', [CatalogController::class, 'category']);
    $r->get('/nr/{nr:\d{1,2}}', [CatalogController::class, 'nr']);
    $r->get('/nrs', [CatalogController::class, 'nrs']);

    $r->get('/carrinho', [CartController::class, 'show']);
    $r->post('/carrinho/adicionar', [CartController::class, 'add']);
    $r->post('/carrinho/atualizar', [CartController::class, 'update']);
    $r->post('/carrinho/remover', [CartController::class, 'remove']);
    $r->post('/carrinho/cupom', [CartController::class, 'coupon']);
    $r->post('/carrinho/cupom/remover', [CartController::class, 'removeCoupon']);

    $r->get('/checkout', [CheckoutController::class, 'show'], ['auth']);
    $r->post('/checkout', [CheckoutController::class, 'place'], ['auth']);
    $r->get('/pedido/{number:DF\d{6}}', [OrderController::class, 'show'], ['auth']);
    $r->get('/pedido/{number:DF\d{6}}/pagar', [OrderController::class, 'pay'], ['auth']);
    $r->get('/pedido/{number:DF\d{6}}/retorno', [OrderController::class, 'returned'], ['auth']);
    $r->post('/webhooks/mercadopago', [WebhookController::class, 'mercadoPago']);

    $r->get('/contato', [PageController::class, 'contact']);
    $r->post('/contato', [PageController::class, 'sendContact']);
    $r->get('/termos-de-uso', [PageController::class, 'terms']);
    $r->get('/politica-de-privacidade', [PageController::class, 'privacy']);
    $r->get('/sitemap.xml', [PageController::class, 'sitemap']);
    $r->get('/robots.txt', [PageController::class, 'robots']);

    $r->get('/instalar', [InstallController::class, 'form']);
    $r->post('/instalar', [InstallController::class, 'install']);

    // Conta -----------------------------------------------------------------
    $r->get('/login', [AuthController::class, 'loginForm'], ['guest']);
    $r->post('/login', [AuthController::class, 'login'], ['guest']);
    $r->get('/entrar', [AuthController::class, 'legacyLogin']);
    $r->get('/cadastro', [AuthController::class, 'registerForm'], ['guest']);
    $r->post('/cadastro', [AuthController::class, 'register'], ['guest']);
    $r->post('/sair', [AuthController::class, 'logout']);
    $r->get('/esqueci-senha', [PasswordController::class, 'forgotForm'], ['guest']);
    $r->post('/esqueci-senha', [PasswordController::class, 'sendLink'], ['guest']);
    $r->get('/redefinir-senha/{token:[A-Za-z0-9_-]{43}}', [PasswordController::class, 'resetForm']);
    $r->post('/redefinir-senha', [PasswordController::class, 'reset']);

    $r->group('/minha-conta', ['auth'], static function (Router $r): void {
        $r->get('', [AccountController::class, 'dashboard']);
        $r->get('/cursos', [AccountController::class, 'courses']);
        $r->get('/certificados', [AccountController::class, 'certificates']);
        $r->get('/certificados/{id:\d+}/baixar', [AccountController::class, 'downloadCertificate']);
        $r->get('/pedidos', [AccountController::class, 'orders']);
        $r->get('/pedidos/{number:DF\d{6}}', [AccountController::class, 'order']);
        $r->post('/vagas/{id:\d+}', [AccountController::class, 'assignSeat']);
        $r->get('/dados', [AccountController::class, 'profile']);
        $r->post('/dados', [AccountController::class, 'updateProfile']);
        $r->post('/senha', [AccountController::class, 'updatePassword']);
    });

    // Painel da equipe ------------------------------------------------------
    $r->group('/admin', ['auth', 'admin'], static function (Router $r): void {
        $r->get('', [Admin\DashboardController::class, 'index']);

        $r->get('/cursos', [Admin\CourseController::class, 'index']);
        $r->get('/cursos/novo', [Admin\CourseController::class, 'create']);
        $r->post('/cursos', [Admin\CourseController::class, 'store']);
        $r->get('/cursos/{id:\d+}/editar', [Admin\CourseController::class, 'edit']);
        $r->post('/cursos/{id:\d+}', [Admin\CourseController::class, 'update']);
        $r->post('/cursos/{id:\d+}/alternar', [Admin\CourseController::class, 'toggle']);
        $r->post('/cursos/{id:\d+}/excluir', [Admin\CourseController::class, 'destroy']);

        $r->get('/categorias', [Admin\CategoryController::class, 'index']);
        $r->post('/categorias', [Admin\CategoryController::class, 'store']);
        $r->post('/categorias/{id:\d+}', [Admin\CategoryController::class, 'update']);
        $r->post('/categorias/{id:\d+}/excluir', [Admin\CategoryController::class, 'destroy']);

        $r->get('/pedidos', [Admin\OrderController::class, 'index']);
        $r->get('/pedidos/{id:\d+}', [Admin\OrderController::class, 'show']);
        $r->post('/pedidos/{id:\d+}/confirmar-pagamento', [Admin\OrderController::class, 'markPaid']);
        $r->post('/pedidos/{id:\d+}/cancelar', [Admin\OrderController::class, 'cancel']);
        $r->post('/pedidos/{id:\d+}/sincronizar', [Admin\OrderController::class, 'sync']);
        $r->post('/pedidos/{id:\d+}/observacoes', [Admin\OrderController::class, 'notes']);

        $r->get('/matriculas', [Admin\EnrollmentController::class, 'index']);
        $r->get('/matriculas/{id:\d+}', [Admin\EnrollmentController::class, 'show']);
        $r->post('/matriculas/{id:\d+}', [Admin\EnrollmentController::class, 'update']);
        $r->post('/matriculas/{id:\d+}/certificado', [Admin\EnrollmentController::class, 'certificate']);
        $r->get('/matriculas/{id:\d+}/certificado', [Admin\EnrollmentController::class, 'downloadCertificate']);

        $r->get('/usuarios', [Admin\UserController::class, 'index']);
        $r->get('/usuarios/{id:\d+}', [Admin\UserController::class, 'show']);
        $r->post('/usuarios/{id:\d+}', [Admin\UserController::class, 'update']);

        $r->get('/cupons', [Admin\CouponController::class, 'index']);
        $r->get('/cupons/novo', [Admin\CouponController::class, 'create']);
        $r->post('/cupons', [Admin\CouponController::class, 'store']);
        $r->get('/cupons/{id:\d+}/editar', [Admin\CouponController::class, 'edit']);
        $r->post('/cupons/{id:\d+}', [Admin\CouponController::class, 'update']);
        $r->post('/cupons/{id:\d+}/excluir', [Admin\CouponController::class, 'destroy']);

        $r->get('/contatos', [Admin\ContactController::class, 'index']);
        $r->post('/contatos/{id:\d+}', [Admin\ContactController::class, 'toggle']);

        $r->get('/configuracoes', [Admin\SettingsController::class, 'edit']);
        $r->post('/configuracoes', [Admin\SettingsController::class, 'update']);
    });
};
