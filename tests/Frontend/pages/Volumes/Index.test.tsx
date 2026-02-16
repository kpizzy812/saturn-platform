import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import VolumesIndex from '@/pages/Volumes/Index';
import type { Volume } from '@/types';

const mockVolumes: Volume[] = [{
    id: 1, uuid: 'vol-1', name: 'data-volume', description: 'Main data', size: 100, used: 50,
    status: 'active' as const, storage_class: 'standard' as const, mount_path: '/data',
    attached_services: [], created_at: '2024-01-01', updated_at: '2024-01-15'
}];

describe('Volumes Index Page', () => {
    beforeEach(() => vi.clearAllMocks());

    it('should render page title', () => {
        render(<VolumesIndex volumes={mockVolumes} />);
        expect(screen.getByText('Volumes')).toBeInTheDocument();
    });

    it('should render storage overview stats', () => {
        render(<VolumesIndex volumes={mockVolumes} />);
        expect(screen.getByText('Total Volumes')).toBeInTheDocument();
        expect(screen.getByText('Total Storage')).toBeInTheDocument();
    });

    it('should render create button', () => {
        render(<VolumesIndex volumes={mockVolumes} />);
        expect(screen.getByText('Create Volume')).toBeInTheDocument();
    });

    it('should render empty state when no volumes', () => {
        render(<VolumesIndex volumes={[]} />);
        expect(screen.getByText('No volumes yet')).toBeInTheDocument();
    });
});
