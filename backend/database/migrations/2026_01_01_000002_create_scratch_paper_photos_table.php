<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scratch_paper_photos')) {
            return;
        }

        Schema::create('scratch_paper_photos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_record_id')->comment('考试记录ID');
            $table->unsignedBigInteger('user_id')->comment('拍摄学生ID');
            $table->string('phase', 20)->comment('拍摄阶段: pre-开考前空白纸 final-交卷前最终页');
            $table->string('file_path')->comment('存储相对路径');
            $table->string('original_name')->nullable()->comment('原始文件名');
            $table->unsignedBigInteger('file_size')->default(0)->comment('文件大小(字节)');
            $table->string('mime_type', 50)->nullable()->comment('MIME类型');
            $table->unsignedInteger('elapsed_seconds')->default(0)->comment('相对开考已用时(秒)，用于回放对齐答题时间');
            $table->string('client_captured_at', 30)->nullable()->comment('前端设备拍摄时间(ISO字符串)');
            $table->timestamps();

            $table->index('exam_record_id');
            $table->index('user_id');
            $table->index(['exam_record_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scratch_paper_photos');
    }
};
