<?php
// app/Http/Controllers/DeviceController.php
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $r)
    {
        $q = Device::query();
        if ($s = trim((string)$r->input('q'))) $q->where('name','like',"%{$s}%");
        if ($r->filled('active')) $q->where('is_active',(int)$r->active?1:0);
        return response()->json($q->orderBy('sort_order')->paginate($r->integer('perPage', 100)));
    }

    public function store(Request $r)
    {
        $data = $r->validate([
            'code'=>['nullable','string','max:100'],
            'name'=>['required','string','max:255'],
            'sort_order'=>['nullable','integer'],
            'is_active'=>['nullable','boolean'],
        ]);
        return response()->json(Device::create($data), 201);
    }

    public function show($id){ return response()->json(Device::findOrFail($id)); }

    public function update(Request $r, $id)
    {
        $dev = Device::findOrFail($id);
        $data = $r->validate([
            'code'=>['sometimes','nullable','string','max:100'],
            'name'=>['sometimes','required','string','max:255'],
            'sort_order'=>['sometimes','nullable','integer'],
            'is_active'=>['sometimes','boolean'],
        ]);
        $dev->update($data);
        return response()->json($dev);
    }

    public function destroy($id)
    {
        $dev = Device::findOrFail($id);
        if ($dev->mobileIns()->exists()) {
            return response()->json(['message'=>'Đang được sử dụng, không thể xoá.'], 409);
        }
        $dev->delete();
        return response()->json(['message'=>'Đã xoá.']);
    }
}
