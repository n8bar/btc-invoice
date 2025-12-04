<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserWalletAccount>
 */
class UserWalletAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Wallet ' . fake()->unique()->word(),
            'network' => 'testnet',
            'bip84_xpub' => 'vpub' . fake()->regexify('[A-Za-z0-9]{10}'),
            'next_derivation_index' => 0,
            'active' => true,
        ];
    }
}
