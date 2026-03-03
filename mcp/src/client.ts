/**
 * Saturn Platform REST API client.
 *
 * Token & URL resolution (first wins):
 *   1. CLI args:  --token <token> --url <url>
 *   2. Env vars:  SATURN_API_TOKEN, SATURN_API_URL
 */
export function parseCliArgs(): { url?: string; token?: string } {
    const args = process.argv.slice(2);
    const result: { url?: string; token?: string } = {};
    for (let i = 0; i < args.length; i++) {
        if (args[i] === '--token' && args[i + 1]) {
            result.token = args[++i];
        } else if (args[i] === '--url' && args[i + 1]) {
            result.url = args[++i];
        }
    }
    return result;
}

export class SaturnClient {
    private readonly baseUrl: string;
    private readonly token: string;

    constructor(opts?: { url?: string; token?: string }) {
        this.baseUrl = (opts?.url ?? process.env.SATURN_API_URL ?? 'https://saturn.ac').replace(/\/$/, '');
        this.token = opts?.token ?? process.env.SATURN_API_TOKEN ?? '';
        if (!this.token) {
            throw new Error(
                'Saturn API token is required.\n' +
                'Pass --token <token> or set SATURN_API_TOKEN env var.\n' +
                'Create a token at: <your-saturn-url>/settings/tokens',
            );
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

    async patch<T>(path: string, body?: unknown): Promise<T> {
        const res = await fetch(`${this.baseUrl}/api/v1${path}`, {
            method: 'PATCH',
            headers: this.headers(),
            body: body !== undefined ? JSON.stringify(body) : undefined,
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(`Saturn API ${res.status} on PATCH ${path}: ${text}`);
        }
        return res.json() as Promise<T>;
    }

    async delete<T>(path: string): Promise<T> {
        const res = await fetch(`${this.baseUrl}/api/v1${path}`, {
            method: 'DELETE',
            headers: this.headers(),
        });
        if (!res.ok) {
            const text = await res.text();
            throw new Error(`Saturn API ${res.status} on DELETE ${path}: ${text}`);
        }
        return res.json() as Promise<T>;
    }
}
