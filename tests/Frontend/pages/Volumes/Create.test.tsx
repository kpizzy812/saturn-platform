import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import VolumeCreate from '@/pages/Volumes/Create';

describe('Volume Create Page', () => {
    beforeEach(() => vi.clearAllMocks());

    it('should render page title', () => {
        render(<VolumeCreate />);
        expect(screen.getByText('Create Volume')).toBeInTheDocument();
    });

    it('should render form fields', () => {
        render(<VolumeCreate />);
        expect(screen.getByText('Volume Name')).toBeInTheDocument();
        expect(screen.getByText('Mount Path')).toBeInTheDocument();
    });

    it('should render size options', () => {
        render(<VolumeCreate />);
        expect(screen.getByText('Size Selection')).toBeInTheDocument();
    });
});
