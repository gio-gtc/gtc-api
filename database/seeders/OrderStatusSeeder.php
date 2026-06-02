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
        // Clear status tables before applying seeds to avoid constraint duplicates
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('order_status')->truncate(); 
        OrderItemStatus::truncate();
        OrderStatus::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Initialize the master parent Order Status options
        $newOrder     = OrderStatus::create(['name' => 'New Order']);
        $inProgress   = OrderStatus::create(['name' => 'In Progress']);
        $clientReview = OrderStatus::create(['name' => 'Client Review']);
        $complete     = OrderStatus::create(['name' => 'Complete']);
        $canceled     = OrderStatus::create(['name' => 'Canceled']);

        // Map individual child Order Item statuses directly to parent order rules
        OrderItemStatus::create(['name' => 'Still In Cart',    'order_status_id' => null]);
        OrderItemStatus::create(['name' => 'Unassigned',       'order_status_id' => $newOrder->id]);
        OrderItemStatus::create(['name' => 'In Production',    'order_status_id' => $inProgress->id]);
        OrderItemStatus::create(['name' => 'Client Review',    'order_status_id' => $clientReview->id]);
        OrderItemStatus::create(['name' => 'Out For Delivery', 'order_status_id' => $complete->id]);
        OrderItemStatus::create(['name' => 'Canceled',         'order_status_id' => $canceled->id]);
    }
}