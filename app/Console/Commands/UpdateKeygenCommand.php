<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateKeygenCommand extends Command
{
    protected $signature = 'update:keygen';

    protected $description = 'Generează o pereche de chei Ed25519 pentru semnarea pachetelor de update (rulați pe mașina de dezvoltare).';

    public function handle(): int
    {
        $pair = sodium_crypto_sign_keypair();
        $this->info('Cheie PUBLICĂ (se pune pe server: UPDATE_PUBLIC_KEY în .env):');
        $this->line(base64_encode(sodium_crypto_sign_publickey($pair)));
        $this->newLine();
        $this->warn('Cheie SECRETĂ (NU se comite, NU se urcă pe server — se păstrează în siguranță pentru release:build):');
        $this->line(base64_encode(sodium_crypto_sign_secretkey($pair)));

        return self::SUCCESS;
    }
}
