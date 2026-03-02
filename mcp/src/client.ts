/**
 * Saturn Platform REST API client.
 * Configured via environment variables:
 *   SATURN_API_URL   — base URL, e.g. https://dev.saturn.ac  (default)
 *   SATURN_API_TOKEN — Bearer token (required)
 */
export class SaturnClient {
    private readonly baseUrl: string;
    private readonly token: string;

    constructor() {
        this.baseUrl = (process.env.SATURN_API_URL ?? 'https://dev.saturn.ac').replace(/\/$/, '');
        this.token = process.env.SATURN_API_TOKEN ?? '';
        if (!this.token) {
            throw new Error('SATURN_API_TOKEN environment variable is required');
        }
    }

    private headers(): Record<string, string> {
        return {
            Authorization: `Bearer ${this.token}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
        };
    }

    async get<T>(path: string): Promise<T> {
        const res = await fetch(`${this.baseUrl}/api/v1${path}`, {
            headers: this.headers(),
        });
        if (!res.ok) {
            const body = await res.text();
            throw new Error(`Saturn API ${res.status} on GET ${path}: ${body}`);
        }
        return res.json() as Promise<T>;
    }

    async post<T>(path: string, body?: unknown): Promise<T> {
        const res = await fetch(`${this.baseUrl}/api/v1${path}`, {
            method: 'POST',
            headers: this.headers(),
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(`Saturn API ${res.status} on POST ${path}: ${text}`);
        }
        return res.json() as Promise<T>;
    }
}
