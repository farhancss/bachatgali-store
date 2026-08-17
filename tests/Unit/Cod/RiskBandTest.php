<?php

declare(strict_types=1);

use App\Domain\Cod\Enums\RiskBand;

/*
| The thresholds are domain rules, not configuration, so the boundaries are
| asserted exactly — including the score either side of each one. If a
| threshold moves, this file says which scores changed meaning.
*/

it('maps a score to a band at the exact threshold boundaries', function (int $score, RiskBand $expected): void {
    expect(RiskBand::fromScore($score))->toBe($expected);
})->with([
    'zero' => [0, RiskBand::Low],
    'just below medium' => [RiskBand::MEDIUM_THRESHOLD - 1, RiskBand::Low],
    'exactly medium' => [RiskBand::MEDIUM_THRESHOLD, RiskBand::Medium],
    'just below high' => [RiskBand::HIGH_THRESHOLD - 1, RiskBand::Medium],
    'exactly high' => [RiskBand::HIGH_THRESHOLD, RiskBand::High],
    'just below blocked' => [RiskBand::BLOCKED_THRESHOLD - 1, RiskBand::High],
    'exactly blocked' => [RiskBand::BLOCKED_THRESHOLD, RiskBand::Blocked],
    'maximum' => [100, RiskBand::Blocked],
]);

it('dispatches low and medium risk without a human in the loop', function (RiskBand $band, bool $canDispatch): void {
    expect($band->canDispatch())->toBe($canDispatch);
})->with([
    'low' => [RiskBand::Low, true],
    'medium' => [RiskBand::Medium, true],
    'high' => [RiskBand::High, false],
    'blocked' => [RiskBand::Blocked, false],
]);

it('routes only high risk to a confirmation call', function (): void {
    $needsCall = array_values(array_filter(RiskBand::cases(), fn (RiskBand $b): bool => $b->requiresConfirmationCall()));

    expect($needsCall)->toEqualCanonicalizing([RiskBand::High]);
});

it('never sends a blocked order for confirmation — it is refused outright', function (): void {
    expect(RiskBand::Blocked->requiresConfirmationCall())->toBeFalse()
        ->and(RiskBand::Blocked->canDispatch())->toBeFalse();
});

it('labels and colours every band for the ops queue', function (RiskBand $band): void {
    expect($band->label())->not->toBeEmpty()
        ->and($band->colour())->toBeIn(['success', 'warning', 'danger', 'gray']);
})->with(RiskBand::cases());

it('tells the operator what to do in the high-risk label', function (): void {
    expect(RiskBand::High->label())->toContain('confirm by call')
        ->and(RiskBand::Low->colour())->toBe('success');
});
