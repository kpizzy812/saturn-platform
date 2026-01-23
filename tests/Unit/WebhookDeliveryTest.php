<?php

use App\Models\WebhookDelivery;

it('checks if delivery is success', function () {
    $delivery = new WebhookDelivery;

    $delivery->status = 'success';
    expect($delivery->isSuccess())->toBeTrue();
    expect($delivery->isFailed())->toBeFalse();
    expect($delivery->isPending())->toBeFalse();
});

it('checks if delivery is failed', function () {
    $delivery = new WebhookDelivery;

    $delivery->status = 'failed';
    expect($delivery->isFailed())->toBeTrue();
    expect($delivery->isSuccess())->toBeFalse();
    expect($delivery->isPending())->toBeFalse();
});

it('checks if delivery is pending', function () {
    $delivery = new WebhookDelivery;

    $delivery->status = 'pending';
    expect($delivery->isPending())->toBeTrue();
    expect($delivery->isSuccess())->toBeFalse();
    expect($delivery->isFailed())->toBeFalse();
});

it('casts payload to array', function () {
    $delivery = new WebhookDelivery;
    $delivery->payload = ['event' => 'test', 'data' => ['key' => 'value']];

    expect($delivery->payload)->toBeArray();
    expect($delivery->payload)->toHaveKey('event');
    expect($delivery->payload['event'])->toBe('test');
});

it('casts status_code to integer', function () {
    $delivery = new WebhookDelivery;
    $delivery->status_code = '200';

    expect($delivery->status_code)->toBeInt();
    expect($delivery->status_code)->toBe(200);
});

it('casts response_time_ms to integer', function () {
    $delivery = new WebhookDelivery;
    $delivery->response_time_ms = '150';

    expect($delivery->response_time_ms)->toBeInt();
    expect($delivery->response_time_ms)->toBe(150);
});

it('casts attempts to integer', function () {
    $delivery = new WebhookDelivery;
    $delivery->attempts = '3';

    expect($delivery->attempts)->toBeInt();
    expect($delivery->attempts)->toBe(3);
});
