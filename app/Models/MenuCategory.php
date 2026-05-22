<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuCategory extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $fillable = ['name'];

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }
}