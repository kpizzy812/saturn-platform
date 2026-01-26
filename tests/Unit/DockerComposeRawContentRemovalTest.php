<?php

/**
 * Unit tests to verify that docker_compose_raw only has content: removed from volumes,
 * while docker_compose contains all Saturn Platform additions (labels, environment variables, networks).
 *
 * These tests verify the fix for the issue where docker_compose_raw was being set to the
 * fully processed compose (with Saturn Platform labels, networks, etc.) instead of keeping it clean
 * with only content: fields removed.
 */
it('ensures ApplicationComposeParser stores original compose before processing', function () {
    // Read the ApplicationComposeParser class
    $parserFile = file_get_contents(__DIR__.'/../../app/Parsers/ApplicationComposeParser.php');

    // Check that originalCompose is stored at the start of the parsing
    expect($parserFile)
        ->toContain("\$compose = data_get(\$this->resource, 'docker_compose_raw');")
        ->toContain('$this->originalCompose = $compose;');
});

it('ensures ServiceComposeParser stores original compose before processing', function () {
    // Read the ServiceComposeParser class
    $parserFile = file_get_contents(__DIR__.'/../../app/Parsers/ServiceComposeParser.php');

    // Check that originalCompose is stored at the start of the parsing
    expect($parserFile)
        ->toContain('class ServiceComposeParser')
        ->toContain("\$compose = data_get(\$this->resource, 'docker_compose_raw');")
        ->toContain('$this->originalCompose = $compose;');
});

it('ensures ApplicationComposeParser updates docker_compose_raw from original compose, not cleaned compose', function () {
    // Read the ApplicationComposeParser class
    $parserFile = file_get_contents(__DIR__.'/../../app/Parsers/ApplicationComposeParser.php');

    // Check that docker_compose_raw is set from originalCompose, not cleanedCompose
    expect($parserFile)
        ->toContain('$originalYaml = Yaml::parse($this->originalCompose);')
        ->toContain('$this->resource->docker_compose_raw = Yaml::dump($originalYaml, 10, 2);')
        ->not->toContain('$this->resource->docker_compose_raw = $cleanedCompose;');
});

it('ensures ServiceComposeParser updates docker_compose_raw from original compose, not cleaned compose', function () {
    // Read the ServiceComposeParser class
    $parserFile = file_get_contents(__DIR__.'/../../app/Parsers/ServiceComposeParser.php');

    // Check that docker_compose_raw is set from originalCompose within ServiceComposeParser
    expect($parserFile)
        ->toContain('$originalYaml = Yaml::parse($this->originalCompose);')
        ->toContain('$this->resource->docker_compose_raw = Yaml::dump($originalYaml, 10, 2);')
        ->not->toContain('$this->resource->docker_compose_raw = $cleanedCompose;');
});

it('ensures ApplicationComposeParser removes content, isDirectory, and is_directory from volumes', function () {
    // Read the ApplicationComposeParser class
    $parserFile = file_get_contents(__DIR__.'/../../app/Parsers/ApplicationComposeParser.php');

    // Check that content removal logic exists in saveResource method
    expect($parserFile)
        ->toContain("unset(\$volume['content']);")
        ->toContain("unset(\$volume['isDirectory']);")
        ->toContain("unset(\$volume['is_directory']);");
});

it('ensures ServiceComposeParser removes content, isDirectory, and is_directory from volumes', function () {
    // Read the ServiceComposeParser class
    $parserFile = file_get_contents(__DIR__.'/../../app/Parsers/ServiceComposeParser.php');

    // Check that content removal logic exists in saveResource method
    expect($parserFile)
        ->toContain("unset(\$volume['content']);")
        ->toContain("unset(\$volume['isDirectory']);")
        ->toContain("unset(\$volume['is_directory']);");
});

it('ensures docker_compose_raw update is wrapped in try-catch for error handling', function () {
    // Read both parser files
    $applicationParserFile = file_get_contents(__DIR__.'/../../app/Parsers/ApplicationComposeParser.php');
    $serviceParserFile = file_get_contents(__DIR__.'/../../app/Parsers/ServiceComposeParser.php');

    // Check that docker_compose_raw update has error handling in ApplicationComposeParser
    expect($applicationParserFile)
        ->toContain('// Update docker_compose_raw to remove content: from volumes only')
        ->toContain('try {')
        ->toContain('$originalYaml = Yaml::parse($this->originalCompose);')
        ->toContain('} catch (\Exception $e) {')
        ->toContain("ray('Failed to update docker_compose_raw in applicationParser");

    // Check that docker_compose_raw update has error handling in ServiceComposeParser
    expect($serviceParserFile)
        ->toContain('// Update docker_compose_raw to remove content: from volumes only')
        ->toContain('try {')
        ->toContain('$originalYaml = Yaml::parse($this->originalCompose);')
        ->toContain('} catch (\Exception $e) {')
        ->toContain("ray('Failed to update docker_compose_raw in serviceParser");
});
