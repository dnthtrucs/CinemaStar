<?php

namespace App\Services;

class MembershipService
{
    public static function tierFor(int $points, array $tiers): array
    {
        $current = null;
        foreach ($tiers as $key => $tier) {
            if ($points < $tier['minimum_points']) {
                $current['next_threshold'] = $tier['minimum_points'];
                break;
            }
            $current = $tier + ['key' => $key, 'next_threshold' => null];
        }

        return $current;
    }
}
