import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '../../utils/test-utils';
import { Select } from '@/components/ui/Select';

const options = [
    { value: '1', label: 'Option 1' },
    { value: '2', label: 'Option 2' },
    { value: '3', label: 'Option 3' },
];

describe('Select Component', () => {
    it('renders with options', () => {
        render(<Select options={options} />);
        expect(screen.getByRole('combobox')).toBeInTheDocument();
        expect(screen.getAllByRole('option')).toHaveLength(3);
    });

    it('renders with label', () => {
        render(<Select label="Choose option" options={options} />);
        expect(screen.getByLabelText('Choose option')).toBeInTheDocument();
    });

    it('shows error message', () => {
        render(<Select options={options} error="Selection required" />);
        expect(screen.getByText('Selection required')).toBeInTheDocument();
    });

    it('handles selection change', async () => {
        const handleChange = vi.fn();
        const { user } = render(<Select options={options} onChange={handleChange} />);

        await user.selectOptions(screen.getByRole('combobox'), '2');
        expect(handleChange).toHaveBeenCalled();
    });

    it('can be disabled', () => {
        render(<Select options={options} disabled />);
        expect(screen.getByRole('combobox')).toBeDisabled();
    });
});
