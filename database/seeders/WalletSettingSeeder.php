<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class WalletSettingSeeder extends Seeder
{
    public function run(): void
    {
        $testXpub = 'vpub5YUkAHxWxGXwCFzJGmpRBSnn9x7Y8NEqX5vLh6Ek2n5UHkRhGTt2f8RJ82jkB1nxFJKH6ewWcQdzWtrpGgbjyyCRi5NN1ysZf7zrjq6w4gf';

        User::with('walletSetting')->get()->each(function (User $user) use ($testXpub) {
            $user->walletSetting()->create([
                'network' => 'testnet',
                'bip84_xpub' => $testXpub,
                'next_derivation_index' => 0,
                'onboarded_at' => now(),
            ]);
        });
    }
}
