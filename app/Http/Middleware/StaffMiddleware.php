<?php
namespace App\Http\Middleware;
use Closure; use Illuminate\Http\Request;
class StaffMiddleware { public function handle(Request $request, Closure $next) { abort_unless(in_array($request->user()?->role, ['admin','staff'], true), 403, 'Chỉ nhân viên rạp được sử dụng chức năng này.'); return $next($request); } }
