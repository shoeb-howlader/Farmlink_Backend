<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    /**
     * Determine whether the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view all orders in admin management.
     */
    public function viewAdmin(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'data_entry_operator']);
    }

    /**
     * Determine whether the user can view the order.
     * A farmer can only view their own orders; admin and DEO can view any.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->hasAnyRole(['admin', 'data_entry_operator']);
    }

    /**
     * Determine whether the user can create orders.
     * A farmer, admin, or DEO can create orders.
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['farmer', 'admin', 'data_entry_operator']);
    }

    /**
     * Determine whether the user can update the order.
     * Only an admin can update orders.
     */
    public function update(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can change order status.
     * Only an admin can change order status.
     */
    public function updateStatus(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the order.
     */
    public function delete(User $user, Order $order): bool
    {
        return $user->hasRole('admin');
    }
}
