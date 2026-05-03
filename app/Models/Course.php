<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Utilisateur;

class Course extends Model
{
    protected $fillable = [
        'client_id',
        'motard_id',
        'pickup_address',
        'delivery_address',
        'pickup_latitude',
        'pickup_longitude',
        'delivery_latitude',
        'delivery_longitude',
        'description',
        'distance_km',
        'duration_min',
        'waiting_minutes',
        'pickup_fee',
        'estimated_price',
        'final_price',
        'client_location_shared',
        'client_current_latitude',
        'client_current_longitude',
        'status',
    ];

    public function client()
    {
        return $this->belongsTo(Utilisateur::class, 'client_id');
    }

    public function motard()
    {
        return $this->belongsTo(Utilisateur::class, 'motard_id');
    }
}