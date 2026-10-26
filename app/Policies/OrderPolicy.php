<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('orders');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->hasPermission('orders');
    }

    public function viewInvoice(User $user, Order $order): bool
    {
        if (! $this->view($user, $order)) {
            return false;
        }

        return $user->id === $order->user_id || $user->hasPermission('invoices');
    }

    public function cancelAsCustomer(User $user, Order $order): bool
    {
        return $user->id === $order->user_id
            && $user->hasPermission('customer_orders')
            && in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_ACCEPTED], true)
            && ! $order->isDispatched();
    }

    public function transition(User $user, Order $order): bool
    {
        return $user->hasPermission('orders')
            && ! $order->isCancelled()
            && $order->status !== Order::STATUS_DELIVERED;
    }

    public function cancelAsShop(User $user, Order $order): bool
    {
        return $user->hasPermission('orders')
            && in_array($order->status, [
                Order::STATUS_PENDING,
                Order::STATUS_ACCEPTED,
                Order::STATUS_IN_TRANSIT,
            ], true);
    }

    public function recordReturn(User $user, Order $order): bool
    {
        // A physical return is only possible after the goods left the shop.
        // A shop cancellation does not bring dispatched goods back, so a
        // cancelled order that was already dispatched stays eligible; a
        // cancelled order that was never dispatched does not.
        return $user->hasPermission('inventory')
            && $order->isDispatched();
    }
}
