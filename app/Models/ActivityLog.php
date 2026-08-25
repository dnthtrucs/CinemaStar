<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ActivityLog extends Model { protected $fillable=['user_id','action','subject_type','subject_id','description','properties','ip_address']; protected function casts(): array{return ['properties'=>'array'];} public function user(){return $this->belongsTo(User::class);} public static function record(string $action, string $description, ?Model $subject=null, array $properties=[]): void { static::create(['user_id'=>auth()->id(),'action'=>$action,'subject_type'=>$subject?->getMorphClass(),'subject_id'=>$subject?->getKey(),'description'=>$description,'properties'=>$properties,'ip_address'=>request()?->ip()]); } }
