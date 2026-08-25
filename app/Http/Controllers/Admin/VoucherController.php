<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller; use App\Models\Voucher; use Illuminate\Http\Request;
class VoucherController extends Controller {
 public function index(){ return view('admin.vouchers.index',['vouchers'=>Voucher::latest()->paginate(15)]); }
 public function store(Request $r){ $d=$r->validate(['code'=>'required|string|max:40|unique:vouchers,code','name'=>'required|string|max:120','type'=>'required|in:fixed,percent','value'=>'required|integer|min:1','min_order'=>'nullable|integer|min:0','usage_limit'=>'nullable|integer|min:1','ends_at'=>'nullable|date']); if($d['type']==='percent' && $d['value']>100) return back()->withErrors(['value'=>'Phần trăm không vượt quá 100.'])->withInput(); $d['code']=strtoupper($d['code']); Voucher::create($d); return back()->with('success','Đã tạo mã giảm giá.'); }
 public function update(Request $r,Voucher $voucher){ $d=$r->validate(['is_active'=>'nullable|boolean']); $voucher->update(['is_active'=>$r->boolean('is_active')]); return back()->with('success','Đã cập nhật trạng thái voucher.'); }
}
