<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Filter Form -->
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            {{ $this->form }}
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card card-green">
                <div class="card-flex">
                    <div class="card-icon" style="margin-right: 1rem">
                        <x-heroicon-o-banknotes class="w-8 h-8 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="card-content">
                        <p class="card-label">Total Pemasukan</p>
                        <p class="card-value">
                            Rp {{ number_format($this->getSummary()['total_income'], 0, ',', '.') }}
                        </p>
                        <p class="card-meta">{{ $this->getSummary()['total_orders'] }} orders</p>
                    </div>
                </div>
            </div>

            <div class="summary-card card-red">
                <div class="card-flex">
                    <div class="card-icon" style="margin-right: 1rem">
                        <x-heroicon-o-scissors class="w-8 h-8 text-red-600 dark:text-red-400" />
                    </div>
                    <div class="card-content">
                        <p class="card-label">Total Pengeluaran</p>
                        <p class="card-value">
                            Rp {{ number_format($this->getSummary()['total_expense'], 0, ',', '.') }}
                        </p>
                        <p class="card-meta">{{ $this->getSummary()['total_expenses'] }} expenses</p>
                    </div>
                </div>
            </div>

            <div class="summary-card card-blue">
                <div class="card-flex">
                    <div class="card-icon" style="margin-right: 1rem">
                        <x-heroicon-o-sparkles class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="card-content">
                        <p class="card-label">Laba Bersih</p>
                        <p
                            class="card-value {{ $this->getSummary()['net_profit'] >= 0 ? 'profit-positive' : 'profit-negative' }}">
                            Rp {{ number_format($this->getSummary()['net_profit'], 0, ',', '.') }}
                        </p>
                        <p class="card-meta">
                            {{ $this->getSummary()['net_profit'] >= 0 ? 'Profit' : 'Loss' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="summary-card card-purple">
                <div class="card-flex">
                    <div class="card-icon" style="margin-right: 1rem">
                        <x-heroicon-o-presentation-chart-bar class="w-8 h-8 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="card-content">
                        <p class="card-label">Total Transaksi</p>
                        <p class="card-value">
                            {{ $this->getSummary()['total_orders'] + $this->getSummary()['total_expenses'] }}
                        </p>
                        <p class="card-meta">Semua transaksi</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Table -->
        <div class="transaction-container">
            <div class="transaction-header">
                <h3 class="transaction-title">Detail Transaksi</h3>
            </div>

            <div class="table-wrapper">
                <table class="transaction-table">
                    <thead class="table-header">
                        <tr>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Deskripsi</th>
                            <th>Kategori</th>
                            <th>Metode Pembayaran</th>
                            <th>Referensi</th>
                            <th>Jumlah</th>
                        </tr>
                    </thead>
                    <tbody class="table-body">
                        @forelse($this->getReportData() as $item)
                            <tr>
                                <td class="whitespace-nowrap">
                                    {{ \Carbon\Carbon::parse($item['date'])->format('d/m/Y') }}
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($item['type'] === 'income')
                                        <span class="status-badge status-income">
                                            <svg class="status-icon" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-8.707l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13a1 1 0 102 0V9.414l1.293 1.293a1 1 0 001.414-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Pemasukan
                                        </span>
                                    @else
                                        <span class="status-badge status-expense">
                                            <svg class="status-icon" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            Pengeluaran
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    {{ $item['description'] }}
                                    @if ($item['type'] === 'income' && isset($item['details']['items']))
                                        <div class="item-details">
                                            {{ $item['details']['items'] }} items
                                        </div>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ \App\Enum\ExpenseType::tryFrom($item['category'])?->getLabel() ?? $item['category'] }}
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ $item['payment_method'] ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap">
                                    {{ $item['reference'] ?? '-' }}
                                </td>
                                <td class="whitespace-nowrap">
                                    @if ($item['type'] === 'income')
                                        <span class="amount-positive">
                                            +Rp {{ number_format($item['amount'], 0, ',', '.') }}
                                        </span>
                                    @else
                                        <span class="amount-negative">
                                            -Rp {{ number_format($item['amount'], 0, ',', '.') }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="no-data">
                                    Tidak ada data transaksi ditemukan
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @push('styles')
            <style>
                .transaction-container { background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden; }
                .transaction-header { padding: 24px; border-bottom: 1px solid #e5e7eb; background: #fafafa; }
                .transaction-title { font-size: 18px; font-weight: 600; color: #1f2937; margin: 0; }
                .table-wrapper { overflow-x: auto; }
                .transaction-table { width: 100%; border-collapse: collapse; font-size: 14px; }
                .table-header { background: #f9fafb; }
                .table-header th { padding: 12px 24px; text-align: left; font-size: 11px; font-weight: 500; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
                .table-header th:last-child { text-align: right; }
                .table-body tr { border-bottom: 1px solid #e5e7eb; transition: background-color 0.2s; }
                .table-body tr:hover { background: #f9fafb; }
                .table-body td { padding: 16px 24px; color: #1f2937; vertical-align: top; }
                .table-body td:last-child { text-align: right; font-weight: 500; }
                .status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 500; }

                .status-income { background: #dcfce7; color: #166534; }
                .status-expense { background: #fef2f2; color: #991b1b; }
                .status-icon { width: 12px; height: 12px; margin-right: 4px; }
                .amount-positive { color: #059669; }
                .amount-negative { color: #dc2626; }
                .item-details { font-size: 12px; color: #6b7280; margin-top: 4px; }
                .no-data { text-align: center; padding: 40px 24px; color: #6b7280; font-style: italic; }
                .whitespace-nowrap { white-space: nowrap; }
                .summary-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
                .summary-card { border-radius: 8px; padding: 1rem; border: 1px solid; }
                .card-flex { display: flex; align-items: center; }
                .card-icon { flex-shrink: 0; margin-right: 1rem; width: 2rem; height: 2rem; }
                .card-content { margin-left: 0.75rem; }
                .card-label { font-size: 0.875rem; font-weight: 500; }
                .card-value { font-size: 1.5rem; font-weight: 700; }
                .card-meta { font-size: 0.75rem; }

                .card-green .card-icon { color: #16a34a; }
                .card-red .card-icon { color: #dc2626; }
                .card-blue .card-icon { color: #2563eb; }
                .card-purple .card-icon { color: #9333ea; }

                .profit-positive { color: #16a34a; }
                .profit-negative { color: #dc2626; }

                @media (min-width: 768px) {
                    .summary-grid { grid-template-columns: repeat(2, 1fr); }
                }

                @media (min-width: 1024px) {
                    .summary-grid { grid-template-columns: repeat(4, 1fr); }
                }
            </style>
        @endpush
    </div>
</x-filament-panels::page>
