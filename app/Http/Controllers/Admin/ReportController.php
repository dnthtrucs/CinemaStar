<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\{Booking,Payment,Ticket}; use Illuminate\Http\Request;
class ReportController extends Controller {
 private function rows(Request $request){$from=$request->date('from')?->startOfDay() ?: now()->subMonth()->startOfDay(); $to=$request->date('to')?->endOfDay() ?: now()->endOfDay(); return Payment::with('booking.showtime.movie')->where('status','success')->whereBetween('paid_at',[$from,$to])->latest('paid_at')->get();}
 public function revenueCsv(Request $r){$rows=$this->rows($r); return response()->streamDownload(function()use($rows){$o=fopen('php://output','w');fprintf($o,"\xEF\xBB\xBF");fputcsv($o,['Mã đơn','Phim','Phương thức','Số tiền','Thanh toán']);foreach($rows as $p)fputcsv($o,[$p->booking->code,$p->booking->showtime->movie->title,strtoupper($p->provider),$p->amount,$p->paid_at]);fclose($o);},'doanh-thu.csv',['Content-Type'=>'text/csv; charset=UTF-8']);}
 public function excel(Request $r){$rows=$this->rows($r); return response()->view('admin.reports.excel',compact('rows'),200,['Content-Type'=>'application/vnd.ms-excel','Content-Disposition'=>'attachment; filename="doanh-thu.xls"']);}
 public function pdf(Request $r){$rows=$this->rows($r); return response()->view('admin.reports.pdf',compact('rows'),200,['Content-Type'=>'text/html; charset=UTF-8','Content-Disposition'=>'inline; filename="doanh-thu.html"']);}
}
