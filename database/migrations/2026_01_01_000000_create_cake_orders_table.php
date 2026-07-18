<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cake_orders', function (Blueprint $table) {
            $table->id();
            $table->json('spec');               // full submitted configuration, for audit/replay
            $table->unsignedInteger('subtotal'); // rupees, server-computed
            $table->unsignedInteger('gst');      // rupees, server-computed
            $table->unsignedInteger('total');    // rupees, server-computed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cake_orders');
    }
};
