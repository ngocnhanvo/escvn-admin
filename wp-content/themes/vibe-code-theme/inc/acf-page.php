<?php
// File: wp-content/themes/your-theme/inc/acf-settings.php
// Lưu ý: Nhớ phải có thẻ mở <?php ở đầu file nhé!

function update_page_title_from_acf( $post_id ) {
    if ( get_post_type($post_id) !== 'page' ) return;
    $acf_title = get_field('label', $post_id); // Đổi lại tên field của bạn

    if ( $acf_title ) {
        remove_action( 'acf/save_post', 'update_page_title_from_acf', 20 );
        wp_update_post( array(
            'ID'         => $post_id,
            'post_title' => $acf_title,
            'post_name'  => sanitize_title($acf_title)
        ) );
        add_action( 'acf/save_post', 'update_page_title_from_acf', 20 );
    }
}
add_action( 'acf/save_post', 'update_page_title_from_acf', 20 );

// Code JS đồng bộ thời gian thực cho Gutenberg (Đã sửa lỗi vòng lặp)
function admin_sync_acf_to_gutenberg_title() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->base !== 'post' ) return;
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Chờ cho Gutenberg và ACF tải xong hoàn toàn
            setTimeout(function() {
                if ( typeof wp !== 'undefined' && typeof wp.data !== 'undefined' ) {
                    
                    // Lấy selector ô nhập liệu dựa trên cấu trúc hình ảnh của bạn (Field có nhãn "Tên trang")
                    // Đi thẳng vào class chung của ACF để tránh viết sai data-name
                    var $acfInput = $('.acf-field[data-name="label"] input, .acf-field input').first();
                    
                    if ( $acfInput.length ) {
                        // Lắng nghe sự kiện khi người dùng gõ chữ hoặc thả chuột vào ô ACF
                        $acfInput.on('input propertychange change', function() {
                            var acfValue = $(this).val();
                            var currentGutenbergTitle = wp.data.select('core/editor').getEditedPostAttribute('title');
                            
                            // Chỉ cập nhật lên trên nếu giá trị thực sự khác nhau
                            if ( acfValue !== currentGutenbergTitle ) {
                                wp.data.dispatch('core/editor').editPost({ title: acfValue });
                            }
                        });
                    }
                }
            }, 1000); // Trì hoãn 1 giây để đảm bảo mọi phần tử React đã render xong
        });
    </script>
    <?php
}
add_action('admin_footer', 'admin_sync_acf_to_gutenberg_title');

add_filter('gettext', 'doi_chu_tieu_de_thanh_ten_trang', 20, 3);
function doi_chu_tieu_de_thanh_ten_trang($translated_text, $text, $domain) {
    global $pagenow;
    
    // Chỉ áp dụng trong trang quản trị (wp-admin) và tại trang danh sách Trang (edit.php?post_type=page)
    if (is_admin() && $pagenow === 'edit.php' && isset($_GET['post_type']) && $_GET['post_type'] === 'page') {
        if ($text === 'Title') {
            return 'Tên trang';
        }
    }
    return $translated_text;
}

add_action('save_post_page', 'dong_bo_tieu_de_sang_acf_label', 10, 3);
function dong_bo_tieu_de_sang_acf_label($post_id, $post, $update) {
    // Nếu là bản tự động lưu (autosave) hoặc bản nháp hệ thống thì bỏ qua
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Kiểm tra quyền chỉnh sửa của tài khoản hiện tại
    if (!current_user_can('edit_page', $post_id)) {
        return;
    }

    // Lấy tiêu đề mới vừa được nhập
    $title = get_the_title($post_id);

    // Cập nhật giá trị tiêu đề này vào trường ACF có key (hoặc field name) là 'label'
    // Sử dụng update_post_meta để ăn chắc cho cả lưu nhanh lẫn lưu chậm
    update_post_meta($post_id, 'label', $title);
}

add_action('admin_footer', 'restrict_gutenberg_toggle_by_css');
function restrict_gutenberg_toggle_by_css() {
    ?>
    <style>
        /* 1. Vô hiệu hóa toàn bộ lượt click vào nút tiêu đề lớn */
		.acf-field, .acf-field .acf-label, .acf-field .acf-input {
			position: unset;
		}
		.edit-post-meta-boxes-main__presenter, .select2-container.-acf {
			z-index: 0;
		}
        .edit-post-meta-boxes-main__presenter button[aria-expanded] {
            pointer-events: none !important;
            cursor: default !important;
        }

        /* 2. Nhả quyền click RIÊNG cho icon mũi tên SVG và vùng bọc của nó */
        .edit-post-meta-boxes-main__presenter button[aria-expanded] svg {
            pointer-events: auto !important;
            cursor: pointer !important;
            padding: 4px;
            border-radius: 4px;
        }

        /* Hiệu ứng đổi màu nhẹ khi rê chuột trúng mũi tên */
        .edit-post-meta-boxes-main__presenter button[aria-expanded] svg:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
    </style>
    <?php
}

add_filter( 'block_editor_settings_all', 'disable_gutenberg_autosave', 10, 2 );

function disable_gutenberg_autosave( $editor_settings, $editor_context ) {
    // Thay đổi khoảng thời gian autosave của Gutenberg thành 24 tiếng (tính bằng giây)
    $editor_settings['autosaveInterval'] = 86400; 
    return $editor_settings;
}

add_action( 'wp_creating_autosave', 'block_autosave_database_write' );

function block_autosave_database_write( $autosave ) {
    // Trả về một lỗi WP_Error sẽ ngăn WordPress chèn bản ghi lưu nháp vào DB
    return new WP_Error( 'autosave_disabled', 'Đã tắt tính năng tự động lưu nháp.' );
}