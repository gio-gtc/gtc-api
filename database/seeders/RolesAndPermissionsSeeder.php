<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear cache to prevent errors when seeding
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Create Permissions (The Keys)
        $permissions = [
            'GTC - View Management Dashboard',
            'GTC - Manage Contacts',
            'GTC - Manage Clients/Suppliers',
            'GTC - Manage Orders',
            'GTC - Accounts Receivable',
            'GTC - Accounts Payable',
            'GTC - GTC Force Administration',
            'GTC - Sales',
            'Client - Place Order',
            'Client - Add Client Contact',
            'Supplier Screens',
            'GTC - Purchasing',
            'GTC - Allow Manual Purchase Order',
            'Client - Approve Material',
            'Client - View Billing',
            'GTC - Manage Tours',
            'Agent - Tour Management',
            'GTC - Worklist',
            'GTC - View Billing',
            'Print Control Center',
            'GTC - Items into production after shipped'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // 3. Create Roles (The Keyrings)
        Role::firstOrCreate(['name' => 'Super Admin']);

        // Create Admin and assign specific permissions
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $adminRole->givePermissionTo([
            'GTC - Manage Contacts', 
            'GTC - Manage Clients/Suppliers',
            'GTC - Manage Orders',
            'GTC - GTC Force Administration'
        ]);

        Role::firstOrCreate(['name' => 'Supervisor']);
        Role::firstOrCreate(['name' => 'Designer']);
        Role::firstOrCreate(['name' => 'Client']);
    }
}