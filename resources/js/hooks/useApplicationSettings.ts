import * as React from 'react';
import { router } from '@inertiajs/react';
import type { ApplicationSettings } from '@/types';

interface UseApplicationSettingsOptions {
    applicationUuid: string;
    initialSettings: Partial<ApplicationSettings>;
}

interface UseApplicationSettingsReturn {
    settings: Partial<ApplicationSettings>;
    setSettings: React.Dispatch<React.SetStateAction<Partial<ApplicationSettings>>>;
    isSaving: boolean;
    errors: Record<string, string>;
    saveStatus: 'idle' | 'success' | 'error';
    save: (overrideSettings?: Partial<ApplicationSettings>) => void;
}

export function useApplicationSettings({
    applicationUuid,
    initialSettings,
}: UseApplicationSettingsOptions): UseApplicationSettingsReturn {
    const [settings, setSettings] = React.useState<Partial<ApplicationSettings>>(initialSettings);
    const [isSaving, setIsSaving] = React.useState(false);
    const [errors, setErrors] = React.useState<Record<string, string>>({});
    const [saveStatus, setSaveStatus] = React.useState<'idle' | 'success' | 'error'>('idle');

    React.useEffect(() => {
        if (saveStatus !== 'idle') {
            const timer = setTimeout(() => setSaveStatus('idle'), 3000);
            return () => clearTimeout(timer);
        }
    }, [saveStatus]);

    const save = (overrideSettings?: Partial<ApplicationSettings>) => {
        setIsSaving(true);
        setErrors({});
        router.patch(`/applications/${applicationUuid}/settings`, overrideSettings ?? settings, {
            preserveScroll: true,
            onSuccess: () => {
                setIsSaving(false);
                setSaveStatus('success');
            },
            onError: (validationErrors) => {
                setIsSaving(false);
                setErrors(validationErrors);
                setSaveStatus('error');
            },
        });
    };

    return { settings, setSettings, isSaving, errors, saveStatus, save };
}
