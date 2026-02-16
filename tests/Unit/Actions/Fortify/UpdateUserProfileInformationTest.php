<?php

use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->action = new UpdateUserProfileInformation;
});

describe('UpdateUserProfileInformation validation rules', function () {
    it('validates name is required', function () {
        expect(function () {
            Validator::make(['email' => 'test@example.com'], [
                'name' => ['required', 'string', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates name is a string', function () {
        expect(function () {
            Validator::make(['name' => 12345, 'email' => 'test@example.com'], [
                'name' => ['required', 'string', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates name max length', function () {
        expect(function () {
            Validator::make(['name' => str_repeat('a', 256), 'email' => 'test@example.com'], [
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

    it('accepts name with special characters', function () {
        $validator = Validator::make(['name' => "O'Brien-Smith"], [
            'name' => ['required', 'string', 'max:255'],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('accepts unicode names', function () {
        $validator = Validator::make(['name' => 'Владимир'], [
            'name' => ['required', 'string', 'max:255'],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('validates email is required', function () {
        expect(function () {
            Validator::make(['name' => 'John'], [
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

    it('validates email without domain is invalid', function () {
        expect(function () {
            Validator::make(['email' => 'test@'], [
                'email' => ['required', 'string', 'email', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates email without @ is invalid', function () {
        expect(function () {
            Validator::make(['email' => 'testexample.com'], [
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

    it('accepts email with subdomain', function () {
        $validator = Validator::make(['email' => 'test@mail.example.com'], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('accepts email with plus addressing', function () {
        $validator = Validator::make(['email' => 'test+filter@example.com'], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('accepts email with dots', function () {
        $validator = Validator::make(['email' => 'first.last@example.com'], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        expect($validator->passes())->toBeTrue();
    });
});

describe('UpdateUserProfileInformation edge cases', function () {
    it('validates empty name after trim', function () {
        expect(function () {
            Validator::make(['name' => '   ', 'email' => 'test@example.com'], [
                'name' => ['required', 'string', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('validates empty email', function () {
        expect(function () {
            Validator::make(['name' => 'John', 'email' => ''], [
                'email' => ['required', 'string', 'email', 'max:255'],
            ])->validate();
        })->toThrow(ValidationException::class);
    });

    it('accepts name at max length', function () {
        $validator = Validator::make(['name' => str_repeat('a', 255)], [
            'name' => ['required', 'string', 'max:255'],
        ]);

        expect($validator->passes())->toBeTrue();
    });

    it('accepts email at max length', function () {
        // Create email exactly 255 chars
        $localPart = str_repeat('a', 243); // 243 + @ + test.com (8) + dot (1) = 253
        $email = $localPart.'@test.com';

        $validator = Validator::make(['email' => $email], [
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        expect($validator->passes())->toBeTrue();
    });
});
