<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Transaction::with('user');

        if ($request->search) {
            $query->where('reference',   'like', '%' . $request->search . '%')
                  ->orWhere('category',  'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->from_date) {
            $query->whereDate('date', '>=', $request->from_date);
        }

        if ($request->to_date) {
            $query->whereDate('date', '<=', $request->to_date);
        }

        $transactions = $query->orderBy('date', 'desc')->get();

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $request->validate([
            'type'           => 'required|in:income,expense',
            'category'       => 'required|string|max:255',
            'amount'         => 'required|numeric|min:0',
            'description'    => 'nullable|string',
            'date'           => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,card,other',
        ]);

        $transaction = Transaction::create([
            'user_id'        => $request->user()->id,
            'reference'      => 'TXN-' . strtoupper(uniqid()),
            'type'           => $request->type,
            'category'       => $request->category,
            'amount'         => $request->amount,
            'description'    => $request->description,
            'date'           => $request->date,
            'payment_method' => $request->payment_method,
        ]);

        return response()->json([
            'message'     => 'Transaction created successfully',
            'transaction' => $transaction,
        ], 201);
    }

    public function show(Transaction $transaction)
    {
        return response()->json($transaction);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $request->validate([
            'type'           => 'required|in:income,expense',
            'category'       => 'required|string|max:255',
            'amount'         => 'required|numeric|min:0',
            'description'    => 'nullable|string',
            'date'           => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,card,other',
        ]);

        $transaction->update($request->all());

        return response()->json([
            'message'     => 'Transaction updated successfully',
            'transaction' => $transaction,
        ]);
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return response()->json(['message' => 'Transaction deleted successfully']);
    }

    public function stats()
    {
        $totalIncome  = Transaction::where('type', 'income')->sum('amount');
        $totalExpense = Transaction::where('type', 'expense')->sum('amount');

        return response()->json([
            'total_income'       => $totalIncome,
            'total_expense'      => $totalExpense,
            'net_profit'         => $totalIncome - $totalExpense,
            'total_transactions' => Transaction::count(),
        ]);
    }

    public function monthlyReport()
    {
        $months = Transaction::selectRaw('
                MONTH(date) as month,
                YEAR(date) as year,
                SUM(CASE WHEN type = "income"  THEN amount ELSE 0 END) as income,
                SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as expense
            ')
            ->groupByRaw('YEAR(date), MONTH(date)')
            ->orderByRaw('YEAR(date) DESC, MONTH(date) DESC')
            ->limit(12)
            ->get();

        return response()->json($months);
    }
}