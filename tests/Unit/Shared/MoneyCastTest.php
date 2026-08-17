<?php

declare(strict_types=1);

use App\Domain\Shared\Casts\MoneyCast;
use App\Domain\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Model;

/*
| The cast is the boundary between a bigInteger paisa column and Money. It
| never touches the database, so this stays a unit test: the model argument
| is only ever passed through.
*/

function moneyCast(): MoneyCast
{
    return new MoneyCast;
}

function castModel(): Model
{
    return new class extends Model {};
}

it('reads an integer column as Money', function (): void {
    expect(moneyCast()->get(castModel(), 'total', 429_900, []))->toBeMoney(429_900);
});

it('reads a numeric string as Money', function (): void {
    // PDO hands bigint back as a string on some drivers — a total must not
    // silently become null or throw because of the transport type.
    expect(moneyCast()->get(castModel(), 'total', '429900', []))->toBeMoney(429_900);
});

it('reads a negative balance', function (): void {
    expect(moneyCast()->get(castModel(), 'balance', '-2500', []))->toBeMoney(-2_500);
});

it('passes null through in both directions', function (): void {
    expect(moneyCast()->get(castModel(), 'total', null, []))->toBeNull()
        ->and(moneyCast()->set(castModel(), 'total', null, []))->toBeNull();
});

it('refuses to read a value that is not integer paisa', function (mixed $value): void {
    expect(fn (): ?Money => moneyCast()->get(castModel(), 'total', $value, []))
        ->toThrow(InvalidArgumentException::class, 'total');
})->with([
    'a float-shaped string' => ['4299.00'],
    'plain text' => ['free'],
    'a bare minus sign' => ['-'],
    'a float' => [42.99],
    'an empty string' => [''],
]);

it('writes Money back as integer paisa', function (): void {
    expect(moneyCast()->set(castModel(), 'total', Money::fromRupees(4_299), []))->toBe(429_900);
});

it('accepts raw integer paisa on the way in', function (): void {
    expect(moneyCast()->set(castModel(), 'total', 429_900, []))->toBe(429_900);
});

it('refuses to write anything that is not Money or int paisa', function (mixed $value): void {
    expect(fn (): ?int => moneyCast()->set(castModel(), 'total', $value, []))
        ->toThrow(InvalidArgumentException::class, 'must be a Money instance or int paisa');
})->with([
    'a float' => [4299.00],
    'a numeric string' => ['429900'],
    'an array' => [[429_900]],
]);

it('round-trips without drift', function (int $paisa): void {
    $money = moneyCast()->get(castModel(), 'total', $paisa, []);

    expect(moneyCast()->set(castModel(), 'total', $money, []))->toBe($paisa);
})->with([0, 1, 250_000, 5_000_000, PHP_INT_MAX]);
