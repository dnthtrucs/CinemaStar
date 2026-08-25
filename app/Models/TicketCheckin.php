<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class TicketCheckin extends Model { protected $fillable=['ticket_id','staff_id','checked_in_at']; protected function casts(): array{return ['checked_in_at'=>'datetime'];} }
