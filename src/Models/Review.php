<?php

declare(strict_types=1);

namespace Atrium\Reviews\Models;

use Atrium\Admin\Columns\DateColumn;
use Atrium\Admin\Columns\TextColumn;
use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\DateTimeField;
use Atrium\Admin\Fields\NumberField;
use Atrium\Admin\Fields\SelectField;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Modules\Soft;
use Atrium\Admin\Traits\BelongsToSite;
use Atrium\Admin\Traits\HasSoftRelations;
use Atrium\Admin\Traits\InteractsWithAdmin;
use Atrium\Reviews\ReviewCounters;
use Atrium\Reviews\Reviews;
use Atrium\Reviews\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A review of the site or of a record of another module.
 *
 * @property int $id
 * @property int|null $site_id
 * @property string|null $reviewable_type
 * @property int|null $reviewable_id
 * @property string $author_name
 * @property int $rating
 * @property string $text
 * @property ReviewStatus $status
 * @property Carbon|null $posted_at
 * @property Model|null $reviewable
 */
class Review extends Model implements Administrable
{
    use BelongsToSite, HasSoftRelations, InteractsWithAdmin;

    protected $table = 'reviews';

    protected $fillable = ['reviewable_type', 'reviewable_id', 'author_name', 'rating', 'text', 'status', 'posted_at'];

    protected $attributes = ['status' => 'new'];

    /** Shown in the admin list next to the stored values. */
    protected $appends = ['status_label', 'subject_label'];

    protected function casts(): array
    {
        return [
            'reviewable_id' => 'integer',
            'rating' => 'integer',
            'status' => ReviewStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $review): void {
            $review->posted_at ??= now();
        });

        // The record's counter is counted again; mass updates in the
        // database go past this, atrium:reviews:recount puts them right.
        static::created(static fn (self $review) => app(ReviewCounters::class)->changed($review));
        static::updated(static function (self $review): void {
            if ($review->wasChanged(ReviewCounters::COUNTED)) {
                app(ReviewCounters::class)->changed($review);
            }
        });
        static::deleted(static fn (self $review) => app(ReviewCounters::class)->changed($review));
    }

    public static function getAdminLabel(): string
    {
        return __('atrium-reviews::reviews.module');
    }

    public static function getAdminRoutePrefix(): string
    {
        return 'reviews';
    }

    public static function getDefaultSortColumn(): string
    {
        return 'posted_at';
    }

    public static function getAdministrationFields(): array
    {
        $ratings = [];

        foreach ([5, 4, 3, 2, 1] as $rating) {
            $ratings[$rating] = str_repeat('★', $rating).str_repeat('☆', 5 - $rating).' '.$rating;
        }

        return [
            SelectField::make('status')->label(__('atrium-reviews::reviews.fields.status'))
                ->fromEnum(ReviewStatus::class)->required()->clearable(false)->default(ReviewStatus::New->value),
            SelectField::make('rating')->label(__('atrium-reviews::reviews.fields.rating'))
                ->options($ratings)->required()->clearable(false)->rules(['integer']),
            TextField::make('author_name')->label(__('atrium-reviews::reviews.fields.author_name'))->required()->rules(['string', 'max:255']),
            DateTimeField::make('posted_at')->label(__('atrium-reviews::reviews.fields.posted_at')),
            TextareaField::make('text')->label(__('atrium-reviews::reviews.fields.text'))->required(),
            SelectField::make('reviewable_type')->label(__('atrium-reviews::reviews.fields.subject_type'))
                ->options(array_diff_key(Soft::targets(), [self::class => true]))
                ->placeholder(__('atrium-reviews::reviews.site')),
            NumberField::make('reviewable_id')->label(__('atrium-reviews::reviews.fields.subject_id'))
                ->min(1)->rules(['required_with:reviewable_type', 'integer']),
        ];
    }

    public static function getAdministrationListColumns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('author_name')->label(__('atrium-reviews::reviews.fields.author_name'))->sortable()->searchable(),
            TextColumn::make('rating')->label(__('atrium-reviews::reviews.fields.rating'))->sortable(),
            TextColumn::make('text')->label(__('atrium-reviews::reviews.fields.text'))->limit(80)->searchable(),
            TextColumn::make('subject_label')->label(__('atrium-reviews::reviews.fields.subject')),
            TextColumn::make('status_label')->label(__('atrium-reviews::reviews.fields.status')),
            DateColumn::make('posted_at')->label(__('atrium-reviews::reviews.fields.posted_at'))->sortable(),
        ];
    }

    public function getAdminTitle(): string
    {
        return $this->author_name;
    }

    /**
     * The reviewed record: a product, a page, a store. Null for the site, for
     * a deleted record and for a module that is no longer installed.
     *
     * @return MorphTo<covariant Model,$this>
     */
    public function reviewable(): MorphTo
    {
        return $this->softMorphTo();
    }

    /**
     * The list shows what each review is about.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeAdminList(Builder $query): Builder
    {
        return $query->with('reviewable');
    }

    /**
     * An empty type from the form is the site, not a broken link.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public function transformAdminData(array $data): array
    {
        if (array_key_exists('reviewable_type', $data) && blank($data['reviewable_type'])) {
            $data['reviewable_type'] = null;
            $data['reviewable_id'] = null;
        }

        return $data;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ReviewStatus::Published->value);
    }

    /**
     * Reviews of one record, or of the site as a whole when it is null.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAbout(Builder $query, ?Model $record): Builder
    {
        if ($record === null) {
            return $query->whereNull('reviewable_type');
        }

        return $query->where('reviewable_type', $record->getMorphClass())->where('reviewable_id', $record->getKey());
    }

    public function getStatusLabelAttribute(): string
    {
        return $this->status->label();
    }

    public function getSubjectLabelAttribute(): string
    {
        return app(Reviews::class)->describe($this);
    }
}
