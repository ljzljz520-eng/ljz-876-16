# 在线考试题库系统（876）

## 项目类型
- 全栈 Web 项目（`frontend` + `backend`）

## 项目简介
本项目是一个基于 Vue 3 + Laravel 12 的在线考试与题库管理系统，支持多角色登录、题库管理、试卷管理、在线考试与成绩统计。

## 技术栈
### 前端
- Vue 3
- Vite
- Pinia
- Vue Router
- Axios
- TailwindCSS

### 后端
- Laravel 12（PHP 8.2）
- Laravel Sanctum（Token 鉴权）
- MySQL 8.0

### 运行方式
- Docker Compose（推荐，当前项目默认方式）

## 目录结构
```text
876/
├── docker-compose.yml
├── README.md
├── frontend/
│   ├── Dockerfile
│   ├── nginx.conf
│   ├── package.json
│   └── src/
├── backend/
│   ├── Dockerfile
│   ├── composer.json
│   ├── app/
│   └── routes/
├── docs/
│   ├── ARCHITECTURE.md
│   └── Database.sql
├── scripts/
└── evidence/
```

说明：`node_modules/`、`vendor/` 等依赖目录由 Docker 构建时自动安装，不需要打包提交。

## 启动与重建
在仓库根目录执行：

```bash
docker compose down
docker compose up -d --build
docker compose ps
```

## 服务地址
| 服务 | 地址 | 说明 |
|---|---|---|
| 前端 | http://localhost:8080 | 用户界面 |
| 后端 API | http://localhost:9000/api | Laravel API |
| MySQL | localhost:3307 | 数据库端口映射 |

## 测试账号
| 角色 | 邮箱 | 密码 |
|------|-------|----------|
| Admin | admin@example.com | password |
| Teacher | teacher@example.com | password |
| Student | student1@example.com | password |

> 登录页已移除快捷测试账号模块，请手动输入账号密码。

## README 与测试账号清单同步（必跑）
在截图前、提交前执行以下命令：

```bash
node scripts/sync-readme-test-credentials.mjs --manifest qa/.runtime/test-credentials.current.json --readme README.md
node scripts/verify-readme-test-credentials.mjs --manifest qa/.runtime/test-credentials.current.json --readme README.md
```

阻断规则：任一命令失败都应视为 `README_TEST_CREDENTIALS_MISMATCH`，不得继续提交流程。

## 核心功能
1. 用户认证：注册、登录、退出。
2. 题库管理：题目增删改查、分类管理。
3. 试卷管理：试卷创建、编辑、题目关联。
4. 在线考试：开始考试、提交答卷、自动评分。
5. 成绩统计：个人成绩与管理端统计数据。
6. 草稿纸拍照留存：数学/会计/编程等允许纸质草稿的笔试，开考前拍空白页、交卷前拍最终页，漏拍拦截交卷，支持交老师处理与监考回放。

## 草稿纸拍照留存功能
适用场景：数学、会计、编程等允许学生使用纸质草稿纸的笔试。

- 试卷开关：教师在「试卷管理」创建/编辑试卷时勾选「允许使用纸质草稿纸（拍照留存）」。
- 开考前门禁：学生开考后、答题前必须先拍摄**空白草稿页**，未拍不能进入答题（计时从拍完后开始）。
- 交卷前拍照：点「提交答卷」时先引导拍摄**草稿最终页**；两张照片缺一不可，否则后端拦截、无法交卷。
- 漏拍处理：学生无法拍照时可填写原因「交给监考老师处理」，答卷暂挂（`scratch_pending`），不计分；
  教师在「草稿监考 → 异常处理」中**放行**（学生可正常交卷计分）或**驳回**（学生补拍后重新交卷）。
- 监考回放：教师可查看某场考试的草稿照片，以及照片拍摄时间与每题作答时间合并的时间线，便于核对。
- 照片经鉴权接口访问（接口同时返回 base64 data URI 供页面直接显示），保存在后端 `storage/app/private/scratch-photos/`。

### 数据库升级（已存在的库）
全新 `docker compose up` 会自动使用最新内嵌表结构。已初始化过的旧库需执行增量脚本：

```bash
# 方式一：直接执行 SQL
mysql -h 127.0.0.1 -P 3307 -uroot -proot exam_system < scripts/db/2026_09_scratch_paper.sql

# 方式二：在 backend 容器内执行 Laravel 迁移
docker compose exec backend php artisan migrate --force
```

涉及表：`exam_papers` 新增 `allow_scratch_paper`；`exam_records` 新增草稿异常处理字段及 `scratch_pending` 状态；
`exam_record_answers` 新增 `answered_at`；新增 `exam_scratch_photos` 照片表。


## 角色权限
| 角色 | 可访问模块 |
|---|---|
| Student | 在线考试、我的成绩 |
| Teacher | 在线考试、我的成绩、题库管理、试卷管理 |
| Admin | 全部功能（含数据统计） |

## 人工验证步骤（建议）
1. 打开登录页：`http://localhost:8080/login`。
2. 使用测试账号手动登录，确认菜单与角色权限一致。
3. 进入题库管理，验证新增/编辑/删除流程。
4. 进入试卷管理，验证题目关联与试卷删除流程。
5. 学生账号完成一次在线考试并查看成绩。
6. Admin 查看统计页数据。
7. API 冒烟：

```bash
docker compose exec backend sh -lc "curl -s -o /tmp/unauth.txt -w '%{http_code}\n' http://localhost:8080/api/exams"
docker compose exec backend sh -lc "curl -s -X POST http://localhost:8080/api/auth/login -H 'Content-Type: application/json' -d '{\"email\":\"admin@example.com\",\"password\":\"password\"}'"
```

预期：未登录访问受保护接口返回 `401`；登录接口返回包含 `token` 的 JSON。

## 安全与质量说明
- 密码为哈希存储（bcrypt）。
- API 使用 Sanctum Token 鉴权。
- 接口包含输入校验与错误处理。
- CORS 与基础限流已配置。

## 数据库说明
当前初始化后包含 10 张核心表（含用户、题目、试卷、考试记录、答案记录等）。

详见：
- `docs/Database.sql`
- `docker-compose.yml` 中 `db-init` 初始化段

## 证据目录
测试与质检证据统一放在 `evidence/`（含 `evidence/run-slot*/`）目录。

---
如需进行质检修复闭环，请配合 `qa/qc-feedback-inbox.md`、`qa/qc-fix-send-template.md`、`qa/qc-fix-loop-template.md` 使用。

