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
        'order_id', 'order_menu_item_id', 'locked_price', 'status', 'specifications', 'due_date'
    ];

    protected $casts = [
        'specifications' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderMenuItem(): BelongsTo {
        return $this->belongsTo(OrderMenuItem::class, 'order_menu_item_id');
    }

    // Many-to-Many connection mapping out team assignees
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'order_item_assignee', 'order_item_id', 'user_id')
            ->withTimestamps();
    }
}