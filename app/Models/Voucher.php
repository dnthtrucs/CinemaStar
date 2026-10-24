<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = ['code', 'name', 'type', 'value', 'min_order', 'usage_limit', 'used_count', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean'];
    }

    public function discountFor(int $subtotal): int
    {
        return $this->type === 'percent' ? min($subtotal, (int) round($subtotal * $this->value / 100)) : min($subtotal, $this->value);
    }

    public function usages()
    {
        return $this->hasMany(VoucherUsage::class);
    }

    public function isUsableFor(int $subtotal, ?User $user = null): bool
    {
        if (! $this->is_active || $subtotal < $this->min_order
            || ($this->starts_at && $this->starts_at->isFuture())
            || ($this->ends_at && ! $this->ends_at->isFuture())) {
            return false;
        }

        // Đọc hiện trạng sau khóa voucher, kể cả khi transaction đã có snapshot cũ.
        $pending = Booking::query()->where('voucher_id', $this->id)
            ->where('status', 'pending')->where('payment_status', 'unpaid')
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->lockForUpdate()->get(['id', 'user_id']);

        if ($user && ($this->usages()->where('user_id', $user->id)->lockForUpdate()->first()
            || $pending->contains('user_id', $user->id))) {
            return false;
        }

        return ! $this->usage_limit || $this->used_count + $pending->count() < $this->usage_limit;
    }
}
