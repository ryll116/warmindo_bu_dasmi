<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Table;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class AuthenticationTest extends AdminDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_name')->nullable();
            $table->string('order_status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->string('payment_type')->nullable();
            $table->dateTime('payment_time')->nullable();
            $table->decimal('total', 20, 2)->default(0);
            $table->timestamps();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->uuid('order_id');
            $table->string('product_name');
            $table->integer('qty');
            $table->decimal('price', 12, 2);
            $table->decimal('subtotal', 20, 2);
        });
    }

    public function test_guests_can_view_menu_and_login_but_all_admin_routes_require_login(): void
    {
        $table = Table::factory()->create();
        $this->get(route('customer.menu', $table->qr_token))->assertOk();
        $this->get(route('login'))->assertOk();
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'admin')) {
                $uri = preg_replace('/\{[^}]+\}/', '1', $route->uri());
                $this->call($route->methods()[0], '/'.$uri)->assertRedirect(route('login'));
            }
        }
        $this->getJson(route('admin.orders.index'))->assertUnauthorized();
        $this->assertGuest();
        $this->assertFalse(Route::has('register'));
    }

    public function test_valid_login_regenerates_session_and_redirects_each_role(): void
    {
        foreach (['admin' => 'admin.products.index', 'kasir' => 'admin.orders.index'] as $role => $destination) {
            $user = User::factory()->create(['role' => $role]);
            $this->withSession(['url.intended' => route('admin.users.index')]);
            $oldId = session()->getId();
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route($destination));
            $this->assertAuthenticatedAs($user);
            $this->assertNotSame($oldId, session()->getId());
            $this->get(route('login'))->assertRedirect(route($destination));
            $this->get(route('admin.home'))->assertRedirect(route($destination));
            $this->post(route('logout'))->assertRedirect(route('login'));
        }
    }

    public function test_invalid_login_is_generic_and_rate_limited_then_recovers(): void
    {
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors(['email' => 'Email atau password tidak sesuai.']);
        }
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('admin.orders.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_validates_email_and_password_and_never_flashes_password(): void
    {
        $this->post(route('login.store'), ['email' => 'invalid', 'password' => ''])->assertSessionHasErrors(['email', 'password']);
        $this->post(route('login.store'), ['email' => 'missing@example.test', 'password' => 'secret-value'])->assertSessionHasErrors('email');
        $this->assertNull(session()->getOldInput('password'));
        $this->assertGuest();
    }

    public function test_logout_clears_session_and_regenerates_csrf_token(): void
    {
        $this->actingAs(User::factory()->admin()->create())->withSession(['private_value' => 'secret', '_token' => 'old-token']);
        $oldId = session()->getId();
        $this->get('/logout')->assertStatus(405);
        $this->post(route('logout'))->assertRedirect(route('login'))->assertSessionMissing('private_value');
        $this->assertGuest();
        $this->assertNotSame($oldId, session()->getId());
        $this->assertNotSame('old-token', session()->token());
        $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_access_every_management_area_and_cashier(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (['products.index', 'categories.index', 'tables.index', 'reports.sales', 'users.index', 'users.create', 'orders.index', 'dashboard'] as $name) {
            $this->get(route('admin.'.$name))->assertOk();
        }
        $this->get(route('admin.users.index'))->assertSee('User Management')->assertSee('Logout');
    }

    public function test_kasir_is_forbidden_from_all_admin_only_routes_including_writes(): void
    {
        $user = User::factory()->admin()->create();
        $table = Table::factory()->create();
        $category = Category::factory()->create();
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create());
        foreach (Route::getRoutes() as $route) {
            if (in_array('role:admin', $route->gatherMiddleware(), true)) {
                $uri = str_replace(['{user}', '{table}', '{category}', '{product}'], [$user->id, $table->id, $category->id, $product->id], $route->uri());
                $this->call($route->methods()[0], '/'.$uri)->assertForbidden();
            }
        }
        $this->get(route('admin.orders.index'))->assertOk()->assertDontSee('User Management')->assertDontSee('Laporan Penjualan')->assertDontSee('Manajemen Produk');
        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'admin']);
    }

    public function test_unknown_role_has_no_admin_access(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'other']));
        $this->get(route('admin.orders.index'))->assertForbidden();
    }

    public function test_demo_watermark_on_login_and_user_management_follows_configuration(): void
    {
        $user = User::factory()->admin()->create();
        foreach ([true, false] as $enabled) {
            config(['app.demo_mode' => $enabled]);
            Auth::forgetGuards();
            $login = $this->get(route('login'))->assertOk();
            $users = $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
            foreach ([$login, $users] as $response) {
                if ($enabled) {
                    $response->assertSee('DEMO VERSION');
                } else {
                    $response->assertDontSee('DEMO VERSION');
                }
            }
        }
    }
}
