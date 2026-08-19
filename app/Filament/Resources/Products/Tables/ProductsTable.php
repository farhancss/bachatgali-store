<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Product;
use App\Domain\Shared\ValueObjects\Money;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Eager load what the columns read. Model::preventLazyLoading()
            // is on outside production, so a missing relation here throws
            // rather than quietly issuing a query per row.
            ->modifyQueryUsing(fn ($query) => $query->with(['brand', 'variants']))
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Product $record): string => $record->slug)
                    ->wrap(),

                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->sortable()
                    ->toggleable()
                    ->placeholder('—'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (ProductType $state): string => $state->label()),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProductStatus $state): string => $state->label())
                    ->color(fn (ProductStatus $state): string => $state->colour()),

                TextColumn::make('variants_count')
                    ->label('Variants')
                    ->counts('variants')
                    ->alignRight(),

                TextColumn::make('price')
                    ->label('From')
                    ->alignRight()
                    ->state(fn (Product $record): string => $record->lowestPrice() instanceof Money
                        ? $record->lowestPrice()->format()
                        : '—'),

                TextColumn::make('stock')
                    ->label('Stock')
                    ->alignRight()
                    ->state(fn (Product $record): int => (int) $record->variants->sum('stock_quantity'))
                    // Red at zero, amber when the whole product is nearly gone.
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'danger',
                        $state <= 5 => 'warning',
                        default => 'gray',
                    }),

                IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(fn (): array => collect(ProductStatus::cases())
                        ->mapWithKeys(fn (ProductStatus $s): array => [$s->value => $s->label()])
                        ->all()),

                SelectFilter::make('brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('is_featured')->label('Featured'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
