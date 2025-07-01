<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Set;
use App\Enum\GenderType;
use App\Models\Customer;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Filament\Resources\Resource;
use App\Filament\Forms\FieldUtils;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\CustomerResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Filament\Resources\CustomerResource\RelationManagers\OrderRelationManager;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('customer_code')
                    ->label('Kode Pelanggan')
                    ->unique(ignoreRecord: true)
                    ->required()
                    ->placeholder('Enter code')
                    ->suffixAction(
                        Forms\Components\Actions\Action::make('generate')
                            ->label('Generate Code')
                            ->icon('heroicon-o-arrow-path')
                            ->action(fn(Set $set) => $set('customer_code', strtoupper(Str::random(8))))
                    ),
                Forms\Components\TextInput::make('name')
                    ->label('Nama Pelanggan')
                    ->required()
                    ->maxLength(255),
                FieldUtils::applyNumericSanitizer(
                    Forms\Components\TextInput::make('phone')
                        ->label('Nomor Telepon')
                        ->tel()
                        ->required()
                        ->prefix('+62')
                        ->rules(['digits_between:10,13'])
                        ->placeholder('e.g. 81234567890')
                        ->validationMessages([
                            'numeric' => 'Nomor Telepon harus berupa angka.',
                            'digits_between' => 'Nomor Telepon harus terdiri dari 10 hingga 13 digit angka.',
                            'regex' => 'Format Nomor Telepon tidak valid. e.g. 81234567890',
                        ]),
                    'phone',
                    13
                ),
                Forms\Components\TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(200)
                    ->visible(function ($livewire, $record) {
                        return !($livewire instanceof \Filament\Resources\Pages\ViewRecord) || !filled($record?->email);
                    })
                    ->validationMessages([
                        'max' => 'Email tidak boleh lebih dari 200 karakter.',
                        'email' => 'Email tidak valid.',
                    ]),
                Forms\Components\TextArea::make('address')
                    ->label('Alamat')
                    ->required()
                    ->reactive()
                    ->hint(fn($state) => strlen($state) . '/200')
                    ->hintColor('gray')
                    ->validationMessages([
                        'required' => 'Alamat wajib diisi.',
                    ]),
                Forms\Components\TextArea::make('notes')
                    ->label('Catatan')
                    ->reactive()
                    ->hint(fn($state) => strlen($state) . '/200')
                    ->hintColor('gray'),
                Forms\Components\Select::make('gender')
                    ->label('Jenis Kelamin')
                    ->required()
                    ->options([
                        'male' => 'Laki-Laki',
                        'female' => 'Perempuan',
                    ])
                    ->validationMessages([
                        'required' => 'Jenis Kelamin wajib diisi.',
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
                Tables\Columns\TextColumn::make('customer_code')
                    ->searchable()
                    ->label('Kode Pelanggan'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('Nama Pelanggan'),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->label('Nomor Telepon'),
                Tables\Columns\TextColumn::make('gender')
                    ->searchable()
                    ->label('Jenis Kelamin')
                    ->formatStateUsing(function (?string $state) {
                        return GenderType::tryFrom($state)?->getLabel() ?? '-';
                    }),
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
                        ->modalHeading(fn($record) => $record->is_active ? 'Nonaktifkan Pelanggan?' : 'Aktifkan Pelanggan?')
                        ->modalDescription(fn($record) => $record->is_active ? 'Apakah Anda yakin ingin menonaktifkan pelanggan ini?' : 'Apakah Anda yakin ingin mengaktifkan pelanggan ini?')
                        ->action(function ($record) {
                            $record->update([
                                'is_active' => !$record->is_active,
                            ]);

                            $record->refresh();

                            Notification::make()
                                ->title($record->is_active ? 'Pelanggan diaktifkan' : 'Pelanggan dinonaktifkan')
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
            OrderRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Pelanggan';
    }

    public static function getPluralLabel(): ?string
    {
        return 'Pelanggan';
    }
}
