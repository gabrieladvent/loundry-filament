<?php

namespace App\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use App\Models\Order;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use App\Enum\OrderStatus;
use App\Enum\OrderPayment;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use App\Helpers\OrderHelper;
use App\Helpers\PaymentHelper;
use Filament\Resources\Resource;
use App\Filament\Forms\FieldUtils;
use function Laravel\Prompts\form;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Actions\ActionGroup;
use App\Filament\Forms\Fields\MoneyField;

use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\OrderResource\Pages;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\OrderResource\RelationManagers;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Pesanan';

    protected static ?string $navigationGroup = 'Transaksi';

    protected static ?int $navigationSort = -3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Pesanan')->schema([
                    Forms\Components\TextInput::make('order_code')
                        ->required()
                        ->label('Kode Pesanan')
                        ->unique(ignoreRecord: true)
                        ->default(fn() => 'ORD-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT))
                        ->readOnly()
                        ->columnSpanFull(),

                    Forms\Components\Select::make('customer_id')
                        ->relationship(
                            name: 'customer',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn(Builder $query) => $query->where('is_active', true)
                        )
                        ->searchable()
                        ->label('Pelanggan')
                        ->preload()
                        ->createOptionForm([
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
                        ])
                        ->required(),

                    Forms\Components\DateTimePicker::make('order_date')
                        ->label('Tanggal Pesanan')
                        ->required()
                        ->default(now())
                        ->maxDate(now()),
                    Forms\Components\Hidden::make('user_id')
                        ->default(auth()->user()->id),
                ])->columns(2),

                Forms\Components\Section::make('Detail Pemesanan')->schema([
                    Forms\Components\Repeater::make('orderDetails')
                        ->label('Tambah Detail Layanan')
                        ->schema([
                            Forms\Components\Select::make('service_id')
                                ->label('Layanan')
                                ->relationship('service', 'name')
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set, $state) {
                                    $service = \App\Models\Service::find($state);
                                    $weight = $get('weight') ?? 0;

                                    $price = $service ? $service->price * $weight : 0;

                                    $set('unit_price', $service?->price);
                                    $set('price', $price);
                                    $set('updated', rand());
                                }),

                            Forms\Components\TextInput::make('unit_price')
                                ->label('Harga Satuan')
                                ->numeric()
                                ->readOnly()
                                ->formatStateUsing(fn($state) => number_format($state, 0, ',', '.')),

                            Forms\Components\TextInput::make('weight')
                                ->label('Berat (Kg)')
                                ->numeric()
                                ->required()
                                ->reactive()
                                ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                                    $unitPrice = $get('unit_price') ?? 0;
                                    $weight = $get('weight') ?? 0;
                                    $set('price', $unitPrice * $weight);
                                    $set('updated', rand());
                                }),

                            Forms\Components\TextInput::make('price')
                                ->label('Total Harga')
                                ->numeric()
                                ->readOnly()
                                ->formatStateUsing(fn($state) => number_format($state, 0, ',', '.')),

                            Forms\Components\TextArea::make('notes')
                                ->label('Catatan')
                                ->hint(fn($state) => strlen($state) . '/200')
                                ->hintColor('gray'),

                            Forms\Components\Hidden::make('updated'),
                        ])
                        ->columns(5)
                        ->defaultItems(1)
                        ->createItemButtonLabel('Tambah Detail Baru')
                        ->reorderable()
                        ->live(),

                    // === Tombol Hitung Manual ===
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('calculateSubtotal')
                            ->label('Hitung Subtotal')
                            ->color('primary')
                            ->icon('heroicon-o-calculator')
                            ->action(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                                $orderDetails = $get('orderDetails') ?? [];
                                \App\Helpers\OrderHelper::updateOrderSummary($orderDetails, $set);
                            }),
                    ]),

                ])->columns(1),


                Forms\Components\Section::make('Ringkasan Pesanan')->schema([
                    Forms\Components\Placeholder::make('order_summary')
                        ->label('')
                        ->content(function (\Filament\Forms\Get $get): \Illuminate\Support\HtmlString {
                            $orderDetails = $get('orderDetails') ?? [];
                            $subtotal = \App\Helpers\OrderHelper::calculateSubtotal($orderDetails);
                            $totalItems = count($orderDetails);

                            if ($subtotal > 0) {
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="bg-gray-50 p-4 rounded-lg">
                                        <div class="flex justify-between mb-2">
                                            <span class="font-medium">Total Item:</span>
                                            <span>' . $totalItems . ' layanan</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="font-bold text-lg">Subtotal:</span>
                                            <span class="font-bold text-lg text-blue-600">Rp ' . number_format($subtotal, 0, ',', '.') . '</span>
                                        </div>
                                    </div>'
                                );
                            }

                            return new \Illuminate\Support\HtmlString(
                                '<div class="bg-yellow-50 p-4 rounded-lg text-center">
                                    <span class="text-gray-600">Pilih layanan dan masukkan berat untuk melihat total</span>
                                </div>'
                            );
                        })
                        ->live(),

                ])->columns(1),

                Forms\Components\Section::make('Detail Pemesanan Lanjutan')->schema([
                    Forms\Components\TextInput::make('total_weight')
                        ->numeric()
                        ->step(0.1)
                        ->suffix('kg')
                        ->readOnly(),

                    Forms\Components\TextInput::make('total_items')
                        ->numeric()
                        ->default(0)
                        ->readOnly(),

                    Forms\Components\DateTimePicker::make('estimated_finish')
                        ->label('Tanggal Estimasi Selesai')
                        ->required()
                        ->minDate(now())
                        ->helperText('Tanggal sudah dihitung berdasarkan layanan yang dipilih tapi masih bisa diubah'),

                    Forms\Components\DateTimePicker::make('pickup_date')
                        ->label('Tanggal Pengambilan')
                        ->required()
                        ->minDate(now())
                        ->helperText('Tanggal sudah dihitung berdasarkan layanan yang dipilih tapi masih bisa diubah'),

                    Forms\Components\Radio::make('status')
                        ->label('Status Pemesanan')
                        ->options([
                            'pending' => 'Pending',
                            'in_progress' => 'Dalam Proses',
                            'ready' => 'Siap',
                            'delivered' => 'Dikirim',
                            'cancelled' => 'Dibatalkan',
                        ])
                        ->inline()
                        ->default('pending')
                        ->columns(4)
                        ->columnSpanFull(),

                ])->columns(2),

                Forms\Components\Section::make('Pembayaran')->schema([
                    Forms\Components\Select::make('payment_status')
                        ->options([
                            'unpaid' => 'Belum Dibayar',
                            'partial' => 'Sebagian Dibayar',
                            'paid' => 'Dibayar',
                            'refunded' => 'Dikembalikan',
                        ])
                        ->required()
                        ->default('unpaid')
                        ->live(),

                    Forms\Components\Select::make('payment_id')
                        ->label('Metode Pembayaran')
                        ->options(fn() => PaymentHelper::getPaymentMethods())
                        ->required()
                        ->visible(fn(Get $get) => PaymentHelper::shouldShowPaymentFields($get))
                        ->searchable(),

                    MoneyField::make(null, 'paid_amount', 'Jumlah Dibayar')
                        ->visible(fn(Get $get) => PaymentHelper::shouldShowPaymentFields($get))
                        ->rules(fn(Get $get) => PaymentHelper::validatePaymentAmount($get))
                        ->helperText('Jumlah yang sudah dibayar'),

                    Forms\Components\Select::make('discount_id')
                        ->label('Diskon')
                        ->options(fn(Get $get) => PaymentHelper::getAvailableDiscounts($get('subtotal_amount') ?? 0))
                        ->live()
                        ->afterStateUpdated(fn($state, Set $set, Get $get) => PaymentHelper::handleDiscountSelection($state, $set, $get))
                        ->searchable()
                        ->placeholder('Pilih diskon (opsional)'),

                    MoneyField::make(null, 'discount_amount', 'Jumlah Diskon', false)
                        ->readOnly(),

                    MoneyField::make(null, 'tax_amount', 'Pajak', false)
                        ->default(0)
                        ->live()
                        ->afterStateUpdated(fn($state, Set $set, Get $get) => PaymentHelper::updateTotalAmount($set, $get)),

                    MoneyField::make(null, 'additional_fee', 'Biaya Tambahan', false)
                        ->default(0)
                        ->live()
                        ->afterStateUpdated(fn($state, Set $set, Get $get) => PaymentHelper::updateTotalAmount($set, $get)),

                    MoneyField::make(null, 'subtotal_amount', 'Subtotal')
                        ->readOnly()
                        ->helperText('Total dari semua layanan')
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            $set('discount_id', null);
                            $set('discount_amount', 0);
                            PaymentHelper::updateTotalAmount($set, $get);
                        }),

                    MoneyField::make(null, 'total_amount', 'Total Pembayaran')
                        ->readOnly()
                        ->helperText(fn(Get $get) => PaymentHelper::getDiscountHelperText($get)),

                ])->columns(2),

                Forms\Components\Section::make('Informasi Lainnya')->schema([
                    Forms\Components\Select::make('pickup_type')
                        ->label('Jenis Pickup')
                        ->required()
                        ->options([
                            'drop_off' => 'Antar Sendiri',
                            'pickup' => 'Jemput Laundry',
                        ])
                        ->default('drop_off')
                        ->live()
                        ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                            self::handleWeightAdjustment($get, $set);
                        }),

                    Forms\Components\Select::make('delivery_type')
                        ->label('Jenis Delivery')
                        ->required()
                        ->options([
                            'pickup' => 'Ambil Sendiri',
                            'delivery' => 'Antar Laundry',
                        ])
                        ->default('pickup')
                        ->live()
                        ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                            self::handleWeightAdjustment($get, $set);
                        }),

                    Forms\Components\Textarea::make('pickup_address')
                        ->label('Alamat Pickup')
                        ->rows(2)
                        ->default(fn($get, $record) => ($record?->customer?->address ?? null))
                        ->visible(fn($get) => $get('pickup_type') === 'pickup')
                        ->required(fn($get) => $get('pickup_type') === 'pickup'),

                    Forms\Components\Textarea::make('delivery_address')
                        ->label('Alamat Delivery')
                        ->rows(2)
                        ->default(fn($get, $record) => ($record?->customer?->address ?? null))
                        ->visible(fn($get) => $get('delivery_type') === 'delivery')
                        ->required(fn($get) => $get('delivery_type') === 'delivery'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Catatan')
                        ->rows(3)
                        ->columnSpanFull(),
                ])->columns(2),

                Forms\Components\Hidden::make('trigger_calculation')
                    ->default(1)
                    ->live()
                    ->afterStateUpdated(function (\Filament\Forms\Get $get, \Filament\Forms\Set $set) {
                        $orderDetails = $get('orderDetails') ?? [];
                        \App\Helpers\OrderHelper::updateOrderSummary($orderDetails, $set);
                    })

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_code')
                    ->searchable()
                    ->label('Kode Order'),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->label('Nama Pelanggan'),
                Tables\Columns\TextColumn::make('pickup_type')
                    ->searchable()
                    ->label('Jenis Pickup'),
                Tables\Columns\TextColumn::make('delivery_type')
                    ->searchable()
                    ->label('Jenis Delivery'),
                Tables\Columns\TextColumn::make('order_date')
                    ->searchable()
                    ->dateTime(format: 'd F Y')
                    ->label('Tanggal Order'),
                Tables\Columns\TextColumn::make('estimated_finish')
                    ->searchable()
                    ->dateTime(format: 'd F Y')
                    ->label('Tanggal Selesai'),

                Tables\Columns\TextColumn::make('total_weight')
                    ->sortable()
                    ->toggleable()
                    ->label('Total Berat'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->sortable()
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->toggleable()
                    ->label('Total Harga'),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->color(fn($state) => OrderStatus::from($state)->getColor())
                    ->formatStateUsing(fn($state) => OrderStatus::from($state)->getLabel()),


                Tables\Columns\BadgeColumn::make('payment_status')
                    ->label('Status Pembayaran')
                    ->color(fn($state) => OrderPayment::from($state)->getColor())
                    ->formatStateUsing(fn($state) => OrderPayment::from($state)->getLabel()),
            ])
            ->filters([])
            ->actions([
                ActionGroup::make([
                    Tables\Actions\ViewAction::make()->color('primary'),
                    Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function refreshSubtotal(Get $get, Set $set)
    {
        $orderDetails = $get('orderDetails') ?? [];
        $subtotal = self::calculateSubtotal($orderDetails);
        $set('subtotal_amount', $subtotal);
    }

    protected static function handleWeightAdjustment(\Filament\Forms\Get $get, \Filament\Forms\Set $set): void
    {
        $isPickupService = $get('pickup_type') === 'pickup';
        $isDeliveryService = $get('delivery_type') === 'delivery';
        $orderDetails = $get('orderDetails') ?? [];
        $totalWeight = array_sum(array_map(fn($item) => (float) ($item['weight'] ?? 0), $orderDetails));

        if (($isPickupService || $isDeliveryService) && $totalWeight < 3) {
            $adjustedWeight = 3;

            $newOrderDetails = \App\Helpers\OrderHelper::recalculateOrderDetails($orderDetails, $adjustedWeight);

            $set('orderDetails', $newOrderDetails);
            $set('total_weight', $adjustedWeight);
        }
    }
}
