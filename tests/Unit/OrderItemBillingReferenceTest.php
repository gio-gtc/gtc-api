<?php

namespace Tests\Unit;

use App\Models\OrderItem;
use App\Models\OrderItemBroadcastSpecs;
use App\Models\OrderItemKeyArtSpecs;
use App\Support\OrderItemBillingReference;
use Tests\TestCase;

class OrderItemBillingReferenceTest extends TestCase
{
    public function test_media_line_formats_type_cut_and_duration(): void
    {
        $item = new OrderItem(['id' => 1]);
        $item->setRelation('specifiable', new OrderItemBroadcastSpecs([
            'type' => 'Generic',
            'cut' => 'On Sale Now',
            'duration_seconds' => '30',
        ]));

        $this->assertSame(
            'Generic On Sale Now :30',
            OrderItemBillingReference::fromOrderItem($item),
        );
    }

    public function test_key_art_line_formats_type_and_dimensions(): void
    {
        $item = new OrderItem(['id' => 99]);
        $item->setRelation('specifiable', new OrderItemKeyArtSpecs([
            'type' => 'Key Art Package',
            'w' => '1920',
            'h' => '1080',
        ]));

        $this->assertSame(
            'Key Art Package 1920×1080',
            OrderItemBillingReference::fromOrderItem($item),
        );
    }
}
