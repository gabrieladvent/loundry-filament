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
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;

use Filament\Tables\Actions\ActionGroup;
use App\Filament\Forms\Fields\MoneyField;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Placeholder;
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
                Grid::make(2)->schema([
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
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, $state) {
                                if ($state) {
                                    $customer = \App\Models\Customer::select('id', 'address')->find($state);
                                    $set('pickup_address', $customer?->address);
                                    $set('delivery_address', $customer?->address);
                                } else {
                                    $set('pickup_address', null);
                                    $set('delivery_address', null);
                                }
                            })
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
                                    ->maxLength(200),
                                Forms\Components\TextArea::make('address')
                                    ->label('Alamat')
                                    ->required()
                                    ->maxLength(500),
                                Forms\Components\TextArea::make('notes')
                                    ->label('Catatan')
                                    ->maxLength(500),
                                Forms\Components\Select::make('gender')
                                    ->label('Jenis Kelamin')
                                    ->required()
                                    ->options([
                                        'male' => 'Laki-Laki',
                                        'female' => 'Perempuan',
                                    ]),
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Status')
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
                    ])->columns(2)->columnSpan(1),

                    Forms\Components\Section::make('Ringkasan Pesanan')->schema([
                        Forms\Components\Placeholder::make('order_summary')
                            ->label('')
                            ->content(function (Get $get): \Illuminate\Support\HtmlString {
                                $orderDetails = $get('orderDetails') ?? [];
                                $subtotal = OrderHelper::calculateSubtotal($orderDetails);
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

                    ])->columns(1)
                        ->columnSpan(1),
                ]),

                Forms\Components\Section::make('Detail Pemesanan')->schema([
                    Forms\Components\Repeater::make('orderDetails')->schema([
                        Forms\Components\Select::make('service_id')
                            ->label('Layanan')
                            ->relationship('service', 'name')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                $service = \App\Models\Service::find($state);
                                $weight = $get('weight') ?? 0;

                                $price = $service ? $service->price * $weight : 0;

                                $unitPrice = $service ? $service->price : 0;
                                $formattedUnitPrice = $unitPrice > 0 ? number_format($unitPrice, 0, ',', '.') : '';

                                $set('unit_price', $unitPrice);
                                $set('price_display', $formattedUnitPrice);
                                $set('price', $price);
                            }),

                        Hidden::make('unit_price'),

                        MoneyField::make(null, 'price_display', 'Harga Satuan')
                            ->readOnly()
                            ->reactive(),

                        Forms\Components\TextInput::make('weight')
                            ->label('Berat (Kg)')
                            ->numeric()
                            ->required()
                            ->step(0.1)
                            ->suffix('kg')
                            ->reactive()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                $unitPrice = (float) ($get('unit_price') ?? 0);
                                $weight = (float) ($get('weight') ?? 0);
                                $formattedPrice = $unitPrice * $weight;
                                $formattedPriceDisplay = $formattedPrice > 0 ? number_format($formattedPrice, 0, ',', '.') : '';
                                $set('price', $formattedPrice);
                                $set('price_total_display', $formattedPriceDisplay);
                            }),

                        Hidden::make('price'),

                        MoneyField::make(null, 'price_total_display', 'Total Harga')
                            ->readOnly()
                            ->reactive(),

                        Forms\Components\TextArea::make('notes')
                            ->label('Catatan')
                            ->maxLength(200),
                    ])
                        ->label('Tambah Detail')
                        ->relationship('orderDetails')
                        ->columns(5)
                        ->defaultItems(1)
                        ->createItemButtonLabel('Tambah Detail Baru')
                        ->reorderable()
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set) {
                            $orderDetails = $get('orderDetails') ?? [];
                            OrderHelper::updateOrderSummary($orderDetails, $set);
                        }),

                    // Tombol Hitung Manual
                    Forms\Components\Actions::make([
                        Forms\Components\Actions\Action::make('calculateSubtotal')
                            ->label('Hitung Subtotal')
                            ->color('primary')
                            ->icon('heroicon-o-calculator')
                            ->action(function (Get $get, Set $set) {
                                $orderDetails = $get('orderDetails') ?? [];
                                OrderHelper::updateOrderSummary($orderDetails, $set);
                            }),
                    ]),

                    Placeholder::make('')
                        ->content(function (Get $get): \Illuminate\Support\HtmlString {
                            return new \Illuminate\Support\HtmlString(
                                '<div>
                                    <span class="text-gray-600">Klik tombol hitung subtotal untuk menghitung total harga</span>
                                </div>'
                            );
                        }),
                ])->columns(1),

                Grid::make(2)->schema([
                    Forms\Components\Section::make('Detail Pemesanan Lanjutan')->schema([
                        Forms\Components\TextInput::make('total_weight')
                            ->label('Total Berat')
                            ->numeric()
                            ->step(0.1)
                            ->suffix('kg')
                            ->readOnly(),

                        Forms\Components\TextInput::make('total_items')
                            ->label('Total Item')
                            ->numeric()
                            ->readOnly(),

                        Forms\Components\DateTimePicker::make('estimated_finish')
                            ->label('Tanggal Estimasi Selesai')
                            ->required()
                            ->minDate(now()),

                        Forms\Components\DateTimePicker::make('pickup_date')
                            ->label('Tanggal Pengambilan')
                            ->required()
                            ->minDate(now()),

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

                    ])->columns(2)->columnSpan(1),

                    Forms\Components\Section::make('Informasi Lainnya')->schema([
                        Forms\Components\Select::make('pickup_type')
                            ->label('Jenis Pickup')
                            ->required()
                            ->options([
                                'drop_off' => 'Antar Sendiri',
                                'pickup' => 'Diambil Kurir',
                            ])
                            ->default('drop_off')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::handleWeightAdjustment($get, $set);
                            }),

                        Forms\Components\Select::make('delivery_type')
                            ->label('Jenis Delivery')
                            ->required()
                            ->options([
                                'pickup' => 'Ambil Sendiri',
                                'delivery' => 'Diantar Kurir',
                            ])
                            ->default('pickup')
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set) {
                                self::handleWeightAdjustment($get, $set);
                            }),

                        Forms\Components\Textarea::make('pickup_address')
                            ->label('Alamat Pengambilan')
                            ->rows(2)
                            ->reactive()
                            ->visible(fn(Get $get) => $get('pickup_type') === 'pickup')
                            ->required(fn(Get $get) => $get('pickup_type') === 'pickup'),

                        Forms\Components\Textarea::make('delivery_address')
                            ->label('Alamat Pengiriman')
                            ->rows(2)
                            ->reactive()
                            ->visible(fn(Get $get) => $get('delivery_type') === 'delivery')
                            ->required(fn(Get $get) => $get('delivery_type') === 'delivery'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                    ])->columns(2)
                        ->columnSpan(1),
                ]),

                Forms\Components\Section::make('Pembayaran')->schema([
                    Forms\Components\Select::make('payment_status')
                        ->label('Status Pembayaran')
                        ->options([
                            'unpaid' => 'Belum Dibayar',
                            'partial' => 'Sebagian Dibayar',
                            'paid' => 'Dibayar',
                            'refunded' => 'Dikembalikan',
                        ])
                        ->required()
                        ->default('unpaid')
                        ->live()
                        ->afterStateUpdated(function (Get $get, Set $set, $state) {
                            // Reset paid amount when status changes
                            if ($state === 'unpaid' || $state === 'refunded') {
                                $set('paid_amount', 0);
                                $set('payment_id', null);
                            }

                            // Auto-set paid amount for 'paid' status
                            if ($state === 'paid') {
                                $totalAmount = $get('total_amount') ?? 0;
                                $set('paid_amount', $totalAmount);
                            }

                            PaymentHelper::updateTotalAmount($set, $get);
                        })
                        ->helperText(fn(Get $get) => PaymentHelper::getPaymentStatusHelperText($get)),

                    Forms\Components\Select::make('payment_id')
                        ->label('Metode Pembayaran')
                        ->options(fn() => PaymentHelper::getPaymentMethods())
                        ->nullable()
                        ->visible(fn(Get $get) => PaymentHelper::shouldShowPaymentFields($get))
                        ->searchable()
                        ->required(fn(Get $get) => PaymentHelper::shouldShowPaymentFields($get)),

                    Forms\Components\TextInput::make('paid_amount')
                        ->label('Jumlah Dibayar')
                        ->numeric()
                        ->prefix('Rp')
                        ->live()
                        ->visible(fn(Get $get) => PaymentHelper::shouldShowPaymentFields($get))
                        ->rules(fn(Get $get) => PaymentHelper::validatePaymentAmount($get))
                        ->required(fn(Get $get) => PaymentHelper::shouldShowPaymentFields($get))
                        ->helperText(function (Get $get) {
                            $paymentStatus = $get('payment_status');
                            $totalAmount = PaymentHelper::toFloat($get('total_amount') ?? 0);

                            if ($paymentStatus === 'paid' && $totalAmount > 0) {
                                return 'Harus sama dengan total pembayaran: Rp ' . number_format($totalAmount, 0, ',', '.');
                            }

                            if ($paymentStatus === 'partial' && $totalAmount > 0) {
                                return 'Maksimal: Rp ' . number_format($totalAmount - 1, 0, ',', '.');
                            }

                            return null;
                        }),

                    Forms\Components\Select::make('discount_id')
                        ->label('Diskon')
                        ->options(fn(Get $get) => PaymentHelper::getAvailableDiscounts($get('subtotal_amount') ?? 0))
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            PaymentHelper::handleDiscountSelection($state, $set, $get);
                        })
                        ->searchable()
                        ->placeholder('Pilih diskon (opsional)'),

                    Forms\Components\TextInput::make('discount_amount')
                        ->label('Jumlah Diskon')
                        ->numeric()
                        ->prefix('Rp')
                        ->readOnly()
                        ->default(0)
                        ->formatStateUsing(fn($state) => $state ? number_format($state, 0, ',', '.') : '0'),

                    Forms\Components\TextInput::make('tax_amount')
                        ->label('Pajak')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(0)
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            PaymentHelper::updateTotalAmount($set, $get);
                        })
                        ->formatStateUsing(fn($state) => $state ? number_format($state, 0, ',', '.') : '0'),

                    Forms\Components\TextInput::make('additional_fee')
                        ->label('Biaya Tambahan')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(0)
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            PaymentHelper::updateTotalAmount($set, $get);
                        })
                        ->formatStateUsing(fn($state) => $state ? number_format($state, 0, ',', '.') : '0'),

                    Forms\Components\TextInput::make('subtotal_amount')
                        ->label('Subtotal')
                        ->numeric()
                        ->prefix('Rp')
                        ->readOnly()
                        ->helperText('Total dari semua layanan')
                        ->formatStateUsing(fn($state) => $state ? number_format($state, 0, ',', '.') : '0'),

                    Forms\Components\TextInput::make('total_amount')
                        ->label(function (Get $get) {
                            $paymentStatus = $get('payment_status');
                            if ($paymentStatus === 'partial') {
                                return 'Sisa Pembayaran';
                            }
                            return 'Total Pembayaran';
                        })
                        ->numeric()
                        ->prefix('Rp')
                        ->readOnly()
                        ->helperText(function (Get $get) {
                            $paymentStatus = $get('payment_status');
                            $discountText = PaymentHelper::getDiscountHelperText($get);

                            if ($paymentStatus === 'partial') {
                                // Untuk partial, tampilkan total asli (sebelum diskon)
                                $subtotal = PaymentHelper::toFloat($get('subtotal_amount') ?? 0);
                                $tax = PaymentHelper::toFloat($get('tax_amount') ?? 0);
                                $additionalFee = PaymentHelper::toFloat($get('additional_fee') ?? 0);
                                $totalBeforeDiscount = $subtotal + $tax + $additionalFee;

                                return ($discountText ? $discountText . ' • ' : '') .
                                    'Total sebelum diskon: Rp ' . number_format($totalBeforeDiscount, 0, ',', '.');
                            }

                            return $discountText;
                        })
                        ->formatStateUsing(function ($state, Get $get) {
                            $paymentStatus = $get('payment_status');

                            if ($paymentStatus === 'partial') {
                                // Untuk partial payment, tampilkan sisa yang harus dibayar
                                $totalAmount = PaymentHelper::toFloat($state ?? 0);
                                $paidAmount = PaymentHelper::toFloat($get('paid_amount') ?? 0);
                                $remaining = max(0, $totalAmount - $paidAmount);
                                return number_format($remaining, 0, ',', '.');
                            }

                            // Untuk status lainnya, tampilkan total normal
                            return $state ? number_format($state, 0, ',', '.') : '0';
                        }),
                ])->columns(2),

                // Hidden fields untuk trigger calculation
                Forms\Components\Hidden::make('trigger_calculation')
                    ->default(1)
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $orderDetails = $get('orderDetails') ?? [];
                        OrderHelper::updateOrderSummary($orderDetails, $set);
                    }),

                Forms\Components\Hidden::make('subtotal')
                    ->default(0),

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
            $set('price_total_display', array_column($newOrderDetails, 'price'));
            OrderHelper::updateOrderSummary($newOrderDetails, $set);
        }
    }
}
