<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\OrderItemKeyArtSpecs;

/**
 * Billing reference string aligned with gtc-laravel orderItemBillingReference().
 * Media: "{type} {cut} {duration}" (duration as M:SS, e.g. :30, 1:00).
 * Key Art: "{type} {w}×{h}".
 */
class OrderItemBillingReference
{
    /**
     * Compile a clean format-aware descriptive string from an OrderItem configuration.
     */
    public static function fromOrderItem(OrderItem $item): string
    {
        $spec = $item->specifiable;
        $descriptionParts = [];
        
        if ($spec) {
            if (!empty($spec->type)) {
                $descriptionParts[] = $spec->type;
            }
            if (!empty($spec->cut)) {
                $descriptionParts[] = $spec->cut;
            }
            
            // Time Calculator Engine: Formats values >= 60 into clock formats (e.g. 90 -> 1:30)
            $duration = $spec->duration_seconds ?? $spec->duration ?? null;
            if (!empty($duration)) {
                if (is_numeric($duration)) {
                    $totalSeconds = (int) $duration;
                    if ($totalSeconds >= 60) {
                        $minutes = floor($totalSeconds / 60);
                        $remainingSeconds = $totalSeconds % 60;
                        $descriptionParts[] = $minutes . ':' . str_pad($remainingSeconds, 2, '0', STR_PAD_LEFT);
                    } else {
                        $descriptionParts[] = ":{$totalSeconds}";
                    }
                } else {
                    $descriptionParts[] = $duration;
                }
            }
        }

        // Return compiled string or drop back to the catalog name fallback
        return !empty($descriptionParts) 
            ? implode(' ', $descriptionParts) 
            : ($item->orderMenuItem?->name ?? 'Creative Production Asset Deliverable');
    }
}