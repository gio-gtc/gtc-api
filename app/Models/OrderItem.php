<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'menu_item_id', 'price_locked', 
        'status', 'due_date', 'asset_url', 'mime_type', 'specifications'
    ];

    protected $casts = [
        'specifications' => 'array'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    // Many-to-Many connection mapping out team assignees
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'order_item_user');
    }
}