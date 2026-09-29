-- 草稿纸拍照留存功能 - 增量迁移（适用于已初始化的数据库）
-- 用法: mysql -h <host> -uroot -p exam_system < scripts/db/2026_09_scratch_paper.sql

SET NAMES utf8mb4;

-- 1. 试卷增加"允许纸质草稿"开关
ALTER TABLE exam_papers
  ADD COLUMN allow_scratch_paper TINYINT(1) DEFAULT 0
  COMMENT '是否允许使用纸质草稿: 1-允许 0-不允许' AFTER type;

-- 2. 考试记录增加草稿漏拍异常处理字段
ALTER TABLE exam_records
  MODIFY COLUMN status ENUM('in_progress', 'submitted', 'graded', 'scratch_pending')
  DEFAULT 'in_progress' COMMENT '状态',
  ADD COLUMN scratch_exception_status ENUM('none', 'pending', 'approved', 'rejected')
  DEFAULT 'none' COMMENT '草稿漏拍异常状态' AFTER status,
  ADD COLUMN scratch_exception_reason TEXT NULL
  COMMENT '学生申请异常处理原因' AFTER scratch_exception_status,
  ADD COLUMN scratch_exception_handled_by BIGINT UNSIGNED NULL
  COMMENT '异常处理教师ID' AFTER scratch_exception_reason,
  ADD COLUMN scratch_exception_handled_at TIMESTAMP NULL
  COMMENT '异常处理时间' AFTER scratch_exception_handled_by,
  ADD COLUMN scratch_exception_remark TEXT NULL
  COMMENT '教师处理备注' AFTER scratch_exception_handled_at,
  ADD INDEX idx_scratch_exception (scratch_exception_status);

-- 3. 答案增加作答时间，用于监考回放时与草稿照片时间对应
ALTER TABLE exam_record_answers
  ADD COLUMN answered_at TIMESTAMP NULL
  COMMENT '作答时间(用于与草稿照片时间对应)' AFTER answer;

-- 4. 草稿纸照片表
CREATE TABLE IF NOT EXISTS exam_scratch_photos (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  exam_record_id BIGINT UNSIGNED NOT NULL COMMENT '考试记录ID',
  user_id BIGINT UNSIGNED NOT NULL COMMENT '考生ID',
  phase ENUM('before_start', 'before_submit') NOT NULL COMMENT '拍照阶段',
  photo_path VARCHAR(255) NOT NULL COMMENT '照片存储路径',
  taken_at TIMESTAMP NOT NULL COMMENT '拍照时间(客户端)',
  server_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '服务器接收时间',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_exam_record_id (exam_record_id),
  INDEX idx_user_id (user_id),
  INDEX idx_phase (phase)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='草稿纸拍照留存表';
