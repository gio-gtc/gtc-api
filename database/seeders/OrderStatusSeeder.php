<?php

namespace Database\Seeders;

use App\Models\OrderItemStatus;
use App\Models\OrderStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('order_order_status')->delete(); 
        OrderItemStatus::query()->delete();
        OrderStatus::query()->delete();

        // Initialize the master parent Order Status options
        $newOrder     = OrderStatus::create(['name' => 'New Order']);
        $inProgress   = OrderStatus::create(['name' => 'In Progress']);
        $clientReview = OrderStatus::create(['name' => 'Client Review']);
        $revisionRequest = OrderStatus::create(['name' => 'Revision Request']);
        $complete     = OrderStatus::create(['name' => 'Complete']);
        $cancelled     = OrderStatus::create(['name' => 'Cancelled']);

        // Map individual child Order Item statuses directly to parent order rules
        OrderItemStatus::create(['name' => 'Still In Cart',    'order_status_id' => null]);
        OrderItemStatus::create(['name' => 'Unassigned',       'order_status_id' => $newOrder->id]);
        OrderItemStatus::create(['name' => 'In Production',    'order_status_id' => $inProgress->id]);
        OrderItemStatus::create(['name' => 'Client Review',    'order_status_id' => $clientReview->id]);
        OrderItemStatus::create(['name' => 'Revision Request',    'order_status_id' => $revisionRequest->id]);
        OrderItemStatus::create(['name' => 'Out For Delivery', 'order_status_id' => $complete->id]);
        OrderItemStatus::create(['name' => 'Cancelled',         'order_status_id' => $cancelled->id]);
    }
}