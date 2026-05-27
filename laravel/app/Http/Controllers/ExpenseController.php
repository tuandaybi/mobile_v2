<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesStore;
use App\Http\Controllers\Traits\IndexHelpers;
use App\Http\Requests\{ExpenseStoreRequest, ExpenseUpdateRequest};
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\Notification;
use App\Notifications\TelegramNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpenseController extends Controller
{
    use ResolvesStore;
    use IndexHelpers;

    public function index(Request $r)
    {
        $storeId = $this->resolveStoreId($r);

        $q = Expense::query()
            ->with(['user:id,name', 'category:id,name,code'])
            ->leftJoin('users as u', 'u.id', '=', 'expenses.user_id')
            ->leftJoin('expense_categories as ec', 'ec.id', '=', 'expenses.category_id')
            ->where('expenses.store_id', $storeId)
            ->select('expenses.*');

        $this->applySearch($q, $r->input('q'), [
            'expenses.name',
            'expenses.note',
            'u.name',
            'ec.name',
        ]);

        if ($f = $r->input('date_from')) $q->whereDate('expenses.date', '>=', $f);
        if ($t = $r->input('date_to'))   $q->whereDate('expenses.date', '<=', $t);
        if ($r->filled('category_id'))   $q->where('expenses.category_id', $r->input('category_id'));

        $sortMap = [
            'id'        => 'expenses.id',
            'name'      => 'expenses.name',
            'amount'    => 'expenses.amount',
            'date'      => 'expenses.date',
            'category'  => 'ec.name',
            'user_name' => 'u.name',
        ];
        $this->applySort($q, $r, $sortMap, 'date', 'desc');

        $paginator = $q->paginate($this->perPage($r))->appends($r->query());

        return ExpenseResource::collection($paginator);
    }

    public function store(ExpenseStoreRequest $r)
    {
        $storeId = $this->resolveStoreId($r);
        $userId  = $r->user()->id;

        $data = $r->validated();
        unset($data['store_id']);
        $data['store_id'] = $storeId;
        $data['user_id']  = $userId;
        $data['date']     = Carbon::parse($data['date'])->toDateString();

        $expense = DB::transaction(function () use ($data, $storeId, $userId) {
            $expense = Expense::create($data);
            $expense->load('category:id,name,code');

            $catLabel = $expense->category->name ?? '';
            $amountFmt = number_format((float) $expense->amount) . 'đ';

            TelegramNotification::send(
                "Tạo chi phí:\n- {$expense->name}\nLoại: {$catLabel}\nSố tiền: {$amountFmt}\nCửa hàng: " . $this->resolveStoreName($storeId)
            );

            $noti = Notification::create([
                'store_id'   => $storeId,
                'created_by' => $userId,
                'type'       => 'log',
                'title'      => 'Chi phí',
                'body'       => "Thêm chi phí '{$expense->name}' ({$catLabel}) số tiền {$amountFmt}",
                'ref_type'   => 'expense',
                'ref_id'     => $expense->id,
                'priority'   => 'normal',
            ]);

            $uids = DB::table('user_in_store')->where('store_id', $storeId)->pluck('user_id')->all();
            if (!empty($uids)) {
                DB::table('notification_recipients')->insert(array_map(fn($uid) => [
                    'notification_id' => $noti->id,
                    'user_id'         => $uid,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ], $uids));
            }

            return $expense;
        });

        return (new ExpenseResource($expense->load(['user:id,name', 'category:id,name,code'])))
            ->additional(['message' => 'Tạo chi phí thành công'])
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $expense = Expense::with(['user:id,name', 'category:id,name,code'])
            ->where('store_id', $storeId)
            ->findOrFail($id);

        return new ExpenseResource($expense);
    }

    public function update(ExpenseUpdateRequest $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $userId  = $r->user()->id;

        $expense = Expense::where('store_id', $storeId)->findOrFail($id);

        $data = $r->validated();
        unset($data['store_id']);
        $data['user_id'] = $userId;
        if (!empty($data['date'])) {
            $data['date'] = Carbon::parse($data['date'])->toDateString();
        }

        DB::transaction(function () use ($expense, $data, $storeId, $userId) {
            $expense->update($data);
            $expense->load('category:id,name,code');

            $catLabel = $expense->category->name ?? '';
            $amountFmt = number_format((float) $expense->amount) . 'đ';

            TelegramNotification::send(
                "Sửa chi phí:\n- {$expense->name}\nLoại: {$catLabel}\nSố tiền: {$amountFmt}\nCửa hàng: " . $this->resolveStoreName($storeId)
            );

            $noti = Notification::create([
                'store_id'   => $storeId,
                'created_by' => $userId,
                'type'       => 'log',
                'title'      => 'Sửa chi phí',
                'body'       => "Sửa chi phí '{$expense->name}' ({$catLabel}) số tiền {$amountFmt}",
                'ref_type'   => 'expense',
                'ref_id'     => $expense->id,
                'priority'   => 'normal',
            ]);

            $uids = DB::table('user_in_store')->where('store_id', $storeId)->pluck('user_id')->all();
            if (!empty($uids)) {
                DB::table('notification_recipients')->insert(array_map(fn($uid) => [
                    'notification_id' => $noti->id,
                    'user_id'         => $uid,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ], $uids));
            }
        });

        return new ExpenseResource($expense->fresh()->load(['user:id,name', 'category:id,name,code']));
    }

    public function destroy(Request $r, $id)
    {
        $storeId = $this->resolveStoreId($r);
        $userId  = $r->user()->id;

        $expense = Expense::with('category:id,name,code')
            ->where('store_id', $storeId)
            ->findOrFail($id);

        DB::transaction(function () use ($expense, $storeId, $userId) {
            $catLabel = $expense->category->name ?? '';
            $amountFmt = number_format((float) $expense->amount) . 'đ';

            TelegramNotification::send(
                "Xoá chi phí:\n- {$expense->name}\nLoại: {$catLabel}\nSố tiền: {$amountFmt}\nCửa hàng: " . $this->resolveStoreName($storeId)
            );

            $noti = Notification::create([
                'store_id'   => $storeId,
                'created_by' => $userId,
                'type'       => 'log',
                'title'      => 'Xoá chi phí',
                'body'       => "Xoá chi phí '{$expense->name}' ({$catLabel}) số tiền {$amountFmt}",
                'ref_type'   => 'expense',
                'ref_id'     => $expense->id,
                'priority'   => 'normal',
            ]);

            $uids = DB::table('user_in_store')->where('store_id', $storeId)->pluck('user_id')->all();
            if (!empty($uids)) {
                DB::table('notification_recipients')->insert(array_map(fn($uid) => [
                    'notification_id' => $noti->id,
                    'user_id'         => $uid,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ], $uids));
            }

            $expense->delete();
        });

        return response()->json(['message' => 'Đã xoá chi phí.']);
    }
}
