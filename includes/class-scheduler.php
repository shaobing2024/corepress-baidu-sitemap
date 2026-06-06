<?php
/**
 * WP-Cron 定时任务类
 * 定时批量推送、死链检测、收录状态检查等
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Scheduler {
    
    /**
     * 注册 Cron 任务
     */
    public function register_schedules() {
        // 添加自定义时间间隔
        add_filter('cron_schedules', array($this, 'add_custom_intervals'));
        
        // 注册定时任务
        if (!wp_next_scheduled('cp_seo_daily_push')) {
            wp_schedule_event(time(), 'daily', 'cp_seo_daily_push');
        }
        
        if (!wp_next_scheduled('cp_seo_hourly_check')) {
            wp_schedule_event(time(), 'hourly', 'cp_seo_hourly_check');
        }
        
        if (!wp_next_scheduled('cp_seo_weekly_analysis')) {
            wp_schedule_event(time(), 'weekly', 'cp_seo_weekly_analysis');
        }
        
        if (!wp_next_scheduled('cp_seo_cleanup_task')) {
            wp_schedule_event(time(), 'daily', 'cp_seo_cleanup_task');
        }
    }
    
    /**
     * 添加自定义时间间隔
     */
    public function add_custom_intervals($schedules) {
        $schedules['every_6_hours'] = array(
            'interval' => 21600,
            'display'  => 'Every 6 Hours',
        );
        
        $schedules['every_12_hours'] = array(
            'interval' => 43200,
            'display'  => 'Every 12 Hours',
        );
        
        return $schedules;
    }
    
    /**
     * 每日推送任务
     */
    public function daily_push_task() {
        $settings = get_option('cp_seo_settings', array());
        
        if (empty($settings['auto_submit']) || empty($settings['baidu_token'])) {
            return;
        }
        
        // 推送最近 24 小时的文章
        $posts = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'date_query'     => array(
                array(
                    'after'     => '1 day ago',
                    'inclusive' => true,
                ),
            ),
        ));
        
        if (empty($posts)) {
            return;
        }
        
        $urls = array();
        foreach ($posts as $post) {
            $url = get_permalink($post->ID);
            if ($url) {
                $urls[] = $url;
            }
        }
        
        // 批量推送
        $engine_handler = new CP_Search_Engines();
        $engine_handler->batch_push_urls($urls, 'baidu');
        
        // 记录日志
        $logger = new CP_Logger();
        $logger->info('定时推送：推送 ' . count($urls) . ' 条 URL 到百度');
    }
    
    /**
     * 每小时检查任务
     */
    public function hourly_check_task() {
        // 检查收录状态
        $tracker = new CP_Index_Tracker();
        $not_indexed = $tracker->get_not_indexed('baidu', 50);
        
        foreach ($not_indexed as $record) {
            $is_indexed = $tracker->check_index_status($record->post_id, $record->url, 'baidu');
            
            if ($is_indexed) {
                $logger = new CP_Logger();
                $logger->info('收录检测：URL 已被收录', array(
                    'post_id' => $record->post_id,
                    'url'     => $record->url,
                ));
            }
        }
    }
    
    /**
     * 每周 SEO 分析任务
     */
    public function weekly_analysis_task() {
        $analyzer = new CP_SEO_Analyzer();
        
        // 分析所有文章
        $posts = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));
        
        if (empty($posts)) {
            return;
        }
        
        $stats = array();
        $poor_posts = array();
        
        foreach ($posts as $post_id) {
            $analysis = $analyzer->analyze_post($post_id);
            
            if ($analysis['score'] < 70) {
                $poor_posts[] = array(
                    'post_id' => $post_id,
                    'title'   => get_the_title($post_id),
                    'score'   => $analysis['score'],
                );
            }
        }
        
        $logger = new CP_Logger();
        $logger->info('SEO 周分析报告：发现 ' . count($poor_posts) . ' 篇需要优化的文章');
        
        // 存储分析结果
        update_option('cp_seo_weekly_analysis', array(
            'timestamp'    => time(),
            'poor_posts'   => $poor_posts,
            'stats'        => $analyzer->get_site_statistics(),
        ));
    }
    
    /**
     * 清理任务
     */
    public function cleanup_task() {
        // 清理旧日志
        $logger = new CP_Logger();
        $deleted_logs = $logger->cleanup_old_logs(3);
        
        // 清理旧的收录记录
        $tracker = new CP_Index_Tracker();
        $tracker->cleanup_old_records(90);
        
        // 清理已解决的死链
        $detector = new CP_Broken_Link_Detector();
        $cleaned_links = $detector->cleanup_resolved_links();
        
        // 清理过期的 transients
        delete_transient('cp_baidu_sitemap_urls');
        
        $logger = new CP_Logger();
        $logger->info('清理任务完成', array(
            'deleted_logs'    => $deleted_logs,
            'cleaned_links'   => $cleaned_links,
        ));
    }
    
    /**
     * 停用插件时清除所有 Cron 任务
     */
    public function clear_schedules() {
        wp_clear_scheduled_hook('cp_seo_daily_push');
        wp_clear_scheduled_hook('cp_seo_hourly_check');
        wp_clear_scheduled_hook('cp_seo_weekly_analysis');
        wp_clear_scheduled_hook('cp_seo_cleanup_task');
    }
    
    /**
     * 获取定时任务状态
     */
    public function get_schedule_status() {
        $hooks = array(
            'cp_seo_daily_push'      => '每日推送',
            'cp_seo_hourly_check'    => '每小时检查',
            'cp_seo_weekly_analysis' => '每周分析',
            'cp_seo_cleanup_task'    => '清理任务',
        );
        
        $status = array();
        
        foreach ($hooks as $hook => $label) {
            $next_run = wp_next_scheduled($hook);
            
            $status[$hook] = array(
                'label'      => $label,
                'scheduled'  => $next_run !== false,
                'next_run'   => $next_run ? date('Y-m-d H:i:s', $next_run) : '未安排',
                'interval'   => $this->get_schedule_interval($hook),
            );
        }
        
        return $status;
    }
    
    /**
     * 获取任务执行间隔
     */
    private function get_schedule_interval($hook) {
        switch ($hook) {
            case 'cp_seo_daily_push':
                return '每天';
            case 'cp_seo_hourly_check':
                return '每小时';
            case 'cp_seo_weekly_analysis':
                return '每周';
            case 'cp_seo_cleanup_task':
                return '每天';
            default:
                return '未知';
        }
    }
    
    /**
     * 手动触发任务
     */
    public function run_manual_task($task_name) {
        switch ($task_name) {
            case 'daily_push':
                $this->daily_push_task();
                return array('success' => true, 'message' => '每日推送任务已执行');
                
            case 'hourly_check':
                $this->hourly_check_task();
                return array('success' => true, 'message' => '每小时检查任务已执行');
                
            case 'weekly_analysis':
                $this->weekly_analysis_task();
                return array('success' => true, 'message' => '每周分析任务已执行');
                
            case 'cleanup':
                $this->cleanup_task();
                return array('success' => true, 'message' => '清理任务已执行');
                
            default:
                return array('success' => false, 'message' => '未知的任务名称');
        }
    }
}
