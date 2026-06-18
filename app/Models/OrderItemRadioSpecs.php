<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemRadioSpecs extends Model
{
    protected $fillable = ['type', 'cut', 'duration_seconds', 'language', 'isci'];
}