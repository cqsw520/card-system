<?php
/**
 * 认证管理类
 * 处理后台登录、会话管理、权限验证
 */
class Auth {
    
    /**
     * 初始化会话
     */
    public static function initSession() {
        if (session_status() !== PHP_SESSION_NONE) {
            return; // 已经启动
        }
        
        // 只有在没有输出的情况下才设置session参数
        if (!headers_sent()) {
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        }
        
        @session_start();
    }
    
    /**
     * 检查是否登录（后台）
     */
    public static function isAdminLoggedIn() {
        self::initSession();
        return isset($_SESSION['admin_id']) && 
               isset($_SESSION['admin_login_time']) &&
               (time() - $_SESSION['admin_login_time']) < SESSION_LIFETIME;
    }
    
    /**
     * 后台登录
     */
    public static function adminLogin($username, $password) {
        $ip = Security::getClientIp();
        
        // 检查尝试次数
        if (!Security::checkLoginAttempts($ip)) {
            return ['success' => false, 'msg' => '登录尝试过多，请15分钟后再试'];
        }
        
        $db = Database::getInstance();
        $admin = $db->fetch("SELECT * FROM admins WHERE username = ?", [$username]);
        
        if (!$admin || !Security::verifyPassword($password, $admin['password_hash'])) {
            Security::recordLoginFailure($ip);
            return ['success' => false, 'msg' => '用户名或密码错误'];
        }
        
        // 登录成功
        Security::clearLoginAttempts($ip);
        self::initSession();
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        $_SESSION['admin_login_time'] = time();
        
        return ['success' => true, 'msg' => '登录成功'];
    }
    
    /**
     * 后台登出
     */
    public static function adminLogout() {
        self::initSession();
        unset($_SESSION['admin_id']);
        unset($_SESSION['admin_username']);
        unset($_SESSION['admin_login_time']);
        session_destroy();
    }
    
    /**
     * 要求登录（中间件）
     */
    public static function requireAdmin() {
        if (!self::isAdminLoggedIn()) {
            // 如果是AJAX请求，返回JSON
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strpos($_SERVER['REQUEST_URI'] ?? '', 'api.php') !== false) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'msg' => '未登录']);
                exit;
            }
            header('Location: /' . ADMIN_PATH . '/login.php');
            exit;
        }
        // 刷新登录时间
        $_SESSION['admin_login_time'] = time();
    }
    
    /**
     * 验证API请求权限
     */
    public static function verifyApiRequest() {
        self::initSession();
        
        // 检查登录状态
        if (!self::isAdminLoggedIn()) {
            header('Content-Type: application/json');
            http_response_code(401);
            echo json_encode(['success' => false, 'msg' => '未登录']);
            exit;
        }
        
        // 验证CSRF (GET请求除外)
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
            if (!Security::verifyCsrfToken($token)) {
                header('Content-Type: application/json');
                http_response_code(403);
                echo json_encode(['success' => false, 'msg' => 'CSRF验证失败']);
                exit;
            }
        }
    }
}
