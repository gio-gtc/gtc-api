<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemKeyArtSpecs extends Model
{
    protected $table = 'order_item_key_art_specs';
    
    protected $fillable = ['type', 'w', 'h'];
}