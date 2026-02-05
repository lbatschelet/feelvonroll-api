<?php
/**
 * Tests for JSON error handling helpers.
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/errors.php';
require_once __DIR__ . '/../lib/json.php';

final class JsonErrorTest extends TestCase
{
    /**
     * @return void
     */
    public function testJsonErrorThrowsApiError(): void
    {
        $this->expectException(ApiError::class);
        $this->expectExceptionMessage('Boom');
        json_error('Boom', 418);
    }
}
