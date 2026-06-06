<?php
/**
 * 死链检测和管理类
 * 自动检测 404 页面并提交给百度死链工具
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Broken_Link_Detector {
    
    /**
     * 数据库表名
     */
    private $table_name;
    
    /**
     * 构造函数
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'cp_broken_links';
    }
    
    /**
     * 创建数据库表
     */
    public function create_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            url varchar(2048) NOT NULL,
            source_url varchar(2048) DEFAULT '',
            status_code int(11) DEFAULT 404,
            found_at datetime DEFAULT NULL,
            last_checked datetime DEFAULT NULL,
            checked_count int(11) DEFAULT 0,
            is_submitted tinyint(1) DEFAULT 0,
            submitted_at datetime DEFAULT NULL,
            notes text,
            PRIMARY KEY  (id),
            KEY url (url(255)),
            KEY status_code (status_code),
            KEY is_submitted (is_submitted)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * 记录死链
     */
    public function record_broken_link($url, $source_url = '', $status_code = 404) {
        global $wpdb;
        
        // 检查是否已存在
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE url = %s",
            $url
        ));
        
        if ($existing) {
            $wpdb->update(
                $this->table_name,
                array(
                    'status_code'   => $status_code,
                    'last_checked'  => current_time('mysql'),
                    'checked_count' => $existing->checked_count + 1,
                ),
                array('id' => $existing->id),
                array('%d', '%s', '%d'),
                array('%d')
            );
            return $existing->id;
        } else {
            $wpdb->insert(
                $this->table_name,
                array(
                    'url'          => $url,
                    'source_url'   => $source_url,
                    'status_code'  => $status_code,
                    'found_at'     => current_time('mysql'),
                    'last_checked' => current_time('mysql'),
                ),
                array('%s', '%s', '%d', '%s', '%s')
            );
            return $wpdb->insert_id;
        }
    }
    
    /**
     * 检测文章中的死链
     */
    public function check_post_links($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return array();
        }
        
        $content = $post->post_content;
        $broken_links = array();
        
        // 提取所有链接
        preg_match_all('/<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches);
        
        if (empty($matches[1])) {
            return array();
        }
        
        $links = array_unique($matches[1]);
        $settings = get_option('cp_seo_settings', array());
        $max_requests = isset($settings['max_link_requests']) ? intval($settings['max_link_requests']) : 50;
        $request_count = 0;
        
        foreach ($links as $url) {
            if ($request_count >= $max_requests) {
                break;
            }
            
            // 跳过特殊链接
            if ($this->should_skip_link($url)) {
                continue;
            }
            
            $status_code = $this->check_url_status($url);
            
            if ($status_code >= 400) {
                $this->record_broken_link($url, get_permalink($post_id), $status_code);
                $broken_links[] = array(
                    'url' => $url,
                    'status_code' => $status_code,
                );
            }
            
            $request_count++;
            
            // 避免请求过快
            usleep(100000); // 100ms 延迟
        }
        
        return $broken_links;
    }
    
    /**
     * 检查 URL 状态
     */
    private function check_url_status($url) {
        $response = wp_remote_head($url, array(
            'timeout'     => 10,
            'redirection' => 5,
            'user-agent'  => 'Mozilla/5.0 (compatible; CorePress-SEO/1.0)',
        ));
        
        if (is_wp_error($response)) {
            return 0; // 无法连接
        }
        
        return wp_remote_retrieve_response_code($response);
    }
    
    /**
     * 判断是否应该跳过链接检查
     */
    private function should_skip_link($url) {
        // 跳过特殊协议
        if (strpos($url, 'mailto:') === 0 || 
            strpos($url, 'tel:') === 0 || 
            strpos($url, 'javascript:') === 0 ||
            strpos($url, '#') === 0) {
            return true;
        }
        
        // 跳过内部锚点链接
        if (strpos($url, '#') !== false && strpos($url, 'http') !== 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 获取所有死链
     */
    public function get_broken_links($limit = 100, $submitted_only = false) {
        global $wpdb;
        
        $sql = "SELECT * FROM {$this->table_name} WHERE 1=1";
        
        if ($submitted_only) {
            $sql .= " AND is_submitted = 1";
        }
        
        $sql .= " ORDER BY found_at DESC LIMIT %d";
        
        return $wpdb->get_results($wpdb->prepare($sql, $limit));
    }
    
    /**
     * 获取未提交的死链
     */
    public function get_unsubmitted_links() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT * FROM {$this->table_name} 
            WHERE is_submitted = 0 
            ORDER BY found_at DESC
        ");
    }
    
    /**
     * 提交死链到百度
     */
    public function submit_to_baidu($link_ids = null) {
        $settings = get_option('cp_seo_settings', array());
        
        if (empty($settings['baidu_token'])) {
            return array('success' => false, 'message' => '未配置百度 Token');
        }
        
        global $wpdb;
        
        // 获取要提交的死链
        if ($link_ids && is_array($link_ids)) {
            $ids_placeholder = implode(',', array_fill(0, count($link_ids), '%d'));
            $links = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT url FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                    ...$link_ids
                )
            );
        } else {
            $links = $wpdb->get_results("
                SELECT url FROM {$this->table_name} 
                WHERE is_submitted = 0 
                LIMIT 500
            ");
        }
        
        if (empty($links)) {
            return array('success' => true, 'message' => '没有需要提交的死链');
        }
        
        $urls = wp_list_pluck($links, 'url');
        $site = parse_url(home_url(), PHP_URL_HOST);
        $api_url = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($settings['baidu_token']);
        
        $response = wp_remote_post($api_url, array(
            'headers' => array('Content-Type' => 'text/plain'),
            'body'    => implode("\n", $urls),
            'timeout' => 30,
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => '提交失败：' . $response->get_error_message(),
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($http_code === 200 && $result) {
            // 更新提交状态
            $ids = wp_list_pluck($links, 'id');
            if (!empty($ids)) {
                $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$this->table_name} SET is_submitted = 1, submitted_at = NOW() WHERE id IN ({$ids_placeholder})",
                        ...$ids
                    )
                );
            }
            
            return array(
                'success' => true,
                'message' => '成功提交 ' . count($urls) . ' 条死链',
                'response' => $result,
            );
        } else {
            $error_msg = isset($result['message']) ? $result['message'] : 'HTTP ' . $http_code;
            return array(
                'success' => false,
                'message' => '提交失败：' . $error_msg,
                'response' => $result,
            );
        }
    }
    
    /**
     * 生成死链文件
     */
    public function generate_dead_link_file() {
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/corepress_sitemaps';
        
        if (!is_dir($sitemap_dir)) {
            wp_mkdir_p($sitemap_dir);
        }
        
        $txt_file = $sitemap_dir . '/deadlinks.txt';
        $links = $this->get_broken_links(0);
        
        if (empty($links)) {
            return false;
        }
        
        $content = '';
        foreach ($links as $link) {
            $content .= $link->url . "\n";
        }
        
        if (file_put_contents($txt_file, $content) === false) {
            return false;
        }
        
        return array(
            'url' => home_url('/deadlinks.txt'),
            'file' => $txt_file,
            'count' => count($links),
        );
    }
    
    /**
     * 获取统计信息
     */
    public function get_statistics() {
        global $wpdb;
        
        $stats = array(
            'total'         => 0,
            'submitted'     => 0,
            'unsubmitted'   => 0,
            '404_count'     => 0,
            '500_count'     => 0,
            'other_count'   => 0,
        );
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name}");
        $stats['total'] = intval($total);
        
        $submitted = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE is_submitted = 1");
        $stats['submitted'] = intval($submitted);
        
        $unsubmitted = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name} WHERE is_submitted = 0");
        $stats['unsubmitted'] = intval($unsubmitted);
        
        $results = $wpdb->get_results("
            SELECT 
                SUM(CASE WHEN status_code = 404 THEN 1 ELSE 0 END) as count_404,
                SUM(CASE WHEN status_code >= 500 THEN 1 ELSE 0 END) as count_500,
                SUM(CASE WHEN status_code NOT IN (404, 500) THEN 1 ELSE 0 END) as count_other
            FROM {$this->table_name}
        ");
        
        if ($results) {
            $stats['404_count'] = intval($results[0]->count_404);
            $stats['500_count'] = intval($results[0]->count_500);
            $stats['other_count'] = intval($results[0]->count_other);
        }
        
        return $stats;
    }
    
    /**
     * 清理已解决的死链
     */
    public function cleanup_resolved_links() {
        global $wpdb;
        
        $links = $wpdb->get_results("SELECT id, url FROM {$this->table_name} WHERE is_submitted = 0 LIMIT 100");
        
        $cleaned = 0;
        
        foreach ($links as $link) {
            $status_code = $this->check_url_status($link->url);
            
            if ($status_code === 200) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$this->table_name} WHERE id = %d",
                    $link->id
                ));
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
    
    /**
     * 批量删除死链记录
     */
    public function delete_links($ids) {
        global $wpdb;
        
        $ids_placeholder = implode(',', array_fill(0, count($ids), '%d'));
        
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE id IN ({$ids_placeholder})",
                ...$ids
            )
        );
    }
}
