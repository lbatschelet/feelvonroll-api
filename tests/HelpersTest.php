<?php

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testNormalizePinRowWithReasons(): void
    {
        $row = [
            'id' => '10',
            'floor_index' => '2',
            'position_x' => '1.25',
            'position_y' => '2.5',
            'position_z' => '3.75',
            'wellbeing' => '4',
            'approved' => '1',
            'reason_keys' => 'noise,light',
            'group_key' => 'staff',
        ];

        $result = normalize_pin_row($row);

        $this->assertSame(10, $result['id']);
        $this->assertSame(2, $result['floor_index']);
        $this->assertSame(1.25, $result['position_x']);
        $this->assertSame(2.5, $result['position_y']);
        $this->assertSame(3.75, $result['position_z']);
        $this->assertSame(4, $result['wellbeing']);
        $this->assertSame(1, $result['approved']);
        $this->assertSame(['noise', 'light'], $result['reasons']);
        $this->assertSame('staff', $result['group_key']);
        $this->assertArrayNotHasKey('reason_keys', $result);
    }

    public function testNormalizePinRowWithoutReasons(): void
    {
        $row = [
            'id' => 5,
            'floor_index' => 0,
            'position_x' => 0,
            'position_y' => 0,
            'position_z' => 0,
            'wellbeing' => 1,
            'approved' => 0,
            'reason_keys' => null,
            'group_key' => null,
        ];

        $result = normalize_pin_row($row);

        $this->assertSame([], $result['reasons']);
        $this->assertNull($result['group_key']);
    }
}
