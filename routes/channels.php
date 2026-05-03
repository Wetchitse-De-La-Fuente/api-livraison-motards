<?php

use Illuminate\Support\Facades\Broadcast;
use App\Models\Course;
use App\Models\Utilisateur;

Broadcast::channel('user.{id}', function (Utilisateur $user, int $id) {
    return $user->id === $id;
}, ['guards' => ['api']]);

Broadcast::channel('course.{courseId}', function (Utilisateur $user, int $courseId) {
    $course = Course::find($courseId);

    if (!$course) {
        return false;
    }

    return $user->isAdmin()
        || $course->client_id === $user->id
        || $course->motard_id === $user->id;
}, ['guards' => ['api']]);