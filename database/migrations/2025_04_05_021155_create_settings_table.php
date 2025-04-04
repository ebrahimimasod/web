<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create(Setting::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string(Setting::COL_NAME)->unique();
            $table->text(Setting::COL_VALUE)->nullable();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists(Setting::TABLE);
    }
};
