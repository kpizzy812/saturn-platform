<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A security or code quality violation found during code review.
 *
 * @property int $id
 * @property int $code_review_id
 * @property string $rule_id
 * @property string $source
 * @property string $severity
 * @property float $confidence
 * @property string $file_path
 * @property int|null $line_number
 * @property string $message
 * @property string|null $snippet
 * @property string|null $suggestion
 * @property bool $contains_secret
 * @property string|null $fingerprint
 * @property \Carbon\Carbon $created_at
 * @property-read CodeReview $codeReview
 */
class CodeReviewViolation extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'code_review_id',
        'rule_id',
        'source',
        'severity',
        'confidence',
        'file_path',
        'line_number',
        'message',
        'snippet',
        'suggestion',
        'contains_secret',
        'fingerprint',
    ];

    protected $casts = [
        'confidence' => 'float',
        'line_number' => 'integer',
        'contains_secret' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected $attributes = [
        'confidence' => 1.0,
        'contains_secret' => false,
    ];

    // Severity constants
    public const SEVERITY_CRITICAL = 'critical';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_LOW = 'low';

    // Source constants
    public const SOURCE_REGEX = 'regex';

    public const SOURCE_AST = 'ast';

    public const SOURCE_LLM = 'llm';

    /**
     * Get the code review this violation belongs to.
     */
    public function codeReview(): BelongsTo
    {
        return $this->belongsTo(CodeReview::class);
    }

    /**
     * Scope: critical severity only.
     */
    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', self::SEVERITY_CRITICAL);
    }

    /**
     * Scope: high or critical severity.
     */
    public function scopeHighOrCritical(Builder $query): Builder
    {
        return $query->whereIn('severity', [self::SEVERITY_CRITICAL, self::SEVERITY_HIGH]);
    }

    /**
     * Scope: deterministic sources only (regex, ast).
     */
    public function scopeDeterministic(Builder $query): Builder
    {
        return $query->whereIn('source', [self::SOURCE_REGEX, self::SOURCE_AST]);
    }

    /**
     * Scope: violations that contain secrets (restricted access).
     */
    public function scopeWithSecrets(Builder $query): Builder
    {
        return $query->where('contains_secret', true);
    }

    /**
     * Check if this is a deterministic (high-confidence) finding.
     */
    public function isDeterministic(): bool
    {
        return in_array($this->source, [self::SOURCE_REGEX, self::SOURCE_AST]);
    }

    /**
     * Check if this violation should block deployment.
     * MVP: blocking is disabled, always returns false.
     */
    public function shouldBlock(): bool
    {
        // MVP: report-only mode, no blocking
        // Phase 3: will check rule_id against blocking whitelist
        return false;
    }

    /**
     * Get severity badge color for UI.
     */
    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'red',
            self::SEVERITY_HIGH => 'orange',
            self::SEVERITY_MEDIUM => 'yellow',
            self::SEVERITY_LOW => 'green',
            default => 'gray',
        };
    }

    /**
     * Get rule category from rule_id.
     */
    public function getRuleCategoryAttribute(): string
    {
        return match (true) {
            str_starts_with($this->rule_id, 'SEC') => 'Security',
            str_starts_with($this->rule_id, 'PERF') => 'Performance',
            str_starts_with($this->rule_id, 'QUAL') => 'Quality',
            default => 'Other',
        };
    }

    /**
     * Get rule description based on rule_id.
     */
    public function getRuleDescriptionAttribute(): string
    {
        return match ($this->rule_id) {
            'SEC001' => 'Hardcoded API Key or Secret',
            'SEC002' => 'Hardcoded Password',
            'SEC003' => 'Hardcoded Connection String',
            'SEC004' => 'Shell Command Execution',
            'SEC005' => 'Code Evaluation',
            'SEC006' => 'Unsafe Deserialization',
            'SEC007' => 'Private Key in Code',
            'SEC008' => 'Known Token Format',
            default => 'Unknown Rule',
        };
    }

    /**
     * Get file location string for display.
     */
    public function getLocationAttribute(): string
    {
        if ($this->line_number) {
            return "{$this->file_path}:{$this->line_number}";
        }

        return $this->file_path;
    }

    /**
     * Generate fingerprint for deduplication.
     */
    public static function generateFingerprint(string $ruleId, string $filePath, ?int $lineNumber, string $message): string
    {
        return hash('sha256', implode('|', [
            $ruleId,
            $filePath,
            $lineNumber ?? 0,
            $message,
        ]));
    }
}
