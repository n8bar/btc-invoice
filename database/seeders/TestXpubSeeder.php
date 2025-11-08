<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestXpubSeeder extends Seeder
{
    public function run(): void
    {
        $testXpub = 'tpubDCebkncrKQyknyD2vUPDtF3WN62cQUMqj5Md3roBSosCf1KePmyZshW3sNhBrKmNsuB9SSxxcq2bat68jkyajPcThA1jJqHgfByb8rNz7tV';

        User::all()->each(function (User $user) use ($testXpub) {
            $user->walletSetting()->updateOrCreate([], [
                'network' => 'testnet',
                'bip84_xpub' => $testXpub,
                'next_derivation_index' => 0,
                'onboarded_at' => now(),
            ]);
        });
    }
}
