<?php

use App\Traits\FormatHelpers;

// Anonymous class using the trait to test protected methods
class FormatHelpersTestClass
{
    use FormatHelpers;

    public function testFormatBytes(int $bytes): string
    {
        return $this->formatBytes($bytes);
    }

    public function testFormatSeconds(int $seconds): string
    {
        return $this->formatSeconds($seconds);
    }
}

beforeEach(function () {
    $this->testClass = new FormatHelpersTestClass;
});

it('formats zero bytes', function () {
    expect($this->testClass->testFormatBytes(0))->toBe('0 B');
});

it('formats bytes less than 1KB', function () {
    expect($this->testClass->testFormatBytes(512))->toBe('512 B');
    expect($this->testClass->testFormatBytes(1023))->toBe('1023 B');
});

it('formats bytes in KB range', function () {
    expect($this->testClass->testFormatBytes(1024))->toBe('1 KB');
    expect($this->testClass->testFormatBytes(1536))->toBe('1.5 KB');
    expect($this->testClass->testFormatBytes(2048))->toBe('2 KB');
});

it('formats bytes in MB range', function () {
    expect($this->testClass->testFormatBytes(1048576))->toBe('1 MB');
    expect($this->testClass->testFormatBytes(1572864))->toBe('1.5 MB');
    expect($this->testClass->testFormatBytes(5242880))->toBe('5 MB');
});

it('formats bytes in GB range', function () {
    expect($this->testClass->testFormatBytes(1073741824))->toBe('1 GB');
    expect($this->testClass->testFormatBytes(2147483648))->toBe('2 GB');
    expect($this->testClass->testFormatBytes(5368709120))->toBe('5 GB');
});

it('formats bytes in TB range', function () {
    expect($this->testClass->testFormatBytes(1099511627776))->toBe('1 TB');
    expect($this->testClass->testFormatBytes(2199023255552))->toBe('2 TB');
});

it('formats bytes with proper rounding', function () {
    expect($this->testClass->testFormatBytes(1536))->toBe('1.5 KB');
    expect($this->testClass->testFormatBytes(1638))->toBe('1.6 KB');
    expect($this->testClass->testFormatBytes(1843))->toBe('1.8 KB');
});

it('formats zero seconds', function () {
    expect($this->testClass->testFormatSeconds(0))->toBe('0s');
});

it('formats seconds less than 60', function () {
    expect($this->testClass->testFormatSeconds(30))->toBe('30s');
    expect($this->testClass->testFormatSeconds(59))->toBe('59s');
});

it('formats exactly 60 seconds as minutes', function () {
    expect($this->testClass->testFormatSeconds(60))->toBe('1m');
});

it('formats seconds between 60 and 3600 in minutes and seconds', function () {
    expect($this->testClass->testFormatSeconds(90))->toBe('1m 30s');
    expect($this->testClass->testFormatSeconds(125))->toBe('2m 5s');
    expect($this->testClass->testFormatSeconds(3599))->toBe('59m 59s');
});

it('formats minutes without seconds when seconds part is zero', function () {
    expect($this->testClass->testFormatSeconds(120))->toBe('2m');
    expect($this->testClass->testFormatSeconds(180))->toBe('3m');
    expect($this->testClass->testFormatSeconds(300))->toBe('5m');
});

it('formats exactly 3600 seconds as hours', function () {
    expect($this->testClass->testFormatSeconds(3600))->toBe('1h');
});

it('formats seconds greater than 3600 in hours and minutes', function () {
    expect($this->testClass->testFormatSeconds(3660))->toBe('1h 1m');
    expect($this->testClass->testFormatSeconds(7200))->toBe('2h');
    expect($this->testClass->testFormatSeconds(7380))->toBe('2h 3m');
    expect($this->testClass->testFormatSeconds(10800))->toBe('3h');
});

it('formats hours without minutes when minutes part is zero', function () {
    expect($this->testClass->testFormatSeconds(7200))->toBe('2h');
    expect($this->testClass->testFormatSeconds(10800))->toBe('3h');
    expect($this->testClass->testFormatSeconds(14400))->toBe('4h');
});

it('formats large hour values correctly', function () {
    expect($this->testClass->testFormatSeconds(86400))->toBe('24h');
    expect($this->testClass->testFormatSeconds(90000))->toBe('25h');
});

it('formats seconds ignoring remaining seconds when in hours', function () {
    // 3661 seconds = 1h 1m 1s, but we only show 1h 1m
    expect($this->testClass->testFormatSeconds(3661))->toBe('1h 1m');
    // 7325 seconds = 2h 2m 5s, but we only show 2h 2m
    expect($this->testClass->testFormatSeconds(7325))->toBe('2h 2m');
});
