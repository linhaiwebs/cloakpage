# トレードアイ - 株式分析ランディングページ

これは Nginx + PHP-FPM + Slim Framework をベースにした株式分析ランディングページプロジェクトで、フロントエンドルーティングシステムとバックエンドAPIを含んでいます。

## 项目结构

```
├── backend/                 # 后端API (Slim Framework + PHP-FPM)
│   ├── src/
│   │   ├── Controllers/     # 控制器
│   │   └── Application/     # 应用程序处理器
│   ├── config/             # 配置文件
│   ├── public/             # 公共入口
│   └── composer.json       # PHP依赖管理
├── frontend/               # 前端项目 (PHP-FPM)
│   ├── index/              # 主站点
│   ├── home/               # 备用站点
│   └── index.php           # 前端路由文件
├── docker-compose.yml      # Docker编排文件
└── nginx.conf             # Nginx配置 (处理所有路由和静态文件)
```

## 功能特性

### 后端 API
- **股票信息API**: `/app/maike/api/stock/getinfo` - 获取股票数据
- **页面追踪API**: `/app/maike/api/info/page_track` - 记录用户行为
- **页面访问追踪**: `/app/maike/api/info/uppage_track` - 记录页面访问
- **错误日志API**: `/app/maike/api/info/logError` - 记录前端错误
- **跳转页面**: `/jpint` - LINE引导页面

### 前端路由系统
- 支持多站点管理（index 和 home）
- 支持URL参数路由（?site=home）
- 支持路径路由（/home/）
- Nginx 处理静态文件和路由
- PHP-FPM 处理动态内容

## 安装和运行

### 方式一：使用 Docker（推荐）

1. 克隆项目并进入目录
2. 启动服务：
```bash
docker-compose up -d
```

3. 访问地址：
   - 前端：http://localhost
   - 备用端口：http://localhost:8000
   - 后端API：通过 Nginx 代理访问

### 方式二：本地开发（不推荐）
本项目现在使用 Nginx + PHP-FPM 架构，推荐使用 Docker 进行开发和部署。

## 路由规则

### 前端路由
- `http://domain.com` → index 项目
- `http://domain.com?site=home` → home 项目
- `http://domain.com/home/` → home 项目

### API路由
所有 `/app/maike/api/` 开头的请求会被 Nginx 转发到后端 PHP-FPM 服务。

## 配置说明

### 环境变量 (backend/.env)
```
APP_ENV=development
APP_DEBUG=true
DB_HOST=localhost
DB_NAME=stock_analysis
DB_USER=root
DB_PASS=
LOG_LEVEL=debug
```

### 前端项目差异化
在 `frontend/index.php` 中可以为不同项目设置不同的配置：
- 修改页面标题
- 调整API端点
- 添加项目特定的样式或脚本

## 日志和监控

- 后端日志：`backend/logs/app.log`
- 追踪日志：`backend/logs/tracking.log`
- Nginx 访问日志：容器内 `/var/log/nginx/access.log`
- Nginx 错误日志：容器内 `/var/log/nginx/error.log`

## 开发说明

### 添加新的API端点
1. 在 `backend/src/Controllers/` 中创建控制器
2. 在 `backend/config/routes.php` 中注册路由

### 添加新的前端项目
1. 在 `frontend/` 下创建新目录
2. 在 `frontend/index.php` 中添加路由逻辑
3. 如需要，在 `nginx.conf` 中添加特殊路由规则

## 生产环境部署

1. 设置环境变量 `APP_ENV=production`
2. 配置真实的数据库连接
3. 在 `nginx.conf` 中配置 SSL 证书
4. 设置适当的文件权限
5. 优化 PHP-FPM 和 Nginx 配置

## 安全注意事项

- Nginx 已实现目录遍历攻击防护
- CORS 头部已在 Nginx 中配置
- 输入验证和错误处理已实现
- 安全头部已在 Nginx 中配置
- 建议在生产环境中添加更多安全措施

## 架构优势

- **性能提升**：Nginx 处理静态文件，PHP-FPM 处理动态内容
- **可扩展性**：可以独立扩展 Nginx 和 PHP-FPM 服务
- **资源效率**：更好的内存和 CPU 使用率
- **生产就绪**：符合现代 Web 应用部署最佳实践