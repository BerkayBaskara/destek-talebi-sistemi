<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Csrf;
use App\Core\Session\ArraySession;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    public function test_token_is_stable_within_session_and_validates(): void
    {
        $csrf = new Csrf(new ArraySession());

        $token = $csrf->token();

        self::assertSame(64, strlen($token));
        self::assertSame($token, $csrf->token());
        self::assertTrue($csrf->isValid($token));
    }

    public function test_rejects_null_and_wrong_token(): void
    {
        $csrf = new Csrf(new ArraySession());
        $csrf->token();

        self::assertFalse($csrf->isValid(null));
        self::assertFalse($csrf->isValid(''));
        self::assertFalse($csrf->isValid(str_repeat('a', 64)));
    }

    public function test_new_session_gets_different_token(): void
    {
        self::assertNotSame((new Csrf(new ArraySession()))->token(), (new Csrf(new ArraySession()))->token());
    }
}
