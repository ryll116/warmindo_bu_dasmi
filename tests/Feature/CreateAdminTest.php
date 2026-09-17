<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\Admin\AdminDatabaseTestCase;

class CreateAdminTest extends AdminDatabaseTestCase
{
    public function test_command_creates_admin_with_hashed_password(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Nama', 'Owner')->expectsQuestion('Email', 'owner@example.test')
            ->expectsQuestion('Password', 'safe-password-123')->expectsQuestion('Konfirmasi password', 'safe-password-123')
            ->expectsOutput('Admin berhasil dibuat.')->assertSuccessful();
        $user = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue(Hash::check('safe-password-123', $user->password));
    }

    public function test_existing_email_is_not_overwritten_or_promoted(): void
    {
        $user = User::factory()->create();
        $before = $user->fresh()->getAttributes();
        $this->artisan('app:create-admin')->expectsQuestion('Nama', 'Owner')->expectsQuestion('Email', $user->email)
            ->expectsOutput('Email sudah digunakan. Akun dan password tidak diubah.')->assertFailed();
        $this->assertSame($before, $user->fresh()->getAttributes());
    }

    public function test_invalid_password_confirmation_creates_nothing(): void
    {
        $this->artisan('app:create-admin')->expectsQuestion('Nama', 'Owner')->expectsQuestion('Email', 'owner@example.test')
            ->expectsQuestion('Password', 'safe-password-123')->expectsQuestion('Konfirmasi password', 'different')->assertFailed();
        $this->assertSame(0, User::count());
    }

    public function test_non_interactive_execution_is_rejected(): void
    {
        $this->artisan('app:create-admin', ['--no-interaction' => true])->assertFailed();
        $this->assertSame(0, User::count());
    }
}
