<?php

use App\Http\Requests\Api\CliAuth\ApproveCliAuthRequest;
use App\Http\Requests\Api\CliAuth\CheckCliAuthRequest;
use App\Http\Requests\Api\CliAuth\DenyCliAuthRequest;
use App\Models\CliAuthSession;
use Illuminate\Support\Facades\Validator;

test('check validates secret is required', function () {
    $rules = (new CheckCliAuthRequest)->rules();
    $validator = Validator::make([], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('secret'))->toBeTrue();
});

test('check validates secret must be exactly 40 characters', function () {
    $rules = (new CheckCliAuthRequest)->rules();
    $validator = Validator::make(['secret' => 'tooshort'], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('secret'))->toBeTrue();
});

test('approve validates code must be 9 characters', function () {
    $rules = (new ApproveCliAuthRequest)->rules();
    $validator = Validator::make(['code' => 'bad'], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('code'))->toBeTrue();
});

test('approve validates team_id is required', function () {
    $rules = (new ApproveCliAuthRequest)->rules();
    $validator = Validator::make(['code' => 'ABCD-EFGH'], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('team_id'))->toBeTrue();
});

test('deny validates code must be 9 characters', function () {
    $rules = (new DenyCliAuthRequest)->rules();
    $validator = Validator::make(['code' => 'bad'], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('code'))->toBeTrue();
});

test('deny validates code is required', function () {
    $rules = (new DenyCliAuthRequest)->rules();
    $validator = Validator::make([], $rules);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('code'))->toBeTrue();
});

test('CliAuthSession model has correct fillable fields for security', function () {
    $model = new CliAuthSession;
    $fillable = $model->getFillable();

    // Ensure no sensitive fields are fillable
    expect($fillable)->not->toContain('is_superadmin')
        ->not->toContain('platform_role');

    // Ensure required fields are present
    expect($fillable)->toContain('code')
        ->toContain('secret')
        ->toContain('status')
        ->toContain('ip_address')
        ->toContain('expires_at');
});
