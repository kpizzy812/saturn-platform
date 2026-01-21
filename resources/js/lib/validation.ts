/**
 * Form validation utilities for Saturn frontend
 */

export interface ValidationResult {
    valid: boolean;
    error?: string;
}

export interface PasswordValidationResult extends ValidationResult {
    strength?: 'weak' | 'medium' | 'strong';
}

/**
 * Validates IPv4 address format
 */
function isValidIPv4(ip: string): boolean {
    const ipv4Regex = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/;
    const match = ip.match(ipv4Regex);

    if (!match) return false;

    // Check each octet is between 0-255
    for (let i = 1; i <= 4; i++) {
        const octet = parseInt(match[i], 10);
        if (octet < 0 || octet > 255) return false;
    }

    return true;
}

/**
 * Validates IPv6 address format (basic validation)
 */
function isValidIPv6(ip: string): boolean {
    // Basic IPv6 validation - allows full and compressed formats
    const ipv6Regex = /^([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}$/;
    return ipv6Regex.test(ip) || ip === '::1' || ip === '::';
}

/**
 * Validates hostname format (allows domain names)
 */
function isValidHostname(hostname: string): boolean {
    // Allow localhost
    if (hostname === 'localhost') return true;

    // Hostname regex - allows letters, numbers, dots, and hyphens
    const hostnameRegex = /^([a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9]([a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?$/;
    return hostnameRegex.test(hostname);
}

/**
 * Validates IP address (IPv4, IPv6, or hostname)
 */
export function validateIPAddress(ip: string): ValidationResult {
    if (!ip || ip.trim() === '') {
        return { valid: false, error: 'IP address is required' };
    }

    const trimmedIp = ip.trim();

    // If it looks like an IPv4 address (only digits and dots), validate strictly as IPv4
    // This prevents invalid IPs like "256.168.1.1" from being accepted as hostnames
    if (/^[\d.]+$/.test(trimmedIp)) {
        if (isValidIPv4(trimmedIp)) {
            return { valid: true };
        }
        return {
            valid: false,
            error: 'Invalid IPv4 address format'
        };
    }

    if (isValidIPv6(trimmedIp)) {
        return { valid: true };
    }

    if (isValidHostname(trimmedIp)) {
        return { valid: true };
    }

    return {
        valid: false,
        error: 'Invalid IP address or hostname format'
    };
}

/**
 * Validates CIDR notation (e.g., 192.168.1.0/24)
 */
export function validateCIDR(cidr: string): ValidationResult {
    if (!cidr || cidr.trim() === '') {
        return { valid: false, error: 'CIDR is required' };
    }

    const trimmedCidr = cidr.trim();

    // Check if it's just an IP address (valid for single IP)
    const ipValidation = validateIPAddress(trimmedCidr);
    if (ipValidation.valid) {
        return { valid: true };
    }

    // Check CIDR format (IP/prefix)
    const cidrRegex = /^(.+)\/(\d+)$/;
    const match = trimmedCidr.match(cidrRegex);

    if (!match) {
        return {
            valid: false,
            error: 'Invalid CIDR format (use IP/prefix, e.g., 192.168.1.0/24)'
        };
    }

    const [, ipPart, prefixPart] = match;
    const prefix = parseInt(prefixPart, 10);

    // Validate IP part
    const ipPartValidation = validateIPAddress(ipPart);
    if (!ipPartValidation.valid) {
        return {
            valid: false,
            error: 'Invalid IP address in CIDR notation'
        };
    }

    // Validate prefix for IPv4 (0-32) or IPv6 (0-128)
    const isIPv6 = ipPart.includes(':');
    const maxPrefix = isIPv6 ? 128 : 32;

    if (prefix < 0 || prefix > maxPrefix) {
        return {
            valid: false,
            error: `Prefix must be between 0 and ${maxPrefix} for ${isIPv6 ? 'IPv6' : 'IPv4'}`
        };
    }

    return { valid: true };
}

/**
 * Validates SSH key format (checks for BEGIN/END markers)
 */
export function validateSSHKey(key: string): ValidationResult {
    if (!key || key.trim() === '') {
        return { valid: false, error: 'SSH key is required' };
    }

    const trimmedKey = key.trim();

    // Check for common SSH key formats
    const hasBeginMarker = trimmedKey.includes('BEGIN') &&
                          (trimmedKey.includes('PRIVATE KEY') ||
                           trimmedKey.includes('RSA PRIVATE KEY') ||
                           trimmedKey.includes('OPENSSH PRIVATE KEY') ||
                           trimmedKey.includes('EC PRIVATE KEY') ||
                           trimmedKey.includes('DSA PRIVATE KEY'));

    const hasEndMarker = trimmedKey.includes('END') &&
                        (trimmedKey.includes('PRIVATE KEY') ||
                         trimmedKey.includes('RSA PRIVATE KEY') ||
                         trimmedKey.includes('OPENSSH PRIVATE KEY') ||
                         trimmedKey.includes('EC PRIVATE KEY') ||
                         trimmedKey.includes('DSA PRIVATE KEY'));

    if (!hasBeginMarker || !hasEndMarker) {
        return {
            valid: false,
            error: 'Invalid SSH key format (must contain BEGIN and END markers)'
        };
    }

    // Basic length check (SSH keys should be substantial)
    if (trimmedKey.length < 100) {
        return {
            valid: false,
            error: 'SSH key appears to be incomplete or too short'
        };
    }

    return { valid: true };
}

/**
 * Validates Docker Compose YAML syntax and structure
 */
export function validateDockerCompose(yaml: string): ValidationResult {
    if (!yaml || yaml.trim() === '') {
        return { valid: false, error: 'Docker Compose configuration is required' };
    }

    const trimmedYaml = yaml.trim();

    // Basic YAML structure checks
    try {
        // Check for services or version key (common in docker-compose files)
        const hasServices = /^\s*services:/m.test(trimmedYaml);
        const hasVersion = /^\s*version:/m.test(trimmedYaml);

        if (!hasServices && !hasVersion) {
            return {
                valid: false,
                error: 'Docker Compose must contain "services:" or "version:" declaration'
            };
        }

        // Check for common YAML syntax errors
        const lines = trimmedYaml.split('\n');
        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];

            // Skip empty lines and comments
            if (line.trim() === '' || line.trim().startsWith('#')) continue;

            // Check for tabs (YAML doesn't allow tabs for indentation)
            if (line.includes('\t')) {
                return {
                    valid: false,
                    error: `YAML syntax error on line ${i + 1}: tabs are not allowed (use spaces)`
                };
            }
        }

        return { valid: true };
    } catch (error) {
        return {
            valid: false,
            error: 'Invalid YAML syntax'
        };
    }
}

/**
 * Validates password strength and format
 */
export function validatePassword(password: string): PasswordValidationResult {
    if (!password) {
        return {
            valid: false,
            error: 'Password is required'
        };
    }

    // Minimum length check
    if (password.length < 8) {
        return {
            valid: false,
            error: 'Password must be at least 8 characters long',
            strength: 'weak'
        };
    }

    // Calculate password strength
    const hasLowercase = /[a-z]/.test(password);
    const hasUppercase = /[A-Z]/.test(password);
    const hasNumbers = /[0-9]/.test(password);
    const hasSpecialChars = /[^a-zA-Z0-9]/.test(password);

    // Count character types
    let charTypeCount = 0;
    if (hasLowercase) charTypeCount++;
    if (hasUppercase) charTypeCount++;
    if (hasNumbers) charTypeCount++;
    if (hasSpecialChars) charTypeCount++;

    // Determine strength level
    let strength: 'weak' | 'medium' | 'strong';

    // Strong: must have all 4 character types (uppercase, lowercase, numbers, special chars)
    if (charTypeCount === 4 && password.length >= 8) {
        strength = 'strong';
    }
    // Medium: has at least 3 character types, or good length with 2+ types
    else if (charTypeCount >= 3 || (password.length >= 10 && charTypeCount >= 2)) {
        strength = 'medium';
    }
    // Weak: everything else
    else {
        strength = 'weak';
    }

    // Provide feedback for weak passwords
    if (strength === 'weak') {
        const suggestions = [];
        if (!/[a-z]/.test(password)) suggestions.push('lowercase letters');
        if (!/[A-Z]/.test(password)) suggestions.push('uppercase letters');
        if (!/[0-9]/.test(password)) suggestions.push('numbers');
        if (!/[^a-zA-Z0-9]/.test(password)) suggestions.push('special characters');

        if (suggestions.length > 0) {
            return {
                valid: true,
                strength: 'weak',
                error: `Weak password. Consider adding: ${suggestions.join(', ')}`
            };
        }
    }

    return {
        valid: true,
        strength
    };
}

/**
 * Validates port number (1-65535)
 */
export function validatePort(port: number | string): ValidationResult {
    const portNum = typeof port === 'string' ? parseInt(port, 10) : port;

    if (isNaN(portNum)) {
        return {
            valid: false,
            error: 'Port must be a number'
        };
    }

    if (portNum < 1 || portNum > 65535) {
        return {
            valid: false,
            error: 'Port must be between 1 and 65535'
        };
    }

    return { valid: true };
}

/**
 * Validates password confirmation matches
 */
export function validatePasswordMatch(password: string, confirmation: string): ValidationResult {
    if (password !== confirmation) {
        return {
            valid: false,
            error: 'Passwords do not match'
        };
    }

    return { valid: true };
}
