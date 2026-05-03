<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Utilisateur;

class Notification extends Model
{
    protected $fillable = ['utilisateur_id','message','type','is_read'];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }
}