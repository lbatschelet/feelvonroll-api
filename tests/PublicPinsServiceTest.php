<?php
/**
 * Tests for public pins service helpers.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../services/public_pins_service.php';

final class PublicPinsServiceTest extends TestCase
{
    /**
     * @return void
     */
    public function testNormalizePercentClampsAndRounds(): void
    {
        $this->assertSame(0.0, normalize_percent(-10));
        $this->assertSame(100.0, normalize_percent(120));
        $this->assertSame(55.55, normalize_percent(55.549));
        $this->assertNull(normalize_percent(''));
        $this->assertNull(normalize_percent('abc'));
    }
}
