<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\RefundRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        // Khóa booking trước user, cùng thứ tự với tạo payment và xác nhận thanh toán.
        $deleted = DB::transaction(function () use ($user) {
            $user->bookings()->orderBy('id')->lockForUpdate()->get(['id']);
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            // Mọi payment đều là mã tham chiếu còn có thể nhận callback, kể cả failed.
            $hasFinancialRecords = $user->bookings()->where(fn ($query) => $query->whereIn('payment_status', ['paid', 'refunded'])
                ->orWhereNotNull('paid_at')
                ->orWhereHas('payments'))->exists()
                || RefundRequest::where('requested_by', $user->id)->exists()
                || $user->bookings()->whereHas('refundRequest')->exists();
            if ($hasFinancialRecords) {
                return false;
            }

            $user->delete();

            return true;
        });

        if (! $deleted) {
            return Redirect::route('profile.edit')->withErrors([
                'password' => 'Không thể xóa tài khoản đã có giao dịch thanh toán hoặc yêu cầu hoàn tiền.',
            ], 'userDeletion');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
