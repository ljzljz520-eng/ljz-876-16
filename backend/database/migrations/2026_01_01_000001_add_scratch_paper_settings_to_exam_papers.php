<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('exam_papers', 'scratch_paper_required')) {
            return;
        }

        Schema::table('exam_papers', function (Blueprint $table) {
            $table->boolean('scratch_paper_required')
                ->default(false)
                ->after('type')
                ->comment('是否要求草稿纸拍照留存: 1-是 0-否');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('exam_papers', 'scratch_paper_required')) {
            return;
        }

        Schema::table('exam_papers', function (Blueprint $table) {
            $table->dropColumn('scratch_paper_required');
        });
    }
};
