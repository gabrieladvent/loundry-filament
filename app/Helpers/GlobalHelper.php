<?php

namespace App\Helpers;

if (!function_exists('updateOrderSummary')) {
    function updateOrderSummary($orderDetails, $set)
    {
        $totalWeight = 0;
        $totalItems = 0;
        $maxDuration = 0;

        foreach ($orderDetails as $detail) {
            $totalWeight += (float) ($detail['weight'] ?? 0);
            $totalItems++;

            if (!empty($detail['service_id'])) {
                $service = \App\Models\Service::find($detail['service_id']);
                if ($service && $service->duration_days > $maxDuration) {
                    $maxDuration = $service->duration_days;
                }
            }
        }

        $set('total_weight', $totalWeight);
        $set('total_items', $totalItems);

        if ($maxDuration > 0) {
            $estimatedDate = now()->addDays($maxDuration);
            $set('estimated_finish', $estimatedDate);
            $set('pickup_date', $estimatedDate);
        }
    }
}
