<?php
/**
 * URL 过滤规则类
 * 自定义排除特定分类、标签、URL 模式的页面
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_URL_Filter {
    
    /**
     * 获取过滤设置
     */
    public function get_filter_settings() {
        $settings = get_option('cp_seo_settings', array());
        
        return array(
            'exclude_posts'      => isset($settings['exclude_posts']) ? $settings['exclude_posts'] : array(),
            'exclude_pages'      => isset($settings['exclude_pages']) ? $settings['exclude_pages'] : array(),
            'exclude_categories' => isset($settings['exclude_categories']) ? $settings['exclude_categories'] : array(),
            'exclude_tags'       => isset($settings['exclude_tags']) ? $settings['exclude_tags'] : array(),
            'exclude_pattern'    => isset($settings['exclude_pattern']) ? $settings['exclude_pattern'] : '',
            'include_only'       => isset($settings['include_only']) ? $settings['include_only'] : array(),
        );
    }
    
    /**
     * 检查 URL 是否应该被排除
     */
    public function should_exclude($post_id, $url) {
        $filters = $this->get_filter_settings();
        
        // 检查是否在包含白名单中（如果设置了白名单）
        if (!empty($filters['include_only'])) {
            if (!in_array($post_id, $filters['include_only'])) {
                return true;
            }
        }
        
        // 检查是否被排除
        if (in_array($post_id, $filters['exclude_posts'])) {
            return true;
        }
        
        if (in_array($post_id, $filters['exclude_pages'])) {
            return true;
        }
        
        // 检查分类
        if ($this->is_excluded_category($post_id, $filters['exclude_categories'])) {
            return true;
        }
        
        // 检查标签
        if ($this->is_excluded_tag($post_id, $filters['exclude_tags'])) {
            return true;
        }
        
        // 检查 URL 模式
        if (!empty($filters['exclude_pattern'])) {
            if ($this->matches_pattern($url, $filters['exclude_pattern'])) {
                return true;
            }
        }
        
        // 检查文章状态
        $post_status = get_post_status($post_id);
        if ($post_status !== 'publish') {
            return true;
        }
        
        // 检查是否是私密文章
        $post_visibility = get_post_meta($post_id, '_post_is_private', true);
        if ($post_visibility) {
            return true;
        }
        
        return false;
    }
    
    /**
     * 检查文章是否属于排除的分类
     */
    private function is_excluded_category($post_id, $exclude_cats) {
        if (empty($exclude_cats)) {
            return false;
        }
        
        $categories = wp_get_post_categories($post_id);
        
        if (empty($categories)) {
            return false;
        }
        
        // 检查是否有交集
        $intersection = array_intersect($categories, $exclude_cats);
        
        return !empty($intersection);
    }
    
    /**
     * 检查文章是否属于排除的标签
     */
    private function is_excluded_tag($post_id, $exclude_tags) {
        if (empty($exclude_tags)) {
            return false;
        }
        
        $tags = wp_get_post_tags($post_id, array('fields' => 'ids'));
        
        if (empty($tags)) {
            return false;
        }
        
        // 检查是否有交集
        $intersection = array_intersect($tags, $exclude_tags);
        
        return !empty($intersection);
    }
    
    /**
     * 检查 URL 是否匹配排除模式
     */
    private function matches_pattern($url, $pattern) {
        // 支持正则表达式和简单通配符
        if (preg_match('/^\/.*\/[imsxADSUux]*$/', $pattern)) {
            // 正则表达式模式 /pattern/flags
            return @preg_match($pattern, $url) === 1;
        } else {
            // 简单通配符：转换为正则
            $regex_pattern = str_replace(
                array('*', '?'),
                array('.*', '.?'),
                preg_quote($pattern, '/')
            );
            return preg_match('/' . $regex_pattern . '/i', $url) === 1;
        }
    }
    
    /**
     * 获取所有可排除的分类
     */
    public function get_available_categories() {
        $categories = get_categories(array(
            'hide_empty' => false,
            'orderby'    => 'name',
        ));
        
        $result = array();
        foreach ($categories as $cat) {
            $result[$cat->term_id] = $cat->name;
        }
        
        return $result;
    }
    
    /**
     * 获取所有可排除的标签
     */
    public function get_available_tags() {
        $tags = get_tags(array(
            'hide_empty' => false,
            'orderby'    => 'name',
        ));
        
        $result = array();
        foreach ($tags as $tag) {
            $result[$tag->term_id] = $tag->name;
        }
        
        return $result;
    }
    
    /**
     * 获取所有可排除的文章
     */
    public function get_available_posts() {
        $posts = get_posts(array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ));
        
        $result = array();
        foreach ($posts as $post) {
            $result[$post->ID] = $post->post_title . ' (ID: ' . $post->ID . ')';
        }
        
        return $result;
    }
    
    /**
     * 获取所有可排除的页面
     */
    public function get_available_pages() {
        $pages = get_pages(array(
            'sort_column' => 'post_title',
        ));
        
        $result = array();
        foreach ($pages as $page) {
            $result[$page->ID] = $page->post_title . ' (ID: ' . $page->ID . ')';
        }
        
        return $result;
    }
    
    /**
     * 测试 URL 是否会被过滤
     */
    public function test_url($post_id) {
        $url = get_permalink($post_id);
        $should_exclude = $this->should_exclude($post_id, $url);
        
        $post = get_post($post_id);
        
        return array(
            'post_id'       => $post_id,
            'post_title'    => $post->post_title,
            'url'           => $url,
            'excluded'      => $should_exclude,
            'categories'    => wp_get_post_categories($post_id, array('fields' => 'names')),
            'tags'          => wp_get_post_tags($post_id, array('fields' => 'names')),
            'post_status'   => $post->post_status,
        );
    }
}
