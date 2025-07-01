<?php

namespace App\Helpers;

use App\Models\Service;
use Filament\Forms\Get;
use Filament\Forms\Set;

class OrderHelper
{
    public static function updateOrderSummary($orderDetails, \Filament\Forms\Set $set)
    {
        $totalWeight = 0;
        $totalItems = 0;
        $subtotalAmount = 0;
        $maxDuration = 0;

        if (!is_array($orderDetails) || empty($orderDetails)) {
            $set('total_weight', 0);
            $set('total_items', 0);
            $set('subtotal_amount', 0);
            $set('estimated_finish', null);
            $set('pickup_date', null);
            return;
        }

        foreach ($orderDetails as $detail) {
            $totalWeight += (float) ($detail['weight'] ?? 0);
            $totalItems++;

            if (!empty($detail['price'])) {
                $subtotalAmount += (float) $detail['price'];
            }

            if (!empty($detail['service_id'])) {
                $service = \App\Models\Service::find($detail['service_id']);
                if ($service && $service->duration_days > $maxDuration) {
                    $maxDuration = $service->duration_days;
                }
            }
        }

        $set('total_weight', $totalWeight);
        $set('total_items', $totalItems);
        $set('subtotal_amount', $subtotalAmount);

        if ($maxDuration > 0) {
            $estimatedDate = now()->addDays($maxDuration);
            $set('estimated_finish', $estimatedDate->format('Y-m-d H:i:s'));
            $set('pickup_date', $estimatedDate->format('Y-m-d H:i:s'));
        } else {
            $set('estimated_finish', null);
            $set('pickup_date', null);
        }
    }



    /**
     * Calculate subtotal from order details array
     * This can be used as a standalone method if needed
     */
    public static function calculateSubtotal(array $orderDetails): float
    {
        $subtotal = 0;

        foreach ($orderDetails as $detail) {
            if (!empty($detail['price'])) {
                $price = $detail['price'];

                // Handle formatted price
                if (is_string($price)) {
                    $price = (float) str_replace(['.', ','], '', $price);
                }

                $subtotal += $price;
            }
        }

        return $subtotal;
    }

    public static function refreshSubtotal(Get $get, Set $set)
    {
        $orderDetails = $get('orderDetails') ?? [];
        $subtotal = self::calculateSubtotal($orderDetails);
        $set('subtotal_amount', $subtotal);
    }

    public static function recalculateOrderDetails(array $orderDetails, float $totalWeight): array
    {
        $newOrderDetails = [];

        $itemCount = count($orderDetails);
        $adjustedWeight = max(3, $totalWeight);  // Pastikan minimal 3kg

        // Bagi rata berat ke tiap item sesuai proporsi aslinya
        $totalOriginalWeight = array_sum(array_column($orderDetails, 'weight'));

        foreach ($orderDetails as $detail) {
            $service = Service::find($detail['service_id']);

            // Hitung proporsi berat
            $weightShare = $totalOriginalWeight > 0
                ? ($detail['weight'] / $totalOriginalWeight) * $adjustedWeight
                : ($adjustedWeight / $itemCount); // Kalau berat asli = 0, bagi rata

            $unitPrice = $service?->price ?? 0;
            $price = $unitPrice * $weightShare;

            $newOrderDetails[] = [
                ...$detail,
                'weight' => round($weightShare, 2),
                'unit_price' => $unitPrice,
                'price' => $price,
            ];
        }

        return $newOrderDetails;
    }
}
