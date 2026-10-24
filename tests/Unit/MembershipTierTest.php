<?php

namespace Tests\Unit;

use App\Services\MembershipService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MembershipTierTest extends TestCase
{
    public static function thresholds(): array
    {
        return [[0, 'bronze', 1.0, 300], [299, 'bronze', 1.0, 300], [300, 'silver', 1.2, 1000],
            [999, 'silver', 1.2, 1000], [1000, 'gold', 1.5, null], [10000, 'gold', 1.5, null]];
    }

    #[DataProvider('thresholds')]
    public function test_tier_boundaries(int $points, string $key, float $multiplier, ?int $next): void
    {
        $tier = MembershipService::tierFor($points, [
            'bronze' => ['label' => 'Đồng', 'minimum_points' => 0, 'multiplier' => 1.0],
            'silver' => ['label' => 'Bạc', 'minimum_points' => 300, 'multiplier' => 1.2],
            'gold' => ['label' => 'Vàng', 'minimum_points' => 1000, 'multiplier' => 1.5],
        ]);
        $this->assertSame($key, $tier['key']);
        $this->assertSame($multiplier, $tier['multiplier']);
        $this->assertSame($next, $tier['next_threshold']);
    }
}
