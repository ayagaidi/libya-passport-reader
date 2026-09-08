<?php

namespace Tests\Unit;

use App\Services\Passport\MrzCheckDigitService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MrzCheckDigitServiceTest extends TestCase
{
    #[Test]
    public function it_calculates_icao_check_digits(): void
    {
        $service = new MrzCheckDigitService;

        $this->assertSame(3, $service->calculate('L898902C<'));
        $this->assertTrue($service->matches('L898902C<', '3'));
    }
}
