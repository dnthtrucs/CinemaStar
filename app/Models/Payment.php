<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /** Tiền đã đến nhưng đơn không thể xác nhận; admin phải hoàn tiền thủ công. */
    public const MANUAL_REFUND_REASONS = [
        'PAID_AFTER_EXPIRY' => 'Đơn đã hết hạn',
        'SHOWTIME_CANCELLED' => 'Suất chiếu đã hủy',
        'VOUCHER_UNAVAILABLE' => 'Voucher hết lượt',
        'ALREADY_PAID' => 'Đơn đã thanh toán bằng giao dịch khác',
        'ORDER_CHANGED' => 'Số tiền đơn đã thay đổi',
        'DUPLICATE_TRANSFER' => 'Chuyển khoản trùng QR',
    ];

    public static function manualRefundCodes(): array
    {
        return array_keys(self::MANUAL_REFUND_REASONS);
    }

    public function needsManualRefund(): bool
    {
        return array_key_exists((string) $this->response_code, self::MANUAL_REFUND_REASONS);
    }

    public function scopeAwaitingManualRefund($query)
    {
        return $query->whereIn('response_code', self::manualRefundCodes())->whereNull('refund_resolved_at');
    }

    public function refundResolver()
    {
        return $this->belongsTo(User::class, 'refund_resolved_by');
    }

    protected $fillable = [
        'booking_id', 'provider', 'request_id', 'transaction_id', 'amount',
        'status', 'response_code', 'payload', 'paid_at',
        'refund_resolved_at', 'refund_resolved_by', 'refund_resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'payload' => 'array',
            'paid_at' => 'datetime',
            'refund_resolved_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
