<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use App\Models\Order;
use Filament\Forms\Form;
use App\Enum\OrderStatus;
use App\Enum\OrderPayment;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Resources\RelationManagers\RelationManager;

class OrderRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('order')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('order')
            ->columns([
                TextColumn::make('order_code')
                    ->label('Kode Order'),
                Tables\Columns\TextColumn::make('order_date')
                    ->searchable()
                    ->dateTime(format: 'd F Y')
                    ->label('Tanggal Order'),
                Tables\Columns\TextColumn::make('estimated_finish')
                    ->searchable()
                    ->dateTime(format: 'd F Y')
                    ->label('Tanggal Selesai'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->sortable()
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->toggleable()
                    ->label('Total Harga'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status ')
                    ->color(fn($state) => OrderStatus::from($state)->getColor())
                    ->formatStateUsing(fn($state) => OrderStatus::from($state)->getLabel()),

                Tables\Columns\TextColumn::make('payment_status')
                    ->badge()
                    ->icon(fn(string $state): ?string => OrderPayment::tryFrom($state)?->getIcon())
                    ->color(fn(string $state): ?string => OrderPayment::tryFrom($state)?->getColor())
                    ->label('Status Pembayaran'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('primary')
                    ->url(fn($record) => route('filament.admin.resources.orders.view', [
                        'record' => $record->id,
                    ])),
            ]);
    }
}
