<?php
/**
 * 收录状态追踪类
 * 记录和追踪每个 URL 在各搜索引擎的收录状态
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Index_Tracker {
    
    /**
     * 数据库表名
     */
    private $table_name;
    
    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cp_index_tracker';
    }
    
    /**
     * 创建数据库表
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            url varchar(2048) NOT NULL,
            engine varchar(50) NOT NULL,
            status varchar(20) DEFAULT 'pending',
            submitted_at datetime DEFAULT NULL,
            indexed_at datetime DEFAULT NULL,
            last_checked datetime DEFAULT NULL,
            check_count int(11) DEFAULT 0,
            notes text,
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY engine (engine),
            KEY status (status),
            KEY url (url(255))
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 记录 URL 提交
     */
    public function record_submission($post_id, $url, $engine = 'baidu') {
        global $wpdb;
        
        // 检查是否已存在
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d AND engine = %s",
            $post_id,
            $engine
        ));
        
        if ($existing) {
            // 更新记录
            $wpdb->update(
                $this->table_name,
                array(
                    'url'          => $url,
                    'status'       => 'submitted',
                    'submitted_at' => current_time('mysql'),
                    'last_checked' => current_time('mysql'),
                ),
                array('id' => $existing->id),
                array('%s', '%s', '%s'),
                array('%d')
            );
            return $existing->id;
        } else {
            // 新建记录
            $wpdb->insert(
                $this->table_name,
                array(
                    'post_id'      => $post_id,
                    'url'          => $url,
                    'engine'       => $engine,
                    'status'       => 'submitted',
                    'submitted_at' => current_time('mysql'),
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
            return $wpdb->insert_id;
        }
    }
    
    /**
     * 更新收录状态
     */
    public function update_index_status($post_id, $engine, $is_indexed) {
        global $wpdb;
        
        $status = $is_indexed ? 'indexed' : 'not_indexed';
        
        $wpdb->update(
            $this->table_name,
            array(
                'status'       => $status,
                'indexed_at'   => $is_indexed ? current_time('mysql') : null,
                'last_checked' => current_time('mysql'),
                'check_count'  => $this->get_check_count($post_id, $engine) + 1,
            ),
            array(
                'post_id' => $post_id,
                'engine'  => $engine,
            ),
            array('%s', '%s', '%s', '%d'),
            array('%d', '%s')
        );
    }
    
    /**
     * 获取检查次数
     */
    private function get_check_count($post_id, $engine) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT check_count FROM {$this->table_name} WHERE post_id = %d AND engine = %s",
            $post_id,
            $engine
        ));
        
        return $count ? intval($count) : 0;
    }
    
    /**
     * 获取未收录的 URL 列表
     */
    public function get_not_indexed($engine = 'baidu', $limit = 100) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE engine = %s AND status != 'indexed' 
             ORDER BY submitted_at ASC 
             LIMIT %d",
            $engine,
            $limit
        ));
    }
    
    /**
     * 获取收录统计
     */
    public function get_statistics($engine = 'baidu') {
        global $wpdb;
        
        $stats = array(
            'total'      => 0,
            'indexed'    => 0,
            'pending'    => 0,
            'not_indexed' => 0,
        );
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT status, COUNT(*) as count FROM {$this->table_name} 
             WHERE engine = %s 
             GROUP BY status",
            $engine
        ));
        
        foreach ($results as $row) {
            $stats[$row->status] = intval($row->count);
            $stats['total'] += intval($row->count);
        }
        
        return $stats;
    }
    
    /**
     * 清理旧记录
     */
    public function cleanup_old_records($days = 90) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} 
             WHERE submitted_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
    
    /**
     * 获取文章的所有收录状态
     */
    public function get_post_status($post_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE post_id = %d",
            $post_id
        ));
    }
    
    /**
     * 检查 URL 是否被收录 (通过百度搜索资源平台 API)
     */
    public function check_index_status($post_id, $url, $engine = 'baidu') {
        $settings = get_option('cp_seo_settings', array());
        
        if ($engine === 'baidu' && !empty($settings['baidu_token'])) {
            // 使用百度 API 检查收录状态
            $site = parse_url(home_url(), PHP_URL_HOST);
            $api_url = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($settings['baidu_token']);
            
            // 通过百度 API 查询收录状态 (需要高级 API 权限)
            // 这里使用简化的检查方式：查询百度搜索
            $is_indexed = $this->check_baidu_index($url);
            $this->update_index_status($post_id, $engine, $is_indexed);
            
            return $is_indexed;
        }
        
        return false;
    }
    
    /**
     * 检查 URL 是否在百度搜索中可找到
     */
    private function check_baidu_index($url) {
        // 使用 site:查询检查 URL 是否被收录
        $encoded_url = urlencode($url);
        $search_url = 'https://www.baidu.com/s?wd=site:' . $encoded_url;
        
        $response = wp_remote_get($search_url, array(
            'timeout' => 10,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (compatible; CorePress-Baidu-Sitemap/1.0)',
            ),
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        
        // 简单判断：如果搜索结果包含该 URL，则认为已收录
        // 注意：这只是近似检查，准确数据需要百度 API
        return (strpos($body, $url) !== false);
    }
}
