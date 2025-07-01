<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Payment;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\PaymentResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\PaymentResource\RelationManagers;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('payment_name')
                    ->required()
                    ->label('Nama Pembayaran')
                    ->maxLength(255),
                Forms\Components\TextArea::make('notes')
                    ->label('Catatan')
                    ->reactive()
                    ->hint(fn($state) => strlen($state) . '/200')
                    ->hintColor('gray')
                    ->validationMessages([
                        'required' => 'Catatan wajib diisi.',
                    ]),
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
                Tables\Columns\TextColumn::make('payment_name')
                    ->label('Nama Pembayaran')
                    ->searchable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Catatan')
                    ->searchable(),
                Tables\Columns\BooleanColumn::make('is_active')
                    ->label('Status')
                    ->searchable(),
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
                        ->modalHeading(fn($record) => $record->is_active ? 'Nonaktifkan Pembayaran?' : 'Aktifkan Pembayaran?')
                        ->modalDescription(fn($record) => $record->is_active ? 'Apakah Anda yakin ingin menonaktifkan pembayaran ini?' : 'Apakah Anda yakin ingin mengaktifkan pembayaran ini?')
                        ->action(function ($record) {
                            $record->update([
                                'is_active' => !$record->is_active,
                            ]);

                            $record->refresh();

                            Notification::make()
                                ->title($record->is_active ? 'Pembayaran diaktifkan' : 'Pembayaran dinonaktifkan')
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
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Pembayaran';
    }
}
