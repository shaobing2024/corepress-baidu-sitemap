<?php
/**
 * 高级管理界面
 * 包含多搜索引擎、收录追踪、SEO 分析、死链管理等高级功能
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * 添加高级功能标签页
 */
function cp_baidu_sitemap_advanced_tabs() {
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'basic';
    ?>
    <h2 class="nav-tab-wrapper">
        <a href="?page=corepress-baidu-sitemap&tab=basic" class="nav-tab <?php echo $current_tab === 'basic' ? 'nav-tab-active' : ''; ?>">基础设置</a>
        <a href="?page=corepress-baidu-sitemap&tab=engines" class="nav-tab <?php echo $current_tab === 'engines' ? 'nav-tab-active' : ''; ?>">多搜索引擎</a>
        <a href="?page=corepress-baidu-sitemap&tab=tracking" class="nav-tab <?php echo $current_tab === 'tracking' ? 'nav-tab-active' : ''; ?>">收录追踪</a>
        <a href="?page=corepress-baidu-sitemap&tab=seo-analysis" class="nav-tab <?php echo $current_tab === 'seo-analysis' ? 'nav-tab-active' : ''; ?>">SEO 分析</a>
        <a href="?page=corepress-baidu-sitemap&tab=broken-links" class="nav-tab <?php echo $current_tab === 'broken-links' ? 'nav-tab-active' : ''; ?>">死链管理</a>
        <a href="?page=corepress-baidu-sitemap&tab=scheduler" class="nav-tab <?php echo $current_tab === 'scheduler' ? 'nav-tab-active' : ''; ?>">定时任务</a>
        <a href="?page=corepress-baidu-sitemap&tab=logs" class="nav-tab <?php echo $current_tab === 'logs' ? 'nav-tab-active' : ''; ?>">日志系统</a>
    </h2>
    <?php
}

/**
 * 渲染高级设置页面
 */
function cp_baidu_sitemap_advanced_page() {
    cp_baidu_sitemap_advanced_tabs();
    
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'basic';
    
    switch ($current_tab) {
        case 'engines':
            cp_render_engines_tab();
            break;
            
        case 'tracking':
            cp_render_tracking_tab();
            break;
            
        case 'seo-analysis':
            cp_render_seo_analysis_tab();
            break;
            
        case 'broken-links':
            cp_render_broken_links_tab();
            break;
            
        case 'scheduler':
            cp_render_scheduler_tab();
            break;
            
        case 'logs':
            cp_render_logs_tab();
            break;
            
        default:
            cp_baidu_sitemap_admin_page();
            break;
    }
}

/**
 * 渲染多搜索引擎标签页
 */
function cp_render_engines_tab() {
    $settings = cp_baidu_sitemap_get_settings();
    $engine_handler = $GLOBALS['cp_engine_handler'];
    $engines = $engine_handler->get_all_engines();
    
    // 处理表单提交
    if (isset($_POST['cp_engines_submit'])) {
        check_admin_referer('cp_engines_settings', 'cp_engines_nonce');
        
        $enabled_engines = isset($_POST['enabled_engines']) ? (array)$_POST['enabled_engines'] : array();
        
        $settings['engines'] = array();
        foreach (array_keys($engines) as $engine) {
            $settings['engines'][$engine] = in_array($engine, $enabled_engines) ? 1 : 0;
        }
        
        $settings['bing_api_key'] = sanitize_text_field($_POST['cp_bing_api_key']);
        
        update_option('cp_seo_settings', $settings);
        cp_baidu_sitemap_save_settings($settings);
        
        echo '<div class="updated"><p>搜索引擎设置已保存</p></div>';
    }
    ?>
    
    <h3>多搜索引擎支持</h3>
    <p class="description">选择和配置要支持的搜索引擎</p>
    
    <form method="post" action="">
        <?php wp_nonce_field('cp_engines_settings', 'cp_engines_nonce'); ?>
        
        <table class="form-table">
            <thead>
                <tr>
                    <th>搜索引擎</th>
                    <th>状态</th>
                    <th>推送 API</th>
                    <th>管理后台</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($engines as $name => $config): ?>
                <tr>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled_engines[]" value="<?php echo esc_attr($name); ?>" 
                                <?php echo isset($settings['engines'][$name]) && $settings['engines'][$name] ? 'checked' : ''; ?>>
                            <strong><?php echo esc_html($config['name']); ?></strong>
                        </label>
                    </td>
                    <td>
                        <?php if ($config['has_push_api']): ?>
                            <span style="color:green;">✓ 支持推送</span>
                        <?php else: ?>
                            <span style="color:#999;">手动提交</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php echo esc_html($config['api_name']); ?>
                    </td>
                    <td>
                        <a href="<?php echo esc_url($config['dashboard_url']); ?>" target="_blank" class="button button-small">
                            访问管理后台
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <table class="form-table">
            <tr>
                <th scope="row">Bing API Key</th>
                <td>
                    <input type="text" name="cp_bing_api_key" value="<?php echo esc_attr($settings['bing_api_key']); ?>" class="regular-text">
                    <p class="description">用于 Bing 搜索引擎的 API 推送，在 Bing Webmaster Tools 获取</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <input type="submit" name="cp_engines_submit" class="button-primary" value="保存设置">
        </p>
    </form>
    <?php
}

/**
 * 渲染收录追踪标签页
 */
function cp_render_tracking_tab() {
    $tracker = $GLOBALS['cp_index_tracker'];
    $stats = $tracker->get_statistics('baidu');
    $not_indexed = $tracker->get_not_indexed('baidu', 20);
    ?>
    
    <h3>收录追踪统计</h3>
    
    <div style="display: flex; gap: 20px; margin: 20px 0;">
        <div style="background: #f0f6fc; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #2271b1; font-weight: bold;"><?php echo $stats['total']; ?></div>
            <div style="color: #666;">总计 URL</div>
        </div>
        <div style="background: #f6ffed; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #52c41a; font-weight: bold;"><?php echo $stats['indexed']; ?></div>
            <div style="color: #666;">已收录</div>
        </div>
        <div style="background: #fff7e6; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #fa8c16; font-weight: bold;"><?php echo $stats['pending']; ?></div>
            <div style="color: #666;">等待中</div>
        </div>
        <div style="background: #fff1f0; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #f5222d; font-weight: bold;"><?php echo $stats['not_indexed']; ?></div>
            <div style="color: #666;">未收录</div>
        </div>
    </div>
    
    <h4>未收录 URL 列表</h4>
    <?php if (empty($not_indexed)): ?>
        <p style="color: green;">✓ 所有 URL 都已被收录！</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>文章 ID</th>
                    <th>URL</th>
                    <th>提交时间</th>
                    <th>检查次数</th>
                    <th>状态</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($not_indexed as $record): ?>
                <tr>
                    <td><?php echo $record->post_id; ?></td>
                    <td>
                        <a href="<?php echo esc_url($record->url); ?>" target="_blank" style="word-break: break-all;">
                            <?php echo esc_html($record->url); ?>
                        </a>
                    </td>
                    <td><?php echo $record->submitted_at; ?></td>
                    <td><?php echo $record->check_count; ?></td>
                    <td>
                        <?php if ($record->status === 'indexed'): ?>
                            <span style="color: green;">已收录</span>
                        <?php elseif ($record->status === 'not_indexed'): ?>
                            <span style="color: red;">未收录</span>
                        <?php else: ?>
                            <span style="color: orange;">等待中</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            系统每小时自动检查收录状态一次。未收录的 URL 可能是因为百度还未抓取，也可能是内容质量问题。
        </p>
    <?php endif; ?>
    <?php
}

/**
 * 渲染 SEO 分析标签页
 */
function cp_render_seo_analysis_tab() {
    $analyzer = $GLOBALS['cp_seo_analyzer'];
    $stats = $analyzer->get_site_statistics();
    
    // 处理单个分析请求
    if (isset($_POST['cp_analyze_post'])) {
        check_admin_referer('cp_seo_analysis', 'cp_seo_nonce');
        $post_id = intval($_POST['post_id']);
        $analysis = $analyzer->analyze_post($post_id);
        ?>
        <div class="notice notice-info">
            <h4>SEO 分析结果 - <?php echo esc_html(get_the_title($post_id)); ?></h4>
            <p>
                <strong>评分：</strong>
                <span style="font-size: 24px; color: <?php echo $analysis['grade']['color']; ?>;">
                    <?php echo $analysis['score']; ?> (<?php echo $analysis['grade']['label']; ?>)
                </span>
            </p>
            <?php if (!empty($analysis['issues'])): ?>
                <h5>发现的问题：</h5>
                <ul style="margin: 10px 25px;">
                    <?php foreach ($analysis['issues'] as $issue): ?>
                        <li style="color: <?php echo $issue['type'] === 'error' ? 'red' : ($issue['type'] === 'warning' ? 'orange' : 'blue'); ?>;">
                            <strong>[<?php echo $issue['type']; ?>]</strong> <?php echo esc_html($issue['message']); ?>
                            <br><small style="color: #666;">建议：<?php echo esc_html($issue['suggestion']); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
        <?php
    }
    ?>
    
    <h3>全站 SEO 统计</h3>
    
    <div style="display: flex; gap: 20px; margin: 20px 0;">
        <div style="background: #f0f6fc; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #2271b1; font-weight: bold;"><?php echo $stats['total_posts']; ?></div>
            <div style="color: #666;">总文章数</div>
        </div>
        <div style="background: #f6ffed; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #52c41a; font-weight: bold;"><?php echo $stats['excellent_count']; ?></div>
            <div style="color: #666;">优秀 (90+)</div>
        </div>
        <div style="background: #e6f7ff; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #1890ff; font-weight: bold;"><?php echo $stats['good_count']; ?></div>
            <div style="color: #666;">良好 (80-89)</div>
        </div>
        <div style="background: #fff7e6; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #fa8c16; font-weight: bold;"><?php echo $stats['fair_count']; ?></div>
            <div style="color: #666;">中等 (70-79)</div>
        </div>
        <div style="background: #fff1f0; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #f5222d; font-weight: bold;"><?php echo $stats['poor_count']; ?></div>
            <div style="color: #666;">需改进 (<70)</div>
        </div>
        <div style="background: #f9f0ff; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #722ed1; font-weight: bold;"><?php echo $stats['average_score']; ?></div>
            <div style="color: #666;">平均分</div>
        </div>
    </div>
    
    <h4>单篇文章分析</h4>
    <form method="post" style="margin: 20px 0;">
        <?php wp_nonce_field('cp_seo_analysis', 'cp_seo_nonce'); ?>
        <label>
            选择文章：
            <select name="post_id" style="min-width: 300px;">
                <?php
                $posts = get_posts(array(
                    'post_type'      => 'post',
                    'posts_per_page' => 100,
                    'orderby'        => 'modified',
                    'order'          => 'DESC',
                ));
                foreach ($posts as $post) {
                    printf('<option value="%d">%s (ID: %d)</option>', 
                        $post->ID, esc_html($post->post_title), $post->ID);
                }
                ?>
            </select>
        </label>
        <input type="submit" name="cp_analyze_post" class="button button-secondary" value="分析 SEO">
    </form>
    
    <p class="description">
        SEO 分析功能会检查标题长度、描述优化、内容质量、图片 Alt 文本、内链外链等指标，给出综合评分和改进建议。
    </p>
    <?php
}

/**
 * 渲染死链管理标签页
 */
function cp_render_broken_links_tab() {
    $detector = $GLOBALS['cp_broken_link_detector'];
    $stats = $detector->get_statistics();
    
    // 处理提交死链
    if (isset($_POST['cp_submit_broken_links'])) {
        check_admin_referer('cp_broken_links', 'cp_broken_nonce');
        $result = $detector->submit_to_baidu();
        ?>
        <div class="notice <?php echo $result['success'] ? 'notice-success' : 'notice-error'; ?>">
            <p><?php echo esc_html($result['message']); ?></p>
        </div>
        <?php
    }
    ?>
    
    <h3>死链统计</h3>
    
    <div style="display: flex; gap: 20px; margin: 20px 0;">
        <div style="background: #f0f6fc; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #2271b1; font-weight: bold;"><?php echo $stats['total']; ?></div>
            <div style="color: #666;">总计死链</div>
        </div>
        <div style="background: #fff1f0; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #f5222d; font-weight: bold;"><?php echo $stats['404_count']; ?></div>
            <div style="color: #666;">404 Not Found</div>
        </div>
        <div style="background: #fff7e6; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #fa8c16; font-weight: bold;"><?php echo $stats['500_count']; ?></div>
            <div style="color: #666;">500 Server Error</div>
        </div>
        <div style="background: #f6ffed; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #52c41a; font-weight: bold;"><?php echo $stats['submitted']; ?></div>
            <div style="color: #666;">已提交百度</div>
        </div>
    </div>
    
    <form method="post" style="margin: 20px 0;">
        <?php wp_nonce_field('cp_broken_links', 'cp_broken_nonce'); ?>
        <input type="submit" name="cp_submit_broken_links" class="button button-primary" value="提交死链到百度">
        <button type="button" class="button button-secondary" onclick="cpDetectBrokenLinks()">检测文章中的死链</button>
    </form>
    
    <h4>死链列表</h4>
    <?php
    $links = $detector->get_broken_links(50);
    if (empty($links)):
    ?>
        <p style="color: green;">✓ 没有发现死链！</p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>URL</th>
                    <th>来源页面</th>
                    <th>状态码</th>
                    <th>发现时间</th>
                    <th>提交状态</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($links as $link): ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url($link->url); ?>" target="_blank" style="word-break: break-all;">
                            <?php echo esc_html($link->url); ?>
                        </a>
                    </td>
                    <td>
                        <?php if ($link->source_url): ?>
                            <a href="<?php echo esc_url($link->source_url); ?>" target="_blank">查看来源</a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="color: <?php echo $link->status_code >= 500 ? 'red' : 'orange'; ?>;">
                            <?php echo $link->status_code; ?>
                        </span>
                    </td>
                    <td><?php echo $link->found_at; ?></td>
                    <td>
                        <?php if ($link->is_submitted): ?>
                            <span style="color: green;">✓ 已提交</span>
                        <?php else: ?>
                            <span style="color: orange;">待提交</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <script>
    function cpDetectBrokenLinks() {
        if (!confirm('开始检测文章中的死链？这可能需要几分钟时间。')) {
            return;
        }
        
        jQuery.post(ajaxurl, {
            action: 'cp_detect_broken_links',
            _ajax_nonce: '<?php echo wp_create_nonce("cp_broken_links"); ?>'
        }, function(response) {
            if (response.success) {
                alert('检测完成！发现 ' + response.data.count + ' 个死链。');
                location.reload();
            } else {
                alert('检测失败：' + response.data.message);
            }
        });
    }
    </script>
    <?php
}

/**
 * 渲染定时任务标签页
 */
function cp_render_scheduler_tab() {
    $scheduler = $GLOBALS['cp_scheduler'];
    $status = $scheduler->get_schedule_status();
    ?>
    
    <h3>定时任务状态</h3>
    
    <table class="wp-list-table widefat fixed striped" style="margin: 20px 0;">
        <thead>
            <tr>
                <th>任务名称</th>
                <th>执行间隔</th>
                <th>下次执行时间</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($status as $hook => $info): ?>
            <tr>
                <td><?php echo esc_html($info['label']); ?></td>
                <td><?php echo esc_html($info['interval']); ?></td>
                <td><?php echo esc_html($info['next_run']); ?></td>
                <td>
                    <?php if ($info['scheduled']): ?>
                        <span style="color: green;">✓ 已安排</span>
                    <?php else: ?>
                        <span style="color: red;">✗ 未安排</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="post" style="display: inline;">
                        <?php wp_nonce_field('cp_scheduler_manual', 'cp_scheduler_nonce'); ?>
                        <input type="hidden" name="cp_manual_task" value="<?php echo esc_attr(str_replace('cp_seo_', '', $hook)); ?>">
                        <input type="submit" class="button button-small" value="立即执行">
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <?php
    // 处理手动执行
    if (isset($_POST['cp_manual_task'])) {
        check_admin_referer('cp_scheduler_manual', 'cp_scheduler_nonce');
        $task = sanitize_text_field($_POST['cp_manual_task']);
        $result = $scheduler->run_manual_task($task);
        ?>
        <div class="notice <?php echo $result['success'] ? 'notice-success' : 'notice-error'; ?> inline-block">
            <p><?php echo esc_html($result['message']); ?></p>
        </div>
        <?php
    }
    ?>
    
    <h4>定时任务说明</h4>
    <ul style="margin: 20px 25px; line-height: 1.8;">
        <li><strong>每日推送任务：</strong>每天自动推送最近 24 小时发布的文章到百度</li>
        <li><strong>每小时检查：</strong>每小时检查未收录 URL 的收录状态</li>
        <li><strong>每周分析：</strong>每周进行一次全站 SEO 分析，生成质量报告</li>
        <li><strong>清理任务：</strong>每天清理旧日志、旧记录和已解决的死链</li>
    </ul>
    <?php
}

/**
 * 渲染日志标签页
 */
function cp_render_logs_tab() {
    $logger = $GLOBALS['cp_logger'];
    $stats = $logger->get_statistics();
    $log_files = $logger->get_log_files();
    
    // 查看指定日志
    $view_log = isset($_GET['view_log']) ? sanitize_text_field($_GET['view_log']) : '';
    
    if ($view_log && in_array($view_log, $log_files)):
        $log_data = $logger->read_log($view_log, 200);
        ?>
        <h3>查看日志：<?php echo esc_html($view_log); ?></h3>
        <a href="?page=corepress-baidu-sitemap&tab=logs" class="button">返回日志列表</a>
        
        <div style="background: #f0f6fc; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <strong>统计信息：</strong><br>
            INFO: <?php echo $stats['info']; ?> | 
            WARNING: <?php echo $stats['warning']; ?> | 
            ERROR: <?php echo $stats['error']; ?> | 
            DEBUG: <?php echo $stats['debug']; ?>
        </div>
        
        <pre style="background: #f5f5f5; padding: 15px; overflow-x: auto; max-height: 500px; overflow-y: auto;">
<?php echo esc_html(implode("\n", $log_data['lines'])); ?>
        </pre>
        <?php
    else:
    ?>
    <h3>日志统计</h3>
    
    <div style="display: flex; gap: 20px; margin: 20px 0;">
        <div style="background: #f0f6fc; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #2271b1; font-weight: bold;"><?php echo $stats['total_lines']; ?></div>
            <div style="color: #666;">总日志条数</div>
        </div>
        <div style="background: #e6f7ff; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #1890ff; font-weight: bold;"><?php echo $stats['info']; ?></div>
            <div style="color: #666;">INFO</div>
        </div>
        <div style="background: #fff7e6; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #fa8c16; font-weight: bold;"><?php echo $stats['warning']; ?></div>
            <div style="color: #666;">WARNING</div>
        </div>
        <div style="background: #fff1f0; padding: 20px; border-radius: 5px; flex: 1; text-align: center;">
            <div style="font-size: 32px; color: #f5222d; font-weight: bold;"><?php echo $stats['error']; ?></div>
            <div style="color: #666;">ERROR</div>
        </div>
    </div>
    
    <h3>日志文件列表</h3>
    <table class="wp-list-table widefat fixed striped" style="margin: 20px 0;">
        <thead>
            <tr>
                <th>文件名</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($log_files as $file): ?>
            <tr>
                <td><?php echo esc_html($file); ?></td>
                <td>
                    <a href="?page=corepress-baidu-sitemap&tab=logs&view_log=<?php echo esc_url($file); ?>" class="button button-small">查看</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif;
}
