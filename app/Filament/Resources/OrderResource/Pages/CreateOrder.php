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

    protected $orderDetailsData = [];

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Bersihkan data payment jika unpaid
        if ($data['payment_status'] === 'unpaid') {
            $data['payment_id'] = null;
            $data['paid_amount'] = 0;
        }

        // Adjust weight dan recalculate
        $data = $this->adjustOrderWeightAndPrice($data);

        // Pastikan total fields ter-set dengan benar
        // $data = $this->calculateTotals($data);

        // PENTING: Hapus orderDetails dari data order utama
        // karena ini akan dihandle terpisah di afterCreate
        $orderDetails = $data['orderDetails'] ?? [];
        unset($data['orderDetails']);

        // Simpan orderDetails untuk digunakan di afterCreate
        $this->orderDetailsData = $orderDetails;

        return $data;
    }

    protected function adjustOrderWeightAndPrice(array $data): array
    {
        $isPickupService = $data['pickup_type'] === 'pickup';
        $isDeliveryService = $data['delivery_type'] === 'delivery';

        $orderDetails = $data['orderDetails'] ?? [];
        $totalWeight = array_sum(array_map(fn($item) => (float) ($item['weight'] ?? 0), $orderDetails));

        // Jika ada pickup/delivery service dan weight < 3kg, adjust ke 3kg
        if (($isPickupService || $isDeliveryService) && $totalWeight < 3) {
            $adjustedWeight = 3;
            $data['total_weight'] = $adjustedWeight;

            // Recalculate order details dengan weight yang sudah disesuaikan
            $data['orderDetails'] = \App\Helpers\OrderHelper::recalculateOrderDetails($orderDetails, $adjustedWeight);
        } else {
            $data['total_weight'] = $totalWeight;
        }

        return $data;
    }

    // protected function calculateTotals(array $data): array
    // {
    //     dd($data);
    //     $orderDetails = $data['orderDetails'] ?? [];

    //     // Hitung subtotal dari order details
    //     $subtotal = array_sum(array_map(function ($item) {
    //         return (float) ($item['unit_price'] ?? 0) * (float) ($item['weight'] ?? 0);
    //     }, $orderDetails));

    //     // Set subtotal
    //     $data['subtotal'] = $subtotal;
    //     $data['subtotal_amount'] = $subtotal;

    //     // Hitung total items
    //     $data['total_items'] = count($orderDetails);

    //     // Hitung total amount
    //     $discountAmount = (float) ($data['discount_amount'] ?? 0);
    //     $taxAmount = (float) ($data['tax_amount'] ?? 0);
    //     $additionalFee = (float) ($data['additional_fee'] ?? 0);

    //     $totalAmount = $subtotal - $discountAmount + $taxAmount + $additionalFee;
    //     $data['total_amount'] = $totalAmount;

    //     // Set change amount jika ada
    //     $paidAmount = (float) ($data['paid_amount'] ?? 0);
    //     $data['change_amount'] = max(0, $paidAmount - $totalAmount);

    //     return $data;
    // }

    protected function afterCreate(): void
    {
        $order = $this->record;
        $orderDetailsData = $this->orderDetailsData;
        $data = $this->data;

        DB::transaction(function () use ($orderDetailsData, $order, $data) {
            try {
                // Step 1: Simpan Order Details
                if (!empty($orderDetailsData)) {
                    $orderDetailsToInsert = [];

                    foreach ($orderDetailsData as $detail) {
                        $orderDetailsToInsert[] = [
                            'order_id' => $order->id,
                            'service_id' => $detail['service_id'],
                            'quantity' => $detail['weight'], // weight disimpan sebagai quantity
                            'unit_price' => $detail['unit_price'],
                            'subtotal' => (float)$detail['unit_price'] * (float)$detail['weight'],
                            'notes' => $detail['notes'] ?? null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    if (!empty($orderDetailsToInsert)) {
                        DB::table('order_details')->insert($orderDetailsToInsert);
                    }
                }

                // Step 2: Simpan Diskon jika ada
                if (!empty($data['discount_id']) && ($data['discount_amount'] ?? 0) > 0) {
                    DB::table('transaction_discounts')->insert([
                        'order_id' => $order->id,
                        'discount_id' => $data['discount_id'],
                        'discount_amount' => $data['discount_amount'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                Log::info('Order created successfully', [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                    'total_details' => count($orderDetailsData)
                ]);
            } catch (\Exception $e) {
                Log::error('Error creating order details: ' . $e->getMessage(), [
                    'order_id' => $order->id,
                    'error' => $e->getTraceAsString()
                ]);
                throw $e;
            }
        });
    }
}
