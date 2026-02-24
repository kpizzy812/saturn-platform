# Security Policy

## Supported Versions

| Version | Supported          | Status |
| ------- | ------------------ | ------ |
| 4.x     | :white_check_mark: | Active Development & Security Updates |
| < 4.0   | :x:                | End of Life |

## Reporting a Vulnerability

If you discover a security vulnerability, please follow responsible disclosure:

1. **DO NOT** disclose the vulnerability publicly (no GitHub Issues).
2. Send a detailed report to: **security@saturn.ac**
3. Include in your report:
   - Description of the vulnerability
   - Steps to reproduce
   - Potential impact assessment
   - Suggested fix (if any)

## Response Timeline

- **Acknowledgement:** within 48 hours
- **Initial assessment:** within 5 business days
- **Fix release:** as soon as possible after verification

## Security Updates

Security patches are released immediately upon verification. Critical vulnerabilities may trigger out-of-band releases.

## Scope

The following are in scope for security reports:
- Saturn Platform application (this repository)
- Authentication and authorization bypass
- SQL injection, XSS, CSRF, SSRF
- SSH key management vulnerabilities
- Container escape or privilege escalation
- Sensitive data exposure

Out of scope:
- Third-party services and dependencies (report upstream)
- Social engineering attacks
- Denial of service (unless easily exploitable)
