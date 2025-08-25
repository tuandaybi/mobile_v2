<?php
namespace App\Http\Controllers;

use App\Models\Color;
use Illuminate\Http\Request;
use App\Http\Requests\{ColorStoreRequest, ColorUpdateRequest};
use App\Http\Resources\ColorResource;

class ColorController extends Controller
{
    public function index(Request $r)
    {
        $q = Color::query();

        if ($s = trim((string)$r->input('q'))) {
            $q->where(fn($w)=>$w->where('vi_name','like',"%{$s}%")
                ->orWhere('en_name','like',"%{$s}%"));
        }

        $sortable = ['id','vi_name','en_name','created_at'];
        $sortBy = in_array($r->input('sortBy'), $sortable, true) ? $r->input('sortBy') : 'vi_name';
        $sortDir = strtolower($r->input('sortDir')) === 'desc' ? 'desc' : 'asc';
        $q->orderBy($sortBy, $sortDir);

        $perPage = max(1, min((int)$r->input('perPage', 100), 500));
        return ColorResource::collection($q->paginate($perPage));
    }

    public function store(ColorStoreRequest $r)
    {
        $color = Color::create($r->validated());
        return (new ColorResource($color))->response()->setStatusCode(201);
    }

    public function show($id)
    {
        return new ColorResource(Color::findOrFail($id));
    }

    public function update(ColorUpdateRequest $r, $id)
    {
        $color = Color::findOrFail($id);
        $color->update($r->validated());
        return new ColorResource($color);
    }

    public function destroy($id)
    {
        $color = Color::findOrFail($id);
        if ($color->mobileIns()->exists()) {
            return response()->json(['message'=>'Đang được sử dụng (mobile_in). Không thể xoá.'], 409);
        }
        $color->delete();
        return response()->json(['message'=>'Đã xoá.']);
    }
}
