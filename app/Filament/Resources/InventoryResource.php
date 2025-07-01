<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use App\Models\Inventory;
use Filament\Tables\Table;
use App\Enum\InventoryCategory;
use Filament\Resources\Resource;
use App\Enum\InventoryStatusStock;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use App\Filament\Forms\Fields\MoneyField;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\InventoryResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\InventoryResource\RelationManagers;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;

    protected static ?string $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->label('Nama Barang')
                    ->maxLength(255),
                Forms\Components\Select::make('category')
                    ->options(InventoryCategory::options())
                    ->label('Kategori')
                    ->required(),
                Forms\Components\Select::make('unit')
                    ->options([
                        'kg' => 'Kg',
                        'liter' => 'Liter',
                        'pcs' => 'Pcs',
                        'bottle' => 'Bottle',
                    ])
                    ->label('Satuan')
                    ->required(),
                Forms\Components\TextInput::make('current_stock')
                    ->numeric()
                    ->required()
                    ->label('Stok Saat Ini'),
                Forms\Components\TextInput::make('minimum_stock')
                    ->numeric()
                    ->required()
                    ->label('Stok Minimum'),
                MoneyField::make(null, 'unit_price', 'Harga Satuan'),
                Forms\Components\TextInput::make('supplier')
                    ->label('Supplier'),
                Forms\Components\Textarea::make('notes')
                    ->rows(3)
                    ->label('Catatan'),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->label('Aktif'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Barang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->formatStateUsing(function (?string $state) {
                        return InventoryCategory::tryFrom($state)?->getLabel() ?? '-';
                    }),
                Tables\Columns\TextColumn::make('unit')
                    ->label('Satuan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('current_stock')
                    ->label('Stok Saat Ini')
                    ->formatStateUsing(function ($state, $record) {
                        return $state . ' ' . $record->unit;
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('unit_price')
                    ->label('Harga Satuan')
                    ->sortable(),
                Tables\Columns\TextColumn::make('supplier')
                    ->label('Supplier')
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('stock_status')
                    ->label('Status Stok')
                    ->getStateUsing(function ($record) {
                        return match (true) {
                            $record->current_stock == 0 => InventoryStatusStock::OUT_OF_STOCK,
                            $record->current_stock < $record->minimum_stock => InventoryStatusStock::LOW_STOCK,
                            default => InventoryStatusStock::NORMAL_STOCK,
                        };
                    })

                    ->color(fn(InventoryStatusStock $state) => $state->getColor())
                    ->icon(fn(InventoryStatusStock $state) => $state->getIcon())
                    ->formatStateUsing(fn(InventoryStatusStock $state) => $state->getLabel()),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('Aktif')
                    ->options([
                        true => 'Ya',
                        false => 'Tidak',
                    ])
                    ->query(function ($query, $data) {
                        if (! is_null($data['value'])) {
                            $query->where('is_active', $data['value']);
                        }
                    }),

                Tables\Filters\SelectFilter::make('stock_status')
                    ->label('Status Stok')
                    ->options(InventoryStatusStock::options())
                    ->query(function ($query, $data) {
                        if (! $data['value']) {
                            return $query;
                        }

                        return match ($data['value']) {
                            InventoryStatusStock::OUT_OF_STOCK->value => $query->where('current_stock', '=', 0),
                            InventoryStatusStock::LOW_STOCK->value => $query->whereColumn('current_stock', '<', 'minimum_stock')->where('current_stock', '>', 0),
                            InventoryStatusStock::NORMAL_STOCK->value => $query->whereColumn('current_stock', '>=', 'minimum_stock'),
                            default => $query,
                        };
                    }),
            ])

            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('primary'),
                    Tables\Actions\EditAction::make(),


                    Tables\Actions\Action::make('is_active')
                        ->label(fn($record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                        ->color(fn($record) => $record->is_active ? 'danger' : 'success')
                        ->icon(fn($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->modalHeading(fn($record) => $record->is_active ? 'Nonaktifkan Inventory?' : 'Aktifkan Inventory?')
                        ->modalDescription(fn($record) => $record->is_active ? 'Apakah Anda yakin ingin menonaktifkan inventory ini?' : 'Apakah Anda yakin ingin mengaktifkan inventory ini?')
                        ->action(function ($record) {
                            $record->update([
                                'is_active' => !$record->is_active,
                            ]);

                            $record->refresh();

                            Notification::make()
                                ->title($record->is_active ? 'Inventory diaktifkan' : 'Inventory dinonaktifkan')
                                ->success()
                                ->send();
                        })
                ])
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->size('xl'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'view' => Pages\ViewInventory::route('/{record}'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Stok Persediaan';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Stok Persediaan';
    }
}
