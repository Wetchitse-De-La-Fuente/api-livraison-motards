<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Utilisateur;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class MotardLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $motard;

    public function __construct(Utilisateur $motard)
    {
        $this->motard = [
            'id' => $motard->id,
            'pseudo' => $motard->pseudo,
            'telephone' => $motard->telephone,
            'is_online' => (bool) $motard->is_online,
            'current_latitude' => $motard->current_latitude,
            'current_longitude' => $motard->current_longitude,
        ];
    }

    public function broadcastOn(): Channel
    {
        return new Channel('motards.online');
    }

    public function broadcastAs(): string
    {
        return 'motard.location.updated';
    }
}