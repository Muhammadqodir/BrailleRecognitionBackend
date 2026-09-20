<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Translation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'result_braille', 'result', 'result_json',
        'input_file', 'result_marked', 'lang', 'is_fav', 'rating', 'legacy_id',
    ];

    protected $hidden = ['legacy_id'];

    protected function casts(): array
    {
        return [
            'is_fav' => 'boolean',
            'rating' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * True when the OCR found nothing. Historically about one scan in ten —
     * worth being able to filter on rather than inferring from an empty string.
     */
    public function getFailedAttribute(): bool
    {
        return trim((string) $this->result) === '';
    }
}
