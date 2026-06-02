<?php
/**
 * Plugin Name: 百度收录增强版
 * Plugin URI: https://www.93web.com/baidu-seo-enhanced
 * Description: 自动生成百度兼容的站点地图文件，帮助百度搜索引擎更好地抓取和收录您的网站内容。包含SEO优化、结构化数据、自动推送等增强功能。
 * Version: 1.0.2
 * Author: 不良人
 * Author URI: 
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: baidu-seo-enhanced
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Network: false
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 插件常量
define('CP_BAIDU_SITEMAP_VERSION', '1.0.2');
define('CP_BAIDU_SITEMAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CP_BAIDU_SITEMAP_PLUGIN_PATH', plugin_dir_path(__FILE__));

/**
 * 获取默认设置
 */
function cp_baidu_sitemap_default_settings() {
    return array(
        'enabled'           => true,
        'format'            => 'xml',
        'include_posts'     => true,
        'include_pages'     => true,
        'include_categories' => true,
        'include_tags'      => true,
        'baidu_token'       => '',
        'auto_submit'       => false,
        'changefreq'        => 'daily',
        'post_priority'     => '0.8',
        'page_priority'     => '0.6',
    );
}

/**
 * 获取插件设置
 */
function cp_baidu_sitemap_get_settings() {
    $defaults = cp_baidu_sitemap_default_settings();
    $settings = get_option('cp_baidu_sitemap_settings', array());
    return wp_parse_args($settings, $defaults);
}

/**
 * 保存插件设置
 */
function cp_baidu_sitemap_save_settings($settings) {
    update_option('cp_baidu_sitemap_settings', $settings);
}

/**
 * 获取所有URL
 */
function cp_baidu_sitemap_get_urls($limit = 0) {
    $settings = cp_baidu_sitemap_get_settings();
    $cache_key = 'cp_baidu_sitemap_urls';

    // 先检查持久化缓存
    $cached = get_transient($cache_key);
    if ($cached !== false) {
        return $limit > 0 ? array_slice($cached, 0, $limit) : $cached;
    }

    // 再检查对象缓存
    $cached = wp_cache_get($cache_key, 'corepress_sitemap');
    if ($cached !== false) {
        return $limit > 0 ? array_slice($cached, 0, $limit) : $cached;
    }

    $urls = array();

    // 首页
    $urls[] = array(
        'loc'        => home_url('/'),
        'lastmod'    => current_time('c'),
        'changefreq' => 'daily',
        'priority'   => '1.0',
    );

    // 文章
    if ($settings['include_posts']) {
        $posts = get_posts(array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ));

        // 批量预加载修改日期
        $post_ids = wp_list_pluck($posts, 'ID');
        $modified_dates = array();
        if (!empty($post_ids)) {
            global $wpdb;
            $ids_placeholder = implode(',', array_fill(0, count($post_ids), '%d'));
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_modified_gmt FROM {$wpdb->posts} WHERE ID IN ({$ids_placeholder})",
                    ...$post_ids
                ),
                OBJECT_K
            );
            foreach ($results as $result) {
                $modified_dates[$result->ID] = $result->post_modified_gmt;
            }
        }

        foreach ($posts as $post) {
            // 统一转为 ISO 8601 格式（W3C 标准要求），避免360等严格解析器拒绝
            $raw_date = isset($modified_dates[$post->ID]) ? $modified_dates[$post->ID] : get_post_modified_time('Y-m-d H:i:s', false, $post->ID);
            $lastmod_iso = date('c', strtotime($raw_date));

            $urls[] = array(
                'loc'        => get_permalink($post->ID),
                'lastmod'    => $lastmod_iso,
                'changefreq' => cp_baidu_sitemap_get_post_frequency($post),
                'priority'   => cp_baidu_sitemap_get_post_priority($post),
            );
        }
    }

    // 页面
    if ($settings['include_pages']) {
        $pages = get_posts(array(
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        ));

        // 批量预加载修改日期
        $page_ids = wp_list_pluck($pages, 'ID');
        $modified_dates = array();
        if (!empty($page_ids)) {
            global $wpdb;
            $ids_placeholder = implode(',', array_fill(0, count($page_ids), '%d'));
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_modified_gmt FROM {$wpdb->posts} WHERE ID IN ({$ids_placeholder})",
                    ...$page_ids
                ),
                OBJECT_K
            );
            foreach ($results as $result) {
                $modified_dates[$result->ID] = $result->post_modified_gmt;
            }
        }

        foreach ($pages as $page) {
            $raw_date = isset($modified_dates[$page->ID]) ? $modified_dates[$page->ID] : get_post_modified_time('Y-m-d H:i:s', false, $page->ID);
            $lastmod_iso = date('c', strtotime($raw_date));

            $urls[] = array(
                'loc'        => get_permalink($page->ID),
                'lastmod'    => $lastmod_iso,
                'changefreq' => 'monthly',
                'priority'   => $settings['page_priority'],
            );
        }
    }

    // 分类
    if ($settings['include_categories']) {
        $categories = get_categories(array('hide_empty' => false));

        foreach ($categories as $category) {
            $urls[] = array(
                'loc'        => get_category_link($category->term_id),
                'lastmod'    => current_time('c'),
                'changefreq' => 'weekly',
                'priority'   => '0.7',
            );
        }
    }

    // 标签
    if ($settings['include_tags']) {
        $tags = get_tags(array('hide_empty' => false));

        foreach ($tags as $tag) {
            $urls[] = array(
                'loc'        => get_tag_link($tag->term_id),
                'lastmod'    => current_time('c'),
                'changefreq' => 'weekly',
                'priority'   => '0.5',
            );
        }
    }

    // 缓存结果（同时使用对象缓存和持久化缓存）
    wp_cache_set($cache_key, $urls, 'corepress_sitemap', 3600);
    set_transient($cache_key, $urls, 3600);

    return $limit > 0 ? array_slice($urls, 0, $limit) : $urls;
}

/**
 * 获取文章更新频率
 */
function cp_baidu_sitemap_get_post_frequency($post) {
    $age = (time() - strtotime($post->post_modified)) / (60 * 60 * 24);

    if ($age < 7) {
        return 'daily';
    } elseif ($age < 30) {
        return 'weekly';
    } elseif ($age < 365) {
        return 'monthly';
    } else {
        return 'yearly';
    }
}

/**
 * 获取文章优先级
 */
function cp_baidu_sitemap_get_post_priority($post) {
    $settings = cp_baidu_sitemap_get_settings();
    $base_priority = floatval($settings['post_priority']);

    // 根据评论数调整优先级
    $comment_count = get_comments_number($post->ID);
    if ($comment_count > 10) {
        $base_priority = min(1.0, $base_priority + 0.1);
    }

    return number_format($base_priority, 1);
}

/**
 * 生成站点地图文件
 */
function cp_baidu_sitemap_generate_files() {
    // 检查目录权限
    $upload_dir = wp_upload_dir();
    $sitemap_dir = $upload_dir['basedir'] . '/corepress_sitemaps';

    if (!is_dir($sitemap_dir)) {
        if (!wp_mkdir_p($sitemap_dir)) {
            error_log('CorePress百度地图: 无法创建目录 - ' . $sitemap_dir);
            return false;
        }
    }

    if (!is_writable($sitemap_dir)) {
        error_log('CorePress百度地图: 目录不可写 - ' . $sitemap_dir);
        return false;
    }

    $settings = cp_baidu_sitemap_get_settings();
    $urls = cp_baidu_sitemap_get_urls();

    $xml_file = $sitemap_dir . '/baidu_sitemap.xml';
    $txt_file = $sitemap_dir . '/baidu_sitemap.txt';

    // 生成XML
    $xml_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml_content .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($urls as $url) {
        $xml_content .= '  <url>' . "\n";
        $xml_content .= '    <loc>' . esc_url($url['loc']) . '</loc>' . "\n";
        $xml_content .= '    <lastmod>' . esc_html($url['lastmod']) . '</lastmod>' . "\n";
        $xml_content .= '    <changefreq>' . esc_html($url['changefreq']) . '</changefreq>' . "\n";
        $xml_content .= '    <priority>' . esc_html($url['priority']) . '</priority>' . "\n";
        $xml_content .= '  </url>' . "\n";
    }

    $xml_content .= '</urlset>';

    // 生成TXT
    $txt_content = '';
    foreach ($urls as $url) {
        $txt_content .= esc_url($url['loc']) . "\n";
    }

    // 保存文件（添加错误处理）
    $xml_result = file_put_contents($xml_file, $xml_content);
    $txt_result = file_put_contents($txt_file, $txt_content);

    if ($xml_result === false || $txt_result === false) {
        error_log('CorePress百度地图: 文件写入失败');
        return false;
    }

    return array(
        'xml' => $xml_file,
        'txt' => $txt_file,
    );
}

/**
 * 清除缓存
 */
function cp_baidu_sitemap_clear_cache() {
    wp_cache_delete('cp_baidu_sitemap_urls', 'corepress_sitemap');
    delete_transient('cp_baidu_sitemap_urls');
}

/**
 * 添加到robots.txt
 */
function cp_baidu_sitemap_add_to_robots($output) {
    $settings = cp_baidu_sitemap_get_settings();

    if ($settings['enabled']) {
        $output .= "\n# CorePress 百度地图\n";
        $output .= 'Sitemap: ' . home_url('/baidu-sitemap.xml') . "\n";
        $output .= 'Sitemap: ' . home_url('/baidu-sitemap.txt') . "\n";
    }

    return $output;
}
add_filter('robots_txt', 'cp_baidu_sitemap_add_to_robots');

/**
 * 注册rewrite规则
 */
function cp_baidu_sitemap_rewrite_rules() {
    add_rewrite_rule('baidu-sitemap\.xml$', 'index.php?cp_bsitemap=1', 'top');
    add_rewrite_rule('baidu-sitemap\.txt$', 'index.php?cp_bsitemap_txt=1', 'top');
}
add_action('init', 'cp_baidu_sitemap_rewrite_rules');

/**
 * 阻止 WordPress 给 sitemap URL 加斜杠（301重定向）
 * 360等搜索引擎的sitemap解析器不跟随重定向，会导致0条URL
 */
function cp_baidu_sitemap_no_trailing_slash($redirect_url, $requested_url) {
    if (strpos($requested_url, 'baidu-sitemap.xml') !== false || strpos($requested_url, 'baidu-sitemap.txt') !== false) {
        return false;
    }
    return $redirect_url;
}
add_filter('redirect_canonical', 'cp_baidu_sitemap_no_trailing_slash', 10, 2);

/**
 * 注册query vars
 */
function cp_baidu_sitemap_query_vars($vars) {
    $vars[] = 'cp_bsitemap';
    $vars[] = 'cp_bsitemap_txt';
    return $vars;
}
add_filter('query_vars', 'cp_baidu_sitemap_query_vars');

/**
 * 处理站点地图请求
 */
function cp_baidu_sitemap_handle_request() {
    // XML格式
    if (get_query_var('cp_bsitemap')) {
        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/corepress_sitemaps/baidu_sitemap.xml';

        if (file_exists($xml_file)) {
            header('Content-Type: application/xml; charset=UTF-8');
            readfile($xml_file);
            exit;
        }
    }

    // TXT格式
    if (get_query_var('cp_bsitemap_txt')) {
        $upload_dir = wp_upload_dir();
        $txt_file = $upload_dir['basedir'] . '/corepress_sitemaps/baidu_sitemap.txt';

        if (file_exists($txt_file)) {
            header('Content-Type: text/plain; charset=UTF-8');
            readfile($txt_file);
            exit;
        }
    }
}
add_action('template_redirect', 'cp_baidu_sitemap_handle_request');

/**
 * 文章保存时更新站点地图
 */
function cp_baidu_sitemap_on_post_save($post_id) {
    // 避免自动保存和修订版本
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id)) {
        return;
    }

    // 仅处理已发布的文章
    if (get_post_status($post_id) !== 'publish') {
        return;
    }

    // 清除缓存
    cp_baidu_sitemap_clear_cache();

    // 生成站点地图
    cp_baidu_sitemap_generate_files();

    // 主动推送URL到百度
    cp_baidu_push_single_url($post_id);
}
add_action('save_post', 'cp_baidu_sitemap_on_post_save');

/**
 * 文章删除时更新站点地图
 */
function cp_baidu_sitemap_on_post_delete($post_id) {
    cp_baidu_sitemap_clear_cache();
    cp_baidu_sitemap_generate_files();
}
add_action('delete_post', 'cp_baidu_sitemap_on_post_delete');

/**
 * 文章发布时推送单条URL到百度站长平台
 * 百度普通收录API: http://data.zz.baidu.com/urls?site=SITE&token=TOKEN
 */
function cp_baidu_push_single_url($post_id) {
    $settings = cp_baidu_sitemap_get_settings();

    // 未启用自动推送或未配置token则跳过
    if (!$settings['enabled'] || !$settings['auto_submit'] || empty($settings['baidu_token'])) {
        return;
    }

    $url = get_permalink($post_id);
    if (!$url) {
        return;
    }

    $site = parse_url(home_url(), PHP_URL_HOST);
    $api_url = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($settings['baidu_token']);

    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Content-Type' => 'text/plain',
        ),
        'body' => $url,
        'timeout' => 10,
        'user-agent' => 'CorePress-Baidu-Sitemap/' . CP_BAIDU_SITEMAP_VERSION,
    ));

    if (is_wp_error($response)) {
        error_log('【百度推送失败】URL: ' . $url . ' | 错误: ' . $response->get_error_message());
        return false;
    }

    $body = wp_remote_retrieve_body($response);
    error_log('【百度推送结果】URL: ' . $url . ' | 响应: ' . $body);
    return $body;
}

/**
 * AJAX 测试推送：推送首页URL，验证Token是否有效
 */
function cp_baidu_ajax_push_test() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => '权限不足'));
    }
    check_ajax_referer('cp_baidu_push_test');

    $settings = cp_baidu_sitemap_get_settings();
    if (empty($settings['baidu_token'])) {
        wp_send_json_error(array('message' => '未配置百度推送Token，请先去百度站长平台获取并填入上方设置'));
    }

    $site = parse_url(home_url(), PHP_URL_HOST);
    $api_url = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($settings['baidu_token']);
    $test_url = home_url('/');

    $response = wp_remote_post($api_url, array(
        'headers' => array('Content-Type' => 'text/plain'),
        'body'    => $test_url,
        'timeout' => 15,
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array(
            'message'    => 'HTTP请求失败',
            'error'      => $response->get_error_message(),
            'pushed_url' => $test_url,
        ));
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if ($http_code === 200 && $result && isset($result['success'])) {
        wp_send_json_success(array(
            'message'       => '推送成功',
            'baidu_message' => isset($result['message']) ? $result['message'] : '',
            'remain'        => isset($result['remain']) ? $result['remain'] : '未知',
            'pushed_url'    => $test_url,
            'raw_response'  => $body,
        ));
    } else {
        $error_msg = isset($result['message']) ? $result['message'] : ('HTTP ' . $http_code);
        wp_send_json_error(array(
            'message'       => '推送失败: ' . $error_msg,
            'http_code'     => $http_code,
            'pushed_url'    => $test_url,
            'raw_response'  => $body,
            'token_prefix'  => substr($settings['baidu_token'], 0, 4) . '...',
        ));
    }
}
add_action('wp_ajax_cp_baidu_push_test', 'cp_baidu_ajax_push_test');

/**
 * AJAX 分页批量推送（每批500条）+ 配额预检
 */
function cp_baidu_ajax_push_batch() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => '权限不足'));
    }
    check_ajax_referer('cp_baidu_push_batch');

    $settings = cp_baidu_sitemap_get_settings();
    if (empty($settings['baidu_token'])) {
        wp_send_json_error(array('message' => '未配置Token'));
    }

    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $days = isset($_POST['days']) ? intval($_POST['days']) : 0; // 新增：接收天数参数
    $site = parse_url(home_url(), PHP_URL_HOST);
    $api_url = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($settings['baidu_token']);

    // ==== 第一步：查询剩余配额 ====
    // 用首页URL测试，消耗1条配额获取 remain 信息
    if ($page === 1) {
        $quota_response = wp_remote_post($api_url, array(
            'headers' => array('Content-Type' => 'text/plain'),
            'body'    => home_url('/'),
            'timeout' => 10,
        ));
        if (!is_wp_error($quota_response)) {
            $quota_body = json_decode(wp_remote_retrieve_body($quota_response), true);
            $remain = isset($quota_body['remain']) ? intval($quota_body['remain']) : 0;
            
            if ($remain <= 1) { // <=1 因为测试已消耗1条
                wp_send_json_error(array(
                    'message' => '今日配额已用完（remain=' . $remain . '），请明天0点后再推送。百度每日配额重置。',
                    'remain'  => $remain,
                ));
            }
            
            // 根据剩余配额计算本次推送数量（最多500条/批）
            $per_page = min(500, $remain - 1); // -1 是因为测试已消耗1条
        } else {
            $per_page = 500; // 查询失败时默认500条
        }
    } else {
        $per_page = 500;
    }

    // ==== 第二步：获取文章 ====
    $post_args = array(
        'post_type'      => array('post', 'page'),
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    );
    
    // 如果指定了天数，添加日期筛选
    if ($days > 0) {
        $post_args['date_query'] = array(
            array(
                'after' => $days . ' days ago',
            ),
        );
    }
    
    $posts = get_posts($post_args);

    if (empty($posts)) {
        wp_send_json_success(array(
            'pushed' => 0,
            'message' => '没有更多文章可推送',
            'response' => '',
        ));
    }

    $urls = array();
    foreach ($posts as $post) {
        $url = get_permalink($post->ID);
        if ($url) {
            $urls[] = $url;
        }
    }

    if (empty($urls)) {
        wp_send_json_error(array('message' => '本批没有可推送的URL'));
    }

    $response = wp_remote_post($api_url, array(
        'headers' => array('Content-Type' => 'text/plain'),
        'body'    => implode("\n", $urls),
        'timeout' => 60,
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array(
            'message' => '推送请求失败: ' . $response->get_error_message(),
            'pushed'  => 0,
        ));
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if ($http_code === 200 && $result && isset($result['success'])) {
        wp_send_json_success(array(
            'pushed'   => count($urls),
            'page'     => $page,
            'remain'  => isset($result['remain']) ? $result['remain'] : '未知',
            'response' => $body,
        ));
    } else {
        $error_msg = isset($result['message']) ? $result['message'] : ('HTTP ' . $http_code);
        wp_send_json_error(array(
            'message'  => '推送失败: ' . $error_msg,
            'pushed'   => 0,
            'page'     => $page,
            'response' => $body,
        ));
    }
}
add_action('wp_ajax_cp_baidu_push_batch', 'cp_baidu_ajax_push_batch');

/**
 * 在前端页面底部输出百度自动推送JS（JS自动推送方式：即时收录）
 * 百度自动推送JS（无需token，所有页面都输出）
 * 此方式无需token，百度爬虫抓取页面时自动发现新URL
 */
function cp_baidu_autopush_js() {
    // 不在后台页面输出
    if (is_admin()) {
        return;
    }
    ?>
    <script>
    (function(){
        var bp = document.createElement('script');
        var curProtocol = window.location.protocol.split(':')[0];
        if (curProtocol === 'https') {
            bp.src = 'https://zz.bdstatic.com/linksubmit/push.js';
        } else {
            bp.src = 'http://push.zhanzhang.baidu.com/push.js';
        }
        var s = document.getElementsByTagName("script")[0];
        s.parentNode.insertBefore(bp, s);
    })();
    </script>
    <?php
}
add_action('wp_footer', 'cp_baidu_autopush_js', 99);

/**
 * 注册设置菜单
 */
function cp_baidu_sitemap_admin_menu() {
    add_options_page(
        '百度收录增强版设置',
        '百度收录增强',
        'manage_options',
        'corepress-baidu-sitemap',
        'cp_baidu_sitemap_admin_page'
    );
}
add_action('admin_menu', 'cp_baidu_sitemap_admin_menu');

/**
 * 设置页面内容
 */
function cp_baidu_sitemap_admin_page() {
    $settings = cp_baidu_sitemap_get_settings();

    // 处理表单提交
    if (isset($_POST['cp_baidu_sitemap_submit'])) {
        if (!isset($_POST['cp_baidu_sitemap_nonce']) || !wp_verify_nonce($_POST['cp_baidu_sitemap_nonce'], 'cp_baidu_sitemap')) {
            echo '<div class="error"><p>安全验证失败</p></div>';
        } else {
            $new_settings = array(
                'enabled'           => isset($_POST['cp_enabled']),
                'format'            => sanitize_text_field($_POST['cp_format']),
                'include_posts'     => isset($_POST['cp_include_posts']),
                'include_pages'     => isset($_POST['cp_include_pages']),
                'include_categories' => isset($_POST['cp_include_categories']),
                'include_tags'      => isset($_POST['cp_include_tags']),
                'baidu_token'       => sanitize_text_field($_POST['cp_baidu_token']),
                'auto_submit'       => isset($_POST['cp_auto_submit']),
                'changefreq'        => sanitize_text_field($_POST['cp_changefreq']),
                'post_priority'     => sanitize_text_field($_POST['cp_post_priority']),
                'page_priority'     => sanitize_text_field($_POST['cp_page_priority']),
            );

            cp_baidu_sitemap_save_settings($new_settings);
            $settings = $new_settings;

            echo '<div class="updated"><p>设置已保存</p></div>';
        }
    }

    // 处理生成请求
    if (isset($_POST['cp_generate_now'])) {
        cp_baidu_sitemap_clear_cache();
        $files = cp_baidu_sitemap_generate_files();

        if ($files) {
            echo '<div class="updated"><p>站点地图生成成功！</p></div>';
        } else {
            echo '<div class="error"><p>站点地图生成失败，请检查目录权限。</p></div>';
        }
    }

    // 处理清除缓存请求
    if (isset($_GET['cp_clear_cache'])) {
        cp_baidu_sitemap_clear_cache();
        echo '<div class="updated"><p>缓存已清除</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>百度收录增强版设置</h1>

        <form method="post" action="<?php echo esc_url(admin_url('options-general.php?page=corepress-baidu-sitemap')); ?>">
            <?php wp_nonce_field('cp_baidu_sitemap', 'cp_baidu_sitemap_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row">启用百度地图</th>
                    <td>
                        <label>
                            <input type="checkbox" name="cp_enabled" value="1" <?php checked($settings['enabled']); ?>>
                            启用百度收录增强功能
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">地图格式</th>
                    <td>
                        <select name="cp_format">
                            <option value="xml" <?php selected($settings['format'], 'xml'); ?>>XML格式</option>
                            <option value="txt" <?php selected($settings['format'], 'txt'); ?>>TXT格式</option>
                            <option value="both" <?php selected($settings['format'], 'both'); ?>>双格式</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">包含内容</th>
                    <td>
                        <label>
                            <input type="checkbox" name="cp_include_posts" value="1" <?php checked($settings['include_posts']); ?>>
                            文章
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="cp_include_pages" value="1" <?php checked($settings['include_pages']); ?>>
                            页面
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="cp_include_categories" value="1" <?php checked($settings['include_categories']); ?>>
                            分类目录
                        </label>
                        <br>
                        <label>
                            <input type="checkbox" name="cp_include_tags" value="1" <?php checked($settings['include_tags']); ?>>
                            标签
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">更新频率</th>
                    <td>
                        <select name="cp_changefreq">
                            <option value="always" <?php selected($settings['changefreq'], 'always'); ?>>总是</option>
                            <option value="hourly" <?php selected($settings['changefreq'], 'hourly'); ?>>每小时</option>
                            <option value="daily" <?php selected($settings['changefreq'], 'daily'); ?>>每天</option>
                            <option value="weekly" <?php selected($settings['changefreq'], 'weekly'); ?>>每周</option>
                            <option value="monthly" <?php selected($settings['changefreq'], 'monthly'); ?>>每月</option>
                            <option value="yearly" <?php selected($settings['changefreq'], 'yearly'); ?>>每年</option>
                            <option value="never" <?php selected($settings['changefreq'], 'never'); ?>>从不</option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th scope="row">文章优先级</th>
                    <td>
                        <input type="number" name="cp_post_priority" value="<?php echo esc_attr($settings['post_priority']); ?>" min="0" max="1" step="0.1">
                        <p class="description">范围: 0.0 - 1.0</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">页面优先级</th>
                    <td>
                        <input type="number" name="cp_page_priority" value="<?php echo esc_attr($settings['page_priority']); ?>" min="0" max="1" step="0.1">
                        <p class="description">范围: 0.0 - 1.0</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">百度推送Token</th>
                    <td>
                        <input type="text" name="cp_baidu_token" value="<?php echo esc_attr($settings['baidu_token']); ?>" class="regular-text">
                        <p class="description">从百度站长平台获取的推送Token</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">自动推送</th>
                    <td>
                        <label>
                            <input type="checkbox" name="cp_auto_submit" value="1" <?php checked($settings['auto_submit']); ?>>
                            文章发布时自动推送到百度
                        </label>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="cp_baidu_sitemap_submit" class="button-primary" value="保存设置">
                <input type="submit" name="cp_generate_now" class="button" value="立即生成站点地图">
                <a href="<?php echo esc_url(admin_url('options-general.php?page=corepress-baidu-sitemap&cp_clear_cache=1')); ?>" class="button">清除缓存</a>
            </p>
        </form>

        <hr>

        <h2>百度推送诊断与测试</h2>
        
        <div id="cp-baidu-push-result" style="margin:10px 0;padding:12px;background:#f0f6fc;border-left:4px solid #2271b1;display:none;"></div>

        <table class="form-table">
            <tr>
                <th scope="row">当前Token</th>
                <td>
                    <code style="word-break:break-all;"><?php echo !empty($settings['baidu_token']) ? substr($settings['baidu_token'], 0, 8) . '****' . substr($settings['baidu_token'], -4) : '<span style="color:red;">未配置</span>'; ?></code>
                    <?php if (empty($settings['baidu_token'])): ?>
                        <p style="color:#d63638;">⚠ 未配置Token，API推送无法工作。请从 <a href="https://ziyuan.baidu.com/" target="_blank">百度站长平台</a> → 普通收录 → 获取token。</p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th scope="row">百度推送配额</th>
                <td>
                    <span id="cp-quota-display" style="color:#909399;">未查询</span>
                    <button type="button" id="cp-check-quota-btn" class="button button-secondary" style="margin-left:10px;">🔍 查询今日配额</button>
                    <p class="description">百度每日推送配额通常在3000-10000条，用完需等次日0点重置</p>
                </td>
            </tr>
            <tr>
                <th scope="row">自动推送状态</th>
                <td>
                    <?php if ($settings['auto_submit']): ?>
                        <span style="color:green;">✅ 已启用 — 发布/更新文章时自动推送到百度</span>
                    <?php else: ?>
                        <span style="color:#d63638;">❌ 未启用 — 发布文章不会自动推送（请勾选上方"自动推送"并保存）</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>

        <p>
            <button type="button" id="cp-test-push-btn" class="button button-secondary">🔍 测试推送（推送首页URL，验证Token是否有效）</button>
            <span style="margin:0 10px;color:#ddd;">|</span>
            <label style="margin-right:8px;">
                推送范围：
                <select id="cp-batch-days">
                    <option value="7">最近7天</option>
                    <option value="14">最近14天</option>
                    <option value="30">最近30天</option>
                    <option value="90">最近90天</option>
                    <option value="0" selected>全部文章</option>
                </select>
            </label>
            <button type="button" id="cp-batch-push-btn" class="button button-primary" <?php echo empty($settings['baidu_token']) ? 'disabled' : ''; ?>>📤 批量推送</button>
            <span id="cp-batch-push-status" style="margin-left:10px;font-weight:bold;"></span>
        </p>

        <script>
        jQuery(document).ready(function($) {
            var totalPushed = 0;
            var batchErrors = [];

            // 配额查询
            $('#cp-check-quota-btn').click(function() {
                var btn = $(this);
                btn.prop('disabled', true).text('⏳ 查询中...');
                $('#cp-baidu-push-result').hide();

                $.post(ajaxurl, {
                    action: 'cp_baidu_check_quota',
                    _ajax_nonce: '<?php echo wp_create_nonce("cp_baidu_push_test"); ?>'
                }, function(res) {
                    btn.prop('disabled', false).text('🔍 查询今日配额');
                    
                    if (res.success) {
                        var remain = res.data.remain;
                        var successToday = res.data.success_today;
                        $('#cp-quota-display').html(
                            '今日剩余：<strong style="color:' + (remain > 100 ? 'green' : '#e6a23c') + ';">' + remain + '</strong> 条' +
                            '（已推送：' + successToday + '条）'
                        );
                        $('#cp-baidu-push-result').css('border-left-color', '#00a32a').html(
                            '<strong>✅ 配额查询成功</strong><br>' +
                            '<pre style="margin:8px 0 0;white-space:pre-wrap;word-break:break-all;">' + JSON.stringify(res.data, null, 2) + '</pre>'
                        ).show();
                    } else {
                        $('#cp-quota-display').html('<span style="color:#d63638;">查询失败</span>');
                        $('#cp-baidu-push-result').css('border-left-color', '#d63638').html(
                            '<strong>❌ 配额查询失败</strong><br>' +
                            '<pre style="margin:8px 0 0;white-space:pre-wrap;word-break:break-all;">' + JSON.stringify(res.data) + '</pre>'
                        ).show();
                    }
                }).fail(function(xhr) {
                    btn.prop('disabled', false).text('🔍 查询今日配额');
                    $('#cp-quota-display').html('<span style="color:#d63638;">请求失败</span>');
                    $('#cp-baidu-push-result').css('border-left-color', '#d63638').html(
                        '<strong>❌ 请求失败 (HTTP ' + xhr.status + ')</strong><pre>' + xhr.responseText + '</pre>'
                    ).show();
                });
            });

            // 测试推送
            $('#cp-test-push-btn').click(function() {
                var btn = $(this);
                btn.prop('disabled', true).text('⏳ 推送中...');
                $('#cp-baidu-push-result').hide();

                $.post(ajaxurl, {
                    action: 'cp_baidu_push_test',
                    _ajax_nonce: '<?php echo wp_create_nonce("cp_baidu_push_test"); ?>'
                }, function(res) {
                    btn.prop('disabled', false).text('🔍 测试推送（推送首页URL，验证Token是否有效）');
                    var cls = res.success ? '#00a32a' : '#d63638';
                    $('#cp-baidu-push-result').css('border-left-color', cls).html(
                        '<strong>' + (res.success ? '✅ 推送成功' : '❌ 推送失败') + '</strong><br>' +
                        '<pre style="margin:8px 0 0;white-space:pre-wrap;word-break:break-all;">' + JSON.stringify(res.data, null, 2) + '</pre>'
                    ).show();
                }).fail(function(xhr) {
                    btn.prop('disabled', false).text('🔍 测试推送（推送首页URL，验证Token是否有效）');
                    $('#cp-baidu-push-result').css('border-left-color', '#d63638').html(
                        '<strong>❌ 请求失败 (HTTP ' + xhr.status + ')</strong><pre>' + xhr.responseText + '</pre>'
                    ).show();
                });
            });

            // 批量推送（分页）
            var batchPage = 1;
            $('#cp-batch-push-btn').click(function() {
                var btn = $(this);
                var days = $('#cp-batch-days').val(); // 获取选择的天数
                btn.prop('disabled', true);
                batchPage = 1;
                totalPushed = 0;
                batchErrors = [];
                $('#cp-baidu-push-result').hide();
                pushNextBatch(days);

                function pushNextBatch(days) {
                    $('#cp-batch-push-status').text('⏳ 正在推送第 ' + batchPage + ' 批...');
                    
                    $.post(ajaxurl, {
                        action: 'cp_baidu_push_batch',
                        page: batchPage,
                        days: days, // 传递天数参数
                        _ajax_nonce: '<?php echo wp_create_nonce("cp_baidu_push_batch"); ?>'
                    }, function(res) {
                        if (res.success && res.data.pushed > 0) {
                            totalPushed += res.data.pushed;
                            batchPage++;
                            // 百度API每日限额通常3000-10000条，到达后API返回错误
                            if (res.data.response && res.data.response.indexOf('over quota') === -1 && res.data.pushed >= 500) {
                                pushNextBatch();
                                return;
                            }
                        }
                        
                        // 完成或出错
                        btn.prop('disabled', false);
                        $('#cp-batch-push-status').text('');
                        var cls = res.success ? '#00a32a' : '#d63638';
                        var msg = res.success 
                            ? '✅ 批量推送完成！共推送 <strong>' + totalPushed + '</strong> 条URL。'
                            : '❌ 推送出错';
                        if (batchErrors.length > 0) {
                            msg += '<br>错误: ' + batchErrors.join('; ');
                        }
                        $('#cp-baidu-push-result').css('border-left-color', cls).html(
                            msg + '<pre style="margin:8px 0 0;white-space:pre-wrap;word-break:break-all;">' + (res.data.response || JSON.stringify(res.data)) + '</pre>'
                        ).show();
                    }).fail(function(xhr) {
                        btn.prop('disabled', false);
                        $('#cp-batch-push-status').text('');
                        $('#cp-baidu-push-result').css('border-left-color', '#d63638').html(
                            '<strong>❌ 请求失败 (HTTP ' + xhr.status + ')</strong><pre>' + xhr.responseText + '</pre>'
                        ).show();
                    });
                }
            });
        });
        </script>

        <hr>

        <h2>站点地图信息</h2>

        <?php
        $upload_dir = wp_upload_dir();
        $xml_file = $upload_dir['basedir'] . '/corepress_sitemaps/baidu_sitemap.xml';
        $txt_file = $upload_dir['basedir'] . '/corepress_sitemaps/baidu_sitemap.txt';
        ?>

        <table class="widefat striped">
            <thead>
                <tr>
                    <th>文件</th>
                    <th>状态</th>
                    <th>大小</th>
                    <th>URL</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>XML站点地图</td>
                    <td><?php echo file_exists($xml_file) ? '<span style="color:green;">✓ 已生成</span>' : '<span style="color:red;">✗ 未生成</span>'; ?></td>
                    <td><?php echo file_exists($xml_file) ? size_format(filesize($xml_file)) : '-'; ?></td>
                    <td><a href="<?php echo esc_url(home_url('/baidu-sitemap.xml')); ?>" target="_blank"><?php echo esc_url(home_url('/baidu-sitemap.xml')); ?></a></td>
                </tr>
                <tr>
                    <td>TXT站点地图</td>
                    <td><?php echo file_exists($txt_file) ? '<span style="color:green;">✓ 已生成</span>' : '<span style="color:red;">✗ 未生成</span>'; ?></td>
                    <td><?php echo file_exists($txt_file) ? size_format(filesize($txt_file)) : '-'; ?></td>
                    <td><a href="<?php echo esc_url(home_url('/baidu-sitemap.txt')); ?>" target="_blank"><?php echo esc_url(home_url('/baidu-sitemap.txt')); ?></a></td>
                </tr>
            </tbody>
        </table>

        <?php if (file_exists($xml_file)) : ?>
        <h3>站点地图统计</h3>
        <?php
        $urls = cp_baidu_sitemap_get_urls();
        echo '<p>包含 ' . count($urls) . ' 个URL</p>';
        ?>
        <?php endif; ?>

        <hr>

        <h2>使用说明</h2>

        <ol>
            <li>启用百度收录增强功能</li>
            <li>选择生成格式（XML/TXT/双格式）</li>
            <li>选择要包含的内容类型</li>
            <li>点击"立即生成站点地图"</li>
            <li>复制站点地图地址提交到百度站长平台</li>
        </ol>

        <h3>提交到百度站长平台</h3>

        <ol>
            <li>登录 <a href="https://ziyuan.baidu.com/" target="_blank">百度站长平台</a></li>
            <li>验证网站所有权</li>
            <li>进入"普通收录" → "sitemap"</li>
            <li>提交站点地图地址:
                <ul>
                    <li>XML: <code><?php echo esc_url(home_url('/baidu-sitemap.xml')); ?></code></li>
                    <li>TXT: <code><?php echo esc_url(home_url('/baidu-sitemap.txt')); ?></code></li>
                </ul>
            </li>
        </ol>
    </div>
    <?php
}

/**
 * 插件激活时
 */
function cp_baidu_sitemap_activate() {
    // 添加rewrite规则
    cp_baidu_sitemap_rewrite_rules();
    flush_rewrite_rules();

    // 生成初始站点地图
    cp_baidu_sitemap_generate_files();
}
register_activation_hook(__FILE__, 'cp_baidu_sitemap_activate');

/**
 * AJAX 查询百度推送配额
 */
function cp_baidu_ajax_check_quota() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => '权限不足'));
    }
    check_ajax_referer('cp_baidu_push_test'); // 复用测试推送的 nonce

    $settings = cp_baidu_sitemap_get_settings();
    if (empty($settings['baidu_token'])) {
        wp_send_json_error(array('message' => '未配置Token'));
    }

    $site = parse_url(home_url(), PHP_URL_HOST);
    $api_url = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($settings['baidu_token']);

    // 用首页URL测试，消耗1条配额获取 remain 信息
    $response = wp_remote_post($api_url, array(
        'headers' => array('Content-Type' => 'text/plain'),
        'body'    => home_url('/'),
        'timeout' => 10,
    ));

    if (is_wp_error($response)) {
        wp_send_json_error(array(
            'message' => '查询失败：' . $response->get_error_message(),
        ));
    }

    $http_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if ($http_code === 200 && $result) {
        wp_send_json_success(array(
            'remain' => isset($result['remain']) ? $result['remain'] : '未知',
            'success_today' => isset($result['success']) ? $result['success'] : '未知',
            'raw_response' => $body,
        ));
    } else {
        $error_msg = isset($result['message']) ? $result['message'] : ('HTTP ' . $http_code);
        wp_send_json_error(array(
            'message' => '查询失败：' . $error_msg,
            'raw_response' => $body,
        ));
    }
}
add_action('wp_ajax_cp_baidu_check_quota', 'cp_baidu_ajax_check_quota');

/**
 * 插件停用时
 */
function cp_baidu_sitemap_deactivate() {
    // 清除缓存
    cp_baidu_sitemap_clear_cache();

    // 清除rewrite规则
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'cp_baidu_sitemap_deactivate');
