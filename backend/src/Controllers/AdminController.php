<?php
declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;

class AdminController
{
    private LoggerInterface $logger;
    private string $dataDir;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->dataDir = __DIR__ . '/../../data';
        
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
        
        // 确保 session 已启动
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function login(Request $request, Response $response): Response
    {
        // 如果已经登录，重定向到仪表板
        if ($this->isAuthenticated()) {
            return $response->withHeader('Location', '/admin/dashboard')->withStatus(302);
        }
        
        $html = $this->renderLogin();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function handleLogin(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        // 验证用户名和密码
        if ($username === 'adsadmin' && $password === 'Mm123567..') {
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['admin_login_time'] = date('Y-m-d H:i:s');
            
            $this->logger->info('Admin login successful', [
                'username' => $username,
                'ip' => $this->getClientIp($request),
                'user_agent' => $request->getHeaderLine('User-Agent')
            ]);
            
            return $response->withHeader('Location', '/admin/dashboard')->withStatus(302);
        } else {
            $this->logger->warning('Admin login failed', [
                'username' => $username,
                'ip' => $this->getClientIp($request),
                'user_agent' => $request->getHeaderLine('User-Agent')
            ]);
            
            // 重定向回登录页面，带错误参数
            return $response->withHeader('Location', '/admin?error=1')->withStatus(302);
        }
    }

    public function dashboard(Request $request, Response $response): Response
    {
        if (!$this->isAuthenticated()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        
        $html = $this->renderDashboard();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function customerServices(Request $request, Response $response): Response
    {
        if (!$this->isAuthenticated()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        
        if ($request->getMethod() === 'POST') {
            return $this->handleCustomerServicePost($request, $response);
        }
        
        $html = $this->renderCustomerServices();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function trackingData(Request $request, Response $response): Response
    {
        if (!$this->isAuthenticated()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        
        $html = $this->renderTrackingData();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function assignments(Request $request, Response $response): Response
    {
        if (!$this->isAuthenticated()) {
            return $response->withHeader('Location', '/admin')->withStatus(302);
        }
        
        $html = $this->renderAssignments();
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }

    public function logout(Request $request, Response $response): Response
    {
        $this->logger->info('Admin logout', [
            'username' => $_SESSION['admin_username'] ?? 'unknown',
            'ip' => $this->getClientIp($request)
        ]);
        
        // 清除会话
        $_SESSION = [];
        session_destroy();
        
        // 重定向到登录页面
        return $response->withHeader('Location', '/admin')->withStatus(302);
    }

    public function apiCustomerServices(Request $request, Response $response): Response
    {
        if (!$this->isAuthenticated()) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        try {
            if ($request->getMethod() === 'POST') {
                return $this->createCustomerService($request, $response);
            } elseif ($request->getMethod() === 'PUT') {
                return $this->updateCustomerService($request, $response);
            } elseif ($request->getMethod() === 'DELETE') {
                return $this->deleteCustomerService($request, $response);
            }
            
            $services = $this->loadCustomerServices();
            $response->getBody()->write(json_encode($services));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('API error in customer services', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    public function apiTrackingData(Request $request, Response $response): Response
    {
        if (!$this->isAuthenticated()) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        $page = (int)($request->getQueryParams()['page'] ?? 1);
        $limit = (int)($request->getQueryParams()['limit'] ?? 50);
        
        $data = $this->getTrackingData($page, $limit);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function apiAssignments(Request $request, Response $response): Response
    {
        if (!$this->isAuthenticated()) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(401);
        }
        
        $page = (int)($request->getQueryParams()['page'] ?? 1);
        $limit = (int)($request->getQueryParams()['limit'] ?? 50);
        
        $data = $this->getAssignments($page, $limit);
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function isAuthenticated(): bool
    {
        return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    }

    private function renderLogin(bool $hasError = false): string
    {
        $errorMessage = '';
        if (isset($_GET['error']) && $_GET['error'] == '1') {
            $errorMessage = '<div style="background: #fee; color: #c33; padding: 10px; border-radius: 4px; margin-bottom: 15px; border: 1px solid #fcc;">用户名或密码错误</div>';
        }
        
        return '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台登录</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .login-header p {
            color: #666;
            font-size: 14px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        .login-btn {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .login-btn:hover {
            transform: translateY(-2px);
        }
        .login-btn:active {
            transform: translateY(0);
        }
        .footer {
            text-align: center;
            margin-top: 2rem;
            color: #666;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 管理后台</h1>
            <p>请输入您的登录凭据</p>
        </div>
        
        ' . $errorMessage . '
        
        <form method="POST" action="/admin/login">
            <div class="form-group">
                <label for="username">用户名</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="login-btn">登录</button>
        </form>
        
        <div class="footer">
            © 2025 股票分析系统
        </div>
    </div>
</body>
</html>';
    }

    private function renderDashboard(): string
    {
        $username = $_SESSION['admin_username'] ?? 'unknown';
        $loginTime = $_SESSION['admin_login_time'] ?? 'unknown';
        $stats = $this->getDashboardStats();
        
        return '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - 股票分析系统</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 1rem 2rem; }
        .nav { background: #34495e; padding: 0 2rem; }
        .nav ul { list-style: none; display: flex; }
        .nav li { margin-right: 2rem; }
        .nav a { color: white; text-decoration: none; padding: 1rem 0; display: block; }
        .nav a:hover, .nav a.active { background: #2c3e50; padding: 1rem; margin: 0 -1rem; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .stat-number { font-size: 2rem; font-weight: bold; color: #3498db; }
        .stat-label { color: #7f8c8d; margin-top: 0.5rem; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; }
        .card-header { background: #ecf0f1; padding: 1rem 1.5rem; border-bottom: 1px solid #bdc3c7; }
        .card-body { padding: 1.5rem; }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>股票分析系统 - 管理后台</h1>
            <div style="color: #ecf0f1; font-size: 14px;">
                欢迎, ' . htmlspecialchars($username) . ' | 登录时间: ' . htmlspecialchars($loginTime) . ' | 
                <a href="/admin/logout" style="color: #e74c3c; text-decoration: none;">退出登录</a>
            </div>
        </div>
    </div>
    <nav class="nav">
        <ul>
            <li><a href="/admin/dashboard" class="active">仪表板</a></li>
            <li><a href="/admin/customer-services">客服管理</a></li>
            <li><a href="/admin/tracking">追踪数据</a></li>
            <li><a href="/admin/assignments">分配记录</a></li>
        </ul>
    </nav>
    <div class="container">
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number">' . $stats['total_assignments'] . '</div>
                <div class="stat-label">总分配数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $stats['successful_launches'] . '</div>
                <div class="stat-label">成功启动数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $stats['total_tracking'] . '</div>
                <div class="stat-label">追踪记录数</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . $stats['active_services'] . '</div>
                <div class="stat-label">活跃客服数</div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>系统概览</h2>
            </div>
            <div class="card-body">
                <p>欢迎使用股票分析系统管理后台。您可以通过左侧导航菜单管理各个模块。</p>
                <ul style="margin-top: 1rem; padding-left: 2rem;">
                    <li><strong>客服管理</strong>：添加、编辑和管理客服账号信息</li>
                    <li><strong>追踪数据</strong>：查看用户行为追踪数据和错误日志</li>
                    <li><strong>分配记录</strong>：查看客服分配记录和用户转化情况</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    private function renderCustomerServices(): string
    {
        $username = $_SESSION['admin_username'] ?? 'unknown';
        $loginTime = $_SESSION['admin_login_time'] ?? 'unknown';
        
        return '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客服管理 - 管理后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 1rem 2rem; }
        .nav { background: #34495e; padding: 0 2rem; }
        .nav ul { list-style: none; display: flex; }
        .nav li { margin-right: 2rem; }
        .nav a { color: white; text-decoration: none; padding: 1rem 0; display: block; }
        .nav a:hover { background: #2c3e50; padding: 1rem; margin: 0 -1rem; }
        .nav a.active { background: #2c3e50; padding: 1rem; margin: 0 -1rem; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 2rem; }
        .card-header { background: #ecf0f1; padding: 1rem 1.5rem; border-bottom: 1px solid #bdc3c7; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 1.5rem; }
        .btn { background: #3498db; color: white; border: none; padding: 0.5rem 1rem; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        .form-group input, .form-group select { width: 100%; padding: 0.5rem; border: 1px solid #bdc3c7; border-radius: 4px; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .status-active { color: #27ae60; font-weight: bold; }
        .status-inactive { color: #e74c3c; font-weight: bold; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 5% auto; padding: 2rem; width: 80%; max-width: 500px; border-radius: 8px; }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>股票分析系统 - 管理后台</h1>
            <div style="color: #ecf0f1; font-size: 14px;">
                欢迎, ' . htmlspecialchars($username) . ' | 登录时间: ' . htmlspecialchars($loginTime) . ' | 
                <a href="/admin/logout" style="color: #e74c3c; text-decoration: none;">退出登录</a>
            </div>
        </div>
    </div>
    <nav class="nav">
        <ul>
            <li><a href="/admin/dashboard">仪表板</a></li>
            <li><a href="/admin/customer-services" class="active">客服管理</a></li>
            <li><a href="/admin/tracking">追踪数据</a></li>
            <li><a href="/admin/assignments">分配记录</a></li>
        </ul>
    </nav>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>客服管理</h2>
                <button class="btn btn-success" onclick="openAddModal()">添加客服</button>
            </div>
            <div class="card-body">
                <table class="table" id="servicesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>名称</th>
                            <th>URL</th>
                            <th>备用URL</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 数据将通过JavaScript加载 -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 添加/编辑模态框 -->
    <div id="serviceModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            <h3 id="modalTitle">添加客服</h3>
            <form id="serviceForm">
                <input type="hidden" id="serviceId" name="id">
                <div class="form-group">
                    <label for="serviceName">名称</label>
                    <input type="text" id="serviceName" name="name" required>
                </div>
                <div class="form-group">
                    <label for="serviceUrl">URL</label>
                    <input type="url" id="serviceUrl" name="url" required>
                </div>
                <div class="form-group">
                    <label for="fallbackUrl">备用URL</label>
                    <input type="url" id="fallbackUrl" name="fallback_url" required>
                </div>
                <div class="form-group">
                    <label for="serviceStatus">状态</label>
                    <select id="serviceStatus" name="status">
                        <option value="active">活跃</option>
                        <option value="inactive">停用</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-success">保存</button>
                <button type="button" class="btn" onclick="closeModal()">取消</button>
            </form>
        </div>
    </div>

    <script>
        let services = [];

        async function loadServices() {
            try {
                const response = await fetch("/admin/api/customer-services");
                const data = await response.json();
                services = Array.isArray(data) ? data : [];
                renderServicesTable();
            } catch (error) {
                console.error("加载客服数据失败:", error);
                alert("加载客服数据失败: " + error.message);
            }
        }

        function renderServicesTable() {
            const tbody = document.querySelector("#servicesTable tbody");
            if (!tbody) {
                console.error("找不到表格tbody元素");
                return;
            }
            
            if (services.length === 0) {
                tbody.innerHTML = \'<tr><td colspan="7" style="text-align:center;">暂无客服数据</td></tr>\';
                return;
            }
            
            tbody.innerHTML = services.map(service => `
                <tr>
                    <td>${service.id}</td>
                    <td>${service.name}</td>
                    <td><a href="${service.url}" target="_blank">${service.url}</a></td>
                    <td><a href="${service.fallback_url}" target="_blank">${service.fallback_url}</a></td>
                    <td><span class="status-${service.status}">${service.status === "active" ? "活跃" : "停用"}</span></td>
                    <td>${service.created_at}</td>
                    <td>
                        <button class="btn" onclick="editService(\'${service.id}\')">编辑</button>
                        <button class="btn btn-danger" onclick="deleteService(\'${service.id}\')">删除</button>
                    </td>
                </tr>
            `).join("");
        }

        function openAddModal() {
            document.getElementById("modalTitle").textContent = "添加客服";
            document.getElementById("serviceForm").reset();
            document.getElementById("serviceId").value = "";
            document.getElementById("serviceModal").style.display = "block";
        }

        function editService(id) {
            const service = services.find(s => s.id === id);
            if (service) {
                document.getElementById("modalTitle").textContent = "编辑客服";
                document.getElementById("serviceId").value = service.id;
                document.getElementById("serviceName").value = service.name;
                document.getElementById("serviceUrl").value = service.url;
                document.getElementById("fallbackUrl").value = service.fallback_url;
                document.getElementById("serviceStatus").value = service.status;
                document.getElementById("serviceModal").style.display = "block";
            }
        }

        function closeModal() {
            document.getElementById("serviceModal").style.display = "none";
        }

        async function deleteService(id) {
            if (confirm("确定要删除这个客服吗？")) {
                try {
                    const response = await fetch(`/admin/api/customer-services?id=${id}`, { method: "DELETE" });
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    loadServices();
                } catch (error) {
                    console.error("删除失败:", error);
                    alert("删除失败: " + error.message);
                }
            }
        }

        document.getElementById("serviceForm").addEventListener("submit", async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            
            try {
                const method = data.id ? "PUT" : "POST";
                const response = await fetch("/admin/api/customer-services", {
                    method,
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(data)
                });
                
                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
                }
                
                alert(data.id ? "客服更新成功" : "客服添加成功");
                closeModal();
                loadServices();
            } catch (error) {
                console.error("保存失败:", error);
                alert("保存失败: " + error.message);
            }
        });

        // 页面加载时获取数据
        loadServices();
    </script>
</body>
</html>';
    }

    private function renderTrackingData(): string
    {
        $username = $_SESSION['admin_username'] ?? 'unknown';
        $loginTime = $_SESSION['admin_login_time'] ?? 'unknown';
        
        return '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>追踪数据 - 管理后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 1rem 2rem; }
        .nav { background: #34495e; padding: 0 2rem; }
        .nav ul { list-style: none; display: flex; }
        .nav li { margin-right: 2rem; }
        .nav a { color: white; text-decoration: none; padding: 1rem 0; display: block; }
        .nav a:hover { background: #2c3e50; padding: 1rem; margin: 0 -1rem; }
        .nav a.active { background: #2c3e50; padding: 1rem; margin: 0 -1rem; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 2rem; }
        .card-header { background: #ecf0f1; padding: 1rem 1.5rem; border-bottom: 1px solid #bdc3c7; }
        .card-body { padding: 1.5rem; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .pagination { display: flex; justify-content: center; margin-top: 1rem; }
        .pagination button { margin: 0 0.25rem; padding: 0.5rem 1rem; border: 1px solid #bdc3c7; background: white; cursor: pointer; }
        .pagination button.active { background: #3498db; color: white; }
        .pagination button:hover { background: #ecf0f1; }
        .error-log { background: #fff5f5; border-left: 4px solid #e74c3c; }
        .page-track { background: #f0f9ff; border-left: 4px solid #3498db; }
        .uppage-track { background: #f0fff4; border-left: 4px solid #27ae60; }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>股票分析系统 - 管理后台</h1>
            <div style="color: #ecf0f1; font-size: 14px;">
                欢迎, ' . htmlspecialchars($username) . ' | 登录时间: ' . htmlspecialchars($loginTime) . ' | 
                <a href="/admin/logout" style="color: #e74c3c; text-decoration: none;">退出登录</a>
            </div>
        </div>
    </div>
    <nav class="nav">
        <ul>
            <li><a href="/admin/dashboard">仪表板</a></li>
            <li><a href="/admin/customer-services">客服管理</a></li>
            <li><a href="/admin/tracking" class="active">追踪数据</a></li>
            <li><a href="/admin/assignments">分配记录</a></li>
        </ul>
    </nav>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>用户追踪数据</h2>
            </div>
            <div class="card-body">
                <table class="table" id="trackingTable">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>类型</th>
                            <th>URL/消息</th>
                            <th>IP地址</th>
                            <th>用户代理</th>
                            <th>详情</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 数据将通过JavaScript加载 -->
                    </tbody>
                </table>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        const pageSize = 20;

        async function loadTrackingData(page = 1) {
            try {
                const response = await fetch(`/admin/api/tracking?page=${page}&limit=${pageSize}`);
                const data = await response.json();
                renderTrackingTable(data.items);
                renderPagination(data.total, page);
            } catch (error) {
                console.error("加载追踪数据失败:", error);
            }
        }

        function renderTrackingTable(items) {
            const tbody = document.querySelector("#trackingTable tbody");
            tbody.innerHTML = items.map(item => {
                const typeClass = item.type === "error_log" ? "error-log" : 
                                 item.type === "page_track" ? "page-track" : "uppage-track";
                return `
                    <tr class="${typeClass}">
                        <td>${item.timestamp}</td>
                        <td>${getTypeLabel(item.type)}</td>
                        <td>${item.url || item.message || "-"}</td>
                        <td>${item.ip}</td>
                        <td title="${item.user_agent}">${item.user_agent.substring(0, 50)}...</td>
                        <td><button onclick="showDetails(${JSON.stringify(item).replace(/"/g, "&quot;")})">查看</button></td>
                    </tr>
                `;
            }).join("");
        }

        function getTypeLabel(type) {
            const labels = {
                "page_track": "页面追踪",
                "uppage_track": "页面更新",
                "error_log": "错误日志"
            };
            return labels[type] || type;
        }

        function renderPagination(total, current) {
            const totalPages = Math.ceil(total / pageSize);
            const pagination = document.getElementById("pagination");
            
            let html = "";
            for (let i = 1; i <= totalPages; i++) {
                html += `<button class="${i === current ? "active" : ""}" onclick="loadTrackingData(${i})">${i}</button>`;
            }
            pagination.innerHTML = html;
        }

        function showDetails(item) {
            alert(JSON.stringify(item, null, 2));
        }

        // 页面加载时获取数据
        loadTrackingData();
    </script>
</body>
</html>';
    }

    private function renderAssignments(): string
    {
        $username = $_SESSION['admin_username'] ?? 'unknown';
        $loginTime = $_SESSION['admin_login_time'] ?? 'unknown';
        
        return '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分配记录 - 管理后台</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 1rem 2rem; }
        .nav { background: #34495e; padding: 0 2rem; }
        .nav ul { list-style: none; display: flex; }
        .nav li { margin-right: 2rem; }
        .nav a { color: white; text-decoration: none; padding: 1rem 0; display: block; }
        .nav a:hover { background: #2c3e50; padding: 1rem; margin: 0 -1rem; }
        .nav a.active { background: #2c3e50; padding: 1rem; margin: 0 -1rem; }
        .container { max-width: 1200px; margin: 2rem auto; padding: 0 2rem; }
        .card { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); overflow: hidden; margin-bottom: 2rem; }
        .card-header { background: #ecf0f1; padding: 1rem 1.5rem; border-bottom: 1px solid #bdc3c7; }
        .card-body { padding: 1.5rem; }
        .table { width: 100%; border-collapse: collapse; }
        .table th, .table td { padding: 0.75rem; text-align: left; border-bottom: 1px solid #ecf0f1; }
        .table th { background: #f8f9fa; font-weight: bold; }
        .pagination { display: flex; justify-content: center; margin-top: 1rem; }
        .pagination button { margin: 0 0.25rem; padding: 0.5rem 1rem; border: 1px solid #bdc3c7; background: white; cursor: pointer; }
        .pagination button.active { background: #3498db; color: white; }
        .pagination button:hover { background: #ecf0f1; }
        .success { color: #27ae60; font-weight: bold; }
        .failed { color: #e74c3c; font-weight: bold; }
        .pending { color: #f39c12; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>股票分析系统 - 管理后台</h1>
            <div style="color: #ecf0f1; font-size: 14px;">
                欢迎, ' . htmlspecialchars($username) . ' | 登录时间: ' . htmlspecialchars($loginTime) . ' | 
                <a href="/admin/logout" style="color: #e74c3c; text-decoration: none;">退出登录</a>
            </div>
        </div>
    </div>
    <nav class="nav">
        <ul>
            <li><a href="/admin/dashboard">仪表板</a></li>
            <li><a href="/admin/customer-services">客服管理</a></li>
            <li><a href="/admin/tracking">追踪数据</a></li>
            <li><a href="/admin/assignments" class="active">分配记录</a></li>
        </ul>
    </nav>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>客服分配记录</h2>
            </div>
            <div class="card-body">
                <table class="table" id="assignmentsTable">
                    <thead>
                        <tr>
                            <th>记录ID</th>
                            <th>股票代码</th>
                            <th>文本</th>
                            <th>客服名称</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>IP地址</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- 数据将通过JavaScript加载 -->
                    </tbody>
                </table>
                <div class="pagination" id="pagination"></div>
            </div>
        </div>
    </div>

    <script>
        let currentPage = 1;
        const pageSize = 20;

        async function loadAssignments(page = 1) {
            try {
                const response = await fetch(`/admin/api/assignments?page=${page}&limit=${pageSize}`);
                const data = await response.json();
                renderAssignmentsTable(data.items);
                renderPagination(data.total, page);
            } catch (error) {
                console.error("加载分配记录失败:", error);
            }
        }

        function renderAssignmentsTable(items) {
            const tbody = document.querySelector("#assignmentsTable tbody");
            tbody.innerHTML = items.map(item => {
                const status = getStatus(item);
                return `
                    <tr>
                        <td>${item.id}</td>
                        <td>${item.stockcode || "-"}</td>
                        <td title="${item.text}">${(item.text || "").substring(0, 30)}...</td>
                        <td>${item.customer_service_name}</td>
                        <td><span class="${status.class}">${status.label}</span></td>
                        <td>${item.created_at}</td>
                        <td>${item.ip}</td>
                    </tr>
                `;
            }).join("");
        }

        function getStatus(item) {
            if (item.launch_success) {
                return { class: "success", label: "成功启动" };
            } else if (item.page_leave_at) {
                return { class: "failed", label: "启动失败" };
            } else {
                return { class: "pending", label: "等待中" };
            }
        }

        function renderPagination(total, current) {
            const totalPages = Math.ceil(total / pageSize);
            const pagination = document.getElementById("pagination");
            
            let html = "";
            for (let i = 1; i <= totalPages; i++) {
                html += `<button class="${i === current ? "active" : ""}" onclick="loadAssignments(${i})">${i}</button>`;
            }
            pagination.innerHTML = html;
        }

        // 页面加载时获取数据
        loadAssignments();
    </script>
</body>
</html>';
    }

    private function handleCustomerServicePost(Request $request, Response $response): Response
    {
        $data = json_decode($request->getBody()->getContents(), true);
        
        if (isset($data['action']) && $data['action'] === 'delete') {
            return $this->deleteCustomerService($request, $response);
        }
        
        return $this->createCustomerService($request, $response);
    }

    private function createCustomerService(Request $request, Response $response): Response
    {
        try {
            $data = json_decode($request->getBody()->getContents(), true);
            
            if (!$data) {
                $response->getBody()->write(json_encode(['error' => '无效的JSON数据']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            // 验证必填字段
            if (empty($data['name']) || empty($data['url']) || empty($data['fallback_url'])) {
                $response->getBody()->write(json_encode(['error' => '名称、URL和备用URL为必填项']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $services = $this->loadCustomerServices();
            
            $newService = [
                'id' => $data['id'] ?? 'cs_' . uniqid(),
                'name' => $data['name'],
                'url' => $data['url'],
                'fallback_url' => $data['fallback_url'],
                'status' => $data['status'] ?? 'active',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (isset($data['id']) && !empty($data['id'])) {
                // 更新现有服务
                for ($i = 0; $i < count($services); $i++) {
                    if ($services[$i]['id'] === $data['id']) {
                        $services[$i] = array_merge($services[$i], $newService);
                        break;
                    }
                }
            } else {
                // 添加新服务
                $services[] = $newService;
            }
            
            $this->saveCustomerServices($services);
            
            $this->logger->info('Customer service created/updated', ['service' => $newService]);
            
            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error creating customer service', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    private function updateCustomerService(Request $request, Response $response): Response
    {
        return $this->createCustomerService($request, $response);
    }

    private function deleteCustomerService(Request $request, Response $response): Response
    {
        try {
            $id = $request->getQueryParams()['id'] ?? '';
            
            if (empty($id)) {
                $response->getBody()->write(json_encode(['error' => '缺少客服ID']));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }
            
            $services = $this->loadCustomerServices();
            $services = array_filter($services, function($service) use ($id) {
                return $service['id'] !== $id;
            });
            
            $this->saveCustomerServices(array_values($services));
            
            $this->logger->info('Customer service deleted', ['id' => $id]);
            
            $response->getBody()->write(json_encode(['status' => 'success']));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            $this->logger->error('Error deleting customer service', ['error' => $e->getMessage()]);
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
        }
    }

    private function getDashboardStats(): array
    {
        $assignments = $this->getAssignments(1, 1000);
        $tracking = $this->getTrackingData(1, 1000);
        $services = $this->loadCustomerServices();
        
        $successfulLaunches = 0;
        foreach ($assignments['items'] as $assignment) {
            if ($assignment['launch_success'] ?? false) {
                $successfulLaunches++;
            }
        }
        
        $activeServices = count(array_filter($services, function($service) {
            return $service['status'] === 'active';
        }));
        
        // 添加一些测试数据到分配记录（仅在没有数据时）
        if ($assignments['total'] === 0) {
            $this->createTestAssignments();
            $assignments = $this->getAssignments(1, 1000);
            foreach ($assignments['items'] as $assignment) {
                if ($assignment['launch_success'] ?? false) {
                    $successfulLaunches++;
                }
            }
        }
        
        return [
            'total_assignments' => $assignments['total'],
            'successful_launches' => $successfulLaunches,
            'total_tracking' => $tracking['total'],
            'active_services' => $activeServices
        ];
    }

    private function getTrackingData(int $page, int $limit): array
    {
        $file = __DIR__ . '/../../logs/tracking.log';
        if (!file_exists($file)) {
            return ['items' => [], 'total' => 0];
        }
        
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $items = [];
        
        foreach (array_reverse($lines) as $line) {
            if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) \[([^\]]+)\] (.+)$/', $line, $matches)) {
                $data = json_decode($matches[3], true);
                if ($data) {
                    $items[] = array_merge($data, [
                        'timestamp' => $matches[1],
                        'type' => $matches[2]
                    ]);
                }
            }
        }
        
        $total = count($items);
        $offset = ($page - 1) * $limit;
        $items = array_slice($items, $offset, $limit);
        
        return ['items' => $items, 'total' => $total];
    }

    private function getAssignments(int $page, int $limit): array
    {
        $file = $this->dataDir . '/assignments.jsonl';
        if (!file_exists($file)) {
            return ['items' => [], 'total' => 0];
        }
        
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $items = [];
        
        foreach (array_reverse($lines) as $line) {
            $data = json_decode($line, true);
            if ($data) {
                $items[] = $data;
            }
        }
        
        $total = count($items);
        $offset = ($page - 1) * $limit;
        $items = array_slice($items, $offset, $limit);
        
        return ['items' => $items, 'total' => $total];
    }

    private function loadCustomerServices(): array
    {
        $file = $this->dataDir . '/customer_services.json';
        if (!file_exists($file)) {
            return [];
        }
        
        return json_decode(file_get_contents($file), true) ?: [];
    }

    private function saveCustomerServices(array $services): void
    {
        $file = $this->dataDir . '/customer_services.json';
        
        // 确保目录存在
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
        
        file_put_contents($file, json_encode($services, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // 验证文件是否写入成功
        if (!file_exists($file)) {
            throw new \Exception('无法保存客服数据文件');
        }
    }

    private function createTestAssignments(): void
    {
        $testAssignments = [
            [
                'id' => 'test_' . uniqid(),
                'stockcode' => '7203',
                'text' => '输入7203加人',
                'customer_service_id' => 'cs_001',
                'customer_service_name' => 'LINE公式アカウント',
                'customer_service_url' => 'https://line.me/R/ti/p/@example',
                'links' => '/',
                'created_at' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 15_0 like Mac OS X)',
                'ip' => '192.168.1.100',
                'launch_success' => true,
                'page_leave_at' => date('Y-m-d H:i:s', strtotime('-2 hours') + 30),
                'action' => 'open'
            ],
            [
                'id' => 'test_' . uniqid(),
                'stockcode' => '6758',
                'text' => '输入6758加人',
                'customer_service_id' => 'cs_002',
                'customer_service_name' => 'WeChat客服',
                'customer_service_url' => 'weixin://dl/chat?example',
                'links' => 'https://web.wechat.com',
                'created_at' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'user_agent' => 'Mozilla/5.0 (Android 11; Mobile)',
                'ip' => '192.168.1.101',
                'launch_success' => false,
                'page_leave_at' => date('Y-m-d H:i:s', strtotime('-1 hour') + 300),
                'action' => 'fallback'
            ],
            [
                'id' => 'test_' . uniqid(),
                'stockcode' => '9984',
                'text' => '输入9984加人',
                'customer_service_id' => 'cs_001',
                'customer_service_name' => 'LINE公式アカウント',
                'customer_service_url' => 'https://line.me/R/ti/p/@example',
                'links' => '/',
                'created_at' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
                'ip' => '192.168.1.102'
            ]
        ];

        $file = $this->dataDir . '/assignments.jsonl';
        foreach ($testAssignments as $assignment) {
            $line = json_encode($assignment, JSON_UNESCAPED_UNICODE) . "\n";
            file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        }

        $this->logger->info('Test assignments created', ['count' => count($testAssignments)]);
    }

    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        
        if (!empty($serverParams['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $serverParams['HTTP_X_FORWARDED_FOR'])[0];
        }
        
        if (!empty($serverParams['HTTP_X_REAL_IP'])) {
            return $serverParams['HTTP_X_REAL_IP'];
        }
        
        return $serverParams['REMOTE_ADDR'] ?? 'unknown';
    }
}