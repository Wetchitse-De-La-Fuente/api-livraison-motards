<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'pseudo' => 'required|string|max:255',
            'email' => 'required|email|unique:utilisateurs,email',
            'telephone' => 'nullable|string|max:50',
            'mot_de_passe' => 'required|min:4',
        ]);

        $adminExiste = Utilisateur::where('role', 'admin')->exists();

        $data['role'] = $adminExiste ? 'client' : 'admin';

        $user = Utilisateur::create($data);

        return response()->json([
            'message' => $data['role'] === 'admin'
                ? 'Premier utilisateur créé en tant qu\'admin'
                : 'Client créé avec succès',
            'user' => $user
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'mot_de_passe' => 'required'
        ]);

        $user = Utilisateur::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['mot_de_passe'], $user->mot_de_passe)) {
            return response()->json(['message' => 'Identifiants incorrects'], 401);
        }

        if ($user->is_blocked) {
            return response()->json(['message' => 'Compte bloqué'], 403);
        }

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function me()
    {
        return response()->json(auth()->user());
    }

    public function logout()
    {
        auth()->logout();
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(['message' => 'Déconnexion réussie']);
    }
}