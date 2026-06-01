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
        'order_id', 
        'order_menu_item_id', 
        'locked_price', 
        'status',
        'specifications', 
        'due_date',
        'root_order_item_id',
        'revision_number',
        'supersedes_order_item_id',
        'invoice_line_id'
    ];

    protected $casts = [
        'specifications' => 'array',
    ];

    protected static function booted()
    {
        // Enforce Title Case on entry strings
        static::saving(function (OrderItem $item) {
            if ($item->status) {
                $item->status = ucwords(strtolower($item->status));
            }
        });

        // Generate the automated unique serial tracking code after database insert
        static::created(function (OrderItem $item) {
            $currentSpecs = $item->specifications ?? [];
            
            // Generate GTC + 6-digit zero-padded primary key ID string
            $paddedId = str_pad($item->id, 6, "0", STR_PAD_LEFT);
            $currentSpecs['isci'] = "GTC" . $paddedId;
            
            // Assign back and write silently to the storage cluster
            $item->specifications = $currentSpecs;
            $item->saveQuietly();
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderMenuItem(): BelongsTo 
    {
        return $this->belongsTo(OrderMenuItem::class, 'order_menu_item_id');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'order_item_assignee', 'order_item_id', 'user_id')
            ->withTimestamps();
    }
}