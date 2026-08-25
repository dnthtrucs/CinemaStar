<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class MovieReview extends Model { protected $fillable=['movie_id','user_id','rating','comment','status']; public function movie(){return $this->belongsTo(Movie::class);} public function user(){return $this->belongsTo(User::class);} }
