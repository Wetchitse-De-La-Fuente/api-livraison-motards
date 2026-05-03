<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Notification;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class UserNotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $notification;
    public int $userId;

    public function __construct(Notification $notification)
    {
        $notification->loadMissing('utilisateur');

        $this->userId = $notification->utilisateur_id;

        $this->notification = [
            'id' => $notification->id,
            'utilisateur_id' => $notification->utilisateur_id,
            'message' => $notification->message,
            'type' => $notification->type,
            'is_read' => (bool) $notification->is_read,
            'created_at' => $notification->created_at,
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.' . $this->userId);
    }

    public function broadcastAs(): string
    {
        return 'user.notification.created';
    }
}