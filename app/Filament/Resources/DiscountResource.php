<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Discount;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use App\Filament\Forms\Fields\MoneyField;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\DiscountResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\DiscountResource\RelationManagers;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Diskon')
                    ->required(),

                Forms\Components\Select::make('type')
                    ->label('Tipe Diskon')
                    ->options([
                        'fixed' => 'Harga Fix',
                        'percentage' => 'Persentase',
                    ])
                    ->required(),

                MoneyField::make(null, 'value', 'Potongan Harga', true),
                MoneyField::make(null, 'min_amount', 'Harga Minimal', true),

                Forms\Components\DatePicker::make('valid_from')
                    ->label('Waktu Mulai')
                    ->required()
                    ->default(now())
                    ->reactive(),

                Forms\Components\DatePicker::make('valid_until')
                    ->label('Waktu Selesai')
                    ->required()
                    ->minDate(fn(\Filament\Forms\Get $get) => $get('valid_from'))
                    ->afterOrEqual('valid_from')
                    ->helperText('Waktu selesai tidak boleh lebih awal dari waktu mulai.'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Status')
                    ->required()
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Diskon'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipe Diskon')
                    ->formatStateUsing(function (?string $state) {
                        return match ($state) {
                            'fixed' => 'Harga Fix',
                            'percentage' => 'Persentase',
                            default => '-',
                        };
                    }),

                Tables\Columns\TextColumn::make('value')
                    ->label('Potongan Harga')
                    ->formatStateUsing(fn($state) =>
                    'Rp ' . number_format($state, 0, ',', '.')),
                Tables\Columns\TextColumn::make('min_amount')
                    ->label('Harga Minimal')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                Tables\Columns\TextColumn::make('valid_from')
                    ->label('Waktu Mulai')
                    ->dateTime(format: 'd F Y'),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Waktu Selesai')
                    ->dateTime(format: 'd F Y'),

            Tables\Columns\IconColumn::make('is_active')
                ->label('Status')
                ->boolean(),
            ])
            ->filters([
                //
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
                        ->modalHeading(fn($record) => $record->is_active ? 'Nonaktifkan Diskon?' : 'Aktifkan Diskon?')
                        ->modalDescription(fn($record) => $record->is_active ? 'Apakah Anda yakin ingin menonaktifkan diskon ini?' : 'Apakah Anda yakin ingin mengaktifkan diskon ini?')
                        ->action(function ($record) {
                            $record->update([
                                'is_active' => !$record->is_active,
                            ]);

                            $record->refresh();

                            Notification::make()
                                ->title($record->is_active ? 'Diskon diaktifkan' : 'Diskon dinonaktifkan')
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
            'index' => Pages\ListDiscounts::route('/'),
            'create' => Pages\CreateDiscount::route('/create'),
            'view' => Pages\ViewDiscount::route('/{record}'),
            'edit' => Pages\EditDiscount::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Diskon';
    }
}
