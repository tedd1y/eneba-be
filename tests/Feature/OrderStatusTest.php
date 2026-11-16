<?php

use App\Enums\OrderStatus;

it('knows which statuses can be paid', function (OrderStatus $status, bool $canBePaid, bool $isPaid) {
    expect($status->canBePaid())->toBe($canBePaid)
        ->and($status->isPaid())->toBe($isPaid);
})->with([
    'pending' => [OrderStatus::Pending, true, false],
    'failed' => [OrderStatus::Failed, true, false],
    'paid' => [OrderStatus::Paid, false, true],
    'completed' => [OrderStatus::Completed, false, true],
    'cancelled' => [OrderStatus::Cancelled, false, false],
]);
