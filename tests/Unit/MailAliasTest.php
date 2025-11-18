<?php

namespace Tests\Unit;

use App\Services\MailAlias;
use PHPUnit\Framework\TestCase;

class MailAliasTest extends TestCase
{
    public function test_converts_address_when_enabled(): void
    {
        $alias = new MailAlias('cryptozing.app', true);

        $this->assertSame(
            'n8barlow.gmail.com@cryptozing.app',
            $alias->convert('n8barlow@gmail.com')
        );
    }

    public function test_returns_original_when_disabled(): void
    {
        $alias = new MailAlias('cryptozing.app', false);

        $this->assertSame('client@example.com', $alias->convert('client@example.com'));
    }

    public function test_trims_whitespace_and_normalizes_domain(): void
    {
        $alias = new MailAlias('cryptozing.app', true);

        $this->assertSame(
            'Client+vip.example.co@cryptozing.app',
            $alias->convert('  Client+vip@Example.Co ')
        );
    }

    public function test_returns_original_when_not_a_real_email(): void
    {
        $alias = new MailAlias('cryptozing.app', true);

        $this->assertSame('invalid-address', $alias->convert('invalid-address'));
    }
}
