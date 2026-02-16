import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '../../utils/test-utils';
import { router } from '@inertiajs/react';
import SSLUpload from '../../../../resources/js/pages/SSL/Upload';

vi.mock('@inertiajs/react', async () => {
    const actual = await vi.importActual('@inertiajs/react');
    return {
        ...actual,
        router: {
            post: vi.fn(),
        },
        Link: ({ children, href }: { children: React.ReactNode; href: string }) => (
            <a href={href}>{children}</a>
        ),
    };
});

describe('SSL/Upload', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    const mockDomains = [
        { id: 1, domain: 'example.com' },
        { id: 2, domain: 'staging.example.com' },
    ];

    it('renders upload SSL certificate page with heading', () => {
        render(<SSLUpload />);

        expect(screen.getByRole('heading', { level: 1, name: /upload custom ssl certificate/i })).toBeInTheDocument();
        expect(screen.getByText(/import your own ssl\/tls certificate/i)).toBeInTheDocument();
    });

    it('renders back link to SSL page', () => {
        render(<SSLUpload />);

        const backLink = screen.getByRole('link', { name: /back to ssl certificates/i });
        expect(backLink).toBeInTheDocument();
        expect(backLink).toHaveAttribute('href', '/ssl');
    });

    it('displays domain selection dropdown', () => {
        render(<SSLUpload domains={mockDomains} />);

        expect(screen.getByLabelText(/select domain/i)).toBeInTheDocument();
    });

    it('shows available domains in dropdown', () => {
        render(<SSLUpload domains={mockDomains} />);

        const select = screen.getByLabelText(/select domain/i) as HTMLSelectElement;
        const options = Array.from(select.options).map(opt => opt.text);

        expect(options).toContain('Choose a domain...');
        expect(options).toContain('example.com');
        expect(options).toContain('staging.example.com');
    });

    it('displays certificate details form', () => {
        render(<SSLUpload />);

        expect(screen.getByText(/certificate \(pem format\) \*/i)).toBeInTheDocument();
        expect(screen.getByText(/private key \(pem format\) \*/i)).toBeInTheDocument();
        expect(screen.getByText(/certificate chain \(optional\)/i)).toBeInTheDocument();
    });

    it('shows textarea placeholders with PEM format examples', () => {
        render(<SSLUpload />);

        const textareas = screen.getAllByRole('textbox');
        expect(textareas[0]).toHaveAttribute('placeholder', expect.stringContaining('-----BEGIN CERTIFICATE-----'));
        expect(textareas[1]).toHaveAttribute('placeholder', expect.stringContaining('-----BEGIN PRIVATE KEY-----'));
        expect(textareas[2]).toHaveAttribute('placeholder', expect.stringContaining('-----BEGIN CERTIFICATE-----'));
    });

    it('displays hint text for form fields', () => {
        render(<SSLUpload />);

        expect(screen.getByText(/the domain this certificate will secure/i)).toBeInTheDocument();
        expect(screen.getByText(/your public ssl certificate in pem format/i)).toBeInTheDocument();
        expect(screen.getByText(/the private key for your certificate \(kept secure and encrypted\)/i)).toBeInTheDocument();
        expect(screen.getByText(/intermediate certificates \(ca bundle\) if required by your provider/i)).toBeInTheDocument();
    });

    it('shows sidebar information sections', () => {
        render(<SSLUpload />);

        expect(screen.getByText('About Custom Certificates')).toBeInTheDocument();
        expect(screen.getByText('PEM Format Guide')).toBeInTheDocument();
        expect(screen.getByText('Security Notice')).toBeInTheDocument();
        expect(screen.getByText('Common Issues')).toBeInTheDocument();
    });

    it('displays when to use custom certificates information', () => {
        render(<SSLUpload />);

        expect(screen.getByText(/extended validation \(ev\) certificates/i)).toBeInTheDocument();
        expect(screen.getByText(/organization validated \(ov\) certificates/i)).toBeInTheDocument();
        expect(screen.getByText(/wildcard certificates for multiple subdomains/i)).toBeInTheDocument();
        expect(screen.getByText(/certificates from specific cas required by compliance/i)).toBeInTheDocument();
    });

    it('shows PEM format examples in sidebar', () => {
        render(<SSLUpload />);

        expect(screen.getByText('Certificate:')).toBeInTheDocument();
        expect(screen.getByText('Private Key:')).toBeInTheDocument();
    });

    it('displays security notices', () => {
        render(<SSLUpload />);

        expect(screen.getByText(/private keys are encrypted at rest/i)).toBeInTheDocument();
        expect(screen.getByText(/never share your private key/i)).toBeInTheDocument();
        expect(screen.getByText(/certificates must be valid and not expired/i)).toBeInTheDocument();
        expect(screen.getByText(/domain must match certificate cn\/san/i)).toBeInTheDocument();
        expect(screen.getByText(/you're responsible for renewal/i)).toBeInTheDocument();
    });

    it('shows common issues information', () => {
        render(<SSLUpload />);

        expect(screen.getByText('Format Error')).toBeInTheDocument();
        expect(screen.getByText(/ensure files are in pem format, not der or p12/i)).toBeInTheDocument();

        expect(screen.getByText('Key Mismatch')).toBeInTheDocument();
        expect(screen.getByText(/certificate and private key must be a matching pair/i)).toBeInTheDocument();

        expect(screen.getByText('Chain Order')).toBeInTheDocument();
        expect(screen.getByText(/chain should be ordered: intermediate, then root ca/i)).toBeInTheDocument();
    });

    it('shows validate format button', () => {
        render(<SSLUpload />);

        expect(screen.getByRole('button', { name: /validate format/i })).toBeInTheDocument();
    });

    it('shows upload certificate button', () => {
        render(<SSLUpload />);

        expect(screen.getByRole('button', { name: /upload certificate/i })).toBeInTheDocument();
    });

    it('shows cancel link', () => {
        render(<SSLUpload />);

        const cancelLink = screen.getByRole('link', { name: /cancel/i });
        expect(cancelLink).toHaveAttribute('href', '/ssl');
    });

    it('disables validate button when no input provided', () => {
        render(<SSLUpload />);

        const validateButton = screen.getByRole('button', { name: /validate format/i });
        expect(validateButton).toBeDisabled();
    });

    it('enables validate button when certificate or key is entered', async () => {
        const { user } = render(<SSLUpload />);

        const textareas = screen.getAllByRole('textbox');
        await user.type(textareas[0], '-----BEGIN CERTIFICATE-----\ntest\n-----END CERTIFICATE-----');

        const validateButton = screen.getByRole('button', { name: /validate format/i });
        expect(validateButton).not.toBeDisabled();
    });

    it('validates certificate format when validate button is clicked', async () => {
        const { user } = render(<SSLUpload />);

        const textareas = screen.getAllByRole('textbox');
        // Use fireEvent.change for long PEM strings to avoid user.type timeout
        fireEvent.change(textareas[0], { target: { value: '-----BEGIN CERTIFICATE-----\nMIIDXTCCAkWgAwIBAgIJAKL\n-----END CERTIFICATE-----' } });
        fireEvent.change(textareas[1], { target: { value: '-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhki\n-----END PRIVATE KEY-----' } });

        const validateButton = screen.getByRole('button', { name: /validate format/i });
        await user.click(validateButton);

        expect(screen.getByText('Validation Feedback')).toBeInTheDocument();
        expect(screen.getByText(/certificate format is valid/i)).toBeInTheDocument();
        expect(screen.getByText(/private key format is valid/i)).toBeInTheDocument();
    });

    it('shows error for invalid certificate format', async () => {
        const { user } = render(<SSLUpload />);

        const textareas = screen.getAllByRole('textbox');
        await user.type(textareas[0], 'invalid certificate data');

        const validateButton = screen.getByRole('button', { name: /validate format/i });
        await user.click(validateButton);

        expect(screen.getByText(/certificate must be in pem format/i)).toBeInTheDocument();
    });

    it('validates form on submit', async () => {
        const { user } = render(<SSLUpload domains={mockDomains} />);

        const uploadButton = screen.getByRole('button', { name: /upload certificate/i });
        await user.click(uploadButton);

        expect(screen.getByText(/certificate is required/i)).toBeInTheDocument();
        expect(screen.getByText(/private key is required/i)).toBeInTheDocument();
        expect(screen.getByText(/please select a domain/i)).toBeInTheDocument();
    });

    it('validates certificate PEM format on submit', async () => {
        const { user } = render(<SSLUpload domains={mockDomains} />);

        const textareas = screen.getAllByRole('textbox');
        await user.type(textareas[0], 'invalid cert');
        await user.type(textareas[1], '-----BEGIN PRIVATE KEY-----\nkey\n-----END PRIVATE KEY-----');

        const domainSelect = screen.getByLabelText(/select domain/i);
        await user.selectOptions(domainSelect, '1');

        const uploadButton = screen.getByRole('button', { name: /upload certificate/i });
        await user.click(uploadButton);

        expect(screen.getByText(/invalid certificate format \(must be pem\)/i)).toBeInTheDocument();
    });

    it('validates private key PEM format on submit', async () => {
        const { user } = render(<SSLUpload domains={mockDomains} />);

        const textareas = screen.getAllByRole('textbox');
        await user.type(textareas[0], '-----BEGIN CERTIFICATE-----\ncert\n-----END CERTIFICATE-----');
        await user.type(textareas[1], 'invalid key');

        const domainSelect = screen.getByLabelText(/select domain/i);
        await user.selectOptions(domainSelect, '1');

        const uploadButton = screen.getByRole('button', { name: /upload certificate/i });
        await user.click(uploadButton);

        expect(screen.getByText(/invalid private key format \(must be pem\)/i)).toBeInTheDocument();
    });

    it('submits form with correct data when valid', async () => {
        const { user } = render(<SSLUpload domains={mockDomains} />);

        const textareas = screen.getAllByRole('textbox');
        const certificate = '-----BEGIN CERTIFICATE-----\nMIIDXTCCAkWgAwIBAgIJAKL\n-----END CERTIFICATE-----';
        const privateKey = '-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBgkqhki\n-----END PRIVATE KEY-----';
        const chain = '-----BEGIN CERTIFICATE-----\nMIIEkjCCA3qgAwIBAgIQCg\n-----END CERTIFICATE-----';

        // Use fireEvent.change for long PEM strings to avoid user.type timeout
        fireEvent.change(textareas[0], { target: { value: certificate } });
        fireEvent.change(textareas[1], { target: { value: privateKey } });
        fireEvent.change(textareas[2], { target: { value: chain } });

        const domainSelect = screen.getByLabelText(/select domain/i);
        await user.selectOptions(domainSelect, '1');

        const uploadButton = screen.getByRole('button', { name: /upload certificate/i });
        await user.click(uploadButton);

        expect(router.post).toHaveBeenCalledWith('/ssl/upload', expect.objectContaining({
            certificate,
            private_key: privateKey,
            certificate_chain: chain,
            domain_id: '1',
        }), expect.any(Object));
    });

    it('sends null for optional certificate chain if not provided', async () => {
        const { user } = render(<SSLUpload domains={mockDomains} />);

        const textareas = screen.getAllByRole('textbox');
        const certificate = '-----BEGIN CERTIFICATE-----\ncert\n-----END CERTIFICATE-----';
        const privateKey = '-----BEGIN PRIVATE KEY-----\nkey\n-----END PRIVATE KEY-----';

        // Use fireEvent.change for PEM strings to avoid user.type timeout
        fireEvent.change(textareas[0], { target: { value: certificate } });
        fireEvent.change(textareas[1], { target: { value: privateKey } });

        const domainSelect = screen.getByLabelText(/select domain/i);
        await user.selectOptions(domainSelect, '1');

        const uploadButton = screen.getByRole('button', { name: /upload certificate/i });
        await user.click(uploadButton);

        expect(router.post).toHaveBeenCalledWith('/ssl/upload', expect.objectContaining({
            certificate_chain: null,
        }), expect.any(Object));
    });
});
