<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Course;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

class CourseUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public array $course;
    public int $courseId;

    public function __construct(Course $course)
    {
        $course->loadMissing(['client', 'motard']);

        $this->courseId = $course->id;
        $this->course = $course->toArray();
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('course.' . $this->courseId);
    }

    public function broadcastAs(): string
    {
        return 'course.updated';
    }
}