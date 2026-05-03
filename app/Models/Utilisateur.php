<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use App\Models\Course;
use App\Models\Notification;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Utilisateur extends Authenticatable implements JWTSubject
{
    use Notifiable;

    protected $table = 'utilisateurs';

    protected $fillable = ['pseudo','email','telephone','mot_de_passe','role','is_blocked','is_online','current_latitude','current_longitude'];
    protected $hidden = ['mot_de_passe','remember_token'];

    protected $casts = [
        'is_blocked' => 'boolean',
        'is_online' => 'boolean',
        'current_latitude' => 'decimal:7',
        'current_longitude' => 'decimal:7',
    ];

    public function setMotDePasseAttribute($value)
    {
        $this->attributes['mot_de_passe'] = Hash::make($value);
    }

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    // Role helpers
    public function isAdmin()	{ return $this->role === 'admin'; }
    public function isClient()	{ return $this->role === 'client'; }
    public function isMotard()	{ return $this->role === 'motard'; }

    public function clientCourses()	{ return $this->hasMany(Course::class, 'client_id'); }

    public function motardCourses()	{ return $this->hasMany(Course::class, 'motard_id'); }

    public function notifications()	{ return $this->hasMany(Notification::class, 'utilisateur_id'); }

    public function getJWTIdentifier() { return $this->getKey(); }

    public function getJWTCustomClaims() {
        return [
            'role' => $this->role
        ];
    }
}