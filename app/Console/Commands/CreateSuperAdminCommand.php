<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSuperAdminCommand extends Command
{
    protected $signature = 'app:create-super-admin {--email=} {--first-name=} {--last-name=} {--language=ro} {--password=}';

    protected $description = 'Creează contul SUPER ADMIN (o singură dată, la instalarea inițială).';

    public function handle(): int
    {
        $email = $this->option('email') ?: $this->ask('E-mail');
        $first = $this->option('first-name') ?: $this->ask('Prenume');
        $last = $this->option('last-name') ?: $this->ask('Nume');
        $password = $this->option('password') ?: $this->secret('Parolă (min. 10 caractere)');

        $v = Validator::make(compact('email', 'first', 'last', 'password'), [
            'email' => ['required', 'email', 'unique:users,email'],
            'first' => ['required', 'max:100'],
            'last' => ['required', 'max:100'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'email' => $email, 'first_name' => $first, 'last_name' => $last, 'password' => $password,
            'preferred_language' => in_array($this->option('language'), ['ro', 'hu'], true) ? $this->option('language') : 'ro',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('super_admin');
        $this->info("Super Admin creat: {$user->email} ({$user->member_code})");

        return self::SUCCESS;
    }
}
