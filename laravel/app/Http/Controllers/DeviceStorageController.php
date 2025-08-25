<?php
namespace App\Http\Controllers;

use App\Models\DeviceStorage;
use Illuminate\Http\Request;
use App\Http\Requests\{DeviceStorageStoreRequest, DeviceStorageUpdateRequest};
use App\Http\Resources\DeviceStorageResource;

class DeviceStorageController extends Controller
{
    public function index(Request $r)
    {
        $q = DeviceStorage::query();

        if ($s = trim((string)$r->input('q'))) {
            $q->where(fn($w)=>$w->where('name','like',"%{$s}%")
                ->orWhere('size_gb','like',"%{$s}%"));
        }

        if ($r->filled('active')) {
            $q->where('is_active', (int)$r->input('active') ? 1 : 0);
        }

        $sortable = ['id','name','size_gb','is_active','created_at'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'size_gb';
        $sortDir = strtolower($r->input('sortDir')) === 'desc' ? 'desc' : 'asc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 100), 500));
        return DeviceStorageResource::collection($q->paginate($perPage));
    }

    public function store(DeviceStorageStoreRequest $r)
    {
        $st = DeviceStorage::create($r->validated());
        return (new DeviceStorageResource($st))->response()->setStatusCode(201);
    }

    public function show($id)
    {
        return new DeviceStorageResource(DeviceStorage::findOrFail($id));
    }

    public function update(DeviceStorageUpdateRequest $r, $id)
    {
        $st = DeviceStorage::findOrFail($id);
        $st->update($r->validated());
        return new DeviceStorageResource($st);
    }

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
