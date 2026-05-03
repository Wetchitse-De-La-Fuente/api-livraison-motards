<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Course;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class ClientLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $course;
    public int $courseId;

    public function __construct(Course $course)
    {
        $course->loadMissing(['client', 'motard']);

        $this->courseId = $course->id;

        $this->course = [
            'id' => $course->id,
            'client_id' => $course->client_id,
            'motard_id' => $course->motard_id,
            'client_location_shared' => (bool) $course->client_location_shared,
            'client_current_latitude' => $course->client_current_latitude,
            'client_current_longitude' => $course->client_current_longitude,
            'status' => $course->status,
            'updated_at' => $course->updated_at,
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('course.' . $this->courseId);
    }

    public function broadcastAs(): string
    {
        return 'client.location.updated';
    }
}