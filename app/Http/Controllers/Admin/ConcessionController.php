<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConcessionRequest;
use App\Models\Concession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConcessionController extends Controller
{
    public function index()
    {
        return view('admin.concessions.index', ['concessions' => Concession::orderBy('sort_order')->orderBy('id')->paginate(20)]);
    }

    public function create()
    {
        return view('admin.concessions.form', ['concession' => new Concession(['is_active' => true])]);
    }

    public function store(ConcessionRequest $request)
    {
        Concession::create($this->data($request));

        return redirect()->route('admin.concessions.index')->with('success', 'Đã thêm combo bắp nước.');
    }

    public function edit(Concession $concession)
    {
        return view('admin.concessions.form', compact('concession'));
    }

    public function update(ConcessionRequest $request, Concession $concession)
    {
        $oldImage = $concession->image_path;
        $concession->update($this->data($request));
        if ($oldImage && $oldImage !== $concession->image_path) {
            Storage::disk('public')->delete($oldImage);
        }

        return redirect()->route('admin.concessions.index')->with('success', 'Đã cập nhật combo bắp nước.');
    }

    public function destroy(Concession $concession)
    {
        $deleted = DB::transaction(function () use ($concession) {
            $concession = Concession::query()->lockForUpdate()->findOrFail($concession->id);
            if ($concession->bookings()->exists()) {
                return false;
            }
            $concession->delete();

            return true;
        });
        if (! $deleted) {
            return back()->with('error', 'Combo đã có trong đơn, chỉ được tắt để giữ lịch sử.');
        }
        if ($concession->image_path) {
            Storage::disk('public')->delete($concession->image_path);
        }

        return back()->with('success', 'Đã xóa combo bắp nước.');
    }

    private function data(ConcessionRequest $request): array
    {
        $data = $request->safe()->except('image');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('concessions', 'public');
        }

        return $data;
    }
}
