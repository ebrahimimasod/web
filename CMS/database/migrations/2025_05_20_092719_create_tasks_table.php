<?php

use App\Models\Task;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create(Task::TABLE, function (Blueprint $table) {
            $table->id(Task::COL_ID);
            $table->string(Task::COL_NAME);
            $table->string(Task::COL_TYPE);
            $table->uuid(Task::COL_TRACK_ID);
            $table->json(Task::COL_PAYLOAD)->nullable();
            $table->string(Task::COL_STATUS);
            $table->string(Task::COL_STEP)->nullable();
            $table->text(Task::COL_RESULT)->nullable();
            $table->text(Task::COL_ERRORS)->nullable();
            $table->timestamp(Task::COL_FINISHED_AT)->nullable();
            $table->timestamp(Task::COL_HEARTBEAT_AT)->nullable();
            $table->timestamp(Task::COL_TIMEOUT_AT)->nullable();
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists(Task::TABLE);
    }
};
