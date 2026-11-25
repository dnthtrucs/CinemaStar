<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Cinema;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('cinema')
            ->when($request->filled('q'), fn ($query) => $query
                ->where(fn ($sub) => $sub->where('name', 'like', '%'.$request->string('q').'%')
                    ->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', ['users' => $users, 'cinemas' => Cinema::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'Không thể thay đổi quyền hoặc khóa chính tài khoản đang đăng nhập.');
        $data = $request->validate([
            'role' => ['required', 'in:admin,staff,customer'],
            'is_active' => ['nullable', 'boolean'],
            'cinema_id' => ['nullable', 'exists:cinemas,id', Rule::requiredIf($request->input('role') === 'staff')],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $before = $user->only(['role', 'is_active', 'cinema_id']);
        $data['cinema_id'] = $data['role'] === 'staff' ? $data['cinema_id'] : null;
        $user->update($data);
        ActivityLog::record('user.permissions_updated', 'Cập nhật quyền và rạp của tài khoản '.$user->id, $user, ['before' => $before, 'after' => $user->only(['role', 'is_active', 'cinema_id'])]);

        return back()->with('success', 'Đã cập nhật tài khoản.');
    }
}
