import { useState } from 'react';
import { router } from '@inertiajs/react';
import { AppLayout } from '@/components/layout';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    Button,
    Input,
    Textarea,
    Select,
} from '@/components/ui';
import {
    Search,
    BookOpen,
    MessageCircle,
    ExternalLink,
    ChevronDown,
    ChevronUp,
    Check,
    AlertCircle,
    Zap,
    Shield,
    Server,
    Database,
    Github,
    Users,
} from 'lucide-react';

interface FAQItem {
    id: string;
    category: string;
    question: string;
    answer: string;
}

const faqItems: FAQItem[] = [
    {
        id: '1',
        category: 'Getting Started',
        question: 'How do I deploy my first application?',
        answer:
            'To deploy your first application: 1) Connect your Git repository (GitHub, GitLab, or Bitbucket), 2) Select the repository and branch, 3) Configure build settings, 4) Click Deploy. Saturn will automatically build and deploy your application.',
    },
    {
        id: '2',
        category: 'Getting Started',
        question: 'What types of applications can I deploy?',
        answer:
            'Saturn supports a wide range of applications including Node.js, Python, Ruby, Go, PHP, static sites, and Docker containers. We auto-detect your framework and configure the build process automatically.',
    },
    {
        id: '3',
        category: 'Deployment',
        question: 'How do automatic deployments work?',
        answer:
            'When you connect your Git repository, Saturn automatically deploys whenever you push to your selected branch. You can also trigger manual deployments from the dashboard or disable auto-deploy if needed.',
    },
    {
        id: '4',
        category: 'Deployment',
        question: 'Can I rollback to a previous deployment?',
        answer:
            'Yes! Navigate to your service\'s Deployments page, find the deployment you want to rollback to, and click "Rollback". This will redeploy that specific version of your application.',
    },
    {
        id: '5',
        category: 'Deployment',
        question: 'How long does a deployment take?',
        answer:
            'Deployment times vary based on your application size and complexity. Most Node.js apps deploy in 1-3 minutes. Larger applications or those with extensive build steps may take longer.',
    },
    {
        id: '6',
        category: 'Databases',
        question: 'What databases are supported?',
        answer:
            'Saturn supports PostgreSQL, MySQL, MariaDB, MongoDB, Redis, KeyDB, Dragonfly, and ClickHouse. You can provision a new database or connect to an external one.',
    },
    {
        id: '7',
        category: 'Databases',
        question: 'How do I backup my database?',
        answer:
            'Go to your database settings and enable automatic backups. You can configure backup frequency (hourly, daily, weekly) and retention period. Manual backups can be triggered anytime from the Backups tab.',
    },
    {
        id: '8',
        category: 'Configuration',
        question: 'How do I set environment variables?',
        answer:
            'Navigate to your service > Variables tab. Click "Add Variable", enter the name and value, and save. Variables are encrypted at rest and injected into your application at runtime.',
    },
    {
        id: '9',
        category: 'Configuration',
        question: 'How do I configure custom domains?',
        answer:
            'Go to your service > Domains tab, click "Add Domain", enter your domain name, and follow the DNS configuration instructions. SSL certificates are automatically provisioned and renewed.',
    },
    {
        id: '10',
        category: 'Billing',
        question: 'What is the pricing model?',
        answer:
            'Saturn offers a free tier with limited resources. Paid plans start at $5/month and scale based on resource usage. You only pay for what you use, with no hidden fees or egress charges.',
    },
    {
        id: '11',
        category: 'Billing',
        question: 'Can I upgrade or downgrade my plan?',
        answer:
            'Yes! You can change your plan anytime from Settings > Billing. Upgrades take effect immediately, while downgrades take effect at the start of your next billing cycle.',
    },
    {
        id: '12',
        category: 'Troubleshooting',
        question: 'My deployment failed. What should I do?',
        answer:
            'Check the build logs for error messages. Common issues include: missing dependencies, incorrect build commands, or insufficient resources. Our documentation has detailed troubleshooting guides for each framework.',
    },
    {
        id: '13',
        category: 'Troubleshooting',
        question: 'How do I view application logs?',
        answer:
            'Navigate to your service > Logs tab to view real-time application logs. You can filter by log level, search for specific messages, and download logs for analysis.',
    },
    {
        id: '14',
        category: 'Security',
        question: 'Is my data encrypted?',
        answer:
            'Yes! All data is encrypted in transit (TLS 1.3) and at rest (AES-256). Environment variables and secrets are stored using industry-standard encryption practices.',
    },
    {
        id: '15',
        category: 'Security',
        question: 'How do I report a security vulnerability?',
        answer:
            'Please report security vulnerabilities through the Contact Support form below or via the GitHub repository. We take security seriously and will respond promptly.',
    },
];

const categories = [...new Set(faqItems.map((item) => item.category))];

export default function SupportIndex() {
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedCategory, setSelectedCategory] = useState('all');
    const [expandedFAQ, setExpandedFAQ] = useState<string | null>(null);

    // Contact form state
    const [contactName, setContactName] = useState('');
    const [contactEmail, setContactEmail] = useState('');
    const [contactSubject, setContactSubject] = useState('general');
    const [contactMessage, setContactMessage] = useState('');

    // Filter FAQs
    const filteredFAQs = faqItems.filter((item) => {
        const matchesCategory =
            selectedCategory === 'all' || item.category === selectedCategory;
        const matchesSearch =
            searchQuery === '' ||
            item.question.toLowerCase().includes(searchQuery.toLowerCase()) ||
            item.answer.toLowerCase().includes(searchQuery.toLowerCase());
        return matchesCategory && matchesSearch;
    });

    const handleContactSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.post('/support/contact', {
            name: contactName,
            email: contactEmail,
            subject: contactSubject,
            message: contactMessage,
        });
        // Reset form
        setContactName('');
        setContactEmail('');
        setContactSubject('general');
        setContactMessage('');
    };

    return (
        <AppLayout title="Support" breadcrumbs={[{ label: 'Support' }]}>
            {/* Header */}
            <div className="mb-8 text-center">
                <h1 className="mb-2 text-3xl font-bold text-foreground">How can we help?</h1>
                <p className="text-foreground-muted">
                    Search our documentation or contact support
                </p>
            </div>

            {/* Search */}
            <Card className="mb-8">
                <CardContent className="p-6">
                    <div className="relative">
                        <Search className="absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-foreground-muted" />
                        <Input
                            placeholder="Search for help articles..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-12 text-lg"
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Quick Links */}
            <div className="mb-8 grid gap-4 md:grid-cols-3">
                <QuickLinkCard
                    icon={<BookOpen className="h-6 w-6" />}
                    title="Documentation"
                    description="Comprehensive guides and API reference"
                    href="https://coolify.io/docs"
                />
                <QuickLinkCard
                    icon={<Users className="h-6 w-6" />}
                    title="Community"
                    description="Join our Discord community"
                    href="https://discord.gg/coolify"
                />
                <QuickLinkCard
                    icon={<Github className="h-6 w-6" />}
                    title="GitHub"
                    description="Report issues and contribute"
                    href="https://github.com/coollabsio/coolify"
                />
            </div>

            {/* Popular Topics */}
            <div className="mb-8">
                <h2 className="mb-4 text-xl font-semibold text-foreground">
                    Popular Topics
                </h2>
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <TopicCard
                        icon={<Zap className="h-5 w-5 text-warning" />}
                        title="Getting Started"
                        count="12 articles"
                    />
                    <TopicCard
                        icon={<Server className="h-5 w-5 text-primary" />}
                        title="Deployments"
                        count="18 articles"
                    />
                    <TopicCard
                        icon={<Database className="h-5 w-5 text-info" />}
                        title="Databases"
                        count="15 articles"
                    />
                    <TopicCard
                        icon={<Shield className="h-5 w-5 text-success" />}
                        title="Security"
                        count="8 articles"
                    />
                </div>
            </div>

            {/* FAQ Section */}
            <div className="mb-8">
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-xl font-semibold text-foreground">
                        Frequently Asked Questions
                    </h2>
                    <div className="flex gap-2">
                        <Button
                            variant={selectedCategory === 'all' ? 'default' : 'ghost'}
                            size="sm"
                            onClick={() => setSelectedCategory('all')}
                        >
                            All
                        </Button>
                        {categories.map((category) => (
                            <Button
                                key={category}
                                variant={selectedCategory === category ? 'default' : 'ghost'}
                                size="sm"
                                onClick={() => setSelectedCategory(category)}
                            >
                                {category}
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="space-y-3">
                    {filteredFAQs.map((faq) => (
                        <Card key={faq.id}>
                            <CardContent className="p-0">
                                <button
                                    onClick={() =>
                                        setExpandedFAQ(
                                            expandedFAQ === faq.id ? null : faq.id
                                        )
                                    }
                                    className="flex w-full items-center justify-between p-4 text-left transition-colors hover:bg-background-tertiary"
                                >
                                    <div className="flex-1">
                                        <div className="mb-1 flex items-center gap-2">
                                            <span className="text-sm text-primary">
                                                {faq.category}
                                            </span>
                                        </div>
                                        <h3 className="font-medium text-foreground">
                                            {faq.question}
                                        </h3>
                                    </div>
                                    {expandedFAQ === faq.id ? (
                                        <ChevronUp className="h-5 w-5 text-foreground-muted" />
                                    ) : (
                                        <ChevronDown className="h-5 w-5 text-foreground-muted" />
                                    )}
                                </button>
                                {expandedFAQ === faq.id && (
                                    <div className="border-t border-border bg-background-secondary p-4">
                                        <p className="text-foreground-muted">{faq.answer}</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {filteredFAQs.length === 0 && (
                    <Card className="p-12 text-center">
                        <AlertCircle className="mx-auto h-12 w-12 text-foreground-muted" />
                        <h3 className="mt-4 text-lg font-medium text-foreground">
                            No results found
                        </h3>
                        <p className="mt-2 text-foreground-muted">
                            Try adjusting your search or category filter
                        </p>
                    </Card>
                )}
            </div>

            {/* Contact Support */}
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <MessageCircle className="h-5 w-5" />
                        Contact Support
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="mb-6 text-foreground-muted">
                        Can't find what you're looking for? Send us a message and we'll get
                        back to you within 24 hours.
                    </p>
                    <form onSubmit={handleContactSubmit} className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground">
                                    Name
                                </label>
                                <Input
                                    placeholder="Your name"
                                    value={contactName}
                                    onChange={(e) => setContactName(e.target.value)}
                                    required
                                />
                            </div>
                            <div>
                                <label className="mb-1 block text-sm font-medium text-foreground">
                                    Email
                                </label>
                                <Input
                                    type="email"
                                    placeholder="your@email.com"
                                    value={contactEmail}
                                    onChange={(e) => setContactEmail(e.target.value)}
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground">
                                Subject
                            </label>
                            <Select
                                value={contactSubject}
                                onChange={(e) => setContactSubject(e.target.value)}
                            >
                                <option value="general">General Inquiry</option>
                                <option value="technical">Technical Support</option>
                                <option value="billing">Billing Question</option>
                                <option value="feature">Feature Request</option>
                                <option value="bug">Bug Report</option>
                            </Select>
                        </div>

                        <div>
                            <label className="mb-1 block text-sm font-medium text-foreground">
                                Message
                            </label>
                            <Textarea
                                placeholder="Describe your issue or question..."
                                value={contactMessage}
                                onChange={(e) => setContactMessage(e.target.value)}
                                rows={6}
                                required
                            />
                        </div>

                        <div className="flex justify-end">
                            <Button type="submit">Send Message</Button>
                        </div>
                    </form>
                </CardContent>
            </Card>

            {/* Status Info */}
            <div className="mt-8 text-center">
                <span className="inline-flex items-center gap-2 text-sm text-foreground-muted">
                    <Check className="h-4 w-4 text-success" />
                    Saturn Platform
                </span>
            </div>
        </AppLayout>
    );
}

function QuickLinkCard({
    icon,
    title,
    description,
    href,
}: {
    icon: React.ReactNode;
    title: string;
    description: string;
    href: string;
}) {
    return (
        <a href={href} target="_blank" rel="noopener noreferrer">
            <Card className="h-full transition-all hover:scale-105 hover:border-primary/50">
                <CardContent className="p-6">
                    <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-lg bg-primary/10 text-primary">
                        {icon}
                    </div>
                    <h3 className="mb-2 flex items-center gap-2 font-semibold text-foreground">
                        {title}
                        <ExternalLink className="h-4 w-4" />
                    </h3>
                    <p className="text-sm text-foreground-muted">{description}</p>
                </CardContent>
            </Card>
        </a>
    );
}

function TopicCard({
    icon,
    title,
    count,
}: {
    icon: React.ReactNode;
    title: string;
    count: string;
}) {
    return (
        <Card className="transition-colors hover:border-primary/50">
            <CardContent className="p-4">
                <div className="mb-2 flex items-center gap-2">
                    {icon}
                    <h3 className="font-semibold text-foreground">{title}</h3>
                </div>
                <p className="text-sm text-foreground-muted">{count}</p>
            </CardContent>
        </Card>
    );
}
