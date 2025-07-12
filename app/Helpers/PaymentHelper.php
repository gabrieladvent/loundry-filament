<?php

namespace App\Helpers;

use App\Models\Discount;
use App\Models\Payment;
use Filament\Forms\Get;
use Filament\Forms\Set;

class PaymentHelper
{
    /**
     * Convert formatted money string to float
     */
    public static function toFloat($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            // Remove formatting like "1.000.000" or "1,000,000"
            return (float) str_replace(['.', ',', ' '], '', $value);
        }

        return 0.0;
    }

    /**
     * Get available payment methods
     */
    public static function getPaymentMethods(): array
    {
        return Payment::pluck('payment_name', 'id')->toArray();
    }

    /**
     * Get available discounts based on amount and current date
     */
    public static function getAvailableDiscounts($amount = 0): array
    {
        // Convert to float to handle string inputs
        $amount = self::toFloat($amount);

        $currentDate = now();

        return Discount::where('min_amount', '<=', $amount)
            ->where('valid_from', '<=', $currentDate)
            ->where('valid_until', '>=', $currentDate)
            ->where('is_active', true)
            ->get()
            ->mapWithKeys(function ($discount) {
                $type = $discount->type === 'percentage' ? '%' : 'Rp';
                $value = $discount->type === 'percentage'
                    ? $discount->value
                    : number_format($discount->value, 0, ',', '.');
                return [$discount->id => $discount->name . ' (' . $value . $type . ')'];
            })
            ->toArray();
    }

    /**
     * Calculate discount amount
     */
    public static function calculateDiscount(int $discountId, $subtotal): float
    {
        $discount = Discount::find($discountId);

        if (!$discount) {
            return 0;
        }

        // Convert subtotal to float
        $subtotal = self::toFloat($subtotal);

        if ($discount->type === 'percentage') {
            $discountAmount = ($subtotal * $discount->value) / 100;
            // Apply max discount if set
            if ($discount->max_discount && $discountAmount > $discount->max_discount) {
                return $discount->max_discount;
            }
            return $discountAmount;
        }

        return $discount->value;
    }

    /**
     * Calculate total amount
     */
    public static function calculateTotal($subtotal, $tax = 0, $additionalFee = 0, $discount = 0): float
    {
        // Convert all inputs to float
        $subtotal = self::toFloat($subtotal);
        $tax = self::toFloat($tax);
        $additionalFee = self::toFloat($additionalFee);
        $discount = self::toFloat($discount);

        return max(0, $subtotal + $tax + $additionalFee - $discount);
    }

    /**
     * Handle discount selection and update form
     */
    public static function handleDiscountSelection($state, Set $set, Get $get): void
    {
        if ($state) {
            $subtotal = $get('subtotal_amount') ?? 0;
            $discountAmount = self::calculateDiscount($state, $subtotal);
            $set('discount_amount', $discountAmount);
        } else {
            $set('discount_amount', 0);
        }

        self::updateTotalAmount($set, $get);
    }

    /**
     * Update total amount based on current form values
     */
    public static function updateTotalAmount(Set $set, Get $get): void
    {
        $subtotal = $get('subtotal_amount') ?? 0;
        $tax = $get('tax_amount') ?? 0;
        $additionalFee = $get('additional_fee') ?? 0;
        $discount = $get('discount_amount') ?? 0;

        $total = self::calculateTotal($subtotal, $tax, $additionalFee, $discount);
        $set('total_amount', $total);

        // Auto-adjust paid amount if payment status is 'paid'
        $paymentStatus = $get('payment_status');
        if ($paymentStatus === 'paid') {
            $set('paid_amount', $total);
        }
    }

    /**
     * Check if payment fields should be visible
     */
    public static function shouldShowPaymentFields(Get $get): bool
    {
        return in_array($get('payment_status'), ['partial', 'paid']);
    }

    /**
     * Get discount helper text
     */
    public static function getDiscountHelperText(Get $get): ?string
    {
        $discountAmount = self::toFloat($get('discount_amount'));

        if ($discountAmount > 0) {
            return 'Total sudah dipotong diskon sebesar Rp ' . number_format($discountAmount, 0, ',', '.');
        }

        return null;
    }

    /**
     * Validate payment amount
     */
    public static function validatePaymentAmount(Get $get): array
    {
        $rules = ['numeric', 'min:0'];
        $paymentStatus = $get('payment_status');
        $totalAmount = self::toFloat($get('total_amount') ?? 0);

        if ($paymentStatus === 'paid') {
            $rules[] = function ($attribute, $value, $fail) use ($totalAmount) {
                $paidAmount = self::toFloat($value);
                if (abs($paidAmount - $totalAmount) > 0.01) { // Allow small floating point differences
                    $fail('Jumlah pembayaran harus sama dengan total pembayaran (Rp ' . number_format($totalAmount, 0, ',', '.') . ') untuk status "Dibayar"');
                }
            };
        } elseif ($paymentStatus === 'partial') {
            $rules[] = function ($attribute, $value, $fail) use ($totalAmount) {
                $paidAmount = self::toFloat($value);
                if ($paidAmount <= 0) {
                    $fail('Jumlah pembayaran harus lebih dari 0 untuk status "Sebagian Dibayar"');
                }
                if ($paidAmount >= $totalAmount) {
                    $fail('Jumlah pembayaran harus kurang dari total pembayaran (Rp ' . number_format($totalAmount, 0, ',', '.') . ') untuk status "Sebagian Dibayar"');
                }
            };
        }

        return $rules;
    }

    /**
     * Get remaining amount for partial payment
     */
    public static function getRemainingAmount(Get $get): float
    {
        $totalAmount = self::toFloat($get('total_amount') ?? 0);
        $paidAmount = self::toFloat($get('paid_amount') ?? 0);

        return max(0, $totalAmount - $paidAmount);
    }

    /**
     * Get payment status helper text
     */
    public static function getPaymentStatusHelperText(Get $get): ?string
    {
        $paymentStatus = $get('payment_status');
        $totalAmount = self::toFloat($get('total_amount') ?? 0);
        $paidAmount = self::toFloat($get('paid_amount') ?? 0);

        if ($paymentStatus === 'partial' && $paidAmount > 0) {
            $remaining = self::getRemainingAmount($get);
            return 'Sisa pembayaran: Rp ' . number_format($remaining, 0, ',', '.');
        }

        if ($paymentStatus === 'paid' && $totalAmount > 0) {
            return 'Total pembayaran: Rp ' . number_format($totalAmount, 0, ',', '.');
        }

        return null;
    }
}
