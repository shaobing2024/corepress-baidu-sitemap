<?php
/**
 * 日志系统类
 * 记录推送历史、错误信息、操作日志
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Logger {
    
    /**
     * 日志目录
     */
    private $log_dir;
    
    /**
     * 日志文件
     */
    private $log_file;
    
    /**
     * 日志级别
     */
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_DEBUG = 'debug';
    
    /**
     * 构造函数
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/corepress_seo_logs';
        $this->log_file = $this->log_dir . '/sitemap-' . date('Y-m') . '.log';
        
        if (!is_dir($this->log_dir)) {
            wp_mkdir_p($this->log_dir);
        }
    }
    
    /**
     * 记录日志
     */
    public function log($message, $level = self::LEVEL_INFO, $context = array()) {
        $timestamp = current_time('mysql');
        $message_formatted = sprintf(
            "[%s] [%s] %s%s\n",
            $timestamp,
            strtoupper($level),
            $message,
            !empty($context) ? ' | Context: ' . json_encode($context) : ''
        );
        
        file_put_contents($this->log_file, $message_formatted, FILE_APPEND);
        
        // 触发日志钩子，允许其他模块记录到数据库
        do_action('cp_seo_log_entry', $timestamp, $level, $message, $context);
    }
    
    /**
     * 记录信息日志
     */
    public function info($message, $context = array()) {
        $this->log($message, self::LEVEL_INFO, $context);
    }
    
    /**
     * 记录警告日志
     */
    public function warning($message, $context = array()) {
        $this->log($message, self::LEVEL_WARNING, $context);
    }
    
    /**
     * 记录错误日志
     */
    public function error($message, $context = array()) {
        $this->log($message, self::LEVEL_ERROR, $context);
    }
    
    /**
     * 记录调试日志
     */
    public function debug($message, $context = array()) {
        $settings = get_option('cp_seo_settings', array());
        if (!empty($settings['debug_mode'])) {
            $this->log($message, self::LEVEL_DEBUG, $context);
        }
    }
    
    /**
     * 记录推送历史
     */
    public function log_push($engine, $urls_count, $result) {
        $this->info('推送 URL 到' . $engine, array(
            'urls_count' => $urls_count,
            'result' => $result,
        ));
    }
    
    /**
     * 记录 Sitemap 生成
     */
    public function log_sitemap_generation($files) {
        $this->info('站点地图生成成功', array(
            'files' => $files,
        ));
    }
    
    /**
     * 记录 API 错误
     */
    public function log_api_error($engine, $error_message, $response = null) {
        $this->error($engine . ' API 错误', array(
            'error' => $error_message,
            'response' => $response,
        ));
    }
    
    /**
     * 获取日志文件列表
     */
    public function get_log_files() {
        if (!is_dir($this->log_dir)) {
            return array();
        }
        
        $files = glob($this->log_dir . '/*.log');
        return array_map('basename', $files);
    }
    
    /**
     * 读取日志文件内容
     */
    public function read_log($filename, $lines = 100) {
        $filepath = $this->log_dir . '/' . $filename;
        
        if (!file_exists($filepath)) {
            return array('error' => '文件不存在');
        }
        
        $content = file_get_contents($filepath);
        $lines_array = explode("\n", $content);
        
        // 只显示最后 N 行
        if (count($lines_array) > $lines) {
            $lines_array = array_slice($lines_array, -$lines);
        }
        
        return array(
            'filename' => $filename,
            'lines' => $lines_array,
            'total_lines' => count($lines_array),
        );
    }
    
    /**
     * 清理旧日志文件
     */
    public function cleanup_old_logs($months = 6) {
        $cutoff = strtotime("-{$months} months");
        
        if (!is_dir($this->log_dir)) {
            return 0;
        }
        
        $files = glob($this->log_dir . '/*.log');
        $deleted = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * 获取日志统计
     */
    public function get_statistics() {
        if (!file_exists($this->log_file)) {
            return array(
                'total_lines' => 0,
                'info' => 0,
                'warning' => 0,
                'error' => 0,
                'debug' => 0,
            );
        }
        
        $content = file_get_contents($this->log_file);
        $lines = explode("\n", $content);
        
        $stats = array(
            'total_lines' => count($lines),
            'info' => 0,
            'warning' => 0,
            'error' => 0,
            'debug' => 0,
        );
        
        foreach ($lines as $line) {
            if (strpos($line, '[INFO]') !== false) {
                $stats['info']++;
            } elseif (strpos($line, '[WARNING]') !== false) {
                $stats['warning']++;
            } elseif (strpos($line, '[ERROR]') !== false) {
                $stats['error']++;
            } elseif (strpos($line, '[DEBUG]') !== false) {
                $stats['debug']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * 清空当前日志
     */
    public function clear_current_log() {
        if (file_exists($this->log_file)) {
            return file_put_contents($this->log_file, '');
        }
        return false;
    }
}
