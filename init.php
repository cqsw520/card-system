<?php
/**
 * 数据库初始化与管理类
 * 使用绝对路径避免路径解析问题
 */
class Database {
    private static $instance = null;
    private $db;
    
    private function __construct() {
        // 使用/tmp目录避免沙盒文件系统限制
        $dbPath = '/tmp/card_system_data/cards.db';
        $dir = dirname($dbPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        
        // 确保目录可写
        chmod($dir, 0777);
        
        // 如果db文件存在但为空或损坏，删除它
        if (file_exists($dbPath) && filesize($dbPath) == 0) {
            unlink($dbPath);
        }
        
        try {
            $this->db = new SQLite3($dbPath, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
        } catch (Exception $e) {
            // 如果打开失败，尝试删除后重建
            if (file_exists($dbPath)) {
                unlink($dbPath);
            }
            $this->db = new SQLite3($dbPath, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
        }
        
        chmod($dbPath, 0666);
        
        // 不使用WAL模式（在某些环境下有问题），使用默认的rollback journal
        // $this->db->exec('PRAGMA journal_mode = WAL');
        $this->db->exec('PRAGMA foreign_keys = ON');
        $this->db->exec('PRAGMA synchronous = NORMAL');
        $this->initTables();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initTables() {
        // 管理员表
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            )
        ");
        
        // 卡密表
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS cards (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                card_key TEXT NOT NULL UNIQUE,
                card_secret TEXT NOT NULL,
                amount REAL NOT NULL DEFAULT 0,
                duration INTEGER NOT NULL DEFAULT 30,
                status INTEGER NOT NULL DEFAULT 0,
                batch_no TEXT NOT NULL DEFAULT '',
                created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
                activated_at INTEGER DEFAULT NULL,
                expired_at INTEGER DEFAULT NULL,
                device_id TEXT DEFAULT NULL,
                remark TEXT DEFAULT ''
            )
        ");
        
        // 订单表
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                order_no TEXT NOT NULL UNIQUE,
                amount REAL NOT NULL,
                status INTEGER NOT NULL DEFAULT 0,
                card_id INTEGER DEFAULT NULL,
                payment_method TEXT DEFAULT 'manual',
                created_at INTEGER NOT NULL DEFAULT (strftime('%s','now')),
                paid_at INTEGER DEFAULT NULL,
                contact_info TEXT DEFAULT ''
            )
        ");
        
        // 卡密套餐表
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS packages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                amount REAL NOT NULL,
                duration INTEGER NOT NULL DEFAULT 30,
                price REAL NOT NULL,
                stock INTEGER NOT NULL DEFAULT 0,
                status INTEGER NOT NULL DEFAULT 1,
                created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            )
        ");
        
        // 配置表
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS configs (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL,
                updated_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            )
        ");
        
        // 激活日志表
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS activation_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                card_id INTEGER NOT NULL,
                device_id TEXT NOT NULL,
                ip TEXT NOT NULL,
                user_agent TEXT DEFAULT '',
                created_at INTEGER NOT NULL DEFAULT (strftime('%s','now'))
            )
        ");
    }
    
    public function getDb() {
        return $this->db;
    }
    
    public function query($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) return false;
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        return $stmt->execute();
    }
    
    public function fetch($sql, $params = []) {
        $result = $this->query($sql, $params);
        if ($result && $row = $result->fetchArray(SQLITE3_ASSOC)) {
            return $row;
        }
        return null;
    }
    
    public function fetchAll($sql, $params = []) {
        $result = $this->query($sql, $params);
        $rows = [];
        if ($result) {
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }
    
    public function insert($sql, $params = []) {
        if ($this->query($sql, $params)) {
            return $this->db->lastInsertRowID();
        }
        return false;
    }
    
    public function update($sql, $params = []) {
        return $this->query($sql, $params) !== false;
    }
    
    public function delete($sql, $params = []) {
        return $this->query($sql, $params) !== false;
    }
}
