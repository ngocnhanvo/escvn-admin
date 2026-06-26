<?php
/**
 * Plugin Name: GitHub Trigger
 * Description: Kích hoạt build GITHUB Pages từ WordPress admin
 * Version: 1.0.0
 * Author: Vibe Code Studio
 */

if (!defined('ABSPATH')) exit;

// ============================================================
    // CẤU HÌNH MỚI CHO GITHUB
    // ============================================================
    define('VCS_GH_OWNER', 'ngocnhanvo');              // Username GitHub của bạn
    define('VCS_GH_REPO', 'escvn');                   // Tên Repository trên GitHub
    define('VCS_GH_TOKEN', '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');   // GitHub Personal Access Token (Fine-grained hoặc Classic)
// ============================================================

if ( ! function_exists( 'get_site_domain' ) ) {
    function get_site_domain(): string {
        $posts = get_posts([
            'post_type'      => 'thong-tin-chung',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
        ]);

        if (empty($posts)) return '';

        $domain = function_exists('get_field')
            ? get_field('domain', $posts[0]->ID)
            : get_post_meta($posts[0]->ID, 'domain', true);

        $domain = preg_replace('#^https?://#', '', trim((string) $domain));
        $domain = rtrim($domain, '/');

        return $domain;
    }
}

class VCS_Build_Trigger {

    /**
     * URL xem trước — GitHub Pages preview branch.
     * Quy tắc mặc định: thêm "preview." vào trước domain chính.
     * Chỉnh lại hàm này nếu URL preview của bạn có cấu trúc khác.
     */
    private function get_preview_url(): string {
        $domain = get_site_domain();
        return $domain ? "https://{$domain}/preview" : '';
    }

    /**
     * URL chính thức — domain thực của website.
     */
    private function get_production_url(): string {
        $domain = get_site_domain();
        return $domain ? "https://{$domain}" : '';
    }

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_bar_menu', [$this, 'add_toolbar_button'], 100);
        add_action('wp_ajax_vcs_trigger_build', [$this, 'trigger_build']);
        add_action('wp_ajax_vcs_check_build_status', [$this, 'check_build_status']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('wp_before_admin_bar_render', [$this, 'enqueue_toolbar_scripts']);
    }

    // ── Menu trong sidebar admin ──────────────────────────────
    public function add_admin_menu() {
        // Menu cha
        add_menu_page(
            'GitHub',
            'GitHub',
            'manage_options',
            'vcs-build-trigger',
            [$this, 'render_admin_page'],
            'dashicons-cloud-upload',
            3
        );

        // Menu con 1: Build Website
        add_submenu_page(
            'vcs-build-trigger',
            'Build Website',
            '🚀 Build Website',
            'manage_options',
            'vcs-build-trigger',        // slug trùng menu cha → không tạo trang mới
            [$this, 'render_admin_page']
        );

        // Menu con 2: Xem trước (Preview)
        add_submenu_page(
            'vcs-build-trigger',
            'Xem Website Xem Trước',
            '🔍 Chế độ xem trước',
            'manage_options',
            'vcs-preview-site',
            [$this, 'render_preview_page']
        );

        // Menu con 3: Xem chính thức (Production)
        add_submenu_page(
            'vcs-build-trigger',
            'Xem Website Chính Thức',
            '🌐 Chế độ chính thức',
            'manage_options',
            'vcs-production-site',
            [$this, 'render_production_page']
        );
    }

    // ── Nút trên toolbar trên cùng ───────────────────────────
    public function add_toolbar_button($wp_admin_bar) {
        if (!current_user_can('manage_options')) return;

        // Node cha
        $wp_admin_bar->add_node([
            'id'    => 'vcs-build-btn',
            'title' => 'GitHub',
            'href'  => '#',
            'meta'  => ['class' => 'vcs-toolbar-build-btn'],
        ]);

        // Node con 1: Build Website
        $wp_admin_bar->add_node([
            'id'     => 'vcs-toolbar-build',
            'parent' => 'vcs-build-btn',
            'title'  => '🚀 Build Website',
            'href'   => admin_url('admin.php?page=vcs-build-trigger'),
            'meta'   => ['class' => 'vcs-toolbar-build-action'],
        ]);

        // Node con 2: Xem trước
        $wp_admin_bar->add_node([
            'id'     => 'vcs-toolbar-preview',
            'parent' => 'vcs-build-btn',
            'title'  => '🔍 Xem ở chế độ xem trước',
            'href'   => admin_url('admin.php?page=vcs-preview-site'),
			'meta'   => [
                'target' => 'vcs_site_preview'
            ],
        ]);

        // Node con 3: Xem chính thức
        $wp_admin_bar->add_node([
            'id'     => 'vcs-toolbar-production',
            'parent' => 'vcs-build-btn',
            'title'  => '🌐 Xem ở chế độ chính thức',
            'href'   => admin_url('admin.php?page=vcs-production-site'),
			'meta'   => [
                'target' => 'vcs_site_publish' // Đưa target vào trong meta
            ],
        ]);
    }

    // ── Enqueue scripts cho admin pages ──────────────────────
    public function enqueue_scripts($hook) {
        $allowed_hooks = [
            'toplevel_page_vcs-build-trigger',
            'build-website_page_vcs-preview-site',
            'build-website_page_vcs-production-site',
        ];
        if (!in_array($hook, $allowed_hooks)) return;
        $this->enqueue_build_scripts();
    }

    // ── Enqueue scripts cho toolbar (mọi trang admin) ────────
    public function enqueue_toolbar_scripts() {
        if (!current_user_can('manage_options')) return;
        $this->enqueue_build_scripts();
    }

    private function enqueue_build_scripts() {
        wp_enqueue_script(
            'vcs-build-trigger',
            plugin_dir_url(__FILE__) . 'vcs-build-trigger.js',
            ['jquery'],
            '1.0.0',
            true
        );
        wp_localize_script('vcs-build-trigger', 'vcsBuild', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('vcs_build_nonce'),
        ]);

        // Inline CSS cho toolbar button
        wp_add_inline_style('admin-bar', '
            #wp-admin-bar-vcs-build-btn > a {
                background: #00ffcc !important;
                color: #000 !important;
                font-weight: bold !important;
            }
            #wp-admin-bar-vcs-build-btn > a:hover {
                background: #00ccaa !important;
            }
            #vcs-toast {
                position: fixed;
                bottom: 30px;
                right: 30px;
                padding: 16px 24px;
                border-radius: 8px;
                font-size: 15px;
                font-weight: bold;
                color: #fff;
                z-index: 99999;
                display: none;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            }
            #vcs-toast.success { background: #00a854; }
            #vcs-toast.error   { background: #e53935; }
            #vcs-toast.loading { background: #1976d2; }
        ');
    }

    // ── Trang admin dashboard (Build) ─────────────────────────
    public function render_admin_page() {
        $last_build = get_option('vcs_last_build', []);
        ?>
        <div class="wrap">
            <h1>🚀 Build Website lên GitHub</h1>
            <p style="color:#666;">Nhấn nút bên dưới để build và deploy website mới nhất lên GitHub Pages.</p>

            <div style="margin: 30px 0; padding: 24px; background: #fff; border: 1px solid #ddd; border-radius: 8px; max-width: 600px;">
                <button id="vcs-build-btn-main" class="button button-primary" style="font-size:16px; padding: 10px 30px; height:auto;">
                    🚀 Build Website Ngay
                </button>

                <div id="vcs-build-status" style="margin-top: 20px; display:none;">
                    <div id="vcs-status-icon" style="font-size: 24px; margin-bottom: 8px;"></div>
                    <div id="vcs-status-text" style="font-size: 15px; color: #333;"></div>
                    <div id="vcs-progress-bar" style="margin-top:12px; height:6px; background:#eee; border-radius:3px; display:none;">
                        <div id="vcs-progress-fill" style="height:100%; background:#00ffcc; border-radius:3px; width:0%; transition: width 0.5s;"></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($last_build)): ?>
            <div style="padding: 16px; background: #f9f9f9; border: 1px solid #eee; border-radius: 6px; max-width: 600px;">
                <strong>Lần build gần nhất:</strong><br>
                Thời gian: <?php echo esc_html($last_build['time'] ?? '—'); ?><br>
                <?php echo esc_html($last_build['status'] ?? '—'); ?>
            </div>
            <?php endif; ?>

            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <a target="vcs_site_preview" href="<?php echo esc_url(admin_url('admin.php?page=vcs-preview-site')); ?>" class="button">
                    🔍 Xem ở chế độ xem trước
                </a>
                <a target="vcs_site_publish" href="<?php echo esc_url(admin_url('admin.php?page=vcs-production-site')); ?>" class="button">
                    🌐 Xem ở chế độ chính thức
                </a>
            </div>
        </div>

        <!-- Toast notification -->
        <div id="vcs-toast"></div>
        <?php
    }

    // ── Trang xem trước (Preview) ─────────────────────────────
    public function render_preview_page() {
        $preview_url = $this->get_preview_url();
        ?>
        <div class="wrap">
            <?php if (empty($preview_url)): ?>
                <div class="notice notice-warning" style="max-width:600px;">
                    <p>
                        ⚠️ Chưa tìm thấy domain. Vui lòng điền field <strong>Tên miền</strong>
                        trong mục <strong>Thông tin chung</strong> trước.
                    </p>
                </div>
            <?php else: ?>
                <div style="margin-top: 16px; border: 2px solid #00a0d2; border-radius: 8px; overflow: hidden; max-width: 100%;">
                    <div style="background: #00a0d2; padding: 10px 16px; display: flex; align-items: center; gap: 12px;">
                        <span style="color:#fff; font-weight:bold; font-size:13px;">🔍 Xem Trước:</span>
                        <span style="background:#fff; color:#333; padding: 4px 12px; border-radius: 4px; font-size:13px; flex:1;">
                            <?php echo esc_html($preview_url); ?>
                        </span>
                        <a href="<?php echo esc_url($preview_url); ?>" target="_blank"
                           style="color:#fff; font-size:13px; text-decoration:none; white-space:nowrap;">
                            Mở tab mới ↗
                        </a>
                    </div>
                    <iframe
                        src="<?php echo esc_url($preview_url); ?>"
                        style="width:100%; height:90vh; border:none; display:block;"
                        loading="lazy"
                    ></iframe>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── Trang xem chính thức (Production) ────────────────────
    public function render_production_page() {
        $production_url = $this->get_production_url();
        ?>
        <div class="wrap">
            <?php if (empty($production_url)): ?>
                <div class="notice notice-warning" style="max-width:600px;">
                    <p>
                        ⚠️ Chưa tìm thấy domain. Vui lòng điền field <strong>Tên miền</strong>
                        trong mục <strong>Thông tin chung</strong> trước.
                    </p>
                </div>
            <?php else: ?>
                <div style="margin-top: 16px; border: 2px solid #00a854; border-radius: 8px; overflow: hidden; max-width: 100%;">
                    <div style="background: #00a854; padding: 10px 16px; display: flex; align-items: center; gap: 12px;">
                        <span style="color:#fff; font-weight:bold; font-size:13px;">🌐 Chính Thức:</span>
                        <span style="background:#fff; color:#333; padding: 4px 12px; border-radius: 4px; font-size:13px; flex:1;">
                            <?php echo esc_html($production_url); ?>
                        </span>
                        <a href="<?php echo esc_url($production_url); ?>" target="_blank"
                           style="color:#fff; font-size:13px; text-decoration:none; white-space:nowrap;">
                            Mở tab mới ↗
                        </a>
                    </div>
                    <iframe
                        src="<?php echo esc_url($production_url); ?>"
                        style="width:100%; height:90vh; border:none; display:block;"
                        loading="lazy"
                    ></iframe>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    // ── AJAX: Trigger build (Đã chuyển sang GitHub) ───────────────────────────
    public function trigger_build() {
        check_ajax_referer('vcs_build_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền thực hiện thao tác này.']);
        }

        $owner  = VCS_GH_OWNER;
        $repo   = VCS_GH_REPO;
        $token  = VCS_GH_TOKEN;

        // Endpoint tạo Repository Dispatch event trên GitHub
        $url = "https://api.github.com/repos/{$owner}/{$repo}/dispatches";

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent'    => 'WordPress-VCS-Build-Trigger', // GitHub bắt buộc phải có User-Agent
            ],
            'body' => json_encode([
                'event_type' => 'wp_trigger_build', // Tên event để GitHub Action nhận diện
                'client_payload' => [
                    'triggered_by' => 'WordPress Admin',
                    'site_domain'  => $this->get_site_domain()
                ]
            ]),
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Lỗi kết nối GitHub: ' . $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);

        // GitHub API trả về 204 No Content nếu kích hoạt thành công
        if ($code === 204) {
            update_option('vcs_last_build', [
                'time'   => current_time('d/m/Y H:i:s'),
                'status' => 'Đã gửi tín hiệu lên GitHub Actions...',
                'id'     => '',
            ]);

            wp_send_json_success([
                'message' => 'Đã kích hoạt GitHub Action thành công!',
            ]);
        } else {
            $body_text = wp_remote_retrieve_body($response);
            wp_send_json_error([
                'message' => 'GitHub từ chối yêu cầu. HTTP ' . $code . ' - ' . $body_text,
            ]);
        }
    }

    // ── AJAX: Check build status ──────────────────────────────
    public function check_build_status() {
        check_ajax_referer('vcs_build_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Không có quyền.']);
        }

        $api_token    = VCS_CF_API_TOKEN;
        $account_id   = VCS_CF_ACCOUNT_ID;
        $project_name = VCS_CF_PROJECT_NAME;

        if (empty($api_token) || empty($account_id)) {
            wp_send_json_success(['status' => 'unknown', 'message' => 'Chưa cấu hình API Token.']);
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$account_id}/pages/projects/{$project_name}/deployments";
        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_token,
                'Content-Type'  => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $body        = json_decode(wp_remote_retrieve_body($response), true);
        $deployments = $body['result'] ?? [];
        $latest      = $deployments[0] ?? null;

        if (!$latest) {
            wp_send_json_success(['status' => 'unknown', 'message' => 'Không tìm thấy deployment.']);
        }

        $cf_status = $latest['latest_stage']['status'] ?? '';
        $deploy_id = $latest['id'] ?? '';

        $created_on  = $latest['created_on'] ?? '';
        $ended_on    = $latest['latest_stage']['ended_on'] ?? '';
        $status_text = "Đang xử lý...";

        if ($cf_status === 'success' || $cf_status === 'failure') {
            $start = strtotime($created_on);
            $end   = $ended_on ? strtotime($ended_on) : time();
            $secs  = max(0, $end - $start);

            $h = floor($secs / 3600);
            $m = floor(($secs % 3600) / 60);
            $s = $secs % 60;

            $dur_part  = ($h > 0 ? "{$h} giờ " : "") . ($m > 0 || $h > 0 ? "{$m} phút " : "") . ($h == 0 ? "{$s} giây" : "");
            $date_part = date('d/m/Y H:i', $end);

            if ($cf_status === 'success') {
                $status_text = "Hoàn thành sau " . trim($dur_part) . " vào ngày " . $date_part;
            } else {
                $status_text = "Lỗi sau " . trim($dur_part) . " vào ngày " . $date_part;
            }
        }

        $status_map = [
            'queued'       => ['status' => 'loading', 'message' => '⏳ Đang chờ build...'],
            'initializing' => ['status' => 'loading', 'message' => '🔧 Đang khởi tạo...'],
            'building'     => ['status' => 'loading', 'message' => '🏗️ Đang build website...'],
            'deploying'    => ['status' => 'loading', 'message' => '🚀 Đang deploy...'],
            'success'      => ['status' => 'success', 'message' => $status_text],
            'failure'      => ['status' => 'error',   'message' => $status_text, 'dashboard_url' => "https://dash.cloudflare.com/{$account_id}/pages/view/{$project_name}/{$deploy_id}"],
        ];

        $result = $status_map[$cf_status] ?? ['status' => 'loading', 'message' => "$cf_status"];

        $last_build = get_option('vcs_last_build', []);
        $last_build['status'] = $result['message'];
        $last_build['time']   = current_time('d/m/Y H:i:s');
        update_option('vcs_last_build', $last_build);

        wp_send_json_success($result);
    }
}

new VCS_Build_Trigger();