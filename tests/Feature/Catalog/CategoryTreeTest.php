<?php

declare(strict_types=1);

use App\Domain\Catalog\Models\Category;

/*
| The materialised `path` is the whole reason the listing query stays a
| single indexed prefix match. It is derived state, so the tests are about
| the invariant holding after every kind of write — including a subtree move,
| which is where a hand-maintained path column normally rots.
*/

it('gives a root category an empty path at depth zero', function (): void {
    $root = Category::factory()->create();

    expect($root->path)->toBe('/')
        ->and($root->depth)->toBe(0)
        ->and($root->isRoot())->toBeTrue();
});

it('builds the path from the parent chain', function (): void {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();
    $grandchild = Category::factory()->childOf($child)->create();

    expect($child->path)->toBe("/{$root->id}/")
        ->and($child->depth)->toBe(1)
        ->and($grandchild->path)->toBe("/{$root->id}/{$child->id}/")
        ->and($grandchild->depth)->toBe(2)
        ->and($grandchild->isRoot())->toBeFalse();
});

it('finds every descendant at any depth in one query', function (): void {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();
    $grandchild = Category::factory()->childOf($child)->create();
    $unrelated = Category::factory()->create();

    $descendants = Category::query()->descendantsOf($root)->pluck('id');

    expect($descendants)->toContain($child->id, $grandchild->id)
        ->not->toContain($unrelated->id, $root->id);
});

it('re-paths the whole subtree when a branch is moved', function (): void {
    $oldParent = Category::factory()->create();
    $newParent = Category::factory()->create();
    $branch = Category::factory()->childOf($oldParent)->create();
    $leaf = Category::factory()->childOf($branch)->create();

    $branch->update(['parent_id' => $newParent->id]);

    expect($branch->refresh()->path)->toBe("/{$newParent->id}/")
        ->and($branch->depth)->toBe(1)
        ->and($leaf->refresh()->path)->toBe("/{$newParent->id}/{$branch->id}/")
        ->and($leaf->depth)->toBe(2);

    // And the old parent no longer claims it.
    expect(Category::query()->descendantsOf($oldParent)->pluck('id'))->not->toContain($leaf->id);
});

it('promotes a branch to a root when its parent is removed', function (): void {
    $parent = Category::factory()->create();
    $child = Category::factory()->childOf($parent)->create();

    $child->update(['parent_id' => null]);

    expect($child->refresh()->path)->toBe('/')
        ->and($child->depth)->toBe(0)
        ->and($child->isRoot())->toBeTrue();
});

it('lists ancestor ids outermost first', function (): void {
    $root = Category::factory()->create();
    $child = Category::factory()->childOf($root)->create();
    $grandchild = Category::factory()->childOf($child)->create();

    expect($grandchild->ancestorIds())->toBe([$root->id, $child->id])
        ->and($root->ancestorIds())->toBe([]);
});

it('scopes to roots and to active categories', function (): void {
    $root = Category::factory()->create();
    Category::factory()->childOf($root)->create();
    $hidden = Category::factory()->inactive()->create();

    expect(Category::query()->roots()->pluck('id'))->toContain($root->id, $hidden->id)
        ->and(Category::query()->active()->pluck('id'))->not->toContain($hidden->id);
});
