<?php

namespace App\Filament\Resources\OrderResource\Pages;

use Filament\Actions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Filament\Resources\OrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Hanya adjust data, tidak create record di sini
        return $this->adjustOrderWeightAndPrice($data);
    }

    protected function adjustOrderWeightAndPrice(array $data): array
    {
        $isPickupService = $data['pickup_type'] === 'pickup';
        $isDeliveryService = $data['delivery_type'] === 'delivery';

        $orderDetails = $data['orderDetails'] ?? [];
        $totalWeight = array_sum(array_map(fn($item) => (float) ($item['weight'] ?? 0), $orderDetails));

        if (($isPickupService || $isDeliveryService) && $totalWeight < 3) {
            $adjustedWeight = 3;
            $data['total_weight'] = $adjustedWeight;
            $data['orderDetails'] = \App\Helpers\OrderHelper::recalculateOrderDetails($orderDetails, $adjustedWeight);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $order = $this->record;
        $data = $this->data;

        DB::transaction(function () use ($data, $order) {
            // Step 1: Simpan Order Details
            foreach ($data['orderDetails'] as $detail) {
                $order->orderDetails()->create([
                    'service_id' => $detail['service_id'],
                    'quantity' => 1,
                    'unit_price' => $detail['unit_price'],
                    'weight' => $detail['weight'],
                    'subtotal' => $detail['price'],
                    'notes' => $detail['notes'] ?? null,
                ]);
            }

            // Step 2: Simpan Diskon
            if (!empty($data['discount_id'])) {
                $order->transactionDiscounts()->create([
                    'discount_id' => $data['discount_id'],
                    'discount_amount' => $data['discount_amount'],
                ]);
            }
        });
    }
}
