<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderMenuItem extends Model
{
    use HasFactory;

    protected $table = 'order_menu_items';
    protected $fillable = ['order_menu_category_id', 'name', 'form_blueprint', 'pricing_matrix'];
    protected $casts = [
        'form_blueprint' => 'array',
        'pricing_matrix' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(OrderMenuCategory::class, 'order_menu_category_id');
    }
}