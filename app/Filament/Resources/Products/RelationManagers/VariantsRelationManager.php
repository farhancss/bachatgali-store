<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\RelationManagers;

use App\Domain\Catalog\Enums\StockState;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Shared\ValueObjects\Money;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Variants are edited in place on the product, because a SKU has no meaning
 * on its own — you are always deciding "the medium black one costs what?".
 *
 * Money is stored as integer paisa and typed by staff in whole rupees. The
 * conversion happens here at the form boundary and nowhere else.
 */
class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants & stock';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('sku')
                        ->label('SKU')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(64),

                    TextInput::make('name')
                        ->label('Option name')
                        ->placeholder('Black / Medium')
                        ->maxLength(120),

                    self::rupees('price', 'Price')->required(),
                    self::rupees('compare_at_price', 'Was (optional)')
                        ->helperText('Only shows as a saving if it is higher than the price.'),
                ]),

            Section::make('Stock')
                ->columns(3)
                ->schema([
                    TextInput::make('stock_quantity')->numeric()->default(0)->required(),
                    TextInput::make('low_stock_threshold')
                        ->numeric()
                        ->default(5)
                        ->helperText('At or below this, the storefront says "only a few left".'),
                    TextInput::make('weight_grams')
                        ->numeric()
                        ->default(0)
                        ->suffix('g')
                        ->helperText('Couriers price on weight.'),
                    Toggle::make('backorder_allowed')->helperText('Sellable at zero stock.'),
                    Toggle::make('is_pre_order')->helperText('Overrides stock entirely.'),
                    Toggle::make('is_default')->helperText('The variant the product page opens on.'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('sku')
            ->columns([
                TextColumn::make('sku')->searchable()->sortable(),
                TextColumn::make('name')->label('Option')->placeholder('—'),

                TextColumn::make('price')
                    ->alignRight()
                    ->state(fn (ProductVariant $r): string => $r->price->format()),

                TextColumn::make('compare_at_price')
                    ->label('Was')
                    ->alignRight()
                    ->placeholder('—')
                    ->state(fn (ProductVariant $r): ?string => $r->compare_at_price?->format()),

                TextColumn::make('stock_quantity')->label('Qty')->alignRight()->sortable(),

                TextColumn::make('stock_state')
                    ->label('Availability')
                    ->badge()
                    ->state(fn (ProductVariant $r): string => $r->stockState()->label())
                    ->color(fn (ProductVariant $r): string => match ($r->stockState()) {
                        StockState::InStock => 'success',
                        StockState::LowStock => 'warning',
                        StockState::OutOfStock => 'danger',
                        StockState::Backorder, StockState::PreOrder => 'info',
                    }),

                IconColumn::make('is_default')->label('Default')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('position');
    }

    /** A money field typed in whole rupees, stored as integer paisa. */
    private static function rupees(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->prefix(config('bachatgali.currency.symbol'))
            ->formatStateUsing(fn (Money|int|null $state): ?int => match (true) {
                $state instanceof Money => intdiv($state->paisa, 100),
                is_int($state) => intdiv($state, 100),
                default => null,
            })
            ->dehydrateStateUsing(fn (mixed $state): ?Money => is_numeric($state)
                ? Money::fromRupees((int) $state)
                : null);
    }
}
