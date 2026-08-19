<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Enums\ProductType;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        // Only fill the slug while it is still untouched — a
                        // published URL must never move because someone fixed
                        // a typo in the title.
                        ->afterStateUpdated(function (Set $set, ?string $state, string $operation): void {
                            if ($operation === 'create' && $state !== null) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Changing this breaks existing links. Add a redirect if you do.'),

                    Select::make('brand_id')
                        ->label('Brand')
                        ->options(fn (): array => Brand::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload(),

                    Select::make('type')
                        ->options(fn (): array => collect(ProductType::cases())
                            ->mapWithKeys(fn (ProductType $t): array => [$t->value => $t->label()])
                            ->all())
                        ->default(ProductType::Simple->value)
                        ->required()
                        ->helperText('Every product owns at least one variant. Variable products own several.'),

                    Select::make('categories')
                        ->relationship('categories', 'name')
                        ->multiple()
                        ->preload()
                        ->options(fn (): array => Category::query()
                            ->orderBy('path')
                            ->get()
                            ->mapWithKeys(fn (Category $c): array => [
                                $c->id => str_repeat('— ', $c->depth).$c->name,
                            ])
                            ->all())
                        ->columnSpanFull(),
                ]),

            Section::make('Copy')
                ->schema([
                    Textarea::make('short_description')
                        ->rows(2)
                        ->maxLength(500)
                        ->helperText('Shown on listing cards and used as the meta description.')
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->rows(8)
                        ->columnSpanFull(),
                ]),

            Section::make('Publication')
                ->columns(3)
                ->schema([
                    Select::make('status')
                        ->options(fn (): array => collect(ProductStatus::cases())
                            ->mapWithKeys(fn (ProductStatus $s): array => [$s->value => $s->label()])
                            ->all())
                        ->default(ProductStatus::Draft->value)
                        ->required()
                        ->helperText('Only Active products are visible or indexed.'),

                    DateTimePicker::make('published_at')
                        ->default(now()),

                    Toggle::make('is_featured')
                        ->helperText('Pins the product to the top of listings.'),
                ]),
        ]);
    }
}
