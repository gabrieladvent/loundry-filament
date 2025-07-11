<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Enums\OrderPaymentStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentServices
{
    /**
     * Update payment status for order
     */
    public function updatePayment(Order $order, array $paymentData): array
    {
        try {
            DB::beginTransaction();

            // Validasi data
            $this->validatePaymentData($paymentData);

            // Hitung payment amounts
            $calculatedAmounts = $this->calculatePaymentAmounts($order, $paymentData);

            // Determine final payment status (auto-update to 'paid' if partial payment completes the order)
            $finalPaymentStatus = $calculatedAmounts['remaining_amount'] <= 0
                ? 'paid'
                : $paymentData['payment_status'];

            // Update order
            $updateData = [
                'payment_status' => $finalPaymentStatus,
                'paid_amount' => $calculatedAmounts['paid_amount'],
                'change_amount' => $calculatedAmounts['change_amount'],
                'updated_at' => now(),
            ];

            // Update payment method if provided
            if (isset($paymentData['payment_id']) && !empty($paymentData['payment_id'])) {
                $updateData['payment_method_id'] = $paymentData['payment_id'];
            }

            $order->update($updateData);

            // Log payment history
            $this->logPaymentHistory($order, $paymentData, $calculatedAmounts, $finalPaymentStatus);

            DB::commit();

            return [
                'success' => true,
                'message' => $this->getSuccessMessage($paymentData['payment_status'], $finalPaymentStatus, $calculatedAmounts),
                'data' => [
                    'paid_amount' => $calculatedAmounts['paid_amount'],
                    'change_amount' => $calculatedAmounts['change_amount'],
                    'remaining_amount' => $calculatedAmounts['remaining_amount'],
                    'payment_status' => $finalPaymentStatus,
                ]
            ];
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Payment update failed: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'payment_data' => $paymentData
            ]);

            return [
                'success' => false,
                'message' => 'Pembayaran gagal diperbarui: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Validasi data pembayaran
     */
    private function validatePaymentData(array $paymentData): void
    {
        if (empty($paymentData['payment_status'])) {
            throw new Exception('Status pembayaran harus diisi');
        }

        $requiredAmountStatuses = ['paid', 'partial'];
        if (in_array($paymentData['payment_status'], $requiredAmountStatuses)) {
            if (empty($paymentData['paid_amount']) || $paymentData['paid_amount'] <= 0) {
                throw new Exception('Nominal pembayaran harus diisi dan lebih dari 0');
            }

            if (empty($paymentData['payment_id'])) {
                throw new Exception('Metode pembayaran harus dipilih');
            }
        }
    }

    /**
     * Hitung jumlah pembayaran berdasarkan status
     */
    private function calculatePaymentAmounts(Order $order, array $paymentData): array
    {
        $totalAmount = $order->total_amount;
        $currentPaidAmount = $order->paid_amount ?? 0;
        $inputAmount = $paymentData['paid_amount'] ?? 0;

        switch ($paymentData['payment_status']) {
            case 'partial':
                $paidAmount = $currentPaidAmount + $inputAmount;
                $remainingAmount = max(0, $totalAmount - $paidAmount);

                // Auto-calculate change amount if payment completes the order
                $changeAmount = $paidAmount > $totalAmount
                    ? $paidAmount - $totalAmount
                    : $remainingAmount;

                // If partial payment completes the order, we'll handle the status change in the main method
                break;

            case 'paid':
                $paidAmount = $inputAmount;
                $remainingAmount = 0;
                $changeAmount = $paidAmount - $totalAmount;

                // Validasi: pastikan pembayaran cukup untuk lunas
                if ($paidAmount < $totalAmount - $currentPaidAmount) {
                    throw new Exception('Nominal pembayaran kurang dari sisa yang harus dibayar. Sisa: ' . ($totalAmount - $currentPaidAmount));
                }
                break;

            case 'unpaid':
                $paidAmount = 0;
                $remainingAmount = $totalAmount;
                $changeAmount = $totalAmount;
                break;

            default:
                throw new Exception('Status pembayaran tidak valid');
        }

        return [
            'paid_amount' => $paidAmount,
            'change_amount' => $changeAmount,
            'remaining_amount' => $remainingAmount,
        ];
    }

    /**
     * Log payment history untuk audit trail
     */
    private function logPaymentHistory(Order $order, array $paymentData, array $calculatedAmounts, string $finalStatus): void
    {
        Log::info('Payment updated', [
            'order_id' => $order->id,
            'old_status' => $order->getOriginal('payment_status'),
            'requested_status' => $paymentData['payment_status'],
            'final_status' => $finalStatus,
            'old_paid_amount' => $order->getOriginal('paid_amount'),
            'new_paid_amount' => $calculatedAmounts['paid_amount'],
            'input_amount' => $paymentData['paid_amount'] ?? 0,
            'payment_method_id' => $paymentData['payment_id'] ?? null,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Generate success message based on payment status changes
     */
    private function getSuccessMessage(string $requestedStatus, string $finalStatus, array $amounts): string
    {
        if ($requestedStatus === 'partial' && $finalStatus === 'paid') {
            return 'Pembayaran berhasil diperbarui. Status otomatis berubah ke Lunas karena pembayaran telah mencukupi.';
        }

        $statusMessage = match ($finalStatus) {
            'paid' => 'Lunas',
            'partial' => 'Sebagian',
            'unpaid' => 'Belum Dibayar',
            default => $finalStatus
        };

        return 'Pembayaran berhasil diperbarui dengan status ' . $statusMessage;
    }

    /**
     * Get payment summary untuk display
     */
    public function getPaymentSummary(Order $order): array
    {
        $totalAmount = $order->total_amount;
        $paidAmount = $order->paid_amount ?? 0;
        $changeAmount = $order->change_amount ?? 0;

        $remainingAmount = max(0, $totalAmount - $paidAmount);
        $isOverpaid = $paidAmount > $totalAmount;

        return [
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'change_amount' => $changeAmount,
            'remaining_amount' => $remainingAmount,
            'payment_status' => $order->payment_status,
            'is_overpaid' => $isOverpaid,
            'payment_method' => $order->payment ? $order->payment->payment_name : null,
            'payment_percentage' => $totalAmount > 0 ? round(($paidAmount / $totalAmount) * 100, 2) : 0,
        ];
    }

    /**
     * Check apakah order bisa diupdate payment
     */
    public function canUpdatePayment(Order $order): bool
    {
        // Tambahkan logic bisnis sesuai kebutuhan
        // Misalnya: order yang sudah cancelled/refunded tidak bisa diupdate
        $restrictedStatuses = ['cancelled', 'refunded'];

        return !in_array($order->status, $restrictedStatuses);
    }
}
