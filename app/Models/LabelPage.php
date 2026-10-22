<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone scan in the labeling queue, see the create_label_pages migration.
 */
class LabelPage extends Model
{
    public const STATUSES = ['todo', 'done', 'skipped'];

    public const SKIP_REASONS = ['not_braille', 'unreadable', 'rotated', 'duplicate', 'other'];

    protected $fillable = [
        'translation_id', 'split', 'status', 'skip_reason', 'priority', 'issues',
        'suggestions', 'cells', 'edits', 'admin_id', 'seconds', 'labeled_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'float',
            'issues' => 'array',
            'edits' => 'array',
            'seconds' => 'integer',
            'labeled_at' => 'datetime',
        ];
    }

    public function translation(): BelongsTo
    {
        return $this->belongsTo(Translation::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** @return list<list<float>> */
    public function suggestionList(): array
    {
        return self::decodeCells($this->suggestions);
    }

    /** @return list<list<float>> */
    public function cellList(): array
    {
        return self::decodeCells($this->cells);
    }

    /** @return list<list<float>> */
    public static function decodeCells(?string $json): array
    {
        $cells = json_decode((string) $json, true);

        return is_array($cells) ? array_values(array_filter($cells, fn ($c) => is_array($c) && count($c) >= 5)) : [];
    }
}
