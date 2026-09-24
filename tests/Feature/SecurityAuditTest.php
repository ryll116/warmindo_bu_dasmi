<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Resto;
use App\Models\Table;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class SecurityAuditTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::drop('order_items');
        Schema::drop('orders');
        foreach (['2026_09_12_065707_create_orders_table.php', '2026_09_13_124020_create_order_items_table.php', '2026_09_16_024730_add_customer_name_to_orders_table.php', '2026_09_23_030903_add_resto_snapshot_to_order_items_table.php'] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
    }

    public function test_csrf_is_enforced_on_sensitive_routes_with_test_bypass_disabled(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superAdmin']));
        $this->app->instance('env', 'local');
        try {
            foreach ([['POST', '/login'], ['POST', '/logout'], ['POST', '/menu/token/checkout'], ['POST', '/menu/token/checkout/review'], ['POST', '/admin/products'], ['PUT', '/admin/products/1'], ['DELETE', '/admin/products/1'], ['POST', '/admin/users'], ['PUT', '/admin/users/1'], ['DELETE', '/admin/users/1'], ['PATCH', '/admin/orders/test/status'], ['PATCH', '/admin/orders/test/payment'], ['PATCH', '/admin/orders/test/items/1']] as [$method, $url]) {
                $this->call($method, $url)->assertStatus(419);
                $this->withHeaders(['Sec-Fetch-Site' => 'cross-site'])->call($method, $url)->assertStatus(419);
                $this->flushHeaders();
            }
        } finally {
            $this->app->instance('env', 'testing');
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_benign_sql_payload_is_data_and_does_not_bypass_filters_or_login(): void
    {
        $payload = "' OR '1'='1' --";
        $table = Table::factory()->create();
        Product::factory()->create();
        $this->postJson(route('login.store'), ['email' => $payload, 'password' => $payload])->assertUnprocessable();
        $this->assertGuest();
        $this->get(route('customer.menu', ['qr_token' => $table->qr_token, 'search' => $payload]))->assertOk()
            ->assertViewHas('products', fn ($products): bool => $products->isEmpty());
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('admin.products.index', ['search' => $payload]))->assertOk()
            ->assertViewHas('products', fn ($products): bool => $products->total() === 0);
        $this->get(route('admin.reports.sales', ['search' => $payload]))->assertOk()
            ->assertViewHas('summary', fn ($summary): bool => (int) $summary->transactions === 0);
        foreach (['admin.reports.sales', 'admin.reports.sales.export'] as $route) {
            $this->getJson(route($route, ['resto' => $payload]))->assertUnprocessable();
            $this->getJson(route($route, ['period' => 'custom', 'start' => $payload, 'end' => '2026-09-23']))->assertUnprocessable();
        }
        $this->assertDatabaseCount('products', 1);
    }

    public function test_checkout_tampering_xss_and_signed_object_access(): void
    {
        $marker = '<script>alert(1)</script>';
        $resto = Resto::factory()->create(['resto_name' => $marker]);
        $product = Product::factory()->create(['resto_id' => $resto->id, 'product_name' => $marker]);
        $table = Table::factory()->create();
        $otherTable = Table::factory()->create();
        $review = $this->post(route('customer.checkout.review', $table->qr_token), ['items' => [['product_id' => $product->id, 'quantity' => 1, 'price' => 1, 'subtotal' => 1]]])->assertRedirect();
        $token = basename($review->headers->get('Location'));
        $response = $this->post(route('customer.checkout.store', $table->qr_token), [
            'checkout_token' => $token, 'customer_name' => $marker, 'notes' => $marker, 'payment_type' => 'cash',
            'price' => 1, 'subtotal' => 1, 'total' => 1, 'table_id' => $otherTable->id,
            'resto_id' => 999, 'resto_name' => 'Forged', 'payment_status' => 'paid', 'payment_time' => '2000-01-01', 'order_status' => 'completed',
        ])->assertRedirect();
        $this->assertDatabaseHas('orders', ['id' => $token, 'total' => '15000.00', 'table_id' => $table->id, 'payment_status' => 'unpaid', 'payment_time' => null, 'order_status' => 'pending']);
        $this->assertDatabaseHas('order_items', ['order_id' => $token, 'resto_id' => $resto->id, 'resto_name' => $marker, 'price' => '15000.00']);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($marker)->assertDontSee($marker, false);
        $this->get(route('customer.checkout.success', ['qr_token' => $table->qr_token, 'order' => $token]))->assertForbidden();
        $wrongTableUrl = URL::temporarySignedRoute('customer.checkout.success', now()->addMinute(), ['qr_token' => $otherTable->qr_token, 'order' => $token]);
        $this->get($wrongTableUrl)->assertNotFound();
        $expired = URL::temporarySignedRoute('customer.checkout.success', now()->subMinute(), ['qr_token' => $table->qr_token, 'order' => $token]);
        $this->get($expired)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create());
        foreach ([route('admin.products.index'), route('admin.orders.index'), route('admin.orders.show', $token), route('admin.orders.receipt', $token), route('customer.menu', ['qr_token' => $table->qr_token, 'search' => $marker])] as $url) {
            $this->get($url)->assertOk()->assertDontSee($marker, false);
        }
        Order::findOrFail($token)->forceFill(['payment_status' => 'paid', 'payment_time' => now()])->save();
        $this->get(route('admin.reports.sales'))->assertOk()->assertSee($marker)->assertDontSee($marker, false);
    }

    public function test_direct_privilege_escalation_is_rejected_for_admin_and_kasir(): void
    {
        $super = User::factory()->create(['role' => 'superAdmin']);
        foreach (['admin', 'kasir'] as $role) {
            $actor = User::factory()->create(['role' => $role]);
            $this->actingAs($actor);
            $payload = ['name' => 'Escalated', 'email' => $actor->email, 'role' => 'superAdmin', 'password' => 'test-password-123', 'password_confirmation' => 'test-password-123'];
            $this->postJson(route('admin.users.store'), $payload)->assertForbidden();
            $this->putJson(route('admin.users.update', $actor), $payload)->assertForbidden();
            $this->putJson(route('admin.users.update', $super), $payload)->assertForbidden();
            $this->deleteJson(route('admin.users.destroy', $super))->assertForbidden();
            $this->assertSame($role, $actor->fresh()->role);
        }
        $this->assertSame(1, User::where('role', 'superAdmin')->count());
    }

    public function test_audit_reproduces_old_authenticated_session_surviving_password_reset(): void
    {
        $victim = User::factory()->admin()->create();
        $super = User::factory()->create(['role' => 'superAdmin']);
        $this->post(route('login.store'), ['email' => $victim->email, 'password' => 'password'])->assertRedirect();
        $sessionKey = Auth::guard('web')->getName();
        $this->assertSame($victim->id, session($sessionKey));
        $this->actingAs($super)->put(route('admin.users.update', $victim), [
            'name' => $victim->name, 'email' => $victim->email, 'role' => 'admin', 'password' => 'replacement-password-123', 'password_confirmation' => 'replacement-password-123',
        ])->assertSessionHas('success');
        $this->assertFalse(Hash::check('password', $victim->fresh()->password));
        Auth::forgetGuards();
        $this->assertSame($victim->id, session($sessionKey));
        $this->get(route('admin.products.index'))->assertOk();
        $this->get(route('admin.reports.sales.export'))->assertOk();
        $this->assertAuthenticatedAs($victim);
    }
}
