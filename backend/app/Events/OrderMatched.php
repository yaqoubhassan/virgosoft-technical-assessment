<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $userId;
    public array $trade;
    public array $wallet;
    public array $order;

    public function __construct(int $userId, array $trade, array $wallet, array $order)
    {
        $this->userId = $userId;
        $this->trade = $trade;
        $this->wallet = $wallet;
        $this->order = $order;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'order.matched';
    }

    public function broadcastWith(): array
    {
        return [
            'trade' => $this->trade,
            'wallet' => $this->wallet,
            'order' => $this->order,
        ];
    }
}
