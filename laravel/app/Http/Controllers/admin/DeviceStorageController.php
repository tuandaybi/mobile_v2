<?php
namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\DeviceStorage;
use Illuminate\Http\Request;

class DeviceStorageController extends Controller
{
    // GET /storage
    public function index(Request $r)
    {
        $q = DeviceStorage::query();

        if ($s = trim((string)$r->input('q'))) {
            $q->where(function($w) use ($s) {
                $w->where('name','like',"%{$s}%")
                  ->orWhere('size_gb','like',"%{$s}%");
            });
        }

        if ($r->filled('active')) {
            $q->where('is_active', (int)$r->input('active') ? 1 : 0);
        }

        $sortable = ['id','name','size_gb','is_active','created_at'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'size_gb';
        $sortDir = strtolower($r->input('sortDir')) === 'desc' ? 'desc' : 'asc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 100), 500));
        return response()->json($q->paginate($perPage));
    }

    // POST /storage
    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => ['required','string','max:255'],
            'size_gb' => ['required','integer','min:1'],
            'is_active' => ['nullable','boolean'],
        ]);

        $st = DeviceStorage::create($data);
        return response()->json($st, 201);
    }

    // GET /storage/{id}
    public function show($id)
    {
        return response()->json(DeviceStorage::findOrFail($id));
    }

    // PATCH /storage/{id}
    public function update(Request $r, $id)
    {
        $st = DeviceStorage::findOrFail($id);
        $data = $r->validate([
            'name' => ['sometimes','required','string','max:255'],
            'size_gb' => ['sometimes','required','integer','min:1'],
            'is_active' => ['sometimes','boolean'],
        ]);
        $st->update($data);
        return response()->json($st);
    }

    // DELETE /storage/{id}
    public function destroy($id)
    {
        $st = DeviceStorage::findOrFail($id);
        if ($st->mobileIns()->exists()) {
            return response()->json(['message'=>'Đang được sử dụng (mobile_in). Không thể xoá.'], 409);
        }
        $st->delete();
        return response()->json(['message'=>'Đã xoá.']);
    }
}
