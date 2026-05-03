<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Utilisateur;
use App\Events\MotardLocationUpdated;
use App\Events\MotardStatusChanged;

class UtilisateurController extends Controller
{
    public function index()
    {
        return response()->json(Utilisateur::all());
    }

    public function show($id)
    {
        return response()->json(Utilisateur::findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pseudo' => 'required|string|max:255',
            'email' => 'required|email|unique:utilisateurs,email',
            'telephone' => 'nullable|string|max:50',
            'mot_de_passe' => 'required|min:4',
            'role' => 'required|in:admin,client,motard',
        ]);

        $user = Utilisateur::create($data);

        return response()->json($user, 201);
    }

    public function update(Request $request, $id)
    {
        $user = Utilisateur::findOrFail($id);

        $data = $request->validate([
            'pseudo' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:utilisateurs,email,' . $user->id,
            'telephone' => 'nullable|string|max:50',
            'mot_de_passe' => 'nullable|min:4',
            'role' => 'sometimes|in:admin,client,motard',
            'is_blocked' => 'sometimes|boolean',
            'is_online' => 'sometimes|boolean',
            'current_latitude' => 'nullable|numeric',
            'current_longitude' => 'nullable|numeric',
        ]);

        if (isset($data['mot_de_passe']) && empty($data['mot_de_passe'])) {
            unset($data['mot_de_passe']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Utilisateur mis à jour',
            'user' => $user
        ]);
    }

    public function destroy($id)
    {
        $user = Utilisateur::findOrFail($id);
        $user->delete();

        return response()->json(null, 204);
    }

    // =========================
    // BLOQUER / DÉBLOQUER
    // =========================
    public function toggleBlock($id)
    {
        $user = Utilisateur::findOrFail($id);

        $user->is_blocked = !$user->is_blocked;
        $user->save();

        return response()->json([
            'message' => $user->is_blocked ? 'Utilisateur bloqué' : 'Utilisateur débloqué'
        ]);
    }

    public function toggleOnline(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$user->isMotard()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $data = $request->validate([
            'is_online' => 'required|boolean',
            'current_latitude' => 'nullable|numeric',
            'current_longitude' => 'nullable|numeric',
        ]);

        $user->update($data);
        $user->refresh();

        broadcast(new MotardStatusChanged($user))->toOthers();

        return response()->json([
            'message' => $user->is_online ? 'Motard en ligne' : 'Motard hors ligne',
            'user' => $user
        ]);
    }

    public function updateLocation(Request $request)
    {
        $user = auth()->user();

        if (!$user || !$user->isMotard()) {
            return response()->json(['message' => 'Accès refusé'], 403);
        }

        $data = $request->validate([
            'current_latitude' => 'required|numeric',
            'current_longitude' => 'required|numeric',
        ]);

        $user->update($data);
        $user->refresh();

        broadcast(new MotardLocationUpdated($user))->toOthers();

        return response()->json([
            'message' => 'Position mise à jour',
            'user' => $user
        ]);
    }

    public function motardsOnline()
    {
        $motards = Utilisateur::where('role', 'motard')
            ->where('is_online', true)
            ->where('is_blocked', false)
            ->get();

        return response()->json($motards);
    }
}