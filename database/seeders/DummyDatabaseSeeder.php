<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Customer;
use App\Models\User;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\AdditionalService;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderAdditionalService;
use App\Models\TransactionDiscount;
use App\Models\Payment;
use App\Models\Expense;
use App\Models\Inventory;
use App\Models\InventoryTransaction;
use App\Models\Machine;
use App\Models\MachineUsage;
use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Support\Facades\Hash;

class DummyDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);

        // Service Categories
        $serviceCategory = ServiceCategory::create([
            'name' => 'Laundry',
            'description' => 'Layanan Laundry',
            'is_active' => true,
        ]);

        // Services
        $service = Service::create([
            'service_category_id' => $serviceCategory->id,
            'name' => 'Cuci Kering',
            'price' => 5000,
            'unit' => 'kg',
            'duration_hours' => 24,
            'is_active' => true,
        ]);

        // Additional Services
        $additionalService = AdditionalService::create([
            'name' => 'Pewangi Extra',
            'price' => 2000,
            'is_active' => true,
        ]);

        // Discount
        $discount = Discount::create([
            'name' => 'Diskon Awal Tahun',
            'type' => 'percentage',
            'value' => 10,
            'is_active' => true,
        ]);

        // Customer
        $customer = Customer::create([
            'customer_code' => 'CUST001',
            'name' => 'Budi Santoso',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka No.1',
            'gender' => 'male',
            'is_active' => true,
        ]);

        // Order
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_date' => now(),
            'pickup_date' => now()->addDay(),
            'delivery_date' => now()->addDays(2),
            'status' => 'new',
            'subtotal' => 10000,
            'discount_amount' => 1000,
            'total_amount' => 9000,
            'payment_status' => 'unpaid',
        ]);

        // Order Details
        OrderDetail::create([
            'order_id' => $order->id,
            'service_id' => $service->id,
            'quantity' => 2,
            'unit_price' => 5000,
            'subtotal' => 10000,
        ]);

        // Order Additional Services
        OrderAdditionalService::create([
            'order_id' => $order->id,
            'additional_service_id' => $additionalService->id,
            'quantity' => 1,
            'unit_price' => 2000,
        ]);

        // Transaction Discount
        TransactionDiscount::create([
            'order_id' => $order->id,
            'discount_id' => $discount->id,
        ]);

        // Payment
        Payment::create([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 9000,
            'payment_date' => now(),
        ]);

        // Expense
        Expense::create([
            'category' => 'Operational',
            'description' => 'Beli plastik laundry',
            'amount' => 50000,
            'expense_date' => now(),
            'user_id' => 1,
        ]);

        // Inventory Item
        $inventory = Inventory::create([
            'name' => 'Detergen',
            'category' => 'detergent',
            'unit' => 'kg',
            'current_stock' => 50,
            'minimum_stock' => 10,
            'unit_price' => 20000,
            'supplier' => 'Supplier A',
        ]);

        // Inventory Transaction
        InventoryTransaction::create([
            'inventory_id' => $inventory->id,
            'type' => 'in',
            'quantity' => 10,
            'unit_price' => 20000,
            'total_price' => 200000,
            'reference_type' => 'purchase',
            'transaction_date' => now(),
            'user_id' => 1,
        ]);

        // Machine
        $machine = Machine::create([
            'name' => 'Mesin Cuci A',
            'type' => 'washer',
            'capacity_kg' => 10,
            'status' => 'available',
            'is_active' => true,
        ]);

        // Machine Usage
        MachineUsage::create([
            'machine_id' => $machine->id,
            'order_id' => $order->id,
            'start_time' => now(),
            'end_time' => now()->addMinutes(30),
            'duration_minutes' => 30,
            'user_id' => 1,
        ]);

        // Item Category
        $itemCategory = ItemCategory::create([
            'name' => 'Pakaian',
            'description' => 'Kategori pakaian',
        ]);

        // Item
        Item::create([
            'order_id' => $order->id,
            'item_category_id' => $itemCategory->id,
            'name' => 'Kaos',
            'condition_before' => 'Kotor',
            'condition_after' => 'Bersih',
        ]);
    }
}
