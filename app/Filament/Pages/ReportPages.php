<?php

namespace App\Filament\Pages;

use Carbon\Carbon;
use App\Models\Order;
use App\Models\Expense;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Actions\Action;
use App\Helpers\PaymentHelper;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\Fieldset;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Concerns\InteractsWithForms;

class ReportPages extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static string $view = 'filament.pages.report-pages';
    protected static ?string $navigationGroup = 'Laporan';
    protected static ?string $navigationLabel = 'Laporan Keuangan';
    protected static ?string $title = 'Laporan Keuangan';

    public ?array $data = [];
    public $reportData = [];
    public $summary = [];

    public function mount(): void
    {
        $this->form->fill([
            'start_date' => now()->startOfMonth(),
            'end_date' => now(),
            'report_type' => 'all',
            'payment_id' => 'all',
        ]);

        $this->generateReport();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Fieldset::make('Filter Laporan')
                    ->schema([
                        Grid::make(4)->schema([
                            DatePicker::make('start_date')
                                ->label('Tanggal Mulai')
                                ->required()
                                ->default(now()->startOfMonth())
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn() => $this->generateReport()),

                            DatePicker::make('end_date')
                                ->label('Tanggal Akhir')
                                ->required()
                                ->default(now())
                                ->live(onBlur: true)
                                ->afterStateUpdated(fn() => $this->generateReport()),

                            Select::make('report_type')
                                ->label('Jenis Laporan')
                                ->options([
                                    'all' => 'Semua Transaksi',
                                    'income' => 'Pemasukan (Orders)',
                                    'expense' => 'Pengeluaran (Expenses)'
                                ])
                                ->default('all')
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn() => $this->generateReport()),

                            Select::make('payment_id')
                                ->label('Metode Pembayaran')
                                ->default('all')
                                ->options(['all' => 'Semua Metode'] + PaymentHelper::getPaymentMethods())
                                ->nullable()
                                ->live()
                                ->afterStateUpdated(fn() => $this->generateReport())
                                ->searchable(),

                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(function () {
                    $this->exportToExcel();
                }),
        ];
    }

    public function generateReport()
    {
        $data = $this->form->getState();

        // Validasi data sebelum generate
        if (!isset($data['start_date']) || !isset($data['end_date']) || !isset($data['report_type']) || !isset($data['payment_id'])) {
            return;
        }

        $startDate = Carbon::parse($data['start_date'])->startOfDay();
        $endDate = Carbon::parse($data['end_date'])->endOfDay();
        $reportType = $data['report_type'];
        $paymentId = $data['payment_id'];

        // Validasi tanggal
        if ($startDate > $endDate) {
            Notification::make()
                ->title('Error')
                ->body('Tanggal mulai tidak boleh lebih besar dari tanggal akhir')
                ->danger()
                ->send();
            return;
        }

        $this->reportData = [];
        $this->summary = [
            'total_income' => 0,
            'total_expense' => 0,
            'net_profit' => 0,
            'total_orders' => 0,
            'total_expenses' => 0
        ];

        // Ambil data pemasukan dari orders
        if ($reportType === 'all' || $reportType === 'income') {
            $orders = Order::with(['customer', 'payment'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->where('payment_status', 'paid');

            if ($paymentId !== 'all') {
                $orders->where('payment_id', $paymentId);
            }

            $orders = $orders->get();

            foreach ($orders as $order) {
                $this->reportData[] = [
                    'type' => 'income',
                    'date' => $order->created_at->format('Y-m-d'),
                    'description' => 'Order #' . $order->order_code . ' - ' . ($order->customer->name ?? 'Unknown'),
                    'category' => 'Penjualan',
                    'amount' => $order->total_amount,
                    'payment_method' => $order->payment->payment_name ?? 'Unknown',
                    'reference' => $order->order_code,
                    'details' => [
                        'customer' => $order->customer->name ?? 'Unknown',
                        'payment_method' => $order->payment->payment_name ?? 'Unknown',
                        'status' => $order->status,
                        'items' => $order->total_items
                    ]
                ];

                $this->summary['total_income'] += $order->total_amount;
                $this->summary['total_orders']++;
            }
        }

        // Ambil data pengeluaran dari expenses
        if ($reportType === 'all' || $reportType === 'expense') {
            $expenses = Expense::with('user')
                ->whereBetween('expense_date', [$startDate, $endDate])
                ->get();

            foreach ($expenses as $expense) {
                $this->reportData[] = [
                    'type' => 'expense',
                    'date' => Carbon::parse($expense->expense_date)->format('Y-m-d'),
                    'description' => $expense->description,
                    'category' => $expense->category,
                    'amount' => $expense->amount,
                    'reference' => $expense->receipt_number,
                    'details' => [
                        'user' => $expense->user->name ?? 'Unknown',
                        'receipt' => $expense->receipt_number
                    ]
                ];

                $this->summary['total_expense'] += $expense->amount;
                $this->summary['total_expenses']++;
            }
        }

        // Hitung net profit
        $this->summary['net_profit'] = $this->summary['total_income'] - $this->summary['total_expense'];

        // Urutkan berdasarkan tanggal
        usort($this->reportData, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
    }

    public function exportToExcel()
    {
        // Implementasi export Excel bisa menggunakan Laravel Excel
        Notification::make()
            ->title('Export berhasil')
            ->body('Fitur export akan segera tersedia')
            ->info()
            ->send();
    }

    public function getReportData()
    {
        return $this->reportData;
    }

    public function getSummary()
    {
        return $this->summary;
    }
}
