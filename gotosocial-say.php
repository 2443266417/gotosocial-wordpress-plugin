<?php
/*
Plugin Name: GoToSocial 说说展示
Description: 在 WordPress 页面中通过短代码展示 GoToSocial 的说说内容，支持图片放大、评论展示、加载更多和 Access Token 授权。优化为朋友圈风格展示。
Version: 2.0
Author: 段先森
*/

if (!defined('ABSPATH')) exit;

// ---------------- 后台设置 ------------------
function gts_register_settings() {
    add_option('gts_username', 'yourusername');
    add_option('gts_instance', 'https://duanbo.cc');
    add_option('gts_per_page', 10);
    add_option('gts_access_token', '');
    register_setting('gts_options_group', 'gts_username');
    register_setting('gts_options_group', 'gts_instance');
    register_setting('gts_options_group', 'gts_per_page');
    register_setting('gts_options_group', 'gts_access_token');
}
add_action('admin_init', 'gts_register_settings');

function gts_register_options_page() {
    add_options_page('GoToSocial 设置', 'GoToSocial 设置', 'manage_options', 'gts', 'gts_options_page');
}
add_action('admin_menu', 'gts_register_options_page');

function gts_options_page() {
?>
    <div>
        <h2>GoToSocial 说说展示 设置</h2>
        <form method="post" action="options.php">
            <?php settings_fields('gts_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">GoToSocial 用户名</th>
                    <td><input type="text" name="gts_username" value="<?php echo esc_attr(get_option('gts_username')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">实例地址（如：https://duanbo.cc）</th>
                    <td><input type="text" name="gts_instance" value="<?php echo esc_attr(get_option('gts_instance')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">每页加载数量</th>
                    <td><input type="number" name="gts_per_page" value="<?php echo esc_attr(get_option('gts_per_page')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Access Token（可选）</th>
                    <td><input type="text" name="gts_access_token" value="<?php echo esc_attr(get_option('gts_access_token')); ?>" size="60" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
<?php }

// ---------------- 图片处理函数 ------------------
function gts_filter_images($html) {
    return preg_replace_callback('/<img.*?src=["\']([^"\']+)["\'].*?>/i', function($matches) {
        $src = esc_url($matches[1]);
        return '<a href="' . $src . '" class="gts-image-link">' . $matches[0] . '</a>';
    }, $html);
}

// ---------------- 评论输出函数 ------------------
function gts_fetch_comments_html($status_id, $instance, $headers) {
    $url = "$instance/api/v1/statuses/$status_id/context";
    $res = wp_remote_get($url, ['headers' => $headers]);
    if (is_wp_error($res)) return '';
    $data = json_decode(wp_remote_retrieve_body($res), true);
    if (empty($data['descendants'])) return '';

    $html = '<div class="gts-comment-list">';
    foreach ($data['descendants'] as $comment) {
        $account = $comment['account'] ?? [];
        $avatar = esc_url($account['avatar'] ?? '');
        $name = esc_html($account['display_name'] ?? $account['username'] ?? '');
        $content = gts_filter_images($comment['content']);
        $html .= "<div class='gts-comment'><img src='{$avatar}' class='gts-avatar'><div class='gts-comment-body'><strong>{$name}</strong><div>{$content}</div></div></div>";
    }
    $html .= '</div>';
    return $html;
}

// ---------------- 状态获取 + 图片输出 ------------------
function gts_fetch_statuses_html($username, $instance, $limit = 10, $max_id = '') {
    // --- 缓存优化开始 ---
    $cache_key = 'gts_statuses_' . md5("{$username}_{$instance}_{$limit}_{$max_id}");
    
    // 如果是首页请求 (无 max_id) 且有缓存，直接返回缓存
    if (empty($max_id) && ($cached_html = get_transient($cache_key))) {
        return $cached_html; 
    }
    // --- 缓存优化结束 ---
    $headers = [];
    $token = trim(get_option('gts_access_token'));
    if (!empty($token)) {
        $headers['Authorization'] = 'Bearer ' . $token;
    }

    $lookup_url = "$instance/api/v1/accounts/lookup?acct=$username";
    $lookup_res = wp_remote_get($lookup_url, ['headers' => $headers]);
    if (is_wp_error($lookup_res)) return '无法连接到实例';
    $user = json_decode(wp_remote_retrieve_body($lookup_res), true);
    if (empty($user['id'])) return '未获取到用户信息';

    $statuses_url = "$instance/api/v1/accounts/{$user['id']}/statuses?limit=$limit";
    if (!empty($max_id)) $statuses_url .= "&max_id=" . urlencode($max_id);

    $res = wp_remote_get($statuses_url, ['headers' => $headers]);
    if (is_wp_error($res)) return '获取动态失败';
    $statuses = json_decode(wp_remote_retrieve_body($res), true);

    $html = '';
    foreach ($statuses as $status) {
        if (!empty($status['in_reply_to_id'])) continue;

        $account = $status['account'] ?? null;
        $avatar = esc_url($account['avatar'] ?? '');
        $display_name = esc_html($account['display_name'] ?? $account['username'] ?? '');

        $content = gts_filter_images($status['content']);

        // 多图统一包裹容器
        if (!empty($status['media_attachments'])) {
            $imgs_html = '<div class="gts-images">';
            foreach ($status['media_attachments'] as $media) {
                if ($media['type'] === 'image') {
                    $img_url = esc_url($media['url']);
                    $preview = esc_url($media['preview_url'] ?? $media['url']);
                    $imgs_html .= '<a class="gts-image-link" href="' . $img_url . '"><img src="' . $preview . '" /></a>';
                }
            }
            $imgs_html .= '</div>';
            $content .= $imgs_html;
        }

        $interactions = "<div class='gts-interactions'>";
        $interactions .= "<span>❤️ " . intval($status['favourites_count']) . "</span>";
        $interactions .= "<span>🔁 " . intval($status['reblogs_count']) . "</span>";
        $interactions .= "<span>💬 " . intval($status['replies_count']) . "</span>";
        $interactions .= "</div>";

        $comments_html = gts_fetch_comments_html($status['id'], $instance, $headers);

        $html .= "<div class='gts-status' data-id='{$status['id']}'>";
        $html .= "<div class='gts-header'><img class='gts-avatar' src='{$avatar}' /><span class='gts-display-name'>{$display_name}</span></div>";
        $html .= "<div class='gts-content'>{$content}</div>";
        $html .= "<div class='gts-meta'>" . date('Y-m-d H:i', strtotime($status['created_at'])) . "</div>";
        $html .= $interactions;
        $html .= "<div class='gts-comments'>{$comments_html}<a class='gts-view-comments-link' href='{$status['url']}' target='_blank' rel='noopener noreferrer'>查看更多评论</a></div>";
        $html .= "</div>";
    }
    // --- 缓存优化开始 ---
    // 如果是首页请求 (无 max_id)，将生成的 HTML 写入缓存 10 分钟
    if (empty($max_id)) {
        set_transient($cache_key, $html, 10 * MINUTE_IN_SECONDS);
    }
    return $html;
}

// ---------------- 短代码 + 加载更多按钮 ------------------
function gts_ajax_shortcode() {
    $username = get_option('gts_username');
    $instance = get_option('gts_instance');
    $limit = intval(get_option('gts_per_page', 10));
    $output = '<div id="gts-feed">';
    $output .= gts_fetch_statuses_html($username, $instance, $limit);
    $output .= '</div>';
    $output .= '<button id="gts-load-more">加载更多</button>';
    return $output;
}
add_shortcode('gotosocial_say_ajax', 'gts_ajax_shortcode');

// ---------------- AJAX 接口 ------------------
add_action('wp_ajax_gts_load_more', 'gts_ajax_load_more');
add_action('wp_ajax_nopriv_gts_load_more', 'gts_ajax_load_more');
function gts_ajax_load_more() {
    $max_id = sanitize_text_field($_POST['max_id'] ?? '');
    $username = get_option('gts_username');
    $instance = get_option('gts_instance');
    $limit = intval(get_option('gts_per_page', 10));
    echo gts_fetch_statuses_html($username, $instance, $limit, $max_id);
    wp_die();
}

// ---------------- JS 和样式 ------------------
function gts_assets() {
    wp_enqueue_script('magnific-popup', 'https://cdn.jsdelivr.net/npm/magnific-popup@1.1.0/dist/jquery.magnific-popup.min.js', ['jquery'], null, true);
    wp_enqueue_style('magnific-popup-css', 'https://cdn.jsdelivr.net/npm/magnific-popup@1.1.0/dist/magnific-popup.css');
    wp_enqueue_script('gts-script', plugin_dir_url(__FILE__) . 'gts-script.js', ['jquery'], null, true);
    wp_localize_script('gts-script', 'gts_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'instance' => get_option('gts_instance'),
        'token' => get_option('gts_access_token')
    ]);
}
add_action('wp_enqueue_scripts', 'gts_assets');

function gts_styles() {
    echo '<style>
    #gts-feed {
        max-width: 700px;
        margin: 0 auto;
        display: flex;
        flex-direction: column;
        gap: 20px;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    }
    .gts-status {
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        padding: 16px 20px;
    }
    .gts-header {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 10px;
    }
    .gts-avatar {
    width: 28px !important;
    height: 28px !important;
    border-radius: 50% !important;
    object-fit: cover !important;
    flex-shrink: 0 !important;
    }
    .gts-display-name {
        font-weight: bold;
        font-size: 15px;
    }
    .gts-content {
        font-size: 15px;
        line-height: 1.5;
        color: #333;
    }
    .gts-content p {
        margin-top: 6px;
        margin-bottom: 6px;
    }
    .gts-images {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 6px;
    }
    .gts-image-link {
        flex: 1 1 calc(33.333% - 8px);
        max-width: calc(33.333% - 8px);
        height: 100px;
        border-radius: 10px;
        overflow: hidden;
        cursor: zoom-in;
        box-shadow: 0 1px 6px rgba(0,0,0,0.1);
        display: block;
    }
    .gts-image-link img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 10px;
        transition: transform 0.3s ease;
    }
    .gts-image-link:hover img {
        transform: scale(1.05);
    }
    .gts-meta {
        margin-top: 10px;
        font-size: 12px;
        color: #888;
        text-align: right;
    }
    .gts-interactions {
        font-size: 13px;
        margin-top: 8px;
        display: flex;
        gap: 10px;
        color: #888;
    }
    .gts-comment-list {
        margin-top: 10px;
    }
    .gts-comment {
        display: flex;
        gap: 10px;
        margin-bottom: 8px;
        background: #fafafa;
        padding: 6px 10px;
        border-radius: 8px;
    }
    .gts-comment-body {
        font-size: 14px;
        line-height: 1.4;
        color: #333;
    }
    .gts-load-comments {
        background: transparent;
        border: none;
        color: #4a90e2;
        cursor: pointer;
        padding: 4px;
        font-size: 13px;
    }
    #gts-load-more {
        display: block;
        margin: 30px auto;
        padding: 10px 24px;
        background: #4a90e2;
        color: #fff;
        border: none;
        border-radius: 20px;
        cursor: pointer;
        font-size: 15px;
        box-shadow: 0 2px 8px rgba(74,144,226,0.3);
    }
    #gts-load-more:hover {
        background: #357ABD;
    }
    </style>';
}
add_action('wp_head', 'gts_styles');
