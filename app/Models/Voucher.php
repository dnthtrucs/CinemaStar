<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Voucher extends Model
{
    protected $fillable=['code','name','type','value','min_order','usage_limit','used_count','starts_at','ends_at','is_active'];
    protected function casts(): array { return ['starts_at'=>'datetime','ends_at'=>'datetime','is_active'=>'boolean']; }
    public function discountFor(int $subtotal): int { return $this->type === 'percent' ? min($subtotal, (int) round($subtotal*$this->value/100)) : min($subtotal, $this->value); }
    public function isUsableFor(int $subtotal): bool { return $this->is_active && $subtotal >= $this->min_order && (!$this->starts_at || $this->starts_at->isPast()) && (!$this->ends_at || $this->ends_at->isFuture()) && (!$this->usage_limit || $this->used_count < $this->usage_limit); }
}
