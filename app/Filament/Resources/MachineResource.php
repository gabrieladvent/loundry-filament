<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Machine;
use Filament\Forms\Form;
use App\Enum\MachineType;
use App\Enum\MechineType;
use Filament\Tables\Table;
use Filament\Resources\Resource;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\MachineResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\MachineResource\RelationManagers;

class MachineResource extends Resource
{
    protected static ?string $model = Machine::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationGroup = 'Master Data';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nama Mesin')
                    ->required(),
                Forms\Components\Select::make('type')
                    ->label('Jenis Mesin')
                    ->options([
                        'washing' => 'Mesin Cuci',
                        'drying' => 'Mesin Kering',
                        'ironing' => 'Setrikaan',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('capacity_kg')
                    ->label('Kapasitas')
                    ->numeric()
                    ->default(2)
                    ->suffix('kg')
                    ->required(),
                Forms\Components\DatePicker::make('last_maintenance')
                    ->label('Terakhir Perawatan')
                    ->maxDate(now()),
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'available' => 'Tersedia',
                        'in_use' => 'Dipakai',
                        'maintenance' => 'Perawatan',
                        'broken' => 'Rusak',
                    ])
                    ->required(),
                Forms\Components\TextArea::make('notes')
                    ->label('Catatan'),
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
                    ->label('Nama Mesin'),
                Tables\Columns\TextColumn::make('type')
                    ->searchable()
                    ->label('Jenis Mesin')
                    ->formatStateUsing(function (?string $state) {
                        return MechineType::tryFrom($state)?->getLabel() ?? '-';
                    }),
                Tables\Columns\TextColumn::make('capacity_kg')
                    ->searchable()
                    ->label('Kapasitas')
                    ->suffix(' kg'),
                Tables\Columns\TextColumn::make('last_maintenance')
                    ->label('Terakhir Perawatan')
                    ->formatStateUsing(function ($state) {
                        return $state ? \Carbon\Carbon::parse($state)->format('d F Y') : '-';
                    })
                    ->searchable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Status Aktif')
                    ->boolean(),
                Tables\Columns\IconColumn::make('status')
                    ->label('Status Mesin')
                    ->icon(fn(string $state) => \App\Enum\MechineStatus::tryFrom($state)?->getIcon())
                    ->color(fn(string $state) => \App\Enum\MechineStatus::tryFrom($state)?->getColor()),

            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Tidak Aktif'),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status Mesin')
                    ->options(\App\Enum\MechineStatus::options()),
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
                        ->modalHeading(fn($record) => $record->is_active ? 'Nonaktifkan Mesin?' : 'Aktifkan Mesin?')
                        ->modalDescription(fn($record) => $record->is_active ? 'Apakah Anda yakin ingin menonaktifkan mesin ini?' : 'Apakah Anda yakin ingin mengaktifkan mesin ini?')
                        ->action(function ($record) {
                            $record->update([
                                'is_active' => !$record->is_active,
                            ]);

                            $record->refresh();

                            Notification::make()
                                ->title($record->is_active ? 'Mesin diaktifkan' : 'Mesin dinonaktifkan')
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
            'index' => Pages\ListMachines::route('/'),
            'create' => Pages\CreateMachine::route('/create'),
            'view' => Pages\ViewMachine::route('/{record}'),
            'edit' => Pages\EditMachine::route('/{record}/edit'),
        ];
    }

    public static function getLabel(): ?string
    {
        return 'Mesin';
    }
}
