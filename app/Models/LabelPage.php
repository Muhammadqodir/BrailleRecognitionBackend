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

    /**
     * two_sided: the back's dents show between the front's dots (only the
     * front is labeled); side_light: light from below or from the side, so
     * raised dots can look like dents.
     */
    public const TAGS = ['two_sided', 'side_light'];

    public const SKIP_REASONS = ['not_braille', 'unreadable', 'rotated', 'duplicate', 'other'];

    /** Clockwise turns of the photo the labeler can choose. */
    public const ROTATIONS = [0, 90, 180, 270];

    protected $fillable = [
        'translation_id', 'split', 'status', 'skip_reason', 'priority', 'issues',
        'suggestions', 'cells', 'edits', 'tags', 'rotation', 'admin_id', 'seconds', 'labeled_at',
    ];

    /**
     * The size of the picture the cells are in: the photo's own size, turned.
     *
     * @return array{0: int|float, 1: int|float}
     */
    public static function frame(int|float $width, int|float $height, int $rotation): array
    {
        return $rotation % 180 === 0 ? [$width, $height] : [$height, $width];
    }

    protected function casts(): array
    {
        return [
            'rotation' => 'integer',
            'priority' => 'float',
            'issues' => 'array',
            'edits' => 'array',
            'tags' => 'array',
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
