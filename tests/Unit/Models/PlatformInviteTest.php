<?php

use App\Models\PlatformInvite;

describe('PlatformInvite model', function () {

    it('is invalid when used_at is set', function () {
        $invite = new PlatformInvite;
        $invite->used_at = now();
        $invite->created_at = now();

        expect($invite->isValid())->toBeFalse();
    });

    it('is valid when unused and within expiration period', function () {
        $invite = new PlatformInvite;
        $invite->used_at = null;
        $invite->created_at = now();

        expect($invite->isValid())->toBeTrue();
    });

    it('is invalid when expired', function () {
        $invite = new PlatformInvite;
        $invite->used_at = null;
        $invite->created_at = now()->subDays(30);

        expect($invite->isValid())->toBeFalse();
    });

    it('generates correct link format', function () {
        $invite = new PlatformInvite;
        $invite->uuid = 'test-uuid-123';

        $link = $invite->getLink();

        expect($link)->toContain('/register?platform_invite=test-uuid-123');
    });

    it('has correct fillable fields', function () {
        $invite = new PlatformInvite;

        expect($invite->getFillable())->toBe([
            'uuid',
            'email',
            'created_by',
            'used_at',
        ]);
    });

    it('casts used_at to datetime', function () {
        $invite = new PlatformInvite;
        $casts = $invite->getCasts();

        expect($casts)->toHaveKey('used_at', 'datetime');
    });
});
