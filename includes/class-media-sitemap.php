<?php
/**
 * 图片和视频 Sitemap 支持类
 * 支持百度图片搜索和视频搜索的专用 sitemap
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Media_Sitemap {
    
    /**
     * 生成图片 Sitemap
     */
    public function generate_image_sitemap() {
        $settings = get_option('cp_seo_settings', array());
        
        if (empty($settings['include_images'])) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/corepress_sitemaps';
        
        if (!is_dir($sitemap_dir)) {
            wp_mkdir_p($sitemap_dir);
        }
        
        $xml_file = $sitemap_dir . '/baidu_image_sitemap.xml';
        $urls = $this->get_image_urls();
        
        if (empty($urls)) {
            return false;
        }
        
        $xml_content = $this->build_image_sitemap_xml($urls);
        
        if (file_put_contents($xml_file, $xml_content) === false) {
            return false;
        }
        
        return array(
            'url' => home_url('/baidu-image-sitemap.xml'),
            'file' => $xml_file,
            'count' => count($urls),
        );
    }
    
    /**
     * 生成视频 Sitemap
     */
    public function generate_video_sitemap() {
        $settings = get_option('cp_seo_settings', array());
        
        if (empty($settings['include_videos'])) {
            return false;
        }
        
        $upload_dir = wp_upload_dir();
        $sitemap_dir = $upload_dir['basedir'] . '/corepress_sitemaps';
        
        if (!is_dir($sitemap_dir)) {
            wp_mkdir_p($sitemap_dir);
        }
        
        $xml_file = $sitemap_dir . '/baidu_video_sitemap.xml';
        $urls = $this->get_video_urls();
        
        if (empty($urls)) {
            return false;
        }
        
        $xml_content = $this->build_video_sitemap_xml($urls);
        
        if (file_put_contents($xml_file, $xml_content) === false) {
            return false;
        }
        
        return array(
            'url' => home_url('/baidu-video-sitemap.xml'),
            'file' => $xml_file,
            'count' => count($urls),
        );
    }
    
    /**
     * 获取带图片的文章 URL
     */
    private function get_image_urls() {
        $settings = get_option('cp_seo_settings', array());
        $urls = array();
        
        // 获取所有带图片的文章
        $posts = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_thumbnail_id',
        ));
        
        foreach ($posts as $post) {
            // 获取特色图片
            $thumbnail_id = get_post_thumbnail_id($post->ID);
            $images = array();
            
            if ($thumbnail_id) {
                $image_url = wp_get_attachment_url($thumbnail_id);
                $alt_text = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                
                $images[] = array(
                    'loc'  => $image_url,
                    'caption' => get_the_excerpt($post->ID),
                    'title' => $alt_text ? $alt_text : get_the_title($post->ID),
                    'license' => '', // 可选
                );
            }
            
            // 获取文章内容中的图片
            if (!empty($settings['include_content_images'])) {
                $content_images = $this->extract_images_from_content($post->post_content);
                $images = array_merge($images, $content_images);
            }
            
            if (!empty($images)) {
                $urls[] = array(
                    'loc'     => get_permalink($post->ID),
                    'lastmod' => get_post_modified_time('c', false, $post->ID),
                    'images'  => $images,
                );
            }
        }
        
        return $urls;
    }
    
    /**
     * 从内容中提取图片
     */
    private function extract_images_from_content($content) {
        $images = array();
        
        // 使用正则提取 img 标签
        preg_match_all('/<img[^>]+src="([^"]+)"[^>]*(?:alt="([^"]*)")?[^>]*>/i', $content, $matches);
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $index => $src) {
                // 跳过 data URI 和相对路径
                if (strpos($src, 'data:') === 0 || strpos($src, '//') !== 0) {
                    continue;
                }
                
                $images[] = array(
                    'loc' => $src,
                    'caption' => isset($matches[2][$index]) ? $matches[2][$index] : '',
                );
            }
        }
        
        return $images;
    }
    
    /**
     * 获取带视频的文章 URL
     */
    private function get_video_urls() {
        $urls = array();
        
        // 获取所有带视频的文章
        $posts = get_posts(array(
            'post_type'      => array('post', 'page'),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ));
        
        foreach ($posts as $post) {
            $videos = array();
            
            // 检查是否有视频附件
            $attachments = get_children(array(
                'post_parent'    => $post->ID,
                'post_type'      => 'attachment',
                'post_mime_type' => 'video',
            ));
            
            foreach ($attachments as $attachment) {
                $video_url = wp_get_attachment_url($attachment->ID);
                $metadata = wp_get_attachment_metadata($attachment->ID);
                
                $videos[] = array(
                    'thumbnail_loc' => wp_get_attachment_url(get_post_thumbnail_id($attachment->ID)),
                    'title'         => get_the_title($attachment->ID),
                    'description'   => get_the_excerpt($attachment->ID),
                    'content_loc'   => $video_url,
                    'player_loc'    => get_permalink($post->ID),
                    'duration'      => isset($metadata['length']) ? $metadata['length'] : '',
                    'expiration_date' => '', // 可选
                );
            }
            
            // 检查视频 shortcode (WordPress 默认 shortcode)
            $video_urls = $this->extract_video_from_content($post->post_content);
            $videos = array_merge($videos, $video_urls);
            
            if (!empty($videos)) {
                $urls[] = array(
                    'loc'     => get_permalink($post->ID),
                    'lastmod' => get_post_modified_time('c', false, $post->ID),
                    'videos'  => $videos,
                );
            }
        }
        
        return $urls;
    }
    
    /**
     * 从内容中提取视频
     */
    private function extract_video_from_content($content) {
        $videos = array();
        
        // 提取 [video] shortcode
        preg_match_all('/\[video[^]]*\]/i', $content, $matches);
        
        if (!empty($matches[0])) {
            foreach ($matches[0] as $shortcode) {
                // 解析 shortcode 属性
                preg_match('/src="([^"]+)"/', $shortcode, $src_match);
                
                if (!empty($src_match[1])) {
                    $videos[] = array(
                        'title' => '',
                        'description' => '',
                        'content_loc' => $src_match[1],
                    );
                }
            }
        }
        
        // 提取 iframe (用于嵌入 YouTube、Vimeo 等)
        preg_match_all('/<iframe[^>]+src="([^"]+)"[^>]*>/i', $content, $iframe_matches);
        
        if (!empty($iframe_matches[1])) {
            foreach ($iframe_matches[1] as $src) {
                if (strpos($src, 'youtube.com') !== false || strpos($src, 'vimeo.com') !== false) {
                    $videos[] = array(
                        'title' => '',
                        'description' => '',
                        'player_loc' => $src,
                    );
                }
            }
        }
        
        return $videos;
    }
    
    /**
     * 构建图片 Sitemap XML
     */
    private function build_image_sitemap_xml($urls) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
        
        foreach ($urls as $url_data) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url($url_data['loc']) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . esc_html($url_data['lastmod']) . '</lastmod>' . "\n";
            
            foreach ($url_data['images'] as $image) {
                $xml .= '    <image:image>' . "\n";
                $xml .= '      <image:loc>' . esc_url($image['loc']) . '</image:loc>' . "\n";
                
                if (!empty($image['caption'])) {
                    $xml .= '      <image:caption>' . esc_html($image['caption']) . '</image:caption>' . "\n";
                }
                
                if (!empty($image['title'])) {
                    $xml .= '      <image:title>' . esc_html($image['title']) . '</image:title>' . "\n";
                }
                
                if (!empty($image['license'])) {
                    $xml .= '      <image:license>' . esc_html($image['license']) . '</image:license>' . "\n";
                }
                
                $xml .= '    </image:image>' . "\n";
            }
            
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
    
    /**
     * 构建视频 Sitemap XML
     */
    private function build_video_sitemap_xml($urls) {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        
        foreach ($urls as $url_data) {
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . esc_url($url_data['loc']) . '</loc>' . "\n";
            $xml .= '    <lastmod>' . esc_html($url_data['lastmod']) . '</lastmod>' . "\n";
            
            foreach ($url_data['videos'] as $video) {
                $xml .= '    <video:video>' . "\n";
                
                if (!empty($video['thumbnail_loc'])) {
                    $xml .= '      <video:thumbnail_loc>' . esc_url($video['thumbnail_loc']) . '</video:thumbnail_loc>' . "\n";
                }
                
                $xml .= '      <video:title>' . esc_html($video['title']) . '</video:title>' . "\n";
                $xml .= '      <video:description>' . esc_html($video['description']) . '</video:description>' . "\n";
                
                if (!empty($video['content_loc'])) {
                    $xml .= '      <video:content_loc>' . esc_url($video['content_loc']) . '</video:content_loc>' . "\n";
                }
                
                if (!empty($video['player_loc'])) {
                    $xml .= '      <video:player_loc>' . esc_url($video['player_loc']) . '</video:player_loc>' . "\n";
                }
                
                if (!empty($video['duration'])) {
                    $xml .= '      <video:duration>' . esc_html($video['duration']) . '</video:duration>' . "\n";
                }
                
                if (!empty($video['expiration_date'])) {
                    $xml .= '      <video:expiration_date>' . esc_html($video['expiration_date']) . '</video:expiration_date>' . "\n";
                }
                
                $xml .= '    </video:video>' . "\n";
            }
            
            $xml .= '  </url>' . "\n";
        }
        
        $xml .= '</urlset>';
        
        return $xml;
    }
}
