<?php

use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$payload = json_decode(
    (string) file_get_contents($argv[1]),
    true,
    512,
    JSON_THROW_ON_ERROR,
);

file_put_contents($payload['ready'], 'ready');

$deadline = microtime(true) + 30;

while (! file_exists($payload['go'])) {
    if (microtime(true) > $deadline) {
        fwrite(STDERR, "Timed out waiting for the concurrency barrier.\n");
        exit(3);
    }

    usleep(5000);
}

try {
    /** @var OrderService $orders */
    $orders = $app->make(OrderService::class);

    $result = match ($payload['action']) {
        'place' => (static function () use ($orders, $payload): array {
            $customer = User::query()->findOrFail($payload['customer_id']);

            $order = $orders->place(
                customer: $customer,
                items: [[
                    'variant_id' => (int) $payload['variant_id'],
                    'quantity' => (int) $payload['quantity'],
                ]],
                deliveryAddress: 'কনকারেন্সি টেস্ট ঠিকানা, চট্টগ্রাম',
                lat: (float) $payload['lat'],
                lng: (float) $payload['lng'],
                idempotencyKey: $payload['idempotency_key'] ?? null,
            );

            return ['order_id' => $order->id, 'status' => $order->status];
        })(),

        'return' => (static function () use ($orders, $payload): array {
            $order = Order::query()->findOrFail($payload['order_id']);
            $actor = User::query()->findOrFail($payload['actor_user_id']);

            $batch = $orders->recordReturn(
                $order,
                $actor,
                $payload['lines'],
                (string) $payload['operation_key'],
                $payload['reason'] ?? null,
            );

            return [
                'batch_id' => $batch->id,
                'reference' => $batch->reference,
            ];
        })(),

        'accept' => (static function () use ($orders, $payload): array {
            $order = Order::query()->findOrFail($payload['order_id']);
            $actor = User::query()->findOrFail($payload['actor_user_id']);

            $updated = $orders->transition($order, Order::STATUS_ACCEPTED, $actor);

            return ['status' => $updated->status];
        })(),

        'cancel' => (static function () use ($orders, $payload): array {
            $order = Order::query()->findOrFail($payload['order_id']);
            $customer = User::query()->findOrFail($payload['customer_id']);

            $updated = $orders->cancelByCustomer($order, $customer);

            return ['status' => $updated->status, 'cancelled_by' => $updated->cancelled_by];
        })(),

        'expire' => (static function () use ($orders, $payload): array {
            return [
                'expired' => $orders->expireOrderReservations((int) $payload['order_id']),
            ];
        })(),

        default => throw new InvalidArgumentException('Unknown concurrency worker action.'),
    };

    $response = ['ok' => true, 'result' => $result];
} catch (Throwable $exception) {
    $response = [
        'ok' => false,
        'class' => $exception::class,
        'message' => $exception->getMessage(),
    ];

    if ($exception instanceof ValidationException) {
        $response['errors'] = $exception->errors();
    }
}

file_put_contents(
    $payload['output'],
    json_encode($response, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
);
