<?php

namespace App\Services;

use App\Events\OrderMatched;
use App\Models\Asset;
use App\Models\Order;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OrderMatchingService
{
    const COMMISSION_RATE = '0.015'; // 1.5%

    public function createOrder(User $user, string $symbol, string $side, string $price, string $amount): array
    {
        // Lock user row to prevent race conditions
        $user = User::lockForUpdate()->find($user->id);

        if ($side === 'buy') {
            return $this->createBuyOrder($user, $symbol, $price, $amount);
        }

        return $this->createSellOrder($user, $symbol, $price, $amount);
    }

    protected function createBuyOrder(User $user, string $symbol, string $price, string $amount): array
    {
        $totalCost = bcmul($price, $amount, 8);

        // Check if user has sufficient balance
        if (bccomp($user->balance, $totalCost, 8) < 0) {
            throw new \Exception('Insufficient USD balance. Required: ' . $totalCost . ', Available: ' . $user->balance);
        }

        // Deduct balance and lock funds
        $user->balance = bcsub($user->balance, $totalCost, 8);
        $user->save();

        // Create the order
        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => $symbol,
            'side' => 'buy',
            'price' => $price,
            'amount' => $amount,
            'locked_usd' => $totalCost,
            'status' => Order::STATUS_OPEN,
        ]);

        // Try to match with existing sell orders
        $matchResult = $this->matchBuyOrder($order);

        return [
            'message' => $matchResult['matched'] ? 'Order matched successfully' : 'Order placed successfully',
            'order' => $this->formatOrder($order->fresh()),
            'matched' => $matchResult['matched'],
            'trade' => $matchResult['trade'] ?? null,
        ];
    }

    protected function createSellOrder(User $user, string $symbol, string $price, string $amount): array
    {
        // Get or create asset
        $asset = Asset::lockForUpdate()->firstOrCreate(
            ['user_id' => $user->id, 'symbol' => $symbol],
            ['amount' => 0, 'locked_amount' => 0]
        );

        $availableAmount = bcsub($asset->amount, $asset->locked_amount, 8);

        // Check if user has sufficient asset
        if (bccomp($availableAmount, $amount, 8) < 0) {
            throw new \Exception('Insufficient ' . $symbol . ' balance. Required: ' . $amount . ', Available: ' . $availableAmount);
        }

        // Lock the asset
        $asset->locked_amount = bcadd($asset->locked_amount, $amount, 8);
        $asset->save();

        // Create the order
        $order = Order::create([
            'user_id' => $user->id,
            'symbol' => $symbol,
            'side' => 'sell',
            'price' => $price,
            'amount' => $amount,
            'locked_usd' => 0,
            'status' => Order::STATUS_OPEN,
        ]);

        // Try to match with existing buy orders
        $matchResult = $this->matchSellOrder($order);

        return [
            'message' => $matchResult['matched'] ? 'Order matched successfully' : 'Order placed successfully',
            'order' => $this->formatOrder($order->fresh()),
            'matched' => $matchResult['matched'],
            'trade' => $matchResult['trade'] ?? null,
        ];
    }

    protected function matchBuyOrder(Order $buyOrder): array
    {
        // Find first matching sell order where sell.price <= buy.price
        $sellOrder = Order::where('symbol', $buyOrder->symbol)
            ->where('side', 'sell')
            ->where('status', Order::STATUS_OPEN)
            ->where('user_id', '!=', $buyOrder->user_id) // Can't match own orders
            ->where('price', '<=', $buyOrder->price)
            ->where('amount', $buyOrder->amount) // Full match only
            ->orderBy('price', 'asc')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->first();

        if (!$sellOrder) {
            return ['matched' => false];
        }

        return $this->executeMatch($buyOrder, $sellOrder);
    }

    protected function matchSellOrder(Order $sellOrder): array
    {
        // Find first matching buy order where buy.price >= sell.price
        $buyOrder = Order::where('symbol', $sellOrder->symbol)
            ->where('side', 'buy')
            ->where('status', Order::STATUS_OPEN)
            ->where('user_id', '!=', $sellOrder->user_id) // Can't match own orders
            ->where('price', '>=', $sellOrder->price)
            ->where('amount', $sellOrder->amount) // Full match only
            ->orderBy('price', 'desc')
            ->orderBy('created_at', 'asc')
            ->lockForUpdate()
            ->first();

        if (!$buyOrder) {
            return ['matched' => false];
        }

        return $this->executeMatch($buyOrder, $sellOrder);
    }

    protected function executeMatch(Order $buyOrder, Order $sellOrder): array
    {
        // Use sell order price for execution (maker price)
        $executionPrice = $sellOrder->price;
        $amount = $sellOrder->amount;
        $total = bcmul($executionPrice, $amount, 8);

        // Calculate commission (1.5% of total USD value)
        $commission = bcmul($total, self::COMMISSION_RATE, 8);

        // Lock users
        $buyer = User::lockForUpdate()->find($buyOrder->user_id);
        $seller = User::lockForUpdate()->find($sellOrder->user_id);

        // Get or create buyer's asset
        $buyerAsset = Asset::lockForUpdate()->firstOrCreate(
            ['user_id' => $buyer->id, 'symbol' => $buyOrder->symbol],
            ['amount' => 0, 'locked_amount' => 0]
        );

        // Get seller's asset
        $sellerAsset = Asset::lockForUpdate()
            ->where('user_id', $seller->id)
            ->where('symbol', $sellOrder->symbol)
            ->first();

        // Calculate price difference refund for buyer (if buy price > execution price)
        $buyerLockedTotal = bcmul($buyOrder->price, $buyOrder->amount, 8);
        $actualCost = bcadd($total, $commission, 8); // total + commission
        $refund = bcsub($buyerLockedTotal, $actualCost, 8);

        // Refund any difference to buyer
        if (bccomp($refund, '0', 8) > 0) {
            $buyer->balance = bcadd($buyer->balance, $refund, 8);
        }
        $buyer->save();

        // Credit seller with USD (minus no commission for seller in this implementation)
        $seller->balance = bcadd($seller->balance, $total, 8);
        $seller->save();

        // Transfer asset: decrease seller's locked and amount, increase buyer's amount
        $sellerAsset->amount = bcsub($sellerAsset->amount, $amount, 8);
        $sellerAsset->locked_amount = bcsub($sellerAsset->locked_amount, $amount, 8);
        $sellerAsset->save();

        $buyerAsset->amount = bcadd($buyerAsset->amount, $amount, 8);
        $buyerAsset->save();

        // Update order statuses
        $buyOrder->status = Order::STATUS_FILLED;
        $buyOrder->locked_usd = 0;
        $buyOrder->save();

        $sellOrder->status = Order::STATUS_FILLED;
        $sellOrder->save();

        // Create trade record
        $trade = Trade::create([
            'buy_order_id' => $buyOrder->id,
            'sell_order_id' => $sellOrder->id,
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'symbol' => $buyOrder->symbol,
            'price' => $executionPrice,
            'amount' => $amount,
            'total' => $total,
            'commission' => $commission,
        ]);

        // Broadcast match event to both parties
        $this->broadcastMatch($trade, $buyer, $seller, $buyOrder, $sellOrder);

        return [
            'matched' => true,
            'trade' => $this->formatTrade($trade),
        ];
    }

    public function cancelOrder(Order $order): void
    {
        $user = User::lockForUpdate()->find($order->user_id);

        if ($order->isBuy()) {
            // Refund locked USD
            $user->balance = bcadd($user->balance, $order->locked_usd, 8);
            $user->save();
        } else {
            // Release locked asset
            $asset = Asset::lockForUpdate()
                ->where('user_id', $user->id)
                ->where('symbol', $order->symbol)
                ->first();

            if ($asset) {
                $asset->locked_amount = bcsub($asset->locked_amount, $order->amount, 8);
                $asset->save();
            }
        }

        $order->status = Order::STATUS_CANCELLED;
        $order->locked_usd = 0;
        $order->save();
    }

    protected function broadcastMatch(Trade $trade, User $buyer, User $seller, Order $buyOrder, Order $sellOrder): void
    {
        // Reload users with assets
        $buyer->load('assets');
        $seller->load('assets');

        // Broadcast to buyer
        event(new OrderMatched(
            $buyer->id,
            $this->formatTrade($trade),
            [
                'balance' => $buyer->balance,
                'assets' => $buyer->assets->map(fn($a) => [
                    'symbol' => $a->symbol,
                    'amount' => $a->amount,
                    'locked_amount' => $a->locked_amount,
                ]),
            ],
            $this->formatOrder($buyOrder)
        ));

        // Broadcast to seller
        event(new OrderMatched(
            $seller->id,
            $this->formatTrade($trade),
            [
                'balance' => $seller->balance,
                'assets' => $seller->assets->map(fn($a) => [
                    'symbol' => $a->symbol,
                    'amount' => $a->amount,
                    'locked_amount' => $a->locked_amount,
                ]),
            ],
            $this->formatOrder($sellOrder)
        ));
    }

    protected function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
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

    protected function formatTrade(Trade $trade): array
    {
        return [
            'id' => $trade->id,
            'symbol' => $trade->symbol,
            'price' => $trade->price,
            'amount' => $trade->amount,
            'total' => $trade->total,
            'commission' => $trade->commission,
            'created_at' => $trade->created_at->toIso8601String(),
        ];
    }
}
