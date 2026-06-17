<?php

namespace App\Models;

use App\Concerns\CoupleScoped;
use App\Enums\CardAssignee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** COUPLE-OWNED. Карточка задачи. CoupleScoped. */
class Card extends Model
{
    use CoupleScoped;

    protected $guarded = ['id', 'couple_id'];

    protected function casts(): array
    {
        return [
            'assignee' => CardAssignee::class,
            'due_at' => 'datetime',
            'due_end_at' => 'datetime',
            'completed_at' => 'datetime',
            'done' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(BoardColumn::class, 'board_column_id');
    }

    public function labels(): BelongsToMany
    {
        // couple_id на pivot проставляется явно в сервисе при attach (CoupleContext::id()).
        return $this->belongsToMany(Label::class, 'card_label')->withTimestamps();
    }
}
