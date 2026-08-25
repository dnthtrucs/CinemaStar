<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\{ActivityLog,MovieReview}; use Illuminate\Http\Request;
class ReviewController extends Controller { public function index(){return view('admin.reviews.index',['reviews'=>MovieReview::with('movie','user')->latest()->paginate(30)]);} public function update(Request $request,MovieReview $review){$data=$request->validate(['status'=>'required|in:approved,rejected']); $review->update($data); ActivityLog::record('review.'.$data['status'],"Duyệt đánh giá #{$review->id}",$review); return back()->with('success','Đã cập nhật đánh giá.');} }
