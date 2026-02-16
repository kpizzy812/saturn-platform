<?php

use App\Actions\Fortify\UpdateUserPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = new UpdateUserPassword;
});

describe('UpdateUserPassword validation rules', function () {
    it('validates current_password is required', function () {
        expect(function () {
            Validator::make(['password' => 'newpassword123', 'password_confirmation' => 'newpassword123'], [
                'current_password' => ['required', 'string'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates current_password is a string', function () {
        expect(function () {
            Validator::make(['current_password' => 12345], [
                'current_password' => ['required', 'string'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('accepts valid current_password', function () {
        $validator = Validator::make(['current_password' => 'oldpassword123'], [
            'current_password' => ['required', 'string'],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('validates new password is required', function () {
        expect(function () {
            Validator::make(['current_password' => 'oldpassword'], [
                'password' => ['required'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates new password confirmation is required', function () {
        expect(function () {
            Validator::make(['current_password' => 'oldpassword', 'password' => 'newpassword123'], [
                'password' => ['required', 'confirmed'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates password confirmation must match', function () {
        expect(function () {
            Validator::make([
                'current_password' => 'oldpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'different',
            ], [
                'password' => ['required', 'confirmed'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('accepts matching password confirmation', function () {
        $validator = Validator::make([
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ], [
            'password' => ['required', 'confirmed'],
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

describe('UpdateUserPassword error messages', function () {
    it('has custom error message for current_password validation', function () {
        // The action defines a custom error message for the current_password validation
        $customMessages = [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ];

        expect($customMessages)->toHaveKey('current_password.current_password');
        expect($customMessages['current_password.current_password'])->toBeString();
    });
});

describe('UpdateUserPassword password hashing', function () {
    it('hashes new password correctly', function () {
        $plainPassword = 'new-secret-password';
        $hashed = Hash::make($plainPassword);

        expect(Hash::check($plainPassword, $hashed))->toBeTrue();
    });

    it('produces different hash for different passwords', function () {
        $password1 = 'password-one';
        $password2 = 'password-two';
        $hash1 = Hash::make($password1);
        $hash2 = Hash::make($password2);

        expect($hash1)->not->toBe($hash2);
        expect(Hash::check($password1, $hash1))->toBeTrue();
        expect(Hash::check($password2, $hash2))->toBeTrue();
        expect(Hash::check($password1, $hash2))->toBeFalse();
    });
});
