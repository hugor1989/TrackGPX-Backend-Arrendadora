<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DevicePositionUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $company_id,
        public int $vehicle_id,
        public float $latitude,
        public float $longitude,
        public float $speed,
        public float $heading,
        public bool $ignition,
        public string $timestamp,
    ) {}

    public function broadcastOn(): Channel
    {
        // Canal por empresa — cada empresa solo ve sus vehículos
        return new Channel('fleet.' . $this->company_id);
    }

    public function broadcastAs(): string
    {
        return 'position.updated';
    }
}