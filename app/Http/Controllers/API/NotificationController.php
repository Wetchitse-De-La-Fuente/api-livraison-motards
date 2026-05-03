<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Events\UserNotificationCreated;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return response()->json(Notification::with('utilisateur')->latest()->get());
        }

        return response()->json(Notification::where('utilisateur_id', $user->id)->latest()->get());
    }

    public function show($id)
    {
        $notification = Notification::with('utilisateur')->findOrFail($id);
        $user = auth()->user();

        if (!$user->isAdmin() && $notification->utilisateur_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        return response()->json($notification);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'utilisateur_id' => 'required|exists:utilisateurs,id',
            'message' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'is_read' => 'sometimes|boolean',
        ]);

        $notification = Notification::create([
            'utilisateur_id' => $data['utilisateur_id'],
            'message' => $data['message'],
            'type' => $data['type'],
            'is_read' => $data['is_read'] ?? false,
        ]);

        broadcast(new UserNotificationCreated($notification))->toOthers();

        return response()->json($notification, 201);
    }

    public function update(Request $request, $id)
    {
        $notification = Notification::findOrFail($id);

        $data = $request->validate([
            'message' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|max:255',
            'is_read' => 'sometimes|boolean',
        ]);

        $notification->update($data);

        return response()->json([
            'message' => 'Notification mise à jour',
            'notification' => $notification
        ]);
    }

    public function destroy($id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json(null, 204);
    }

    public function markAsRead($id)
    {
        $notification = Notification::findOrFail($id);
        $user = auth()->user();

        if (!$user->isAdmin() && $notification->utilisateur_id !== $user->id) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $notification->update([
            'is_read' => true
        ]);

        return response()->json([
            'message' => 'Notification marquée comme lue',
            'notification' => $notification
        ]);
    }
}