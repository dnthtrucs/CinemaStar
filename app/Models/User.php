<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\MembershipService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone', 'favorite_genres',
        'role', 'cinema_id',
        'is_active',
        'loyalty_points',
        'lifetime_points',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'lifetime_points' => 'integer',
            'favorite_genres' => 'array',
        ];
    }

    public function cinema()
    {
        return $this->belongsTo(Cinema::class);
    }

    public function canOperateCinema(int $cinemaId): bool
    {
        return $this->isAdmin() || ($this->role === 'staff' && $this->cinema_id !== null && (int) $this->cinema_id === $cinemaId);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function lifetimePoints(): int
    {
        return (int) $this->lifetime_points;
    }

    public function membershipTier(): array
    {
        return MembershipService::tierFor($this->lifetimePoints(), config('cinema.membership.tiers'));
    }

    public function earnMultiplier(): float
    {
        return (float) $this->membershipTier()['multiplier'];
    }

    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'staff'], true);
    }
}
