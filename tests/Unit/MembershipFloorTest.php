<?php

namespace Tests\Unit;

use App\Services\MembershipService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MembershipFloorTest extends TestCase
{
    public static function floorAndBoundaryPoints(): array
    {
        return [[-1, 'bronze', 1.0, 300], [0, 'bronze', 1.0, 300], [50, 'bronze', 1.0, 300],
            [299, 'bronze', 1.0, 300], [300, 'silver', 1.2, 1000],
            [1000, 'gold', 1.5, null], [9000, 'gold', 1.5, null]];
    }

    #[DataProvider('floorAndBoundaryPoints')]
    public function test_lowest_tier_is_a_floor_even_when_config_is_unsorted(int $points, string $key, float $multiplier, ?int $next): void
    {
        $tiers = [
            'gold' => ['label' => 'Vàng', 'minimum_points' => 1000, 'multiplier' => 1.5],
            'bronze' => ['label' => 'Đồng', 'minimum_points' => 50, 'multiplier' => 1.0],
            'silver' => ['label' => 'Bạc', 'minimum_points' => 300, 'multiplier' => 1.2],
        ];
        $tier = MembershipService::tierFor($points, $tiers);

        $this->assertSame($key, $tier['key']);
        $this->assertSame($multiplier, $tier['multiplier']);
        $this->assertSame($next, $tier['next_threshold']);
        $this->assertSame(['gold', 'bronze', 'silver'], array_keys($tiers));
    }

    public function test_single_tier_remains_the_floor_below_its_minimum(): void
    {
        $tier = MembershipService::tierFor(0, [
            'only' => ['label' => 'Thành viên', 'minimum_points' => 50, 'multiplier' => 1.0],
        ]);

        $this->assertSame(['label' => 'Thành viên', 'minimum_points' => 50, 'multiplier' => 1.0, 'key' => 'only', 'next_threshold' => null], $tier);
    }

    public function test_empty_configuration_fails_with_a_clear_error(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MembershipService::tierFor(0, []);
    }
}
