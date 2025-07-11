<?php

namespace App\Filament\Resources\OrderResource\Pages;

use Carbon\Carbon;
use App\Models\Order;
use Filament\Actions;
use App\Models\Payment;
use App\Enum\OrderStatus;
use App\Enum\OrderPayment;
use Filament\Infolists\Infolist;
use App\Services\PaymentServices;
use Filament\Forms\Components\Select;
use Filament\Support\Enums\FontWeight;
use Filament\Infolists\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Group;
use Filament\Infolists\Components\Split;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Forms\Fields\MoneyField;
use App\Filament\Resources\OrderResource;
use Filament\Forms\Components\Placeholder;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\Fieldset;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_receipt')
                ->label('Print Invoice')
                ->icon('heroicon-o-printer')
                ->color('primary'),
            Actions\Action::make('update_status')
                ->label('Update Status')
                ->icon('heroicon-o-clock')
                ->color('warning')
                ->requiresConfirmation()
                ->form([
                    Select::make('status')
                        ->label('Pilih Status')
                        ->placeholder('Pilih Status')
                        ->options(fn() => OrderStatus::options())
                        ->searchable()
                        ->required()
                        ->live(),
                ])
                ->action(function (Order $record, array $data): void {
                    if ($data['status'] === OrderStatus::READY->value) {
                        $record->update([
                            'status' => $data['status'],
                            'actual_finish' => now(),
                        ]);
                    } elseif ($data['status'] === OrderStatus::DELIVERED->value && $record->actual_finish === null) {
                        $record->update([
                            'status' => $data['status'],
                            'actual_finish' => now(),
                            'delivery_date' => now(),
                        ]);
                    } else {
                        $record->update([
                            'status' => $data['status'],
                        ]);
                    }


                    Notification::make()
                        ->title('Status berhasil diperbarui')
                        ->body('Status berhasil diperbarui dengan status ' . $data['status'])
                        ->success()
                        ->send();
                })
                ->visible(fn(Order $record) => $record->status !== OrderStatus::DELIVERED->value && $record->status !== OrderStatus::READY->value),
            Actions\Action::make('update_payment')
                ->label('Update Pembayaran')
                ->color('success')
                ->icon('heroicon-o-document-currency-dollar')
                ->requiresConfirmation()
                ->visible(fn(Order $record) => app(PaymentServices::class)->canUpdatePayment($record))
                ->form([
                    Select::make('payment_status')
                        ->label('Pilih Status Pembayaran')
                        ->placeholder('Pilih Status Pembayaran')
                        ->options(fn() => OrderPayment::options())
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state === 'unpaid') {
                                $set('paid_amount', null);
                                $set('payment_id', null);
                            }
                        }),

                    MoneyField::make(null, 'paid_amount', 'Nominal Pembayaran')
                        ->visible(fn(callable $get) => in_array($get('payment_status'), ['paid', 'partial']))
                        ->required(fn(callable $get) => in_array($get('payment_status'), ['paid', 'partial']))
                        ->minValue(1)
                        ->helperText(
                            fn(Order $record, callable $get) =>
                            match ($get('payment_status')) {
                                'partial' => 'Akan ditambahkan ke pembayaran sebelumnya: ' . number_format($record->paid_amount ?? 0, 0, ',', '.'),
                                'paid' => 'Total yang harus dibayar: ' . number_format($record->total_amount, 0, ',', '.'),
                                default => null
                            }
                        ),

                    Select::make('payment_id')
                        ->label('Metode Pembayaran')
                        ->placeholder('Pilih Metode Pembayaran')
                        ->relationship('payment', 'payment_name')
                        ->options(fn() => Payment::all()->pluck('payment_name', 'id'))
                        ->searchable()
                        ->required()
                        ->visible(fn(callable $get) => in_array($get('payment_status'), ['paid', 'partial'])),


                ])
                ->modalDescription('Pilih status pembayaran untuk order ini')
                ->modalWidth('lg')
                ->action(function (Order $record, array $data): void {
                    $paymentService = app(PaymentServices::class);
                    $result = $paymentService->updatePayment($record, $data);

                    if ($result['success']) {
                        Notification::make()
                            ->title('Pembayaran berhasil diperbarui')
                            ->body($result['message'])
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Pembayaran gagal diperbarui')
                            ->body($result['message'])
                            ->danger()
                            ->send();
                    }
                })
                ->visible(fn(Order $record) => $record->payment_status !== OrderPayment::PAID->value)
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make()->schema([
                    Split::make([
                        Grid::make(2)->schema([
                            Group::make([
                                Fieldset::make('Pesanan')
                                    ->schema([
                                        TextEntry::make('order_code')
                                            ->label('Kode Pesanan')
                                            ->icon('heroicon-o-hashtag')
                                            ->iconColor('primary')
                                            ->copyable()
                                            ->weight(FontWeight::Bold)
                                            ->size('lg'),
                                        TextEntry::make('created_at')
                                            ->label('Dibuat pada')
                                            ->icon('heroicon-o-calendar-days')
                                            ->dateTime('d M Y, H:i')
                                            ->color('gray'),
                                    ]),
                            ]),

                            Group::make([
                                Fieldset::make('Status & Total')
                                    ->schema([
                                        TextEntry::make('status')
                                            ->label('Status Pesanan')
                                            ->badge()
                                            ->color(fn(string $state): string => OrderStatus::tryFrom($state)?->getColor() ?? 'gray')
                                            ->formatStateUsing(fn(string $state): string => OrderStatus::tryFrom($state)?->getLabel() ?? ucfirst($state))
                                            ->size('lg'),

                                        TextEntry::make('total_amount')
                                            ->label('Total Harga')
                                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                            ->weight(FontWeight::Bold)
                                            ->size('lg')
                                            ->icon('heroicon-o-banknotes')
                                            ->iconColor('success'),
                                    ]),
                            ]),
                        ]),
                    ])->from('md'),
                ])
                    ->compact()
                    ->headerActions([]),

                Grid::make(2)->schema([
                    Section::make('Customer')
                        ->icon('heroicon-o-user-circle')
                        ->iconColor('primary')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('customer.name')
                                    ->label('Nama Pelanggan')
                                    ->icon('heroicon-o-user')
                                    ->weight(FontWeight::Medium),
                                TextEntry::make('customer.phone')
                                    ->label('No. Telepon')
                                    ->icon('heroicon-o-phone')
                                    ->copyable(),
                            ])->columnSpan(1),

                            Grid::make(2)->schema([
                                TextEntry::make('customer.address')
                                    ->label('Alamat')
                                    ->icon('heroicon-o-map-pin'),
                                TextEntry::make('order_date')
                                    ->label('Tanggal Pesanan')
                                    ->icon('heroicon-o-calendar')
                                    ->dateTime('d F Y, H:i'),
                            ])->columnSpan(1),
                        ])->columnSpan(1),

                    Section::make('Status Pembayaran')
                        ->icon('heroicon-o-credit-card')
                        ->iconColor('success')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('payment_status')
                                    ->label('Status Pembayaran')
                                    ->badge()
                                    ->size('lg')
                                    ->icon(fn(string $state): string => OrderPayment::tryFrom($state)?->getIcon() ?? 'heroicon-o-question-mark-circle')
                                    ->color(fn(string $state): string => OrderPayment::tryFrom($state)?->getColor() ?? 'gray')
                                    ->formatStateUsing(fn(string $state): string => OrderPayment::tryFrom($state)?->getLabel() ?? 'Tidak diketahui'),

                                TextEntry::make('payment.payment_name')
                                    ->label('Metode Pembayaran')
                                    ->icon('heroicon-o-wallet')
                                    ->placeholder('Belum dipilih'),
                            ]),

                            Grid::make(2)->schema([
                                TextEntry::make('paid_amount')
                                    ->label('Jumlah Dibayar')
                                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                    ->icon('heroicon-o-banknotes'),

                                TextEntry::make('change_amount')
                                    ->label('Sisa/Kembalian')
                                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                    ->color(fn($state) => $state < 0 ? 'danger' : ($state > 0 ? 'warning' : 'success')),
                            ]),

                        ])
                        ->columnSpan(1),


                ]),

                Section::make('Detail Layanan')
                    ->icon('heroicon-o-list-bullet')
                    ->iconColor('primary')
                    ->description('Rincian semua layanan yang dipesan')
                    ->schema([
                        RepeatableEntry::make('orderDetails')
                            ->label('')
                            ->schema([
                                Section::make()
                                    ->schema([
                                        Split::make([
                                            Group::make([
                                                TextEntry::make('service.name')
                                                    ->label('Layanan')
                                                    ->weight(FontWeight::Bold)
                                                    ->icon('heroicon-o-sparkles')
                                                    ->iconColor('primary'),
                                                TextEntry::make('service.description')
                                                    ->label('Deskripsi')
                                                    ->color('gray')
                                                    ->placeholder('Tidak ada deskripsi'),
                                            ])->columnSpan(2),

                                            Grid::make(3)
                                                ->schema([
                                                    TextEntry::make('quantity')
                                                        ->label('Berat')
                                                        ->suffix(' kg')
                                                        ->icon('heroicon-o-scale')
                                                        ->badge()
                                                        ->color('info'),

                                                    TextEntry::make('unit_price')
                                                        ->label('Harga/kg')
                                                        ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                                        ->icon('heroicon-o-currency-dollar'),

                                                    TextEntry::make('subtotal')
                                                        ->label('Total')
                                                        ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                                        ->weight(FontWeight::Bold)
                                                        ->icon('heroicon-o-banknotes')
                                                        ->iconColor('success'),
                                                ])
                                                ->columnSpan(1),
                                        ])->from('md'),

                                        TextEntry::make('notes')
                                            ->label('Catatan Khusus')
                                            ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                            ->placeholder('Tidak ada catatan khusus')
                                            ->color('gray')
                                            ->columnSpanFull()
                                            ->visible(fn($state) => !empty($state)),
                                    ])
                                    ->compact(),
                            ])
                            ->contained(false),
                    ]),

                Grid::make(2)->schema([
                    // Informasi Pickup & Delivery
                    Section::make('Layanan Antar Jemput')
                        ->icon('heroicon-o-truck')
                        ->iconColor('info')
                        ->schema([
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('pickup_type')
                                        ->label('Jenis Pickup')
                                        ->icon('heroicon-o-arrow-up-circle')
                                        ->badge()
                                        ->formatStateUsing(fn($state) => match ($state) {
                                            'drop_off' => 'Diantar',
                                            'pickup' => 'Diambil',
                                            default => 'Tidak diketahui',
                                        })
                                        ->size('lg')
                                        ->color('info'),

                                    TextEntry::make('delivery_type')
                                        ->label('Jenis Delivery')
                                        ->icon('heroicon-o-arrow-down-circle')
                                        ->badge()
                                        ->formatStateUsing(fn($state) => match ($state) {
                                            'delivery' => 'Diantar',
                                            'pickup' => 'Diambil',
                                            default => 'Tidak diketahui',
                                        })
                                        ->size('lg')
                                        ->color('success'),

                                    TextEntry::make('pickup_address')
                                        ->label('Alamat Pickup')
                                        ->icon('heroicon-o-map-pin')
                                        ->visible(fn($record) => $record->pickup_type === 'pickup'),

                                    TextEntry::make('delivery_address')
                                        ->label('Alamat Delivery')
                                        ->icon('heroicon-o-map-pin')
                                        ->visible(fn($record) => $record->delivery_type === 'delivery'),

                                ]),
                            TextEntry::make('catatan_tambahan')
                                ->label('Catatan Tambahan')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->placeholder('Tidak ada catatan tambahan'),
                        ])
                        ->columnSpan(1),

                    // Status Pembayaran
                    Section::make('⏰ Timeline')
                        ->iconColor('success')
                        ->schema([
                            Grid::make(2)->schema([
                                TextEntry::make('estimated_finish')
                                    ->label('Estimasi Selesai')
                                    ->icon('heroicon-o-clock')
                                    ->date('d F Y')
                                    ->color('warning')
                                    ->formatStateUsing(fn($state) => $state ? Carbon::parse($state)->translatedFormat('d F Y') : 'Belum ada estimasi')
                                    ->placeholder('Belum ada estimasi'),

                                TextEntry::make('actual_finish')
                                    ->label('Selesai')
                                    ->icon('heroicon-o-calendar-days')
                                    ->color('success')
                                    ->formatStateUsing(fn($state) => $state ? Carbon::parse($state)->translatedFormat('d F Y') : 'Belum ada selesai')
                                    ->placeholder('Masih dalam proses'),
                            ])->columnSpan(1),

                            Grid::make(2)->schema([
                                TextEntry::make('delivery_date')
                                    ->label('Tanggal Pengiriman')
                                    ->icon('heroicon-o-clock')
                                    ->color('warning')
                                    ->formatStateUsing(fn($state) => $state ? Carbon::parse($state)->translatedFormat('d F Y') : 'Belum ada pengiriman')
                                    ->placeholder('Belum ada pengiriman'),

                                TextEntry::make('pickup_date')
                                    ->label('Tanggal Pengambilan')
                                    ->icon('heroicon-o-calendar-days')
                                    ->color('success')
                                    ->formatStateUsing(fn($state) => $state ? Carbon::parse($state)->translatedFormat('d F Y') : 'Belum ada pengambilan')
                                    ->placeholder('Belum ada pengambilan'),
                            ])->columnSpan(1),

                        ])
                        ->columnSpan(1),
                ]),

                // Rincian Pembayaran dengan Design Menarik
                Section::make('Rincian Pembayaran')
                    ->icon('heroicon-o-calculator')
                    ->iconColor('success')
                    ->description('Detail perhitungan biaya dan pembayaran')
                    ->schema([
                        // Biaya Layanan
                        Fieldset::make('Biaya Layanan')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextEntry::make('subtotal_amount')
                                            ->label('Subtotal Layanan')
                                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                            ->icon('heroicon-o-squares-plus'),
                                        TextEntry::make('additional_fee')
                                            ->label('Biaya Tambahan')
                                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                            ->icon('heroicon-o-plus-circle')
                                            ->color('warning'),
                                        TextEntry::make('tax_amount')
                                            ->label('Pajak')
                                            ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                            ->icon('heroicon-o-receipt-percent')
                                            ->color('info'),
                                    ]),

                                TextEntry::make('discount_amount')
                                    ->label('Jumlah Diskon')
                                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                    ->icon('heroicon-o-minus-circle')
                                    ->color('success'),
                            ]),

                        // Total Akhir
                        Section::make()
                            ->schema([
                                Grid::make(3)->schema([
                                    TextEntry::make('total_amount')
                                        ->label('TOTAL PEMBAYARAN')
                                        ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                        ->weight(FontWeight::Bold)
                                        ->size('xl')
                                        ->icon('heroicon-o-banknotes')
                                        ->iconColor('success')
                                        ->color('success'),

                                    TextEntry::make('paid_amount')
                                        ->label('DIBAYAR')
                                        ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                        ->weight(FontWeight::Bold)
                                        ->size('xl')
                                        ->icon('heroicon-o-banknotes')
                                        ->iconColor('success')
                                        ->color('success'),

                                    TextEntry::make('not_yet_paid')
                                        ->label('BELUM DIBAYAR')
                                        ->getStateUsing(function ($record) {
                                            $total = $record->total_amount ?? 0;
                                            $paid = $record->paid_amount ?? 0;
                                            return $total - $paid;
                                        })
                                        ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                                        ->weight(FontWeight::Bold)
                                        ->size('xl')
                                        ->icon('heroicon-o-banknotes')
                                        ->iconColor('danger')
                                        ->color('danger'),
                                ]),

                            ]),
                    ]),

                // Catatan dan Informasi Tambahan
                Section::make('📝 Informasi Tambahan')
                    ->icon('heroicon-o-document-text')
                    ->iconColor('gray')
                    ->schema([
                        TextEntry::make('catatan_layanan')
                            ->label('Catatan Layanan')
                            ->icon('heroicon-o-chat-bubble-bottom-center-text')
                            ->placeholder('Tidak ada catatan khusus')

                            ->columnSpanFull(),

                        Grid::make(2)
                            ->schema([
                                TextEntry::make('updated_at')
                                    ->label('Terakhir Diupdate')
                                    ->icon('heroicon-o-arrow-path')
                                    ->dateTime('d M Y, H:i')
                                    ->color('gray'),
                                TextEntry::make('created_by.name')
                                    ->label('Dibuat Oleh')
                                    ->icon('heroicon-o-user-plus')
                                    ->placeholder('System')
                                    ->color('gray'),
                            ]),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
