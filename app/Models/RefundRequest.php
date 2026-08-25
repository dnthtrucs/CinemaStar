<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class RefundRequest extends Model { protected $fillable=['booking_id','requested_by','handled_by','status','reason','admin_note','amount','handled_at']; protected function casts(): array{return ['handled_at'=>'datetime'];} public function booking(){return $this->belongsTo(Booking::class);} public function requester(){return $this->belongsTo(User::class,'requested_by');} }
