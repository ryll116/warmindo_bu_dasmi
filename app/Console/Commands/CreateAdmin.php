<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('app:create-admin')]
#[Description('Membuat akun Admin secara interaktif tanpa menimpa akun yang sudah ada')]
class CreateAdmin extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Jalankan command secara interaktif untuk memasukkan identitas dan password.');

            return self::FAILURE;
        }

        $data = ['name' => $this->ask('Nama'), 'email' => $this->ask('Email')];
        $identity = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ], ['email.unique' => 'Email sudah digunakan. Akun dan password tidak diubah.']);
        if ($identity->fails()) {
            $this->error($identity->errors()->first());

            return self::FAILURE;
        }

        $data['password'] = $this->secret('Password');
        $data['password_confirmation'] = $this->secret('Konfirmasi password');
        $password = Validator::make($data, ['password' => ['required', 'string', 'max:255', 'confirmed', Password::min(8)]]);
        if ($password->fails()) {
            $this->error($password->errors()->first());

            return self::FAILURE;
        }

        User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => 'admin']);
        $this->info('Admin berhasil dibuat.');

        return self::SUCCESS;
    }
}
