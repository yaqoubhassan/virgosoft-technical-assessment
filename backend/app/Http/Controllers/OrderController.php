<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    protected OrderMatchingService $matchingService;

    public function __construct(OrderMatchingService $matchingService)
    {
        $this->matchingService = $matchingService;
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => 'sometimes|string|in:BTC,ETH',
        ]);

        $symbol = $validated['symbol'] ?? null;

        $query = Order::where('status', Order::STATUS_OPEN)
            ->with('user:id,name');

        if ($symbol) {
            $query->where('symbol', $symbol);
        }

        $orders = $query->orderBy('created_at', 'desc')->get();

        $buyOrders = $orders->where('side', 'buy')
            ->sortByDesc('price')
            ->values()
            ->map(fn($order) => $this->formatOrder($order));

        $sellOrders = $orders->where('side', 'sell')
            ->sortBy('price')
            ->values()
            ->map(fn($order) => $this->formatOrder($order));

        return response()->json([
            'buy_orders' => $buyOrders,
            'sell_orders' => $sellOrders,
        ]);
    }

    public function userOrders(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => 'sometimes|string|in:BTC,ETH',
            'status' => 'sometimes|integer|in:1,2,3',
            'side' => 'sometimes|string|in:buy,sell',
        ]);

        $query = $request->user()->orders()->orderBy('created_at', 'desc');

        if (isset($validated['symbol'])) {
            $query->where('symbol', $validated['symbol']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['side'])) {
            $query->where('side', $validated['side']);
        }

        $orders = $query->get()->map(fn($order) => $this->formatOrder($order));

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => ['required', 'string', Rule::in(['BTC', 'ETH'])],
            'side' => ['required', 'string', Rule::in(['buy', 'sell'])],
            'price' => 'required|numeric|min:0.00000001',
            'amount' => 'required|numeric|min:0.00000001',
        ]);

        $user = $request->user();

        try {
            $result = DB::transaction(function () use ($user, $validated) {
                return $this->matchingService->createOrder(
                    $user,
                    $validated['symbol'],
                    $validated['side'],
                    $validated['price'],
                    $validated['amount']
                );
            });

            return response()->json($result, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $order = $request->user()->orders()->findOrFail($id);

        if (!$order->isOpen()) {
            return response()->json([
                'message' => 'Only open orders can be cancelled',
            ], 422);
        }

        try {
            DB::transaction(function () use ($order) {
                $this->matchingService->cancelOrder($order);
            });

            return response()->json([
                'message' => 'Order cancelled successfully',
                'order' => $this->formatOrder($order->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    protected function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'user_name' => $order->user?->name ?? 'Unknown',
            'symbol' => $order->symbol,
            'side' => $order->side,
            'price' => $order->price,
            'amount' => $order->amount,
            'total' => bcmul($order->price, $order->amount, 8),
            'status' => $order->status,
            'status_label' => $order->getStatusLabel(),
            'created_at' => $order->created_at->toIso8601String(),
        ];
    }
}
