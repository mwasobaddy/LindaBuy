<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions for buyers
        $buyerPermissions = [
            'create-buy-orders',
            'view-buy-orders',
            'confirm-delivery',
            'release-payment',
        ];

        // Create permissions for sellers
        $sellerPermissions = [
            'create-sell-orders',
            'view-sell-orders',
            'manage-shop',
            'view-income',
        ];

        // Create permissions for agents
        $agentPermissions = [
            'verify-orders',
            'view-assigned-orders',
            'report-issues',
        ];

        // Create permissions for admins
        $adminPermissions = [
            'manage-users',
            'approve-sellers',
            'reject-sellers',
            'approve-agents',
            'reject-agents',
            'manage-orders',
            'view-orders',
            'release-payment-manual',
            'configure-settings',
            'view-admin-dashboard',
            'manage-fees',
            'view-activity-logs',
            'handle-failed-reversals',
            'confirm-g4s-pickup',
            'auto-release-orders',
            'manage-withdrawals',
        ];

        // Create all permissions
        foreach (array_merge($buyerPermissions, $sellerPermissions, $agentPermissions, $adminPermissions) as $permission) {
            Permission::findOrCreate($permission);
        }

        // Create roles
        $adminRole = Role::findOrCreate('admin');
        $buyerRole = Role::findOrCreate('buyer');
        $sellerRole = Role::findOrCreate('seller');
        $agentRole = Role::findOrCreate('agent');

        // Assign permissions to buyer role
        $buyerRole->syncPermissions($buyerPermissions);

        // Assign permissions to seller role
        $sellerRole->syncPermissions($sellerPermissions);

        // Assign permissions to agent role
        $agentRole->syncPermissions($agentPermissions);

        // Assign all permissions to admin role
        $adminRole->syncPermissions(array_merge(
            $buyerPermissions,
            $sellerPermissions,
            $agentPermissions,
            $adminPermissions
        ));
    }
}
