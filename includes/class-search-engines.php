<?php
/**
 * 多搜索引擎支持类
 * 支持百度、Google、Bing、360 等搜索引擎的 sitemap 提交
 */

if (!defined('ABSPATH')) {
    exit;
}

class CP_Search_Engines {
    
    /**
     * 搜索引擎配置
     */
    private $engines = array(
        'baidu' => array(
            'name' => '百度',
            'sitemap_param' => 'sitemap',
            'api_url' => 'http://data.zz.baidu.com/urls?site={site}&token={token}',
            'api_name' => '普通收录 API',
            'dashboard_url' => 'https://ziyuan.baidu.com/',
            'has_push_api' => true,
        ),
        'google' => array(
            'name' => 'Google',
            'sitemap_param' => 'sitemap',
            'api_url' => 'https://searchconsole.google.com/api/webmasters/sites/{site}/sitemaps/{sitemap}',
            'api_name' => 'Search Console API',
            'dashboard_url' => 'https://search.google.com/search-console',
            'has_push_api' => false,
        ),
        'bing' => array(
            'name' => 'Bing',
            'sitemap_param' => 'sitemap',
            'api_url' => 'https://ssl.bing.com/webmaster/api.svc/json/SubmitSitemap',
            'api_name' => 'Webmaster API',
            'dashboard_url' => 'https://www.bing.com/webmasters',
            'has_push_api' => true,
        ),
        'so360' => array(
            'name' => '360 搜索',
            'sitemap_param' => 'sitemap',
            'api_url' => 'https://data.so.360.cn/submit/site_submit?site={site}',
            'api_name' => '站长平台',
            'dashboard_url' => 'https://zhanzhang.so.com/',
            'has_push_api' => false,
        ),
        'sogou' => array(
            'name' => '搜狗',
            'sitemap_param' => 'sitemap',
            'api_url' => 'https://zhanzhang.sogou.com/api/sitemap/submit',
            'api_name' => '站长平台',
            'dashboard_url' => 'https://zhanzhang.sogou.com/',
            'has_push_api' => false,
        ),
    );
    
    /**
     * 获取所有支持的搜索引擎
     */
    public function get_all_engines() {
        return $this->engines;
    }
    
    /**
     * 获取指定引擎配置
     */
    public function get_engine($engine_name) {
        return isset($this->engines[$engine_name]) ? $this->engines[$engine_name] : null;
    }
    
    /**
     * 获取启用的引擎列表
     */
    public function get_enabled_engines() {
        $settings = get_option('cp_seo_settings', array());
        $enabled = array();
        
        foreach ($this->engines as $name => $config) {
            if (isset($settings['engines'][$name]) && $settings['engines'][$name]) {
                $enabled[$name] = $config;
            }
        }
        
        return $enabled;
    }
    
    /**
     * 提交 sitemap 到搜索引擎
     */
    public function submit_sitemap($engine_name, $sitemap_url) {
        $engine = $this->get_engine($engine_name);
        if (!$engine) {
            return array('success' => false, 'message' => '不支持的搜索引擎');
        }
        
        $settings = get_option('cp_seo_settings', array());
        $site = parse_url(home_url(), PHP_URL_HOST);
        
        switch ($engine_name) {
            case 'baidu':
                return $this->submit_baidu($sitemap_url, $site, $settings);
                
            case 'google':
                return $this->submit_google($sitemap_url, $site, $settings);
                
            case 'bing':
                return $this->submit_bing($sitemap_url, $site, $settings);
                
            case 'so360':
                return $this->submit_so360($sitemap_url, $site, $settings);
                
            case 'sogou':
                return $this->submit_sogou($sitemap_url, $site, $settings);
                
            default:
                return array('success' => false, 'message' => '未实现的引擎');
        }
    }
    
    /**
     * 提交到百度
     */
    private function submit_baidu($sitemap_url, $site, $settings) {
        if (empty($settings['baidu_token'])) {
            return array('success' => false, 'message' => '未配置百度 Token');
        }
        
        $api_url = str_replace(
            array('{site}', '{token}'),
            array(urlencode($site), urlencode($settings['baidu_token'])),
            $this->engines['baidu']['api_url']
        );
        
        $response = wp_remote_post($api_url, array(
            'headers' => array('Content-Type' => text/plain'),
            'body'    => $sitemap_url,
            'timeout' => 15,
        ));
        
        return $this->process_response($response, '百度');
    }
    
    /**
     * 提交到 Google
     */
    private function submit_google($sitemap_url, $site, $settings) {
        // Google 主要通过 Search Console 界面提交，API 需要 OAuth2 认证
        // 这里提供提交指引
        return array(
            'success' => true,
            'message' => '请在 Google Search Console 中手动提交：' . $sitemap_url,
            'dashboard_url' => $this->engines['google']['dashboard_url'],
        );
    }
    
    /**
     * 提交到 Bing
     */
    private function submit_bing($sitemap_url, $site, $settings) {
        if (empty($settings['bing_api_key'])) {
            return array('success' => false, 'message' => '未配置 Bing API Key');
        }
        
        $response = wp_remote_post($this->engines['bing']['api_url'], array(
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'siteUrl' => home_url('/'),
                'sitemapUrl' => $sitemap_url,
            )),
            'timeout' => 15,
        ));
        
        return $this->process_response($response, 'Bing');
    }
    
    /**
     * 提交到 360
     */
    private function submit_so360($sitemap_url, $site, $settings) {
        // 360 主要通过站长平台界面提交
        return array(
            'success' => true,
            'message' => '请在 360 站长平台中手动提交：' . $sitemap_url,
            'dashboard_url' => $this->engines['so360']['dashboard_url'],
        );
    }
    
    /**
     * 提交到搜狗
     */
    private function submit_sogou($sitemap_url, $site, $settings) {
        // 搜狗主要通过站长平台界面提交
        return array(
            'success' => true,
            'message' => '请在搜狗站长平台中手动提交：' . $sitemap_url,
            'dashboard_url' => $this->engines['sogou']['dashboard_url'],
        );
    }
    
    /**
     * 处理 API 响应
     */
    private function process_response($response, $engine_name) {
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $engine_name . '请求失败：' . $response->get_error_message(),
            );
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($http_code === 200) {
            return array(
                'success' => true,
                'message' => $engine_name . '提交成功',
                'response' => $result,
            );
        } else {
            $error_msg = isset($result['message']) ? $result['message'] : 'HTTP ' . $http_code;
            return array(
                'success' => false,
                'message' => $engine_name . '提交失败：' . $error_msg,
                'response' => $result,
            );
        }
    }
    
    /**
     * 批量推送 URL 到百度
     */
    public function batch_push_urls($urls, $engine_name = 'baidu') {
        if ($engine_name !== 'baidu') {
            return array('success' => false, 'message' => '暂不支持其他引擎批量推送');
        }
        
        $settings = get_option('cp_seo_settings', array());
        if (empty($settings['baidu_token'])) {
            return array('success' => false, 'message' => '未配置百度 Token');
        }
        
        $site = parse_url(home_url(), PHP_URL_HOST);
        $api_url = 'http://data.zz.baidu.com/urls?site=' . urlencode($site) . '&token=' . urlencode($settings['baidu_token']);
        
        // 分批推送，每批 500 条
        $chunks = array_chunk($urls, 500);
        $total_pushed = 0;
        
        foreach ($chunks as $chunk) {
            $response = wp_remote_post($api_url, array(
                'headers' => array('Content-Type' => 'text/plain'),
                'body'    => implode("\n", $chunk),
                'timeout' => 60,
            ));
            
            $result = $this->process_response($response, '百度');
            if ($result['success']) {
                $total_pushed += count($chunk);
            } else {
                return $result;
            }
        }
        
        return array(
            'success' => true,
            'message' => '成功推送 ' . $total_pushed . ' 条 URL',
        );
    }
}
