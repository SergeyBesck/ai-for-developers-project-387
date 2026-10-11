<?php

declare(strict_types=1);

use App\Health;
use PHPUnit\Framework\TestCase;

final class HealthTest extends TestCase
{
    public function testHealthIsOk(): void
    {
        $this->assertSame('ok', Health::check()['status']);
    }
}
