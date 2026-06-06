<?php
/**
 * SEO 元数据分析类
 * 检测页面的 title、description、keywords 完整性
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_SEO_Analyzer {
    
    /**
     * 分析单个文章的 SEO 元数据
     */
    public function analyze_post($post_id) {
        $post = get_post($post_id);
        
        if (!$post) {
            return array('error' => '文章不存在');
        }
        
        $url = get_permalink($post_id);
        $title = get_the_title($post_id);
        $content = $post->post_content;
        $excerpt = get_the_excerpt($post_id);
        
        // 获取 SEO 插件的元数据 (兼容 Yoast、RankMath 等)
        $meta_description = get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $meta_keywords = get_post_meta($post_id, '_yoast_wpseo_metakeywords', true);
        
        // 如果没有使用 Yoast，尝试获取其他插件的数据
        if (empty($meta_description)) {
            $meta_description = get_post_meta($post_id, 'rank_math_description', true);
        }
        
        if (empty($meta_keywords)) {
            $meta_keywords = get_post_meta($post_id, 'rank_math_keywords', true);
        }
        
        // 分析结果
        $analysis = array(
            'post_id' => $post_id,
            'title' => $title,
            'url' => $url,
            'issues' => array(),
            'score' => 100,
        );
        
        // 检查标题
        $title_analysis = $this->analyze_title($title);
        if (!$title_analysis['passed']) {
            $analysis['issues'] = array_merge($analysis['issues'], $title_analysis['issues']);
            $analysis['score'] -= $title_analysis['penalty'];
        }
        $analysis['title_analysis'] = $title_analysis;
        
        // 检查描述
        $desc_analysis = $this->analyze_description($meta_description, $excerpt, $content);
        if (!$desc_analysis['passed']) {
            $analysis['issues'] = array_merge($analysis['issues'], $desc_analysis['issues']);
            $analysis['score'] -= $desc_analysis['penalty'];
        }
        $analysis['description_analysis'] = $desc_analysis;
        
        // 检查关键词
        $keyword_analysis = $this->analyze_keywords($meta_keywords, $content);
        if (!$keyword_analysis['passed']) {
            $analysis['issues'] = array_merge($analysis['issues'], $keyword_analysis['issues']);
            $analysis['score'] -= $keyword_analysis['penalty'];
        }
        $analysis['keywords_analysis'] = $keyword_analysis;
        
        // 检查内容质量
        $content_analysis = $this->analyze_content($content);
        if (!$content_analysis['passed']) {
            $analysis['issues'] = array_merge($analysis['issues'], $content_analysis['issues']);
            $analysis['score'] -= $content_analysis['penalty'];
        }
        $analysis['content_analysis'] = $content_analysis;
        
        // 检查图片 Alt 文本
        $image_analysis = $this->analyze_images($content, $post_id);
        if (!$image_analysis['passed']) {
            $analysis['issues'] = array_merge($analysis['issues'], $image_analysis['issues']);
            $analysis['score'] -= $image_analysis['penalty'];
        }
        $analysis['image_analysis'] = $image_analysis;
        
        // 检查链接
        $link_analysis = $this->analyze_links($content);
        if (!$link_analysis['passed']) {
            $analysis['issues'] = array_merge($analysis['issues'], $link_analysis['issues']);
            $analysis['score'] -= $link_analysis['penalty'];
        }
        $analysis['link_analysis'] = $link_analysis;
        
        // 确保分数不低于 0
        $analysis['score'] = max(0, $analysis['score']);
        
        // 评分等级
        $analysis['grade'] = $this->get_grade($analysis['score']);
        
        return $analysis;
    }
    
    /**
     * 分析标题
     */
    private function analyze_title($title) {
        $issues = array();
        $passed = true;
        $penalty = 0;
        
        $length = mb_strlen($title);
        
        if (empty($title)) {
            $issues[] = array(
                'type' => 'error',
                'message' => '标题为空',
                'suggestion' => '请为文章添加一个吸引人的标题',
            );
            $passed = false;
            $penalty += 30;
        } else {
            if ($length < 10) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => '标题太短（' . $length . ' 个字符）',
                    'suggestion' => '建议标题长度在 20-60 个字符之间',
                );
                $penalty += 10;
            } elseif ($length > 60) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => '标题太长（' . $length . ' 个字符），在搜索结果中可能被截断',
                    'suggestion' => '建议将标题控制在 60 个字符以内',
                );
                $penalty += 10;
            }
        }
        
        return array(
            'passed' => $passed,
            'issues' => $issues,
            'penalty' => $penalty,
            'length' => $length,
            'optimal_range' => '20-60',
        );
    }
    
    /**
     * 分析描述
     */
    private function analyze_description($meta_description, $excerpt, $content) {
        $issues = array();
        $passed = true;
        $penalty = 0;
        
        $description = $meta_description ?: $excerpt;
        $length = mb_strlen($description);
        
        if (empty($description)) {
            $issues[] = array(
                'type' => 'warning',
                'message' => '没有设置 SEO 描述，将使用文章摘要',
                'suggestion' => '建议手动设置 150-160 个字符的 SEO 描述',
            );
            $penalty += 15;
            
            if (empty($excerpt)) {
                $issues[] = array(
                    'type' => 'error',
                    'message' => '文章摘要也为空',
                    'suggestion' => '请添加文章摘要或手动设置 SEO 描述',
                );
                $penalty += 15;
            }
        } else {
            if ($length < 80) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => '描述太短（' . $length . ' 个字符）',
                    'suggestion' => '建议描述长度在 150-160 个字符之间',
                );
                $penalty += 10;
            } elseif ($length > 160) {
                $issues[] = array(
                    'type' => 'warning',
                    'message' => '描述太长（' . $length . ' 个字符），在搜索结果中可能被截断',
                    'suggestion' => '建议将描述控制在 160 个字符以内',
                );
                $penalty += 10;
            }
        }
        
        return array(
            'passed' => $passed,
            'issues' => $issues,
            'penalty' => $penalty,
            'length' => $length,
            'optimal_range' => '150-160',
            'has_meta' => !empty($meta_description),
        );
    }
    
    /**
     * 分析关键词
     */
    private function analyze_keywords($meta_keywords, $content) {
        $issues = array();
        $passed = true;
        $penalty = 0;
        
        if (empty($meta_keywords)) {
            $issues[] = array(
                'type' => 'info',
                'message' => '没有设置关键词',
                'suggestion' => '虽然关键词标签已不再影响 SEO，但设置关键词有助于内容组织',
            );
            $passed = true; // 这只是建议，不扣分
        }
        
        return array(
            'passed' => $passed,
            'issues' => $issues,
            'penalty' => $penalty,
            'has_keywords' => !empty($meta_keywords),
        );
    }
    
    /**
     * 分析内容质量
     */
    private function analyze_content($content) {
        $issues = array();
        $passed = true;
        $penalty = 0;
        
        $word_count = str_word_count(strip_tags($content));
        
        if ($word_count < 300) {
            $issues[] = array(
                'type' => 'warning',
                'message' => '文章内容太短（' . $word_count . ' 字）',
                'suggestion' => '建议文章至少 300 字以上，优质内容通常 1000 字以上',
            );
            $penalty += 15;
        } elseif ($word_count < 1000) {
            $issues[] = array(
                'type' => 'info',
                'message' => '文章内容适中（' . $word_count . ' 字）',
                'suggestion' => '可以考虑扩展内容，提供更详细的信息',
            );
        }
        
        // 检查段落
        $paragraph_count = substr_count($content, '</p>');
        if ($paragraph_count < 3 && $word_count > 200) {
            $issues[] = array(
                'type' => 'warning',
                'message' => '文章段落过少',
                'suggestion' => '建议将文章分成多个段落，提高可读性',
            );
            $penalty += 5;
        }
        
        // 检查小标题
        $heading_count = preg_match_all('/<h[2-6][^>]*>/i', $content);
        if ($heading_count === 0 && $word_count > 500) {
            $issues[] = array(
                'type' => 'warning',
                'message' => '文章没有使用小标题',
                'suggestion' => '建议使用 H2-H6 标签添加小标题，改善文章结构和 SEO',
            );
            $penalty += 10;
        }
        
        return array(
            'passed' => $passed,
            'issues' => $issues,
            'penalty' => $penalty,
            'word_count' => $word_count,
            'paragraph_count' => $paragraph_count,
            'heading_count' => $heading_count,
        );
    }
    
    /**
     * 分析图片
     */
    private function analyze_images($content, $post_id) {
        $issues = array();
        $passed = true;
        $penalty = 0;
        
        // 获取文章中的图片
        preg_match_all('/<img[^>]+>/i', $content, $img_matches);
        $image_count = count($img_matches[0]);
        
        // 获取特色图片
        $has_thumbnail = has_post_thumbnail($post_id);
        
        if ($image_count === 0 && !$has_thumbnail) {
            $issues[] = array(
                'type' => 'warning',
                'message' => '文章没有任何图片',
                'suggestion' => '建议添加相关图片，图文并茂可以提高用户体验',
            );
            $penalty += 10;
        }
        
        // 检查图片的 alt 属性
        $alt_count = preg_match_all('/alt="[^"]*"/i', $content);
        if ($image_count > 0 && $alt_count < $image_count) {
            $missing = $image_count - $alt_count;
            $issues[] = array(
                'type' => 'warning',
                'message' => '有 ' . $missing . ' 张图片缺少 alt 属性',
                'suggestion' => '为所有图片添加描述性的 alt 文本，改善可访问性和 SEO',
            );
            $penalty += $missing * 5;
        }
        
        return array(
            'passed' => $passed,
            'issues' => $issues,
            'penalty' => $penalty,
            'image_count' => $image_count,
            'has_thumbnail' => $has_thumbnail,
            'alt_count' => $alt_count,
        );
    }
    
    /**
     * 分析链接
     */
    private function analyze_links($content) {
        $issues = array();
        $passed = true;
        $penalty = 0;
        
        // 检查外链
        preg_match_all('/<a[^>]+href=["\']https?:\/\/(?!' . preg_quote(parse_url(home_url(), PHP_URL_HOST)) . ')[^"\']+["\'][^>]*>/i', $content, $outbound_matches);
        $outbound_count = count($outbound_matches[0]);
        
        // 检查内链
        preg_match_all('/<a[^>]+href=["\']https?:\/\/' . preg_quote(parse_url(home_url(), PHP_URL_HOST)) . '[^"\']+["\'][^>]*>/i', $content, $internal_matches);
        $internal_count = count($internal_matches[0]);
        
        if ($outbound_count === 0 && strlen($content) > 500) {
            $issues[] = array(
                'type' => 'info',
                'message' => '没有外链',
                'suggestion' => '适当添加权威外链可以增加文章可信度',
            );
        }
        
        if ($internal_count === 0 && strlen($content) > 500) {
            $issues[] = array(
                'type' => 'warning',
                'message' => '没有内链',
                'suggestion' => '建议添加相关文章的内链，改善网站结构和用户体验',
            );
            $penalty += 10;
        }
        
        return array(
            'passed' => $passed,
            'issues' => $issues,
            'penalty' => $penalty,
            'outbound_count' => $outbound_count,
            'internal_count' => $internal_count,
        );
    }
    
    /**
     * 获取评分等级
     */
    private function get_grade($score) {
        if ($score >= 90) {
            return array('grade' => 'A', 'label' => '优秀', 'color' => 'green');
        } elseif ($score >= 80) {
            return array('grade' => 'B', 'label' => '良好', 'color' => 'blue');
        } elseif ($score >= 70) {
            return array('grade' => 'C', 'label' => '中等', 'color' => 'orange');
        } elseif ($score >= 60) {
            return array('grade' => 'D', 'label' => '及格', 'color' => '#e6a23c');
        } else {
            return array('grade' => 'F', 'label' => '需改进', 'color' => 'red');
        }
    }
    
    /**
     * 批量分析文章
     */
    public function batch_analyze($post_ids) {
        $results = array();
        
        foreach ($post_ids as $post_id) {
            $results[] = $this->analyze_post($post_id);
        }
        
        return $results;
    }
    
    /**
     * 获取全站 SEO 统计
     */
    public function get_site_statistics() {
        global $wpdb;
        
        $posts = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ));
        
        $total = count($posts);
        $scores = array();
        
        foreach ($posts as $post_id) {
            $analysis = $this->analyze_post($post_id);
            $scores[] = $analysis['score'];
        }
        
        return array(
            'total_posts' => $total,
            'average_score' => $total > 0 ? round(array_sum($scores) / $total) : 0,
            'excellent_count' => count(array_filter($scores, function($s) { return $s >= 90; })),
            'good_count' => count(array_filter($scores, function($s) { return $s >= 80 && $s < 90; })),
            'fair_count' => count(array_filter($scores, function($s) { return $s >= 70 && $s < 80; })),
            'poor_count' => count(array_filter($scores, function($s) { return $s < 70; })),
        );
    }
}
