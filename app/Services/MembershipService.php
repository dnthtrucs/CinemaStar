<?php

namespace App\Services;

use InvalidArgumentException;

class MembershipService
{
    public static function tierFor(int $points, array $tiers): array
    {
        if ($tiers === []) {
            throw new InvalidArgumentException('At least one membership tier must be configured.');
        }

        uasort($tiers, fn (array $left, array $right) => $left['minimum_points'] <=> $right['minimum_points']);
        $firstKey = array_key_first($tiers);
        $current = $tiers[$firstKey] + ['key' => $firstKey, 'next_threshold' => null];
        foreach ($tiers as $key => $tier) {
            if ($key === $firstKey) {
                continue;
            }
            if ($points < $tier['minimum_points']) {
                $current['next_threshold'] = $tier['minimum_points'];
                break;
            }
            $current = $tier + ['key' => $key, 'next_threshold' => null];
        }

        return $current;
    }
}
