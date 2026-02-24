import { useState } from 'react';
import { Badge } from '@/components/ui/Badge';
import { ChevronDown, ChevronRight, BookOpen, Sparkles, Package, Database, FolderTree, FileCode, Terminal } from 'lucide-react';
import { cn } from '@/lib/utils';

interface DeployGuideProps {
    /** Compact mode shows just a toggle link, full mode shows an inline card */
    variant?: 'compact' | 'full';
    /** Which section to open by default */
    defaultOpen?: string | null;
    className?: string;
}

interface SectionProps {
    id: string;
    title: string;
    icon: React.ReactNode;
    isOpen: boolean;
    onToggle: () => void;
    children: React.ReactNode;
}

function Section({ title, icon, isOpen, onToggle, children }: SectionProps) {
    return (
        <div className="border-b border-white/[0.06] last:border-b-0">
            <button
                type="button"
                onClick={onToggle}
                className="flex w-full items-center gap-2 py-2.5 text-left text-sm font-medium text-foreground hover:text-primary transition-colors"
            >
                {isOpen ? <ChevronDown className="h-3.5 w-3.5 text-foreground-muted" /> : <ChevronRight className="h-3.5 w-3.5 text-foreground-muted" />}
                <span className="flex items-center gap-2">
                    {icon}
                    {title}
                </span>
            </button>
            {isOpen && (
                <div className="pb-3 pl-6 text-sm text-foreground-muted leading-relaxed">
                    {children}
                </div>
            )}
        </div>
    );
}

function FrameworkGrid() {
    const frameworks = [
        { group: 'Node.js', items: ['Next.js', 'NestJS', 'Nuxt', 'Remix', 'Astro', 'SvelteKit', 'Express', 'Fastify', 'Hono', 'Vite+React', 'Vite+Vue'] },
        { group: 'Python', items: ['Django', 'FastAPI', 'Flask'] },
        { group: 'PHP', items: ['Laravel', 'Symfony'] },
        { group: 'Go', items: ['Fiber', 'Gin', 'Echo'] },
        { group: 'Ruby', items: ['Rails', 'Sinatra'] },
        { group: 'Rust', items: ['Axum', 'Actix'] },
        { group: 'Other', items: ['Phoenix (Elixir)', 'Spring Boot (Java)'] },
    ];

    return (
        <div className="space-y-2 mt-2">
            {frameworks.map(({ group, items }) => (
                <div key={group} className="flex flex-wrap items-center gap-1.5">
                    <span className="text-xs font-medium text-foreground w-16 shrink-0">{group}</span>
                    {items.map(item => (
                        <Badge key={item} variant="secondary" size="sm">{item}</Badge>
                    ))}
                </div>
            ))}
        </div>
    );
}

function DatabaseGrid() {
    const databases = [
        { name: 'PostgreSQL', icon: '🐘', packages: 'pg, prisma, psycopg2, asyncpg' },
        { name: 'MySQL', icon: '🐬', packages: 'mysql2, mysqlclient, pymysql' },
        { name: 'MongoDB', icon: '🍃', packages: 'mongoose, pymongo, mongodb' },
        { name: 'Redis', icon: '🔴', packages: 'ioredis, bull, predis, sidekiq' },
        { name: 'ClickHouse', icon: '🏠', packages: '@clickhouse/client' },
        { name: 'SQLite', icon: '📁', packages: 'better-sqlite3, prisma (sqlite)' },
    ];

    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-2">
            {databases.map(db => (
                <div key={db.name} className="flex items-start gap-2 p-2 rounded bg-white/[0.02]">
                    <span className="text-base">{db.icon}</span>
                    <div>
                        <div className="text-xs font-medium text-foreground">{db.name}</div>
                        <div className="text-[11px] text-foreground-muted">{db.packages}</div>
                    </div>
                </div>
            ))}
        </div>
    );
}

function MonorepoGrid() {
    const types = ['Turborepo', 'Nx', 'Lerna', 'pnpm workspaces', 'Rush', 'npm/yarn workspaces'];

    return (
        <div className="flex flex-wrap gap-1.5 mt-2">
            {types.map(t => (
                <Badge key={t} variant="secondary" size="sm">{t}</Badge>
            ))}
        </div>
    );
}

export function DeployGuide({ variant = 'compact', defaultOpen = null, className }: DeployGuideProps) {
    const [isVisible, setIsVisible] = useState(variant === 'full');
    const [openSection, setOpenSection] = useState<string | null>(defaultOpen);

    const toggleSection = (id: string) => {
        setOpenSection(prev => prev === id ? null : id);
    };

    const guideContent = (
        <div className={cn('space-y-0', variant === 'full' && 'mt-1')}>
            <Section
                id="what-detected"
                title="What gets auto-detected?"
                icon={<Sparkles className="h-3.5 w-3.5 text-primary" />}
                isOpen={openSection === 'what-detected'}
                onToggle={() => toggleSection('what-detected')}
            >
                <p>Saturn clones your repo (shallow, depth=1) and scans for:</p>
                <ul className="mt-1.5 space-y-1 list-disc list-inside">
                    <li><span className="text-foreground">Framework & language</span> — by package.json, requirements.txt, go.mod, etc.</li>
                    <li><span className="text-foreground">Databases</span> — by dependency packages (pg, mongoose, redis...)</li>
                    <li><span className="text-foreground">Monorepo structure</span> — Turborepo, Nx, Lerna, pnpm workspaces</li>
                    <li><span className="text-foreground">Dockerfile</span> — ports, base image, build args</li>
                    <li><span className="text-foreground">docker-compose.yml</span> — services and database containers</li>
                    <li><span className="text-foreground">.env.example</span> — required environment variables</li>
                    <li><span className="text-foreground">CI/CD config</span> — build/test commands from GitHub Actions</li>
                    <li><span className="text-foreground">Health checks</span> — /health, /api/health endpoints</li>
                </ul>
            </Section>

            <Section
                id="frameworks"
                title="Supported frameworks"
                icon={<Package className="h-3.5 w-3.5 text-primary" />}
                isOpen={openSection === 'frameworks'}
                onToggle={() => toggleSection('frameworks')}
            >
                <p>Saturn detects 25+ frameworks across 8 languages:</p>
                <FrameworkGrid />
                <p className="mt-2 text-xs">
                    Projects with a <code className="px-1 py-0.5 bg-white/[0.06] rounded text-foreground">Dockerfile</code> or{' '}
                    <code className="px-1 py-0.5 bg-white/[0.06] rounded text-foreground">Procfile</code> are also supported regardless of framework.
                </p>
            </Section>

            <Section
                id="databases"
                title="Database auto-detection"
                icon={<Database className="h-3.5 w-3.5 text-primary" />}
                isOpen={openSection === 'databases'}
                onToggle={() => toggleSection('databases')}
            >
                <p>Databases are detected from your dependency files and provisioned automatically. Connection URLs are injected as env vars.</p>
                <DatabaseGrid />
                <p className="mt-2 text-xs">
                    SQLite creates a <span className="text-foreground">persistent volume</span> instead of a separate container.
                    External services (S3, Elasticsearch, RabbitMQ, Kafka) are flagged but require manual setup.
                </p>
            </Section>

            <Section
                id="monorepo"
                title="Monorepo support"
                icon={<FolderTree className="h-3.5 w-3.5 text-primary" />}
                isOpen={openSection === 'monorepo'}
                onToggle={() => toggleSection('monorepo')}
            >
                <p>Saturn detects monorepo tools and creates separate applications per workspace with proper dependency ordering.</p>
                <MonorepoGrid />
                <p className="mt-2 text-xs">
                    Even without a monorepo tool, Saturn detects <span className="text-foreground">simple monorepos</span> — 2+ directories at the root each containing a package.json, Dockerfile, or similar markers.
                    Each app gets its own container, and internal service URLs are injected automatically.
                </p>
            </Section>

            <Section
                id="repo-checklist"
                title="Prepare your repo (checklist)"
                icon={<FileCode className="h-3.5 w-3.5 text-primary" />}
                isOpen={openSection === 'repo-checklist'}
                onToggle={() => toggleSection('repo-checklist')}
            >
                <div className="space-y-2 mt-1">
                    <ChecklistItem
                        label="Dependency file at root (or per workspace)"
                        detail="package.json, requirements.txt, go.mod, composer.json, Gemfile, Cargo.toml, etc."
                        required
                    />
                    <ChecklistItem
                        label="Start command defined"
                        detail='In package.json "scripts.start", Procfile, Dockerfile CMD, or Saturn will auto-detect.'
                    />
                    <ChecklistItem
                        label=".env.example for required variables"
                        detail="Saturn reads this file and pre-fills the env configuration form."
                    />
                    <ChecklistItem
                        label="Dockerfile (optional, takes priority)"
                        detail="If present, Saturn uses it instead of Nixpacks auto-build. Make sure EXPOSE is set."
                    />
                    <ChecklistItem
                        label="Health check endpoint (recommended)"
                        detail="GET /health or /api/health — Saturn auto-detects and configures health monitoring."
                    />
                </div>
            </Section>

            <Section
                id="cli-deploy"
                title="CLI smart deploy (.saturn.yml)"
                icon={<Terminal className="h-3.5 w-3.5 text-primary" />}
                isOpen={openSection === 'cli-deploy'}
                onToggle={() => toggleSection('cli-deploy')}
            >
                <p>For monorepo CI/CD, create a <code className="px-1 py-0.5 bg-white/[0.06] rounded text-foreground">.saturn.yml</code> at the root:</p>
                <pre className="mt-2 p-3 rounded bg-white/[0.03] border border-white/[0.06] text-xs font-mono text-foreground overflow-x-auto">
{`version: 1
base_branch: main
components:
  api:
    resource: api-service    # Saturn resource name
    path: apps/api/**        # Glob pattern
    triggers:
      - web                  # Also deploy web when api changes
  web:
    resource: web-app
    path: apps/web/**`}
                </pre>
                <p className="mt-2 text-xs">
                    The CLI compares <code className="px-1 py-0.5 bg-white/[0.06] rounded text-foreground">git diff base_branch..HEAD</code>,
                    matches changed files to components, and deploys only what changed.
                    Run <code className="px-1 py-0.5 bg-white/[0.06] rounded text-foreground">saturn deploy smart</code> or use{' '}
                    <code className="px-1 py-0.5 bg-white/[0.06] rounded text-foreground">saturn deploy smart --init</code> to auto-generate the config.
                </p>
            </Section>
        </div>
    );

    if (variant === 'full') {
        return (
            <div className={cn('rounded-lg border border-white/[0.06] bg-white/[0.02] p-4', className)}>
                <div className="flex items-center gap-2 mb-1">
                    <BookOpen className="h-4 w-4 text-primary" />
                    <span className="text-sm font-medium text-foreground">Deploy Guide</span>
                </div>
                <p className="text-xs text-foreground-muted mb-2">
                    What Saturn auto-detects and how to prepare your repository.
                </p>
                {guideContent}
            </div>
        );
    }

    // Compact variant — toggle link
    return (
        <div className={className}>
            <button
                type="button"
                onClick={() => setIsVisible(!isVisible)}
                className="flex items-center gap-1.5 text-xs text-foreground-muted hover:text-primary transition-colors"
            >
                <BookOpen className="h-3.5 w-3.5" />
                <span>{isVisible ? 'Hide deploy guide' : 'What can Saturn detect? View deploy guide'}</span>
                {isVisible ? <ChevronDown className="h-3 w-3" /> : <ChevronRight className="h-3 w-3" />}
            </button>
            {isVisible && (
                <div className="mt-3 rounded-lg border border-white/[0.06] bg-white/[0.02] p-4">
                    {guideContent}
                </div>
            )}
        </div>
    );
}

interface ChecklistItemProps {
    label: string;
    detail: string;
    required?: boolean;
}

function ChecklistItem({ label, detail, required }: ChecklistItemProps) {
    return (
        <div className="flex items-start gap-2">
            <div className={cn(
                'mt-0.5 h-4 w-4 rounded border flex items-center justify-center shrink-0 text-[10px]',
                required ? 'border-primary/40 text-primary' : 'border-white/[0.12] text-foreground-muted'
            )}>
                {required ? '!' : '~'}
            </div>
            <div>
                <div className="text-xs text-foreground">{label}</div>
                <div className="text-[11px] text-foreground-muted">{detail}</div>
            </div>
        </div>
    );
}
