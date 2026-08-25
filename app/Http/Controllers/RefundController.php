<?php
namespace App\Http\Controllers;
use App\Models\{ActivityLog,Booking,RefundRequest}; use Illuminate\Http\Request;
class RefundController extends Controller {
 public function create(Booking $booking){abort_unless($booking->user_id===auth()->id() && $booking->payment_status==='paid' && !$booking->refundRequest,403); return view('refunds.create',compact('booking'));}
 public function store(Request $request, Booking $booking){abort_unless($booking->user_id===auth()->id() && $booking->payment_status==='paid' && !$booking->refundRequest,403); $data=$request->validate(['reason'=>'required|string|max:1000']); $refund=RefundRequest::create(['booking_id'=>$booking->id,'requested_by'=>auth()->id(),'reason'=>$data['reason'],'amount'=>$booking->total_price]); $booking->update(['refund_status'=>'requested','refund_reason'=>$data['reason'],'refund_requested_at'=>now()]); ActivityLog::record('refund.requested',"Khách yêu cầu hoàn tiền đơn {$booking->code}",$refund); return redirect()->route('bookings.show',$booking)->with('success','Yêu cầu hoàn tiền đã được gửi.'); }
}
