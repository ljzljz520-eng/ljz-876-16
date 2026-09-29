<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exam_record_answers', 'answered_at')) {
            return;
        }

        Schema::table('exam_record_answers', function (Blueprint $table) {
            // 答题时间点，用于监考回放时与草稿照片时间轴对齐
            $table->timestamp('answered_at')->nullable()->after('score')->comment('作答时间(提交时落库)');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('exam_record_answers', 'answered_at')) {
            return;
        }

        Schema::table('exam_record_answers', function (Blueprint $table) {
            $table->dropColumn('answered_at');
        });
    }
};
