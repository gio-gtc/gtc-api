<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderMenuCategory extends Model
{
    use HasFactory;

    protected $table = 'order_menu_categories';
    public $timestamps = false;
    protected $fillable = ['name'];

    public function orderMenuItems(): HasMany
    {
        return $this->hasMany(OrderMenuItem::class, 'order_menu_category_id');
    }
}