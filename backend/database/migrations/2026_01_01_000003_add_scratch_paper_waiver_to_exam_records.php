<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_records', function (Blueprint $table) {
            if (!Schema::hasColumn('exam_records', 'scratch_waiver_status')) {
                $table->string('scratch_waiver_status', 20)
                    ->default('none')
                    ->after('status')
                    ->comment('草稿纸免拍处理: none-未申请 pending-待老师处理 approved-老师批准 rejected-已拒绝');
            }
            if (!Schema::hasColumn('exam_records', 'scratch_waiver_reason')) {
                $table->text('scratch_waiver_reason')->nullable()->after('scratch_waiver_status')->comment('学生申请原因');
            }
            if (!Schema::hasColumn('exam_records', 'scratch_waiver_by')) {
                $table->unsignedBigInteger('scratch_waiver_by')->nullable()->after('scratch_waiver_reason')->comment('处理老师ID');
            }
            if (!Schema::hasColumn('exam_records', 'scratch_waiver_at')) {
                $table->timestamp('scratch_waiver_at')->nullable()->after('scratch_waiver_by')->comment('老师处理时间');
            }
            if (!Schema::hasColumn('exam_records', 'scratch_waiver_note')) {
                $table->text('scratch_waiver_note')->nullable()->after('scratch_waiver_at')->comment('老师处理备注');
            }
        });
    }

    public function down(): void
    {
        Schema::table('exam_records', function (Blueprint $table) {
            $table->dropColumn([
                'scratch_waiver_status',
                'scratch_waiver_reason',
                'scratch_waiver_by',
                'scratch_waiver_at',
                'scratch_waiver_note',
            ]);
        });
    }
};
