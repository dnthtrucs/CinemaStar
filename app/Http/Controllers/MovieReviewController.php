<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog,Movie,MovieReview}; use Illuminate\Http\Request;
class MovieReviewController extends Controller {
 public function store(Request $request, Movie $movie){$watched=auth()->user()->bookings()->where('payment_status','paid')->whereHas('showtime',fn($q)=>$q->where('movie_id',$movie->id))->exists(); abort_unless($watched,403,'Bạn chỉ có thể đánh giá phim đã mua vé.'); $data=$request->validate(['rating'=>'required|integer|between:1,5','comment'=>'nullable|string|max:1000']); $review=MovieReview::updateOrCreate(['movie_id'=>$movie->id,'user_id'=>auth()->id()],$data+['status'=>'pending']); ActivityLog::record('review.submitted',"Gửi đánh giá phim {$movie->title}",$review); return back()->with('success','Đánh giá đã gửi và chờ duyệt.'); }
}
