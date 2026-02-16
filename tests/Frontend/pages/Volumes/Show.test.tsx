import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import VolumeShow from '@/pages/Volumes/Show';
import type { Volume } from '@/types';

const mockVolume: Volume = {
    id: 1, uuid: 'vol-1', name: 'data-volume', description: 'Main data', size: 100, used: 50,
    status: 'active' as const, storage_class: 'standard' as const, mount_path: '/data',
    attached_services: [], created_at: '2024-01-01', updated_at: '2024-01-15'
};

describe('Volume Show Page', () => {
    beforeEach(() => vi.clearAllMocks());

    it('should render volume name', () => {
        render(<VolumeShow volume={mockVolume} />);
        expect(screen.getByText('data-volume')).toBeInTheDocument();
    });

    it('should render storage usage', () => {
        render(<VolumeShow volume={mockVolume} />);
        expect(screen.getByText('Storage Usage')).toBeInTheDocument();
    });

    it('should render snapshot section', () => {
        render(<VolumeShow volume={mockVolume} />);
        expect(screen.getByText('Snapshots')).toBeInTheDocument();
    });
});
