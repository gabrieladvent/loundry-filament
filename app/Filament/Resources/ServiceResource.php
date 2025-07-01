<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Service;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use App\Filament\Forms\Fields\MoneyField;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\ServiceResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\ServiceResource\RelationManagers;

class ServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('service_category_id')
                    ->relationship('serviceCategory', 'name', function (Builder $query) {
                        $query->where('is_active', true);
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->afterStateUpdated(fn(callable $set) => $set('name', null))
                    ->label('Kategori Layanan')
                    ->validationMessages([
                        'required' => 'Kategori Layanan harus diisi',
                        'exists' => 'Kategori Layanan tidak ditemukan',
                    ]),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('Nama Layanan')
                    ->maxLength(255)
                    ->validationMessages([
                        'required' => 'Nama Layanan harus diisi',
                        'max' => 'Nama Layanan tidak boleh lebih dari 255 karakter',
                    ]),
                Forms\Components\Select::make('unit')
                    ->options([
                        'kg' => 'Kilogram',
                        'pcs' => 'Pcs',
                        'set' => 'Set',
                    ])
                    ->required()
                    ->label('Satuan')
                    ->validationMessages([
                        'required' => 'Satuan harus diisi',
                    ]),
                MoneyField::make(null, 'price', 'Harga Layanan', required: true)
                    ->required()
                    ->numeric()
                    ->label('Harga Layanan')
                    ->validationMessages([
                        'required' => 'Harga Layanan harus diisi',
                        'numeric' => 'Harga Layanan harus berupa angka',
                    ]),
                Forms\Components\TextInput::make('duration_days')
                    ->required()
                    ->numeric()
                    ->label('Durasi Layanan')
                    ->suffix('Hari')
                    ->validationMessages([
                        'required' => 'Durasi Layanan harus diisi',
                        'numeric' => 'Durasi Layanan harus berupa angka',
                    ]),
                Forms\Components\Textarea::make('description')
                    ->maxLength(255)
                    ->label('Deskripsi Layanan')
                    ->validationMessages([
                        'max' => 'Deskripsi Layanan tidak boleh lebih dari 255 karakter',
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Nama Layanan'),
                Tables\Columns\TextColumn::make('price')
                    ->searchable()
                    ->label('Harga Layanan')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.')),
                Tables\Columns\TextColumn::make('unit')
                    ->searchable()
                    ->label('Satuan'),
                Tables\Columns\TextColumn::make('duration_days')
                    ->searchable()
                    ->label('Durasi Layanan')
                    ->suffix(' Hari'),
                Tables\Columns\TextColumn::make('created_at')
                    ->sortable()
                    ->dateTime(format: 'd F Y H:i:s')
                    ->label('Dibuat Pada'),
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
                        ->modalHeading(fn($record) => $record->is_active ? 'Nonaktifkan Kategori?' : 'Aktifkan Kategori?')
                        ->modalDescription(fn($record) => $record->is_active ? 'Apakah Anda yakin ingin menonaktifkan kategori ini?' : 'Apakah Anda yakin ingin mengaktifkan kategori ini?')
                        ->action(function ($record) {
                            $record->update([
                                'is_active' => !$record->is_active,
                            ]);

                            $record->refresh();

                            Notification::make()
                                ->title($record->is_active ? 'Kategori diaktifkan' : 'Kategori dinonaktifkan')
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
            'index' => Pages\ListServices::route('/'),
            'create' => Pages\CreateService::route('/create'),
            'view' => Pages\ViewService::route('/{record}'),
            'edit' => Pages\EditService::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Layanan';
    }
}
