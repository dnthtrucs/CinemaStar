<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomRequest;
use App\Models\Cinema;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RoomController extends Controller
{
    public function index()
    {
        $rooms = Room::with('cinema')->withCount('seats')->latest()->paginate(15);
        return view('admin.rooms.index', compact('rooms'));
    }

    public function create()
    {
        return view('admin.rooms.form', ['room' => new Room(), 'cinemas' => Cinema::where('is_active', true)->get()]);
    }

    public function store(RoomRequest $request)
    {
        DB::transaction(function () use ($request) {
            $data = $this->data($request);
            $room = Room::create($data);
            $this->generateSeats($room);
        });
        return redirect()->route('admin.rooms.index')->with('success', 'Đã tạo phòng và sơ đồ ghế.');
    }

    public function edit(Room $room)
    {
        return view('admin.rooms.form', ['room' => $room, 'cinemas' => Cinema::where('is_active', true)->get()]);
    }

    public function update(RoomRequest $request, Room $room)
    {
        $layoutChanged = $room->rows !== $request->integer('rows') || $room->seats_per_row !== $request->integer('seats_per_row');
        if ($layoutChanged && $room->showtimes()->exists()) {
            throw ValidationException::withMessages(['rows' => 'Không thể đổi sơ đồ ghế vì phòng đã có suất chiếu.']);
        }

        DB::transaction(function () use ($request, $room, $layoutChanged) {
            $room->update($this->data($request));
            if ($layoutChanged) {
                $room->seats()->delete();
                $this->generateSeats($room);
            }
        });
        return redirect()->route('admin.rooms.index')->with('success', 'Đã cập nhật phòng.');
    }

    public function destroy(Room $room)
    {
        if ($room->showtimes()->exists()) {
            return back()->with('error', 'Không thể xóa phòng đã có suất chiếu.');
        }
        $room->delete();
        return back()->with('success', 'Đã xóa phòng.');
    }

    private function data(RoomRequest $request): array
    {
        $data = $request->validated();
        $data['total_seats'] = $request->integer('rows') * $request->integer('seats_per_row');
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }

    private function generateSeats(Room $room): void
    {
        for ($rowIndex = 0; $rowIndex < $room->rows; $rowIndex++) {
            $row = chr(65 + $rowIndex);
            $isVip = $rowIndex >= $room->rows - 2;
            for ($number = 1; $number <= $room->seats_per_row; $number++) {
                $room->seats()->create([
                    'row' => $row,
                    'number' => $number,
                    'type' => $isVip ? 'vip' : 'standard',
                    'price_surcharge' => $isVip ? 30000 : 0,
                    'is_active' => true,
                ]);
            }
        }
    }
}
