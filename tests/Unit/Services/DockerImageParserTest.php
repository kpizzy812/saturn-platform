<?php

use App\Services\DockerImageParser;

describe('DockerImageParser basic image parsing', function () {
    it('parses simple image name without tag', function () {
        $parser = (new DockerImageParser)->parse('nginx');

        expect($parser->getImageName())->toBe('nginx');
        expect($parser->getTag())->toBe('latest');
        expect($parser->getRegistryUrl())->toBe('');
        expect($parser->isImageHash())->toBeFalse();
    });

    it('parses image with tag', function () {
        $parser = (new DockerImageParser)->parse('nginx:alpine');

        expect($parser->getImageName())->toBe('nginx');
        expect($parser->getTag())->toBe('alpine');
        expect($parser->getRegistryUrl())->toBe('');
        expect($parser->isImageHash())->toBeFalse();
    });

    it('parses image with version tag', function () {
        $parser = (new DockerImageParser)->parse('postgres:15.2');

        expect($parser->getImageName())->toBe('postgres');
        expect($parser->getTag())->toBe('15.2');
        expect($parser->getRegistryUrl())->toBe('');
    });

    it('parses image with latest tag explicitly', function () {
        $parser = (new DockerImageParser)->parse('redis:latest');

        expect($parser->getImageName())->toBe('redis');
        expect($parser->getTag())->toBe('latest');
        expect($parser->isImageHash())->toBeFalse();
    });
});

describe('DockerImageParser with registry URL', function () {
    it('parses image with registry domain', function () {
        $parser = (new DockerImageParser)->parse('docker.io/library/nginx');

        expect($parser->getRegistryUrl())->toBe('docker.io');
        expect($parser->getImageName())->toBe('library/nginx');
        expect($parser->getTag())->toBe('latest');
    });

    it('parses image with registry and tag', function () {
        $parser = (new DockerImageParser)->parse('docker.io/library/nginx:alpine');

        expect($parser->getRegistryUrl())->toBe('docker.io');
        expect($parser->getImageName())->toBe('library/nginx');
        expect($parser->getTag())->toBe('alpine');
    });

    it('parses image with custom registry domain', function () {
        $parser = (new DockerImageParser)->parse('registry.example.com/myapp/backend');

        expect($parser->getRegistryUrl())->toBe('registry.example.com');
        expect($parser->getImageName())->toBe('myapp/backend');
        expect($parser->getTag())->toBe('latest');
    });

    it('parses image with registry port', function () {
        $parser = (new DockerImageParser)->parse('localhost:5000/myimage');

        expect($parser->getRegistryUrl())->toBe('localhost:5000');
        expect($parser->getImageName())->toBe('myimage');
        expect($parser->getTag())->toBe('latest');
    });

    it('parses image with registry port and tag', function () {
        $parser = (new DockerImageParser)->parse('localhost:5000/myimage:v1.0');

        expect($parser->getRegistryUrl())->toBe('localhost:5000');
        expect($parser->getImageName())->toBe('myimage');
        expect($parser->getTag())->toBe('v1.0');
    });

    it('parses image with IP address registry', function () {
        $parser = (new DockerImageParser)->parse('192.168.1.100:5000/myapp:latest');

        expect($parser->getRegistryUrl())->toBe('192.168.1.100:5000');
        expect($parser->getImageName())->toBe('myapp');
        expect($parser->getTag())->toBe('latest');
    });
});

describe('DockerImageParser with SHA256 hashes', function () {
    it('parses image with @sha256 format', function () {
        $hash = str_repeat('a', 64);
        $parser = (new DockerImageParser)->parse("nginx@sha256:{$hash}");

        expect($parser->getImageName())->toBe('nginx');
        expect($parser->getTag())->toBe($hash);
        expect($parser->isImageHash())->toBeTrue();
    });

    it('parses image with registry and @sha256', function () {
        $hash = str_repeat('b', 64);
        $parser = (new DockerImageParser)->parse("docker.io/library/nginx@sha256:{$hash}");

        expect($parser->getRegistryUrl())->toBe('docker.io');
        expect($parser->getImageName())->toBe('library/nginx');
        expect($parser->getTag())->toBe($hash);
        expect($parser->isImageHash())->toBeTrue();
    });

    it('parses image with colon-separated SHA256', function () {
        $hash = '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $parser = (new DockerImageParser)->parse("nginx:{$hash}");

        expect($parser->getImageName())->toBe('nginx');
        expect($parser->getTag())->toBe($hash);
        expect($parser->isImageHash())->toBeTrue();
    });

    it('recognizes lowercase sha256 hash', function () {
        $hash = 'abcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890';
        $parser = (new DockerImageParser)->parse("alpine:{$hash}");

        expect($parser->isImageHash())->toBeTrue();
    });

    it('recognizes uppercase sha256 hash', function () {
        $hash = 'ABCDEF1234567890ABCDEF1234567890ABCDEF1234567890ABCDEF1234567890';
        $parser = (new DockerImageParser)->parse("alpine:{$hash}");

        expect($parser->isImageHash())->toBeTrue();
    });

    it('recognizes mixed case sha256 hash', function () {
        $hash = 'AbCdEf1234567890aBcDeF1234567890AbCdEf1234567890aBcDeF1234567890';
        $parser = (new DockerImageParser)->parse("alpine:{$hash}");

        expect($parser->isImageHash())->toBeTrue();
    });

    it('rejects sha256 hash with wrong length', function () {
        $hash = 'abc123'; // Too short
        $parser = (new DockerImageParser)->parse("nginx:{$hash}");

        expect($parser->isImageHash())->toBeFalse();
    });

    it('rejects sha256 hash with invalid characters', function () {
        $hash = str_repeat('g', 64); // 'g' is not hex
        $parser = (new DockerImageParser)->parse("nginx:{$hash}");

        expect($parser->isImageHash())->toBeFalse();
    });
});

describe('DockerImageParser full image name methods', function () {
    it('returns full image name without tag for simple image', function () {
        $parser = (new DockerImageParser)->parse('nginx:alpine');

        expect($parser->getFullImageNameWithoutTag())->toBe('nginx');
    });

    it('returns full image name without tag with registry', function () {
        $parser = (new DockerImageParser)->parse('docker.io/library/nginx:alpine');

        expect($parser->getFullImageNameWithoutTag())->toBe('docker.io/library/nginx');
    });

    it('returns full image name with hash using @sha256', function () {
        $hash = str_repeat('a', 64);
        $parser = (new DockerImageParser)->parse("nginx@sha256:{$hash}");

        expect($parser->getFullImageNameWithHash())->toBe("nginx@sha256:{$hash}");
    });

    it('returns full image name with tag for regular tag', function () {
        $parser = (new DockerImageParser)->parse('nginx:alpine');

        expect($parser->getFullImageNameWithHash())->toBe('nginx:alpine');
    });

    it('returns full image name with registry and tag', function () {
        $parser = (new DockerImageParser)->parse('docker.io/library/nginx:alpine');

        expect($parser->getFullImageNameWithHash())->toBe('docker.io/library/nginx:alpine');
    });
});

describe('DockerImageParser toString method', function () {
    it('converts simple image to string', function () {
        $parser = (new DockerImageParser)->parse('nginx');

        expect($parser->toString())->toBe('nginx:latest');
    });

    it('converts image with tag to string', function () {
        $parser = (new DockerImageParser)->parse('nginx:alpine');

        expect($parser->toString())->toBe('nginx:alpine');
    });

    it('converts image with registry to string', function () {
        $parser = (new DockerImageParser)->parse('docker.io/library/nginx:alpine');

        expect($parser->toString())->toBe('docker.io/library/nginx:alpine');
    });

    it('converts image with hash to string using @sha256', function () {
        $hash = str_repeat('a', 64);
        $parser = (new DockerImageParser)->parse("nginx@sha256:{$hash}");

        expect($parser->toString())->toBe("nginx@sha256:{$hash}");
    });

    it('converts image with registry and hash to string', function () {
        $hash = str_repeat('b', 64);
        $parser = (new DockerImageParser)->parse("docker.io/library/nginx@sha256:{$hash}");

        expect($parser->toString())->toBe("docker.io/library/nginx@sha256:{$hash}");
    });
});

describe('DockerImageParser edge cases', function () {
    it('handles image with multiple slashes in name', function () {
        $parser = (new DockerImageParser)->parse('registry.io/org/team/project:v1');

        expect($parser->getRegistryUrl())->toBe('registry.io');
        expect($parser->getImageName())->toBe('org/team/project');
        expect($parser->getTag())->toBe('v1');
    });

    it('handles image without registry but with org prefix', function () {
        $parser = (new DockerImageParser)->parse('myorg/myimage:latest');

        expect($parser->getRegistryUrl())->toBe('');
        expect($parser->getImageName())->toBe('myorg/myimage');
        expect($parser->getTag())->toBe('latest');
    });

    it('handles image with complex tag format', function () {
        $parser = (new DockerImageParser)->parse('myimage:v1.2.3-alpha');

        expect($parser->getImageName())->toBe('myimage');
        expect($parser->getTag())->toBe('v1.2.3-alpha');
    });

    it('handles registry with subdomain', function () {
        $parser = (new DockerImageParser)->parse('registry.us-west.example.com/myapp:latest');

        expect($parser->getRegistryUrl())->toBe('registry.us-west.example.com');
        expect($parser->getImageName())->toBe('myapp');
    });

    it('preserves parse result across multiple method calls', function () {
        $parser = (new DockerImageParser)->parse('docker.io/nginx:alpine');

        // Call methods multiple times
        expect($parser->getRegistryUrl())->toBe('docker.io');
        expect($parser->getImageName())->toBe('nginx');
        expect($parser->getTag())->toBe('alpine');
        expect($parser->getRegistryUrl())->toBe('docker.io'); // Should be same
    });

    it('can reuse parser instance for multiple parses', function () {
        $parser = new DockerImageParser;

        $result1 = $parser->parse('nginx:alpine');
        expect($result1->getImageName())->toBe('nginx');
        expect($result1->getTag())->toBe('alpine');

        $result2 = $parser->parse('postgres:15');
        expect($result2->getImageName())->toBe('postgres');
        expect($result2->getTag())->toBe('15');
    });
});
