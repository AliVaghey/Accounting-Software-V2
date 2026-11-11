<?php

namespace App\Models;

use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;
use IntlDateFormatter;

/**
 * @property int $id
 * @property int $user_id
 * @property int $amount
 * @property int|null $contact_id
 * @property string|null $description
 * @property bool $is_income
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read Contact|null $contact
 */
#[Fillable(['amount', 'contact_id', 'description', 'is_income'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function persianCreatedAt(): string
    {
        if ($this->created_at === null) {
            return '';
        }

        $formatter = new IntlDateFormatter('en_US@calendar=persian', IntlDateFormatter::NONE,
            IntlDateFormatter::NONE, 'Asia/Tehran', IntlDateFormatter::TRADITIONAL, 'yyyy/MM/dd · HH:mm');

        return $formatter->format($this->created_at) ?: '';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'integer', 'is_income' => 'boolean'];
    }
}
