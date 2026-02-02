<?php

use App\Services\AI\CodeReview\Detectors\DangerousFunctionsDetector;
use App\Services\AI\CodeReview\DTOs\DiffLine;
use App\Services\AI\CodeReview\DTOs\DiffResult;

/**
 * Create a DiffResult with the given added lines for a specific file.
 */
function createFileDiff(array $lines, string $filename = 'src/test.php'): DiffResult
{
    $diffLines = collect($lines)->map(
        fn ($content, $i) => new DiffLine(
            file: $filename,
            number: $i + 1,
            content: $content,
            type: 'added'
        )
    );

    return new DiffResult(
        commitSha: 'abc123',
        baseCommitSha: 'def456',
        files: collect([]),
        addedLines: $diffLines,
        totalAdditions: count($lines),
        totalDeletions: 0,
        rawDiff: '',
    );
}

describe('DangerousFunctionsDetector', function () {
    beforeEach(function () {
        $this->detector = new DangerousFunctionsDetector;
    });

    describe('Shell execution (PHP)', function () {
        it('detects exec() function', function () {
            $diff = createFileDiff(['$output = exec($command);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC004')
                ->and($violations->first()->severity)->toBe('high')
                ->and($violations->first()->message)->toContain('exec');
        });

        it('detects shell_exec() function', function () {
            $diff = createFileDiff(['$result = shell_exec("ls -la");']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC004');
        });

        it('detects system() function', function () {
            $diff = createFileDiff(['system($userInput);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC004');
        });

        it('detects passthru() function', function () {
            $diff = createFileDiff(['passthru($cmd);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC004');
        });

        it('detects backtick operator with variable', function () {
            $diff = createFileDiff(['$output = `ls $dir`;']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC004')
                ->and($violations->first()->message)->toContain('backtick');
        });
    });

    describe('Code evaluation (PHP)', function () {
        it('detects eval() function', function () {
            $diff = createFileDiff(['eval($code);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC005')
                ->and($violations->first()->message)->toContain('eval');
        });

        it('detects create_function()', function () {
            $diff = createFileDiff(['$fn = create_function(\'$x\', \'return $x * 2;\');']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC005')
                ->and($violations->first()->message)->toContain('create_function');
        });

        it('detects preg_replace with /e modifier', function () {
            $diff = createFileDiff(['$result = preg_replace("/test/e", $code, $input);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC005');
        });

        it('detects assert() with variable', function () {
            $diff = createFileDiff(['assert($userCondition);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC005');
        });
    });

    describe('Unsafe deserialization (PHP)', function () {
        it('detects unserialize() with variable', function () {
            $diff = createFileDiff(['$obj = unserialize($userInput);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC006')
                ->and($violations->first()->message)->toContain('unserialize');
        });
    });

    describe('SQL injection risks (PHP)', function () {
        it('detects raw SQL query with variable interpolation', function () {
            $diff = createFileDiff(['$db->query("SELECT * FROM users WHERE id = $id");']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC009')
                ->and($violations->first()->severity)->toBe('medium');
        });

        it('detects DB::raw with variable', function () {
            $diff = createFileDiff(['$result = DB::raw("COUNT($column)");']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC009');
        });

        it('detects whereRaw with variable', function () {
            $diff = createFileDiff(['$query->whereRaw("status = $status");']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC009');
        });
    });

    describe('File inclusion risks (PHP)', function () {
        it('detects dynamic include with variable', function () {
            $diff = createFileDiff(['include($file);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC010');
        });

        it('detects dynamic require_once with variable', function () {
            $diff = createFileDiff(['require_once($path);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC010');
        });
    });

    describe('JavaScript dangerous functions', function () {
        it('detects eval() in JavaScript', function () {
            $diff = createFileDiff(['eval(userInput);'], 'src/app.js');

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC011')
                ->and($violations->first()->message)->toContain('eval');
        });

        it('detects Function constructor', function () {
            $diff = createFileDiff(['const fn = new Function("return " + code);'], 'src/app.ts');

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC011');
        });

        it('detects innerHTML concatenation', function () {
            $diff = createFileDiff(['element.innerHTML = "<div>" + userContent + "</div>";'], 'src/app.jsx');

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC011')
                ->and($violations->first()->message)->toContain('innerHTML');
        });

        it('detects document.write', function () {
            $diff = createFileDiff(['document.write(content);'], 'src/app.js');

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1)
                ->and($violations->first()->ruleId)->toBe('SEC011');
        });
    });

    describe('False positive handling', function () {
        it('ignores test files', function () {
            $diff = createFileDiff(['eval($code);'], 'tests/EvalTest.php');

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });

        it('ignores vendor directory', function () {
            $diff = createFileDiff(['exec($cmd);'], 'vendor/package/file.php');

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });

        it('ignores node_modules directory', function () {
            $diff = createFileDiff(['eval(code);'], 'node_modules/package/index.js');

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });

        it('ignores comment lines', function () {
            $diff = createFileDiff([
                '// exec($cmd);',
                '# system($input);',
            ]);

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });

        it('allows Laravel Process class (safe wrapper)', function () {
            $diff = createFileDiff(['Process::run($command);', '$process->run();']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });

        it('allows Symfony Process (safe wrapper)', function () {
            $diff = createFileDiff(['$process = new Process($command);']);

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });
    });

    describe('Detector metadata', function () {
        it('returns correct name', function () {
            expect($this->detector->getName())->toBe('DangerousFunctionsDetector');
        });

        it('returns version string', function () {
            expect($this->detector->getVersion())->toMatch('/^\d+\.\d+\.\d+$/');
        });

        it('respects configuration for enabled state', function () {
            config(['ai.code_review.detectors.dangerous_functions' => true]);
            expect($this->detector->isEnabled())->toBeTrue();

            config(['ai.code_review.detectors.dangerous_functions' => false]);
            expect($this->detector->isEnabled())->toBeFalse();
        });
    });

    describe('Language detection', function () {
        it('applies PHP rules to .php files', function () {
            $diff = createFileDiff(['eval($code);'], 'app/Service.php');

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1);
        });

        it('applies JavaScript rules to .js files', function () {
            $diff = createFileDiff(['eval(code);'], 'src/app.js');

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1);
        });

        it('applies JavaScript rules to .tsx files', function () {
            $diff = createFileDiff(['eval(code);'], 'src/Component.tsx');

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1);
        });

        it('applies JavaScript rules to .vue files', function () {
            $diff = createFileDiff(['eval(code);'], 'src/App.vue');

            $violations = $this->detector->detect($diff);

            expect($violations)->toHaveCount(1);
        });

        it('ignores non-code files', function () {
            $diff = createFileDiff(['eval($code);'], 'README.md');

            $violations = $this->detector->detect($diff);

            expect($violations)->toBeEmpty();
        });
    });
});
