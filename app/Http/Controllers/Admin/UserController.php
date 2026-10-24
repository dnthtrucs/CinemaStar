<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filled('q'), fn ($query) => $query
                ->where(fn ($sub) => $sub->where('name', 'like', '%'.$request->string('q').'%')
                    ->orWhere('email', 'like', '%'.$request->string('q').'%')))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.users.index', compact('users'));
    }

    public function update(Request $request, User $user)
    {
        abort_if($user->is($request->user()), 422, 'Không thể thay đổi quyền hoặc khóa chính tài khoản đang đăng nhập.');
        $data = $request->validate([
            'role' => ['required', 'in:admin,staff,customer'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $user->update($data);

        return back()->with('success', 'Đã cập nhật tài khoản.');
    }
}
