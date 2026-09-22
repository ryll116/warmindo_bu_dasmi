<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class UserManagementTest extends AdminDatabaseTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'superAdmin']);
        $this->actingAs($this->admin);
    }

    public function test_super_admin_creates_all_three_roles_with_hashed_passwords(): void
    {
        foreach (['admin', 'kasir', 'superAdmin'] as $role) {
            $payload = $this->payload(['email' => $role.'@example.test', 'role' => $role]);
            $this->post(route('admin.users.store'), $payload)->assertRedirect(route('admin.users.index'));
            $user = User::where('email', $payload['email'])->firstOrFail();
            $this->assertSame($role, $user->role);
            $this->assertTrue(Hash::check($payload['password'], $user->password));
            $this->assertNotSame($payload['password'], $user->password);
        }
    }

    public function test_invalid_user_fields_are_rejected_without_creating_accounts(): void
    {
        foreach ([['name' => ''], ['email' => 'bad'], ['email' => $this->admin->email], ['role' => 'owner'], ['password' => 'short', 'password_confirmation' => 'short'], ['password_confirmation' => 'different']] as $invalid) {
            $this->postJson(route('admin.users.store'), $this->payload($invalid))->assertUnprocessable();
        }
        $this->assertSame(1, User::count());
    }

    public function test_edit_without_password_preserves_hash_and_password_update_hashes_new_value(): void
    {
        $user = User::factory()->create();
        $oldPassword = $user->password;
        $this->get(route('admin.users.edit', $user))->assertOk()->assertDontSee($oldPassword);
        $this->put(route('admin.users.update', $user), $this->payload(['email' => $user->email, 'password' => '', 'password_confirmation' => '']))->assertRedirect(route('admin.users.index'));
        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->put(route('admin.users.update', $user), $this->payload(['email' => $user->email]))->assertRedirect(route('admin.users.index'));
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertNotSame($oldPassword, $user->fresh()->password);
        $this->putJson(route('admin.users.update', $user), $this->payload(['email' => $this->admin->email]))->assertUnprocessable();
    }

    public function test_last_admin_cannot_be_deleted_or_demoted(): void
    {
        $lastAdmin = User::factory()->admin()->create();
        $this->delete(route('admin.users.destroy', $lastAdmin))->assertSessionHasErrors(['user' => 'Admin terakhir tidak dapat dihapus.']);
        $this->put(route('admin.users.update', $lastAdmin), $this->payload(['email' => $lastAdmin->email]))->assertSessionHasErrors(['role' => 'Admin terakhir tidak dapat diubah menjadi Kasir.']);
        $this->assertSame(1, User::where('role', 'admin')->count());
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_self_delete_and_self_demotion_are_blocked_even_with_other_admins(): void
    {
        User::factory()->admin()->create();
        $this->delete(route('admin.users.destroy', $this->admin))->assertSessionHasErrors(['user' => 'Akun yang sedang digunakan tidak dapat dihapus.']);
        $this->put(route('admin.users.update', $this->admin), $this->payload(['email' => $this->admin->email]))->assertSessionHasErrors('role');
        $this->assertSame('superAdmin', $this->admin->fresh()->role);
    }

    public function test_admin_can_demote_or_delete_other_accounts_while_an_admin_remains(): void
    {
        User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $this->put(route('admin.users.update', $other), $this->payload(['email' => $other->email]))->assertRedirect(route('admin.users.index'));
        $this->assertTrue($other->fresh()->isKasir());
        $this->delete(route('admin.users.destroy', $other))->assertRedirect(route('admin.users.index'));
        $another = User::factory()->admin()->create();
        $this->delete(route('admin.users.destroy', $another))->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $other->id]);
        $this->assertDatabaseMissing('users', ['id' => $another->id]);
        $this->assertSame(1, User::where('role', 'admin')->count());
    }

    public function test_self_profile_and_password_update_keeps_session_usable(): void
    {
        Auth::forgetGuards();
        $this->post(route('login.store'), ['email' => $this->admin->email, 'password' => 'password'])->assertRedirect(route('admin.orders.index'));
        $this->put(route('admin.users.update', $this->admin), $this->payload(['role' => 'superAdmin']))->assertRedirect(route('admin.users.index'));
        Auth::forgetGuards();
        $this->get(route('admin.users.index'))->assertOk()->assertSee('Test User');
        $this->assertTrue(Hash::check('new-password-123', $this->admin->fresh()->password));
    }

    public function test_user_role_migration_defaults_existing_and_new_users_to_kasir(): void
    {
        $migration = require database_path('migrations/2026_09_17_023811_add_role_to_users_table.php');
        $migration->down();
        $migration->up();
        $this->assertTrue($this->admin->fresh()->isKasir());
        $user = User::create(['name' => 'Default', 'email' => 'default@example.test', 'password' => 'new-password-123']);
        $this->assertTrue($user->fresh()->isKasir());
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge(['name' => 'Test User', 'email' => 'test@example.test', 'role' => 'kasir', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'], $overrides);
    }
}
