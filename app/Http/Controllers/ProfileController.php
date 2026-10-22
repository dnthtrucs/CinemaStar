<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Payment;
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

        // Kiểm tra và xóa trong cùng transaction, khóa dòng user để không xóa giữa chừng một thao tác khác.
        $deleted = DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);

            $hasFinancialRecords = $user->bookings()->where(fn ($query) => $query->whereIn('payment_status', ['paid', 'refunded'])
                ->orWhereNotNull('paid_at')
                ->orWhereHas('payments', fn ($payments) => $payments->where('status', 'success')
                    ->orWhereIn('response_code', Payment::manualRefundCodes())))->exists()
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
                'password' => 'Không thể xóa tài khoản đã có đơn thanh toán, khoản tiền chờ hoàn hoặc yêu cầu hoàn tiền.',
            ], 'userDeletion');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
