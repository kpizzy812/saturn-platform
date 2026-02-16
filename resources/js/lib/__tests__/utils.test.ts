import { describe, expect, it, vi, beforeEach } from 'vitest';
import { isSafeUrl, safeOpenUrl } from '../utils';

describe('isSafeUrl', () => {
    beforeEach(() => {
        // Mock window.location.origin for relative URL parsing
        vi.stubGlobal('location', { origin: 'https://example.com' });
    });

    describe('safe URLs', () => {
        it('should return true for https URLs', () => {
            expect(isSafeUrl('https://example.com')).toBe(true);
            expect(isSafeUrl('https://example.com/path')).toBe(true);
            expect(isSafeUrl('https://example.com:8080/path?query=1')).toBe(true);
        });

        it('should return true for http URLs', () => {
            expect(isSafeUrl('http://example.com')).toBe(true);
            expect(isSafeUrl('http://localhost:3000')).toBe(true);
        });

        it('should return true for relative paths starting with /', () => {
            expect(isSafeUrl('/path/to/resource')).toBe(true);
            expect(isSafeUrl('/api/v1/users')).toBe(true);
        });
    });

    describe('unsafe URLs', () => {
        it('should return false for javascript: protocol', () => {
            expect(isSafeUrl('javascript:alert(1)')).toBe(false);
            expect(isSafeUrl('javascript:void(0)')).toBe(false);
            expect(isSafeUrl('JavaScript:alert(1)')).toBe(false); // case insensitive
        });

        it('should return false for data: protocol', () => {
            expect(isSafeUrl('data:text/html,<script>alert(1)</script>')).toBe(false);
            expect(isSafeUrl('data:application/javascript,alert(1)')).toBe(false);
        });

        it('should return false for vbscript: protocol', () => {
            expect(isSafeUrl('vbscript:msgbox(1)')).toBe(false);
        });

        it('should return false for protocol-relative URLs (//)', () => {
            expect(isSafeUrl('//evil.com/malware')).toBe(false);
        });

        it('should return false for file: protocol', () => {
            expect(isSafeUrl('file:///etc/passwd')).toBe(false);
        });

        it('should return false for ftp: protocol', () => {
            expect(isSafeUrl('ftp://example.com')).toBe(false);
        });
    });

    describe('edge cases', () => {
        it('should return false for null', () => {
            expect(isSafeUrl(null)).toBe(false);
        });

        it('should return false for undefined', () => {
            expect(isSafeUrl(undefined)).toBe(false);
        });

        it('should return false for empty string', () => {
            expect(isSafeUrl('')).toBe(false);
        });

        it('should return false for whitespace only', () => {
            expect(isSafeUrl('   ')).toBe(false);
        });
    });
});

describe('safeOpenUrl', () => {
    let windowOpenSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        vi.stubGlobal('location', { origin: 'https://example.com' });
        windowOpenSpy = vi.fn();
        vi.stubGlobal('open', windowOpenSpy);
        vi.spyOn(console, 'warn').mockImplementation(() => {});
    });

    it('should open safe URLs and return true', () => {
        expect(safeOpenUrl('https://example.com')).toBe(true);
        expect(windowOpenSpy).toHaveBeenCalledWith(
            'https://example.com',
            '_blank',
            'noopener,noreferrer'
        );
    });

    it('should open relative paths and return true', () => {
        expect(safeOpenUrl('/dashboard')).toBe(true);
        expect(windowOpenSpy).toHaveBeenCalledWith(
            '/dashboard',
            '_blank',
            'noopener,noreferrer'
        );
    });

    it('should not open unsafe URLs and return false', () => {
        expect(safeOpenUrl('javascript:alert(1)')).toBe(false);
        expect(windowOpenSpy).not.toHaveBeenCalled();
    });

    it('should return false for blocked URLs without opening', () => {
        const result = safeOpenUrl('javascript:alert(1)');
        expect(result).toBe(false);
        expect(windowOpenSpy).not.toHaveBeenCalled();
    });

    it('should return false for null/undefined', () => {
        expect(safeOpenUrl(null)).toBe(false);
        expect(safeOpenUrl(undefined)).toBe(false);
        expect(windowOpenSpy).not.toHaveBeenCalled();
    });
});
