<?php

namespace App\Services;

use App\Models\Movie;
use App\Models\MovieReview;
use App\Models\User;
use Illuminate\Support\Collection;

class MovieRecommendationService
{
    public static function genres(string $value): array
    {
        return array_values(array_filter(array_map(fn ($g) => mb_strtolower(trim($g)), preg_split('/[,;|\/]+/u', $value))));
    }

    public function forUser(?User $user, int $limit = 4): Collection
    {
        $weights = [];
        $watched = [];
        $add = function (string $genre, int $score) use (&$weights) {
            foreach (self::genres($genre) as $g) {
                $weights[$g] = ($weights[$g] ?? 0) + $score;
            }
        };
        if ($user) {
            foreach ($user->favorite_genres ?? [] as $g) {
                $add($g, 6);
            }
            $history = $user->bookings()->where('payment_status', 'paid')->with('showtime.movie')->latest()->limit(50)->get();
            foreach ($history as $booking) {
                if ($movie = $booking->showtime?->movie) {
                    $add($movie->genre, 2);
                    $watched[] = $movie->id;
                }
            }
            foreach (MovieReview::where('user_id', $user->id)->where('status', 'approved')->with('movie')->latest()->limit(50)->get() as $review) {
                if ($review->movie) {
                    $add($review->movie->genre, (int) $review->rating >= 4 ? 3 : ((int) $review->rating <= 2 ? -3 : 0));
                }
            }
        }

        return Movie::whereIn('status', ['now_showing', 'upcoming'])->withExists(['showtimes as has_available_showtime' => fn ($q) => $q->available()])
            ->get()->map(function ($movie) use ($weights, $watched) {
                $matches = array_intersect(self::genres($movie->genre), array_keys(array_filter($weights, fn ($w) => $w > 0)));
                $movie->recommendation_score = array_sum(array_map(fn ($g) => $weights[$g] ?? 0, self::genres($movie->genre))) - (in_array($movie->id, $watched) ? 4 : 0);
                $movie->recommendation_reason = ($matches ? 'Phù hợp thể loại bạn quan tâm' : 'Phim để bạn khám phá').($movie->has_available_showtime ? ' · Có lịch chiếu' : ' · Lịch chiếu chưa công bố');

                return $movie;
            })->sort(fn ($a, $b) => ($b->has_available_showtime <=> $a->has_available_showtime) ?: ($b->recommendation_score <=> $a->recommendation_score) ?: ($b->is_featured <=> $a->is_featured) ?: ($b->id <=> $a->id))->take($limit)->values();
    }
}
