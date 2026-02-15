<?php

use App\Traits\EnvironmentVariableAnalyzer;

// Test class using the trait
class EnvVarAnalyzerTestClass
{
    use EnvironmentVariableAnalyzer;
}

it('returns warning for NODE_ENV production value', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('NODE_ENV', 'production');

    expect($warning)->toBeArray();
    expect($warning)->toHaveKey('variable', 'NODE_ENV');
    expect($warning)->toHaveKey('value', 'production');
    expect($warning)->toHaveKey('affects');
    expect($warning)->toHaveKey('issue');
    expect($warning)->toHaveKey('recommendation');
    expect($warning['affects'])->toContain('Node.js');
});

it('returns warning for NPM_CONFIG_PRODUCTION true value', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('NPM_CONFIG_PRODUCTION', 'true');

    expect($warning)->toBeArray();
    expect($warning['variable'])->toBe('NPM_CONFIG_PRODUCTION');
    expect($warning['value'])->toBe('true');
    expect($warning['affects'])->toContain('npm');
    expect($warning['issue'])->toContain('devDependencies');
});

it('returns warning for YARN_PRODUCTION', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('YARN_PRODUCTION', '1');

    expect($warning)->not()->toBeNull();
    expect($warning['variable'])->toBe('YARN_PRODUCTION');
    expect($warning['affects'])->toContain('Yarn');
});

it('returns warning for COMPOSER_NO_DEV', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('COMPOSER_NO_DEV', '1');

    expect($warning)->not()->toBeNull();
    expect($warning['variable'])->toBe('COMPOSER_NO_DEV');
    expect($warning['affects'])->toContain('PHP/Composer');
});

it('returns warning for MIX_ENV production', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('MIX_ENV', 'prod');

    expect($warning)->not()->toBeNull();
    expect($warning['variable'])->toBe('MIX_ENV');
    expect($warning['affects'])->toContain('Elixir');
});

it('returns warning for RAILS_ENV production', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('RAILS_ENV', 'production');

    expect($warning)->not()->toBeNull();
    expect($warning['variable'])->toBe('RAILS_ENV');
    expect($warning['affects'])->toContain('Ruby on Rails');
});

it('returns warning for APP_ENV production', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('APP_ENV', 'production');

    expect($warning)->not()->toBeNull();
    expect($warning['variable'])->toBe('APP_ENV');
    expect($warning['affects'])->toContain('Laravel');
});

it('returns warning for DJANGO_SETTINGS_MODULE with any value', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('DJANGO_SETTINGS_MODULE', 'myapp.settings.production');

    expect($warning)->not()->toBeNull();
    expect($warning['variable'])->toBe('DJANGO_SETTINGS_MODULE');
    expect($warning['affects'])->toContain('Django');
});

it('returns null for unknown environment variable', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('MY_CUSTOM_VAR', 'some_value');

    expect($warning)->toBeNull();
});

it('returns null for DATABASE_URL', function () {
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('DATABASE_URL', 'postgresql://localhost');

    expect($warning)->toBeNull();
});

it('analyzes multiple variables and returns warnings array', function () {
    $variables = [
        'NODE_ENV' => 'production',
        'DATABASE_URL' => 'postgres://localhost',
        'NPM_CONFIG_PRODUCTION' => 'true',
        'APP_KEY' => 'base64:something',
        'COMPOSER_NO_DEV' => '1',
    ];

    $warnings = EnvVarAnalyzerTestClass::analyzeBuildVariables($variables);

    expect($warnings)->toBeArray();
    expect($warnings)->toHaveCount(3);
    expect($warnings[0]['variable'])->toBe('NODE_ENV');
    expect($warnings[1]['variable'])->toBe('NPM_CONFIG_PRODUCTION');
    expect($warnings[2]['variable'])->toBe('COMPOSER_NO_DEV');
});

it('analyzes multiple variables with no problematic ones', function () {
    $variables = [
        'DATABASE_URL' => 'postgres://localhost',
        'REDIS_HOST' => 'localhost',
        'APP_KEY' => 'base64:something',
    ];

    $warnings = EnvVarAnalyzerTestClass::analyzeBuildVariables($variables);

    expect($warnings)->toBeArray();
    expect($warnings)->toBeEmpty();
});

it('formats build warning into 4-line array', function () {
    $warning = [
        'variable' => 'NODE_ENV',
        'value' => 'production',
        'affects' => 'Node.js/npm',
        'issue' => 'Skips devDependencies',
        'recommendation' => 'Use development',
    ];

    $formatted = EnvVarAnalyzerTestClass::formatBuildWarning($warning);

    expect($formatted)->toBeArray();
    expect($formatted)->toHaveCount(4);
    expect($formatted[0])->toContain('⚠️');
    expect($formatted[0])->toContain('NODE_ENV=production');
    expect($formatted[1])->toContain('Affects:');
    expect($formatted[1])->toContain('Node.js/npm');
    expect($formatted[2])->toContain('Issue:');
    expect($formatted[2])->toContain('Skips devDependencies');
    expect($formatted[3])->toContain('Recommendation:');
    expect($formatted[3])->toContain('Use development');
});

it('shouldShowBuildWarning returns true for known problematic variables', function () {
    expect(EnvVarAnalyzerTestClass::shouldShowBuildWarning('NODE_ENV'))->toBeTrue();
    expect(EnvVarAnalyzerTestClass::shouldShowBuildWarning('NPM_CONFIG_PRODUCTION'))->toBeTrue();
    expect(EnvVarAnalyzerTestClass::shouldShowBuildWarning('COMPOSER_NO_DEV'))->toBeTrue();
    expect(EnvVarAnalyzerTestClass::shouldShowBuildWarning('DJANGO_SETTINGS_MODULE'))->toBeTrue();
    expect(EnvVarAnalyzerTestClass::shouldShowBuildWarning('APP_ENV'))->toBeTrue();
});

it('shouldShowBuildWarning returns false for unknown variables', function () {
    expect(EnvVarAnalyzerTestClass::shouldShowBuildWarning('DATABASE_URL'))->toBeFalse();
    expect(EnvVarAnalyzerTestClass::shouldShowBuildWarning('MY_CUSTOM_VAR'))->toBeFalse();
    expect(EnvVarAnalyzerTestClass::shouldShowBuildWarning('UNKNOWN'))->toBeFalse();
});

it('getUIWarningMessage returns message for known variables', function () {
    $message = EnvVarAnalyzerTestClass::getUIWarningMessage('NODE_ENV');

    expect($message)->not()->toBeNull();
    expect($message)->toBeString();
    expect($message)->toContain('NODE_ENV');
    expect($message)->toContain('production');
    expect($message)->toContain('build-time');
});

it('getUIWarningMessage returns null for unknown variables', function () {
    $message = EnvVarAnalyzerTestClass::getUIWarningMessage('DATABASE_URL');

    expect($message)->toBeNull();
});

it('getUIWarningMessage formats problematic values correctly', function () {
    $message = EnvVarAnalyzerTestClass::getUIWarningMessage('NPM_CONFIG_PRODUCTION');

    expect($message)->toContain('true, 1, yes');
});

it('getProblematicVariablesForFrontend returns config without check_function', function () {
    $frontend = EnvVarAnalyzerTestClass::getProblematicVariablesForFrontend();

    expect($frontend)->toBeArray();
    expect($frontend)->toHaveKey('NODE_ENV');
    expect($frontend['NODE_ENV'])->toHaveKey('problematic_values');
    expect($frontend['NODE_ENV'])->toHaveKey('affects');
    expect($frontend['NODE_ENV'])->toHaveKey('issue');
    expect($frontend['NODE_ENV'])->toHaveKey('recommendation');
    expect($frontend['NODE_ENV'])->not()->toHaveKey('check_function');
});

it('getProblematicVariablesForFrontend includes DJANGO_SETTINGS_MODULE without check_function', function () {
    $frontend = EnvVarAnalyzerTestClass::getProblematicVariablesForFrontend();

    expect($frontend)->toHaveKey('DJANGO_SETTINGS_MODULE');
    expect($frontend['DJANGO_SETTINGS_MODULE'])->not()->toHaveKey('check_function');
    expect($frontend['DJANGO_SETTINGS_MODULE']['problematic_values'])->toBeArray();
});

it('DJANGO_SETTINGS_MODULE uses custom check function', function () {
    // This should trigger the checkDjangoSettings method
    $warning = EnvVarAnalyzerTestClass::analyzeBuildVariable('DJANGO_SETTINGS_MODULE', 'myapp.settings.local');

    expect($warning)->not()->toBeNull();
    expect($warning['variable'])->toBe('DJANGO_SETTINGS_MODULE');
    expect($warning['value'])->toBe('myapp.settings.local');
});

it('always returns warning for known variables regardless of value', function () {
    $warning1 = EnvVarAnalyzerTestClass::analyzeBuildVariable('NODE_ENV', 'development');
    $warning2 = EnvVarAnalyzerTestClass::analyzeBuildVariable('NODE_ENV', 'staging');
    $warning3 = EnvVarAnalyzerTestClass::analyzeBuildVariable('NODE_ENV', 'production');

    expect($warning1)->not()->toBeNull();
    expect($warning2)->not()->toBeNull();
    expect($warning3)->not()->toBeNull();
});
