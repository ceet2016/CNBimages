# CNB + EdgeOne/ESA + PHP 自建图床（开源完整版）

一套**零第三方依赖**的 PHP 图床，自带 Web 管理界面与鉴权，配合：

- **CNB（cnb.cool）**：托管代码并通过 Docker 镜像部署为云原生应用，**图片文件直接存在 CNB 应用的磁盘上**；
- **腾讯云 EdgeOne / 阿里云 ESA**：边缘加速，把 CNB 应用域名作为源站，图片就近缓存；
- **PicGo**：写作端一键上传（通过每用户 API Token 对接）。

## 功能清单

- 🔐 **账号体系**：注册 / 登录 / 退出（首个用户自动为管理员）；会话 Cookie 安全加固
- 🪙 **API Token**：每用户可创建多个 Token 用于 PicGo / 脚本，仅保存哈希，可随时吊销
- ⬆️ **上传**：Web 拖拽上传 + PicGo 上传；自动校验真实图片类型与大小
- ⬇️ **下载 / 删除**：Web 端一键下载、删除（仅能操作自己的资源，管理员可管理全部）
- 🖼️ **图片转换**：基于 GD 的按需缩放 / 格式转换（webp/avif/jpg/png）/ 压缩，带磁盘缓存
- 📊 **配额与统计**：每用户存储配额、管理员全局统计与用户管理
- 🛡️ **安全**：CSRF 防护、密码 bcrypt 哈希、MIME+扩展名双重校验、路径遍历防护、速率限制、隐藏 `.env` 与数据目录、建议 HTTPS

## 目录结构

```
cnb-picgo-image-bed/
├── .cnb.yml                 # CNB 云原生构建流水线（构建并推送镜像）
├── Dockerfile               # PHP 8.2 FPM + Nginx（含 GD / PDO_SQLite）
├── docker-compose.yml       # 本地开发编排
├── .env.example             # 配置样例
├── README.md
├── LICENSE                  # MIT
├── public/
│   ├── index.php            # 前端控制器 / 路由分发
│   ├── nginx.conf           # 容器内 Nginx（缓存 / 隐藏敏感目录）
│   ├── assets/              # CSS / JS（前端交互）
│   └── uploads/             # 原图存储（运行时）
├── docker/
│   ├── entrypoint.sh        # 启动脚本（php-fpm + nginx）
│   └── zz-app.conf          # php-fpm 配置
├── src/
│   ├── bootstrap.php        # 自动加载 + 配置初始化
│   ├── Config.php           # 配置（.env 与环境变量，环境变量优先）
│   ├── Database.php         # SQLite 初始化与建表（首个用户为管理员）
│   ├── Auth.php             # 会话 + API Token 鉴权
│   ├── Csrf.php             # CSRF 防护
│   ├── View.php             # 极简模板渲染
│   ├── Response.php         # JSON / 重定向 / HTML 响应
│   ├── Storage.php          # 文件存储 + 配额 + 元数据
│   ├── ImageProcessor.php   # GD 图片处理（缩放/转换/压缩）
│   ├── RateLimit.php        # 速率限制
│   ├── Controllers/         # Auth / Image / Admin 控制器
│   └── Views/               # 登录 / 仪表盘 / Token / 管理 模板
└── tests/
    └── upload_test.php      # 命令行上传测试
```

## 快速开始（本地）

```bash
cp .env.example .env
docker compose up --build
# 浏览器打开 http://localhost:8080
# 默认管理员：admin / admin12345（请第一时间在「管理后台」改密码）
```

命令行验证上传：

```bash
# 先在「API Token」页面创建一个 Token，然后用它上传
php tests/upload_test.php http://localhost:8080/api/upload <你的TOKEN>
```

## 部署到 CNB

1. 推送代码到 CNB 仓库，`.cnb.yml` 会自动构建并推送镜像；
2. 在 CNB 控制台「云原生应用」使用该镜像创建服务，并**挂载持久化存储**到 `/var/www/uploads` 与 `/var/www/data`（保证图片与数据库重启不丢）；
3. 在部署配置注入环境变量：`APP_ENV=production`、`APP_DEBUG=false`、`APP_SECURE_COOKIE=true`、
   `PUBLIC_BASE_URL=https://你的加速域名`、`ALLOW_REGISTER=false`（按需）、`MAX_FILE_SIZE`、`THUMB_MAX` 等；
4. CNB 分配访问域名，记下它用于 EO/ESA 源站。

> 图片持久化建议：CNB 应用若无持久卷，重启会清空本地磁盘。生产环境请挂载持久化存储，或后续扩展 `src/Storage.php` 接入对象存储（仓库内留有 S3 思路可参考）。

## 配置 EdgeOne / ESA 加速

- **EdgeOne**：站点 → 域名管理 → 添加域名，源站选「**IP/域名**」并填 CNB 分配的域名；
  在规则引擎对 `/i/`、`/t/` 设置较长缓存（如 `Cache-Control: max-age=2592000`），其余路径不缓存。
- **ESA**：添加加速域名，源站填 CNB 应用域名，开启缓存；同样对图片路径长缓存。
- 把加速域名填到 `PUBLIC_BASE_URL`，所有返回的图片外链即为加速域名。
- 建议开启「Referer 防盗链 / 鉴权」防止被刷流量。

## 配置 PicGo

安装插件 `picgo-plugin-web-uploader`（或 `custom-api-uploader`），在「API Token」页创建 Token：

| 配置项 | 值 |
| --- | --- |
| API 地址 | `https://你的加速域名/api/upload` |
| POST 参数名 | `file` |
| JSON 路径 | `result`（custom-api-uploader 用 `data.url`） |
| 自定义请求头 | `{"Authorization":"Bearer 你的TOKEN"}` |

## 安全建议

1. 首次登录后立即修改管理员密码；
2. 生产环境 `APP_SECURE_COOKIE=true` 且全程 HTTPS（EO/ESA 默认提供证书）；
3. 关闭 `ALLOW_REGISTER`（默认关闭），用户由管理员后台创建；
4. `MAX_FILE_SIZE` 与 nginx `client_max_body_size` 保持一致；
5. EO/ESA 侧配置防盗链/鉴权与速率限制；
6. 定期备份 `/var/www/data/app.db` 与 `/var/www/uploads`。

详见 [docs/BLOG.md](docs/BLOG.md)。
