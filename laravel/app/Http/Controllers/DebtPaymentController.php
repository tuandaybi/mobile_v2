<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\DebtPayment;
use Illuminate\Http\Request;

class DebtPaymentController extends Controller
{
    public function index(Debt $debt)
    {
        $items = $debt->payments()->with('createdUser')
            ->orderByDesc('paid_at')->orderByDesc('id')->get();

        return response()->json([
            'debt'     => new DebtResource($debt->append('remaining')),
            'payments' => DebtPaymentResource::collection($items),
        ]);
    }

    public function store(StoreDebtPaymentRequest $req, Debt $debt)
    {
        $data = $req->validated();

        $amount = (float) $data['amount'];
        if ($amount > $debt->remaining) {
            return response()->json(['message' => 'Số tiền vượt quá số còn nợ'], 422);
        }

        $payment = $debt->payments()->create([
            'amount'     => $amount,
            'paid_at'    => $data['paid_at'] ?? now(),
            'note'       => $data['note'] ?? null,
            'created_by' => auth()->id(),
        ]);
        // booted() của model sẽ tự recalc

        return (new DebtPaymentResource($payment->fresh('createdUser')))
            ->additional([
                'message' => 'Thu nợ thành công',
                'debt'    => new DebtResource($debt->fresh()),
            ])
            ->response()->setStatusCode(201);
    }

    public function update(UpdateDebtPaymentRequest $req, DebtPayment $payment)
    {
        $data = $req->validated();

        $debt = $payment->debt()->firstOrFail();
        $sumOther = (float) $debt->payments()->where('id','!=',$payment->id)->sum('amount');
        $newTotal = $sumOther + (float)$data['amount'];
        if ($newTotal > (float)$debt->debt) {
            return response()->json(['message' => 'Số tiền vượt quá số còn nợ'], 422);
        }

        $payment->update([
            'amount'  => $data['amount'],
            'paid_at' => $data['paid_at'] ?? $payment->paid_at,
            'note'    => $data['note'] ?? $payment->note,
        ]);

        return (new DebtPaymentResource($payment->fresh('createdUser')))
            ->additional([
                'message' => 'Cập nhật phiếu thu thành công',
                'debt'    => new DebtResource($debt->fresh()),
            ]);
    }

    public function destroy(DebtPayment $payment)
    {
        $debt = $payment->debt()->first();
        $payment->delete(); // model event -> recalc

        return response()->json([
            'message' => 'Đã xoá phiếu thu',
            'debt'    => $debt?->fresh()?->append('remaining'),
        ]);
    }
}
