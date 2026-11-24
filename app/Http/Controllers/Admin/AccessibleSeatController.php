<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccessibleSeatController extends Controller
{
    public function update(Request $request, Room $room)
    {
        $data = $request->validate(['seats' => ['sometimes', 'array'], 'seats.*' => ['integer', 'distinct']]);
        DB::transaction(function () use ($room, $data) {
            $room->seats()->lockForUpdate()->get();
            $ids = $data['seats'] ?? [];
            abort_if($room->seats()->whereIn('id', $ids)->where('type', '!=', 'couple')->count() !== count($ids), 422, 'Vị trí không hợp lệ hoặc là ghế đôi.');
            $room->seats()->update(['is_accessible' => false]);
            $room->seats()->whereIn('id', $ids)->update(['is_accessible' => true]);
            ActivityLog::record('room.accessibility', 'Cập nhật vị trí hỗ trợ tiếp cận', $room, ['seat_ids' => $ids]);
        });

        return back()->with('success', 'Đã lưu vị trí hỗ trợ tiếp cận do rạp xác nhận.');
    }
}
