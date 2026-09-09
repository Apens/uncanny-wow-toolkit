<?php

declare(strict_types=1);

namespace UncannyWoW\Tests;

use PHPUnit\Framework\TestCase;

final class SmokeTest extends TestCase
{
    public function testFrameworkIsOperational(): void
    {
        $value = (string) time();
        self::assertNotEmpty($value);
    }
}
