<?php

namespace Filament\Launchpad\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single card that ONE user added to ONE section of their own launchpad
 * home, with their own ordering. This is the per-user personalisation layer:
 * users only ever create/reorder/delete their OWN rows here — the admin's
 * pinned cards live in the launchpad_section_card pivot and are never copied
 * into this table.
 */
class UserCard extends Model
{
    protected $table = 'launchpad_user_cards';

    protected $guarded = [];

    protected $casts = [
        'user_id' => 'integer',
        'section_id' => 'integer',
        'card_id' => 'integer',
        'sort' => 'integer',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class, 'card_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }
}
