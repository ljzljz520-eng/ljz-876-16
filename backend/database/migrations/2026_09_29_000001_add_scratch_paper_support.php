<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('exam_papers', 'allow_scratch_paper')) {
            Schema::table('exam_papers', function (Blueprint $table) {
                $table->boolean('allow_scratch_paper')->default(false)->after('type')
                    ->comment('是否允许使用纸质草稿');
            });
        }

        if (!Schema::hasColumn('exam_records', 'scratch_exception_status')) {
            // 扩展 status 枚举（原生 SQL，避免依赖 doctrine/dbal）
            DB::statement("ALTER TABLE exam_records MODIFY COLUMN status
                ENUM('in_progress','submitted','graded','scratch_pending')
                DEFAULT 'in_progress' COMMENT '状态'");

            Schema::table('exam_records', function (Blueprint $table) {
                $table->enum('scratch_exception_status', ['none', 'pending', 'approved', 'rejected'])
                    ->default('none')->after('status')->comment('草稿漏拍异常状态');
                $table->text('scratch_exception_reason')->nullable()->after('scratch_exception_status')
                    ->comment('学生申请异常处理原因');
                $table->unsignedBigInteger('scratch_exception_handled_by')->nullable()
                    ->after('scratch_exception_reason')->comment('异常处理教师ID');
                $table->timestamp('scratch_exception_handled_at')->nullable()
                    ->after('scratch_exception_handled_by')->comment('异常处理时间');
                $table->text('scratch_exception_remark')->nullable()
                    ->after('scratch_exception_handled_at')->comment('教师处理备注');
                $table->index('scratch_exception_status', 'idx_scratch_exception');
            });
        }

        if (!Schema::hasColumn('exam_record_answers', 'answered_at')) {
            Schema::table('exam_record_answers', function (Blueprint $table) {
                $table->timestamp('answered_at')->nullable()->after('answer')
                    ->comment('作答时间(与草稿照片时间对应)');
            });
        }

        if (!Schema::hasTable('exam_scratch_photos')) {
            Schema::create('exam_scratch_photos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('exam_record_id')->comment('考试记录ID');
                $table->unsignedBigInteger('user_id')->comment('考生ID');
                $table->enum('phase', ['before_start', 'before_submit'])
                    ->comment('拍照阶段: before_start-开考前空白页 before_submit-交卷前最终页');
                $table->string('photo_path')->comment('照片存储路径');
                $table->timestamp('taken_at')->comment('拍照时间(客户端)');
                $table->timestamp('server_time')->useCurrent()->comment('服务器接收时间');
                $table->timestamps();
                $table->index('exam_record_id');
                $table->index('user_id');
                $table->index('phase');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_scratch_photos');
        if (Schema::hasColumn('exam_record_answers', 'answered_at')) {
            Schema::table('exam_record_answers', function (Blueprint $table) {
                $table->dropColumn('answered_at');
            });
        }
        if (Schema::hasColumn('exam_records', 'scratch_exception_status')) {
            Schema::table('exam_records', function (Blueprint $table) {
                $table->dropIndex('idx_scratch_exception');
                $table->dropColumn([
                    'scratch_exception_status',
                    'scratch_exception_reason',
                    'scratch_exception_handled_by',
                    'scratch_exception_handled_at',
                    'scratch_exception_remark',
                ]);
            });
        }
        if (Schema::hasColumn('exam_papers', 'allow_scratch_paper')) {
            Schema::table('exam_papers', function (Blueprint $table) {
                $table->dropColumn('allow_scratch_paper');
            });
        }
    }
};
