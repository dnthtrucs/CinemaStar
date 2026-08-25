<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\{ActivityLog,RefundRequest}; use App\Notifications\RefundStatusNotification; use Illuminate\Http\Request;
class RefundController extends Controller {
 public function index(){return view('admin.refunds.index',['refunds'=>RefundRequest::with('booking.user','requester')->latest()->paginate(20)]);}
 public function update(Request $request, RefundRequest $refund){$data=$request->validate(['status'=>'required|in:approved,refunded,rejected','admin_note'=>'nullable|string|max:1000']); abort_if(in_array($refund->status,['refunded','rejected']),422,'Yêu cầu này đã được xử lý.'); $refund->update($data+['handled_by'=>auth()->id(),'handled_at'=>now()]); $refund->booking->update(['refund_status'=>$data['status'],'refunded_at'=>$data['status']==='refunded'?now():null,'status'=>$data['status']==='refunded'?'cancelled':$refund->booking->status]); $refund->requester->notify(new RefundStatusNotification($refund)); ActivityLog::record('refund.'.$data['status'],"Xử lý hoàn tiền đơn {$refund->booking->code}",$refund); return back()->with('success','Đã cập nhật yêu cầu hoàn tiền.'); }
}
