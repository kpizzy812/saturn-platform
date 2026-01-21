import { describe, it, expect } from 'vitest';
import {
    validateIPAddress,
    validateCIDR,
    validateSSHKey,
    validateDockerCompose,
    validatePassword,
    validatePort,
    validatePasswordMatch,
} from '@/lib/validation';

describe('validateIPAddress', () => {
    describe('valid IPv4 addresses', () => {
        it('should validate standard IPv4 address', () => {
            const result = validateIPAddress('192.168.1.1');
            expect(result.valid).toBe(true);
            expect(result.error).toBeUndefined();
        });

        it('should validate IPv4 address with zeros', () => {
            expect(validateIPAddress('10.0.0.1').valid).toBe(true);
        });

        it('should validate IPv4 address at network boundaries', () => {
            expect(validateIPAddress('0.0.0.0').valid).toBe(true);
            expect(validateIPAddress('255.255.255.255').valid).toBe(true);
        });

        it('should validate IPv4 address with trailing/leading spaces', () => {
            expect(validateIPAddress('  192.168.1.1  ').valid).toBe(true);
        });
    });

    describe('invalid IPv4 addresses', () => {
        it('should reject IPv4 with out-of-range octets', () => {
            expect(validateIPAddress('256.168.1.1').valid).toBe(false);
            expect(validateIPAddress('192.256.1.1').valid).toBe(false);
            expect(validateIPAddress('192.168.256.1').valid).toBe(false);
            expect(validateIPAddress('192.168.1.256').valid).toBe(false);
        });

        it('should reject incomplete IPv4', () => {
            expect(validateIPAddress('192.168.1').valid).toBe(false);
            expect(validateIPAddress('192.168').valid).toBe(false);
        });

        it('should reject IPv4 with too many octets', () => {
            expect(validateIPAddress('192.168.1.1.1').valid).toBe(false);
        });
    });

    describe('valid IPv6 addresses', () => {
        it('should validate full IPv6 address', () => {
            expect(validateIPAddress('2001:0db8:85a3:0000:0000:8a2e:0370:7334').valid).toBe(true);
        });

        it('should validate compressed IPv6 address', () => {
            expect(validateIPAddress('2001:db8:85a3::8a2e:370:7334').valid).toBe(true);
        });

        it('should validate localhost IPv6', () => {
            expect(validateIPAddress('::1').valid).toBe(true);
        });

        it('should validate IPv6 zero address', () => {
            expect(validateIPAddress('::').valid).toBe(true);
        });
    });

    describe('valid hostnames', () => {
        it('should validate localhost', () => {
            expect(validateIPAddress('localhost').valid).toBe(true);
        });

        it('should validate domain names', () => {
            expect(validateIPAddress('example.com').valid).toBe(true);
            expect(validateIPAddress('api.example.com').valid).toBe(true);
            expect(validateIPAddress('my-server.example.com').valid).toBe(true);
        });

        it('should validate single label hostname', () => {
            expect(validateIPAddress('server1').valid).toBe(true);
        });
    });

    describe('invalid hostnames', () => {
        it('should reject hostname starting with hyphen', () => {
            expect(validateIPAddress('-invalid.com').valid).toBe(false);
        });

        it('should reject hostname ending with hyphen', () => {
            expect(validateIPAddress('invalid-.com').valid).toBe(false);
        });
    });

    describe('edge cases', () => {
        it('should reject empty string', () => {
            const result = validateIPAddress('');
            expect(result.valid).toBe(false);
            expect(result.error).toBeDefined();
        });

        it('should reject whitespace-only string', () => {
            const result = validateIPAddress('   ');
            expect(result.valid).toBe(false);
        });
    });
});

describe('validateCIDR', () => {
    describe('valid IPv4 CIDR', () => {
        it('should validate standard IPv4 CIDR', () => {
            expect(validateCIDR('192.168.1.0/24').valid).toBe(true);
        });

        it('should validate IPv4 CIDR with /32', () => {
            expect(validateCIDR('192.168.1.1/32').valid).toBe(true);
        });

        it('should validate IPv4 CIDR with /0', () => {
            expect(validateCIDR('0.0.0.0/0').valid).toBe(true);
        });

        it('should validate IPv4 CIDR with common prefixes', () => {
            expect(validateCIDR('10.0.0.0/8').valid).toBe(true);
            expect(validateCIDR('172.16.0.0/12').valid).toBe(true);
            expect(validateCIDR('192.168.0.0/16').valid).toBe(true);
        });

        it('should validate single IP address (no prefix)', () => {
            expect(validateCIDR('192.168.1.1').valid).toBe(true);
        });
    });

    describe('invalid IPv4 CIDR', () => {
        it('should reject CIDR with prefix > 32', () => {
            const result = validateCIDR('192.168.1.0/33');
            expect(result.valid).toBe(false);
            expect(result.error).toContain('32');
        });

        it('should reject CIDR with negative prefix', () => {
            expect(validateCIDR('192.168.1.0/-1').valid).toBe(false);
        });

        it('should reject CIDR with invalid IP', () => {
            const result = validateCIDR('256.168.1.0/24');
            expect(result.valid).toBe(false);
        });
    });

    describe('valid IPv6 CIDR', () => {
        it('should validate IPv6 CIDR', () => {
            expect(validateCIDR('2001:db8::/32').valid).toBe(true);
        });

        it('should validate IPv6 CIDR with /128', () => {
            expect(validateCIDR('::1/128').valid).toBe(true);
        });
    });

    describe('invalid IPv6 CIDR', () => {
        it('should reject IPv6 CIDR with prefix > 128', () => {
            const result = validateCIDR('2001:db8::/129');
            expect(result.valid).toBe(false);
            expect(result.error).toContain('128');
        });
    });

    describe('edge cases', () => {
        it('should reject empty string', () => {
            const result = validateCIDR('');
            expect(result.valid).toBe(false);
            expect(result.error).toBeDefined();
        });

        it('should reject malformed CIDR', () => {
            expect(validateCIDR('192.168.1.0/').valid).toBe(false);
            expect(validateCIDR('192.168.1.0/abc').valid).toBe(false);
        });
    });
});

describe('validateSSHKey', () => {
    describe('valid SSH keys', () => {
        it('should validate PEM RSA private key', () => {
            const key = `-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z
3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3
-----END RSA PRIVATE KEY-----`;
            expect(validateSSHKey(key).valid).toBe(true);
        });

        it('should validate PEM OPENSSH private key', () => {
            const key = `-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAABlwAAAAdzc2gtcn
NhAAAAAwEAAQAAAYEA3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z
-----END OPENSSH PRIVATE KEY-----`;
            expect(validateSSHKey(key).valid).toBe(true);
        });

        it('should validate EC private key', () => {
            const key = `-----BEGIN EC PRIVATE KEY-----
MHcCAQEEIIGLhBkKnRn3Z0z1V2f3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3oAoGCCqGSM49
AwEHoUQDQgAEYWp6pKqY3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3
-----END EC PRIVATE KEY-----`;
            expect(validateSSHKey(key).valid).toBe(true);
        });

        it('should validate DSA private key', () => {
            const key = `-----BEGIN DSA PRIVATE KEY-----
MIIBugIBAAKBgQC3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3
Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3
-----END DSA PRIVATE KEY-----`;
            expect(validateSSHKey(key).valid).toBe(true);
        });
    });

    describe('invalid SSH keys', () => {
        it('should reject key without BEGIN marker', () => {
            const key = `MIIEpAIBAAKCAQEA3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z
-----END RSA PRIVATE KEY-----`;
            const result = validateSSHKey(key);
            expect(result.valid).toBe(false);
            expect(result.error).toContain('BEGIN and END markers');
        });

        it('should reject key without END marker', () => {
            const key = `-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z3Z`;
            const result = validateSSHKey(key);
            expect(result.valid).toBe(false);
            expect(result.error).toContain('BEGIN and END markers');
        });

        it('should reject too short key', () => {
            const key = `-----BEGIN RSA PRIVATE KEY-----
ABC
-----END RSA PRIVATE KEY-----`;
            const result = validateSSHKey(key);
            expect(result.valid).toBe(false);
            expect(result.error).toContain('too short');
        });

        it('should reject empty string', () => {
            const result = validateSSHKey('');
            expect(result.valid).toBe(false);
            expect(result.error).toBeDefined();
        });

        it('should reject random text', () => {
            const result = validateSSHKey('This is not an SSH key');
            expect(result.valid).toBe(false);
        });
    });
});

describe('validateDockerCompose', () => {
    describe('valid Docker Compose files', () => {
        it('should validate Docker Compose with version and services', () => {
            const yaml = `version: '3.8'
services:
  web:
    image: nginx:latest
    ports:
      - "80:80"`;
            expect(validateDockerCompose(yaml).valid).toBe(true);
        });

        it('should validate Docker Compose without version (modern format)', () => {
            const yaml = `services:
  web:
    image: nginx:latest
  db:
    image: postgres:15`;
            expect(validateDockerCompose(yaml).valid).toBe(true);
        });

        it('should validate Docker Compose with comments', () => {
            const yaml = `# Production configuration
services:
  # Web server
  web:
    image: nginx:latest`;
            expect(validateDockerCompose(yaml).valid).toBe(true);
        });

        it('should validate complex Docker Compose', () => {
            const yaml = `version: '3.8'
services:
  web:
    image: nginx:latest
    ports:
      - "80:80"
    environment:
      - NODE_ENV=production
    volumes:
      - ./data:/data
  db:
    image: postgres:15
    environment:
      POSTGRES_PASSWORD: secret`;
            expect(validateDockerCompose(yaml).valid).toBe(true);
        });
    });

    describe('invalid Docker Compose files', () => {
        it('should reject Docker Compose without services or version', () => {
            const yaml = `web:
  image: nginx:latest`;
            const result = validateDockerCompose(yaml);
            expect(result.valid).toBe(false);
            expect(result.error).toContain('services');
        });

        it('should reject Docker Compose with tabs', () => {
            const yaml = `services:
\tweb:
\t  image: nginx:latest`;
            const result = validateDockerCompose(yaml);
            expect(result.valid).toBe(false);
            expect(result.error).toContain('tabs');
        });

        it('should reject empty string', () => {
            const result = validateDockerCompose('');
            expect(result.valid).toBe(false);
            expect(result.error).toBeDefined();
        });

        it('should reject whitespace-only string', () => {
            const result = validateDockerCompose('   \n   ');
            expect(result.valid).toBe(false);
        });
    });
});

describe('validatePassword', () => {
    describe('strong passwords', () => {
        it('should validate strong password with all requirements', () => {
            const result = validatePassword('MyP@ssw0rd123');
            expect(result.valid).toBe(true);
            expect(result.strength).toBe('strong');
            expect(result.error).toBeUndefined();
        });

        it('should validate long strong password', () => {
            const result = validatePassword('SuperSecure!Pass123');
            expect(result.valid).toBe(true);
            expect(result.strength).toBe('strong');
        });
    });

    describe('medium passwords', () => {
        it('should validate medium password', () => {
            const result = validatePassword('MyPass123');
            expect(result.valid).toBe(true);
            expect(result.strength).toBe('medium');
        });

        it('should validate medium password without special chars', () => {
            const result = validatePassword('MyPassword1');
            expect(result.valid).toBe(true);
            expect(result.strength).toBe('medium');
        });
    });

    describe('weak passwords', () => {
        it('should mark weak password but still valid', () => {
            const result = validatePassword('password1');
            expect(result.valid).toBe(true);
            expect(result.strength).toBe('weak');
            expect(result.error).toBeDefined();
        });

        it('should provide suggestions for weak passwords', () => {
            const result = validatePassword('password1');
            expect(result.error).toContain('uppercase');
        });
    });

    describe('invalid passwords', () => {
        it('should reject too short password', () => {
            const result = validatePassword('Pass1');
            expect(result.valid).toBe(false);
            expect(result.error).toContain('8 characters');
        });

        it('should reject empty password', () => {
            const result = validatePassword('');
            expect(result.valid).toBe(false);
            expect(result.error).toBeDefined();
        });
    });

    describe('password strength calculation', () => {
        it('should require lowercase for better strength', () => {
            const result = validatePassword('PASSWORD123!');
            expect(result.valid).toBe(true);
            expect(result.strength).not.toBe('strong');
        });

        it('should require uppercase for better strength', () => {
            const result = validatePassword('password123!');
            expect(result.valid).toBe(true);
            expect(result.strength).not.toBe('strong');
        });

        it('should require numbers for better strength', () => {
            const result = validatePassword('Password!');
            expect(result.valid).toBe(true);
            expect(result.strength).not.toBe('strong');
        });

        it('should reward special characters', () => {
            const withSpecial = validatePassword('MyP@ssw0rd123');
            const withoutSpecial = validatePassword('MyPassw0rd123');

            expect(withSpecial.strength).toBe('strong');
            expect(withoutSpecial.strength).toBe('medium');
        });
    });
});

describe('validatePort', () => {
    describe('valid ports', () => {
        it('should validate port as number', () => {
            expect(validatePort(80).valid).toBe(true);
            expect(validatePort(443).valid).toBe(true);
            expect(validatePort(8080).valid).toBe(true);
        });

        it('should validate port as string', () => {
            expect(validatePort('80').valid).toBe(true);
            expect(validatePort('443').valid).toBe(true);
            expect(validatePort('8080').valid).toBe(true);
        });

        it('should validate port at boundaries', () => {
            expect(validatePort(1).valid).toBe(true);
            expect(validatePort(65535).valid).toBe(true);
        });
    });

    describe('invalid ports', () => {
        it('should reject port 0', () => {
            const result = validatePort(0);
            expect(result.valid).toBe(false);
            expect(result.error).toContain('between 1 and 65535');
        });

        it('should reject port > 65535', () => {
            const result = validatePort(65536);
            expect(result.valid).toBe(false);
            expect(result.error).toContain('between 1 and 65535');
        });

        it('should reject negative port', () => {
            const result = validatePort(-1);
            expect(result.valid).toBe(false);
        });

        it('should reject non-numeric string', () => {
            const result = validatePort('abc');
            expect(result.valid).toBe(false);
            expect(result.error).toContain('must be a number');
        });

        it('should reject empty string', () => {
            const result = validatePort('');
            expect(result.valid).toBe(false);
        });
    });

    describe('edge cases', () => {
        it('should handle port with leading zeros', () => {
            expect(validatePort('0080').valid).toBe(true);
        });

        it('should handle port with whitespace', () => {
            expect(validatePort('  80  ').valid).toBe(true);
        });
    });
});

describe('validatePasswordMatch', () => {
    it('should validate matching passwords', () => {
        const result = validatePasswordMatch('password123', 'password123');
        expect(result.valid).toBe(true);
        expect(result.error).toBeUndefined();
    });

    it('should reject non-matching passwords', () => {
        const result = validatePasswordMatch('password123', 'password456');
        expect(result.valid).toBe(false);
        expect(result.error).toContain('do not match');
    });

    it('should reject case-sensitive mismatch', () => {
        const result = validatePasswordMatch('Password123', 'password123');
        expect(result.valid).toBe(false);
    });

    it('should validate empty matching passwords', () => {
        const result = validatePasswordMatch('', '');
        expect(result.valid).toBe(true);
    });
});
