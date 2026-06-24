<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\OrderItemKeyArtSpecs;

/**
 * Billing reference string aligned with gtc-laravel orderItemBillingReference().
 * Media: "{type} {cut} {duration}" (duration as M:SS, e.g. :30, 1:00).
 * Key Art: "{type} {w}×{h}".
 */
final class OrderItemBillingReference
{
    public static function fromOrderItem(OrderItem $item): string
    {
        $spec = $item->specifiable;

        if ($spec instanceof OrderItemKeyArtSpecs) {
            $parts = [];
            if (! empty($spec->type)) {
                $parts[] = $spec->type;
            }
            if (! empty($spec->w) && ! empty($spec->h)) {
                $parts[] = $spec->w.'×'.$spec->h;
            }

            if ($parts !== []) {
                return implode(' ', $parts);
            }

            return 'Item '.$item->id;
        }

        $parts = [];
        if ($spec) {
            if (! empty($spec->type)) {
                $parts[] = $spec->type;
            }
            if (! empty($spec->cut)) {
                $parts[] = $spec->cut;
            }

            $duration = $spec->duration_seconds ?? $spec->duration ?? null;
            if ($duration !== null && $duration !== '') {
                $formatted = self::formatDurationForBilling($duration);
                if ($formatted !== '') {
                    $parts[] = $formatted;
                }
            }
        }

        if ($parts !== []) {
            return implode(' ', $parts);
        }

        return $item->orderMenuItem?->name ?? 'Creative Deliverable Asset Production Stop';
    }

    private static function formatDurationForBilling(mixed $duration): string
    {
        if (! is_numeric($duration)) {
            return trim((string) $duration);
        }

        $totalSeconds = (int) $duration;

        if ($totalSeconds >= 60) {
            $minutes = intdiv($totalSeconds, 60);
            $remainingSeconds = $totalSeconds % 60;

            return $minutes.':'.str_pad((string) $remainingSeconds, 2, '0', STR_PAD_LEFT);
        }

        return ':'.$totalSeconds;
    }
}
