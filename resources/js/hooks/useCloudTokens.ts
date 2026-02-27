import { router } from '@inertiajs/react';

export function useCloudTokens() {
    const getCsrf = () =>
        document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    const createToken = (name: string, provider: string, token: string) => {
        router.post(
            '/settings/cloud-tokens',
            { name, provider, token },
            { preserveScroll: true },
        );
    };

    const deleteToken = (uuid: string) => {
        router.delete(`/settings/cloud-tokens/${uuid}`, { preserveScroll: true });
    };

    const validateToken = async (uuid: string): Promise<{ valid: boolean; message: string }> => {
        const resp = await fetch(`/settings/cloud-tokens/${uuid}/validate`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': getCsrf(),
            },
        });
        return resp.json();
    };

    return { createToken, deleteToken, validateToken };
}
