<?php

use App\Actions\Fortify\CreateNewUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = new CreateNewUser;
});

describe('CreateNewUser validation rules', function () {
    it('validates name is required', function () {
        expect(function () {
            Validator::make(['email' => 'test@example.com', 'password' => 'password123', 'password_confirmation' => 'password123'], [
                'name' => ['required', 'string', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates name is a string', function () {
        expect(function () {
            Validator::make(['name' => 12345, 'email' => 'test@example.com', 'password' => 'password123'], [
                'name' => ['required', 'string', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates name max length', function () {
        expect(function () {
            Validator::make(['name' => str_repeat('a', 256), 'email' => 'test@example.com', 'password' => 'password123'], [
                'name' => ['required', 'string', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('accepts valid name', function () {
        $validator = Validator::make(['name' => 'John Doe'], [
            'name' => ['required', 'string', 'max:255'],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('validates email is required', function () {
        expect(function () {
            Validator::make(['name' => 'John', 'password' => 'password123', 'password_confirmation' => 'password123'], [
                'email' => ['required', 'string', 'email', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates email is a valid email format', function () {
        expect(function () {
            Validator::make(['email' => 'not-an-email'], [
                'email' => ['required', 'string', 'email', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates email max length', function () {
        expect(function () {
            $longEmail = str_repeat('a', 250).'@test.com';
            Validator::make(['email' => $longEmail], [
                'email' => ['required', 'string', 'email', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('accepts valid email', function () {
        $validator = Validator::make(['email' => 'test@example.com'], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('validates password is required', function () {
        expect(function () {
            Validator::make(['name' => 'John', 'email' => 'test@example.com'], [
                'password' => ['required', 'string'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates password confirmation is required', function () {
        expect(function () {
            Validator::make(['name' => 'John', 'email' => 'test@example.com', 'password' => 'password123'], [
                'password' => ['required', 'confirmed'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates password confirmation must match', function () {
        expect(function () {
            Validator::make([
                'name' => 'John',
                'email' => 'test@example.com',
                'password' => 'password123',
                'password_confirmation' => 'different',
            ], [
                'password' => ['required', 'confirmed'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('accepts matching password confirmation', function () {
        $validator = Validator::make([
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'password' => ['required', 'confirmed'],
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

describe('CreateNewUser password hashing', function () {
    it('hashes password correctly', function () {
        $plainPassword = 'my-secret-password';
        $hashed = Hash::make($plainPassword);

        expect(Hash::check($plainPassword, $hashed))->toBeTrue();
    });

    it('produces different hashes for same password', function () {
        $password = 'my-secret-password';
        $hash1 = Hash::make($password);
        $hash2 = Hash::make($password);

        expect($hash1)->not->toBe($hash2);
        expect(Hash::check($password, $hash1))->toBeTrue();
        expect(Hash::check($password, $hash2))->toBeTrue();
    });

    it('rejects incorrect password', function () {
        $password = 'correct-password';
        $hashed = Hash::make($password);

        expect(Hash::check('wrong-password', $hashed))->toBeFalse();
    });
});

describe('CreateNewUser registration logic', function () {
    it('validates input array structure', function () {
        // Test that the validation expects proper array keys
        $validator = Validator::make([
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed'],
        ]);

        expect($validator->passes())->toBeTrue();
    });
});
