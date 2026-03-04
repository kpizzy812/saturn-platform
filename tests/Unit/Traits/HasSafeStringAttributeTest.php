<?php

/**
 * Unit tests for HasSafeStringAttribute trait.
 *
 * Tests cover:
 * - setNameAttribute() — strips HTML tags from name
 * - setDescriptionAttribute() — strips HTML tags from description
 * - Plain text passes through unchanged
 * - Script injection is stripped
 */

use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Model;

// Concrete anonymous class using the trait
function makeModel(): Model
{
    return new class extends Model
    {
        use HasSafeStringAttribute;
    };
}

// ─── setNameAttribute() ───────────────────────────────────────────────────────

test('setNameAttribute strips script tags', function () {
    $model = makeModel();
    $model->name = '<script>alert("xss")</script>My App';
    expect($model->getAttributes()['name'])->toBe('alert("xss")My App');
});

test('setNameAttribute strips HTML bold tags', function () {
    $model = makeModel();
    $model->name = '<b>Bold Name</b>';
    expect($model->getAttributes()['name'])->toBe('Bold Name');
});

test('setNameAttribute strips img tag', function () {
    $model = makeModel();
    $model->name = '<img src="x" onerror="alert(1)">Name';
    expect($model->getAttributes()['name'])->toBe('Name');
});

test('setNameAttribute passes plain text through unchanged', function () {
    $model = makeModel();
    $model->name = 'My Production App';
    expect($model->getAttributes()['name'])->toBe('My Production App');
});

test('setNameAttribute preserves name with numbers and dashes', function () {
    $model = makeModel();
    $model->name = 'app-v2.0-prod';
    expect($model->getAttributes()['name'])->toBe('app-v2.0-prod');
});

test('setNameAttribute strips nested tags', function () {
    $model = makeModel();
    $model->name = '<div><span>Inner</span></div>';
    expect($model->getAttributes()['name'])->toBe('Inner');
});

// ─── setDescriptionAttribute() ────────────────────────────────────────────────

test('setDescriptionAttribute strips HTML tags', function () {
    $model = makeModel();
    $model->description = '<p>A <strong>great</strong> application</p>';
    expect($model->getAttributes()['description'])->toBe('A great application');
});

test('setDescriptionAttribute strips script tags', function () {
    $model = makeModel();
    $model->description = '<script>malicious()</script>Description';
    expect($model->getAttributes()['description'])->toBe('malicious()Description');
});

test('setDescriptionAttribute passes plain text through unchanged', function () {
    $model = makeModel();
    $model->description = 'A simple description with no HTML.';
    expect($model->getAttributes()['description'])->toBe('A simple description with no HTML.');
});

test('setDescriptionAttribute handles empty string', function () {
    $model = makeModel();
    $model->description = '';
    expect($model->getAttributes()['description'])->toBe('');
});

test('setDescriptionAttribute strips anchor tags', function () {
    $model = makeModel();
    $model->description = 'Visit <a href="http://evil.com">this site</a> now';
    expect($model->getAttributes()['description'])->toBe('Visit this site now');
});
