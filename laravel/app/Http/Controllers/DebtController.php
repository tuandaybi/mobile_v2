<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Resources\DebtResource;
use App\Http\Resources\DebtPaymentResource;
use App\Http\Requests\Admin\Debt\StoreDebtRequest;
use App\Http\Requests\Admin\Debt\UpdateDebtRequest;
use App\Http\Requests\Admin\Debt\StoreDebtPaymentRequest;
use App\Http\Requests\Admin\Debt\UpdateDebtPaymentRequest;

class DebtController extends Controller
{
    public function index(Request $r)
    {
        $q = Debt::query()->orderByDesc('date')->orderByDesc('id');

        if ($r->filled('customer_id')) $q->where('customer_id', $r->integer('customer_id'));
        if ($r->filled('status'))      $q->where('status', $r->input('status'));
        if ($r->filled('from'))        $q->whereDate('date', '>=', $r->date('from'));
        if ($r->filled('to'))          $q->whereDate('date', '<=', $r->date('to'));
        if ($r->filled('q'))           $q->where('note', 'like', '%'.$r->input('q').'%');

        $per = max(1, (int) $r->input('per_page', 50));
        $page = $q->paginate($per);

        // Resource sẽ giữ nguyên cấu trúc paginate (links, meta) + map data
        return DebtResource::collection($page);
    }

    public function show(Debt $debt)
    {
        $debt->load(['customer','service','mobileOut']);
        return new DebtResource($debt);
    }

    public function store(StoreDebtRequest $req)
    {
        $data = $req->validated();

        $debt = Debt::create([
            'mobileout_id' => $data['mobileout_id'] ?? null,
            'service_id'   => $data['service_id'] ?? null,
            'customer_id'  => $data['customer_id'] ?? null,
            'debt'         => $data['debt'],
            'paid_amount'  => 0,
            'status'       => 'pending',
            'date'         => $data['date'],
            'due_date'     => $data['due_date'] ?? null,
            'note'         => $data['note'] ?? null,
        ]);

        return (new DebtResource($debt))
            ->additional(['message' => 'Tạo công nợ thành công'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateDebtRequest $req, Debt $debt)
    {
        $data = $req->validated();
        $debt->fill($data)->save();

        if (array_key_exists('debt', $data)) {
            $debt->recalcCache();
        }

        return (new DebtResource($debt->fresh()))
            ->additional(['message' => 'Cập nhật công nợ thành công']);
    }

    public function destroy(Debt $debt)
    {
        $debt->delete();
        return response()->json(['message' => 'Đã xoá công nợ']);
    }
}
