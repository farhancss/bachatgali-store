<?php

declare(strict_types=1);

namespace App\Filament\Resources\Attributes\Schemas;

use App\Domain\Catalog\Enums\AttributeType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AttributeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('slug')
                    ->required(),
                Select::make('type')
                    ->options(AttributeType::class)
                    ->default('select')
                    ->required(),
                Toggle::make('is_filterable')
                    ->required(),
                Toggle::make('is_variant_defining')
                    ->required(),
                TextInput::make('position')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }
}
