<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_code', 30)->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete()->nullable();
            $table->datetime('order_date');
            $table->datetime('pickup_date')->nullable();
            $table->datetime('delivery_date')->nullable();
            $table->datetime('estimated_finish')->nullable();
            $table->datetime('actual_finish')->nullable();
            $table->enum('status', [
                'pending',
                'in_progress',
                'washing',
                'drying',
                'ironing',
                'ready',
                'delivered',
                'cancelled'
            ])->default('pending');
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid');
            $table->enum('pickup_type', ['drop_off', 'pickup'])->default('drop_off');
            $table->enum('delivery_type', ['pickup', 'delivery'])->default('pickup');
            $table->text('pickup_address')->nullable();
            $table->text('delivery_address')->nullable();
            $table->decimal('total_weight', 8, 2)->default(0);
            $table->integer('total_items')->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('additional_fee', 10, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0)->nullable();
            $table->decimal('change_amount', 10, 2)->default(0)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['order_code']);
            $table->index(['customer_id']);
            $table->index(['order_date']);
            $table->index(['status']);
            $table->index(['payment_status']);
            $table->index(['status', 'order_date']);
            $table->index(['customer_id', 'order_date']);
            $table->index(['payment_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
