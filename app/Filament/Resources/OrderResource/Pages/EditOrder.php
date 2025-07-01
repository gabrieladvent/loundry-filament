<?php

namespace App\Filament\Resources\OrderResource\Pages;

use Filament\Actions;
use Illuminate\Support\Facades\DB;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\OrderResource;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeUpdate(array $data): array
    {
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

    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        /** @var \App\Models\Order $order */
        $order = $record;

        return DB::transaction(function () use ($order, $data) {
            // Step 1: Update master order
            $order->update([
                'order_code' => $data['order_code'],
                'customer_id' => $data['customer_id'],
                'user_id' => $data['user_id'],
                'order_date' => $data['order_date'],
                'pickup_date' => $data['pickup_date'],
                'delivery_date' => $data['delivery_date'] ?? null,
                'estimated_finish' => $data['estimated_finish'],
                'status' => $data['status'],
                'payment_status' => $data['payment_status'],
                'payment_id' => $data['payment_id'] ?? null,
                'pickup_type' => $data['pickup_type'],
                'delivery_type' => $data['delivery_type'],
                'pickup_address' => $data['pickup_address'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'total_weight' => $data['total_weight'],
                'total_items' => $data['total_items'],
                'subtotal' => $data['subtotal_amount'],
                'discount_amount' => $data['discount_amount'],
                'tax_amount' => $data['tax_amount'],
                'additional_fee' => $data['additional_fee'],
                'total_amount' => $data['total_amount'],
                'paid_amount' => $data['paid_amount'] ?? 0,
                'change_amount' => $data['change_amount'] ?? 0,
                'notes' => $data['notes'],
            ]);

            // Step 2: Sync order details with better performance
            $this->syncOrderDetails($order, $data['orderDetails'] ?? []);

            // Step 3: Sync discounts
            $this->syncTransactionDiscounts($order, $data);

            return $order;
        });
    }

    /**
     * Sync order details with better performance using upsert approach
     */
    private function syncOrderDetails(\App\Models\Order $order, array $orderDetails): void
    {
        // Get existing details
        $existingDetails = $order->orderDetails()->get()->keyBy('id');
        $newDetailIds = [];

        foreach ($orderDetails as $detail) {
            // If detail has ID and exists, update it
            if (isset($detail['id']) && $existingDetails->has($detail['id'])) {
                $existingDetail = $existingDetails->get($detail['id']);
                $existingDetail->update([
                    'service_id' => $detail['service_id'],
                    'quantity' => 1,
                    'unit_price' => $detail['unit_price'],
                    'weight' => $detail['weight'],
                    'subtotal' => $detail['price'],
                    'notes' => $detail['notes'] ?? null,
                ]);
                $newDetailIds[] = $detail['id'];
            } else {
                // Create new detail
                $newDetail = $order->orderDetails()->create([
                    'service_id' => $detail['service_id'],
                    'quantity' => 1,
                    'unit_price' => $detail['unit_price'],
                    'weight' => $detail['weight'],
                    'subtotal' => $detail['price'],
                    'notes' => $detail['notes'] ?? null,
                ]);
                $newDetailIds[] = $newDetail->id;
            }
        }

        // Delete details that are no longer present
        $order->orderDetails()
            ->whereNotIn('id', $newDetailIds)
            ->delete();
    }

    /**
     * Sync transaction discounts
     */
    private function syncTransactionDiscounts(\App\Models\Order $order, array $data): void
    {
        // Delete existing discounts
        $order->transactionDiscounts()->delete();

        // Create new discount if present
        if (!empty($data['discount_id'])) {
            $order->transactionDiscounts()->create([
                'discount_id' => $data['discount_id'],
                'discount_amount' => $data['discount_amount'] ?? 0,
            ]);
        }
    }
}
