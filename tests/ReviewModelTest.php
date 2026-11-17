<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Reviews\Tests;

use Atrium\Admin\Fields\Field;
use Atrium\Reviews\Models\Review;
use Atrium\Reviews\ReviewStatus;
use Atrium\Reviews\Tests\Fixtures\Article;
use Atrium\Reviews\Tests\Fixtures\Product;
use Atrium\Reviews\Tests\Fixtures\Titled;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * A review as the admin sees it: its form, its list, its labels and the
 * defaults it is kept with.
 */
final class ReviewModelTest extends TestCase
{
    public function test_a_review_keeps_the_date_it_was_given(): void
    {
        $review = $this->review(['posted_at' => Carbon::parse('2025-05-06 07:08:09')]);

        $this->assertSame('2025-05-06 07:08:09', $review->fresh()?->posted_at?->format('Y-m-d H:i:s'));
    }

    public function test_a_review_without_a_date_is_dated_when_it_is_kept(): void
    {
        $this->travelTo(Carbon::parse('2026-01-02 03:04:05'));

        $this->assertSame('2026-01-02 03:04:05', $this->review()->fresh()?->posted_at?->format('Y-m-d H:i:s'));
    }

    public function test_the_record_id_is_read_as_a_number(): void
    {
        $this->assertSame(5, (new Review(['reviewable_id' => '5']))->reviewable_id);
    }

    public function test_the_rating_is_picked_from_one_to_five_stars(): void
    {
        $fields = $this->fields();

        $this->assertSame([
            5 => '★★★★★ 5',
            4 => '★★★★☆ 4',
            3 => '★★★☆☆ 3',
            2 => '★★☆☆☆ 2',
            1 => '★☆☆☆☆ 1',
        ], array_column($fields['rating']->toArray()['options'], 'label', 'value'));
        $this->assertFalse($fields['rating']->toArray()['clearable']);
        $this->assertContains('integer', $fields['rating']->getValidationRules());
    }

    public function test_the_form(): void
    {
        $fields = $this->fields();

        $this->assertSame(['status', 'rating', 'author_name', 'posted_at', 'text', 'reviewable_type', 'reviewable_id'], array_keys($fields));
        $this->assertSame('new', $fields['status']->getDefault());
        $this->assertFalse($fields['status']->toArray()['clearable']);
        $this->assertSame(['required', 'string', 'max:255'], array_slice($fields['author_name']->getValidationRules(), 0, 3));
        $this->assertEquals(1, $fields['reviewable_id']->toArray()['min']);
        $this->assertContains('required_with:reviewable_type', $fields['reviewable_id']->getValidationRules());

        // A review is about a record of another module, never about a review.
        $types = array_column($fields['reviewable_type']->toArray()['options'], 'label', 'value');
        $this->assertSame('Products', $types[Product::class] ?? null);
        $this->assertArrayNotHasKey(Review::class, $types);
    }

    public function test_the_list(): void
    {
        $columns = Review::getAdministrationListColumns();

        $this->assertSame(
            ['id', 'author_name', 'rating', 'text', 'subject_label', 'status_label', 'posted_at'],
            array_map(fn ($column): string => $column->getName(), $columns),
        );
        $this->assertSame(80, $columns[3]->toArray()['limit']);
    }

    public function test_the_admin_names_a_review_by_its_author(): void
    {
        $this->assertSame('Anna', $this->review(['author_name' => 'Anna'])->getAdminTitle());
    }

    /**
     * @return array<string,array{0:array<string,mixed>,1:array<string,mixed>}>
     */
    public static function adminData(): array
    {
        return [
            'about the site: empty type' => [['reviewable_type' => '', 'reviewable_id' => '7'], ['reviewable_type' => null, 'reviewable_id' => null]],
            'about the site: null type' => [['reviewable_type' => null, 'reviewable_id' => 7], ['reviewable_type' => null, 'reviewable_id' => null]],
            'about the site: spaces' => [['reviewable_type' => '  ', 'reviewable_id' => 7], ['reviewable_type' => null, 'reviewable_id' => null]],
            'about a record' => [['reviewable_type' => 'App\\Product', 'reviewable_id' => 7], ['reviewable_type' => 'App\\Product', 'reviewable_id' => 7]],
            'no type sent' => [['author_name' => 'Anna', 'reviewable_id' => 7], ['author_name' => 'Anna', 'reviewable_id' => 7]],
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<string,mixed>  $expected
     */
    #[DataProvider('adminData')]
    public function test_an_empty_type_from_the_form_is_the_site(array $data, array $expected): void
    {
        $this->assertSame($expected, (new Review)->transformAdminData($data));
    }

    public function test_the_labels_of_the_status_and_the_subject(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $article = Article::query()->create(['name' => 'News', 'code' => 'news']);

        $this->assertSame('The site', $this->review()->getSubjectLabelAttribute());
        $this->assertSame('Products: Phone', $this->review([], $phone)->getSubjectLabelAttribute());
        // Not a module of the panel: its class name and its id.
        $this->assertSame('Article: #'.$article->id, $this->review([], $article)->getSubjectLabelAttribute());

        $this->assertSame('Published', $this->review()->getStatusLabelAttribute());
    }

    public function test_a_record_outside_the_panel_is_named_by_its_id_even_with_a_title_of_its_own(): void
    {
        $titled = Titled::query()->create(['name' => 'News', 'code' => 'news']);

        $this->assertSame('Titled: #'.$titled->id, $this->review([], $titled)->getSubjectLabelAttribute());
    }

    public function test_a_record_that_is_gone_keeps_its_type_and_id(): void
    {
        $article = Article::query()->create(['name' => 'News', 'code' => 'news']);
        $review = $this->review([], $article);
        $article->delete();

        $this->assertSame('Article #'.$article->id.' (removed)', $review->fresh()?->getSubjectLabelAttribute());
    }

    /**
     * @return array<string,array{0:ReviewStatus,1:string}>
     */
    public static function statuses(): array
    {
        return [
            'new' => [ReviewStatus::New, 'New'],
            'published' => [ReviewStatus::Published, 'Published'],
            'rejected' => [ReviewStatus::Rejected, 'Rejected'],
        ];
    }

    #[DataProvider('statuses')]
    public function test_each_status_has_a_label(ReviewStatus $status, string $label): void
    {
        $this->assertSame($label, $status->label());
    }

    public function test_the_scopes_pick_published_reviews_of_one_subject(): void
    {
        $phone = Product::query()->create(['name' => 'Phone', 'code' => 'phone']);
        $site = $this->review();
        $about = $this->review([], $phone);
        $this->review(['status' => ReviewStatus::New], $phone);

        $model = new Review;

        $this->assertSame([$site->id], $model->scopeAbout($model->scopePublished(Review::query()), null)->pluck('id')->all());
        $this->assertSame([$about->id], $model->scopeAbout($model->scopePublished(Review::query()), $phone)->pluck('id')->all());
    }

    /**
     * @return array<string,Field>
     */
    private function fields(): array
    {
        $fields = Review::getAdministrationFields();

        return array_combine(array_map(fn (Field $field): string => $field->getName(), $fields), $fields);
    }

    /**
     * @param  array<string,mixed>  $attributes
     */
    private function review(array $attributes = [], Product|Article|Titled|null $about = null): Review
    {
        $review = new Review(['author_name' => 'Someone', 'rating' => 5, 'text' => 'Text', 'status' => ReviewStatus::Published, ...$attributes]);

        if ($about !== null) {
            $review->reviewable_type = $about->getMorphClass();
            $review->reviewable_id = $about->id;
        }

        $review->save();

        return $review;
    }
}
