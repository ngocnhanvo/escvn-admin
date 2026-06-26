<?php
/**
 * 1. Chuyển URL gốc của file đính kèm thành đường dẫn tương đối
 */
add_filter( 'wp_get_attachment_url', 'make_attachment_url_relative_strictly' );
function make_attachment_url_relative_strictly( $url ) {
    // Lấy domain sạch (loại bỏ http://, https:// và dấu / ở cuối)
    $clean_home_url = preg_replace( '/^https?:\/\//i', '', home_url() );
    
    // Xóa bỏ cả giao thức và tên miền để giữ lại dạng /wp-content/...
    return preg_replace( '/^https?:\/\/' . preg_quote( $clean_home_url, '/' ) . '/i', '', $url );
}

/**
 * 2. Ép dữ liệu JSON trả về cho trình duyệt (Classic Editor/Gutenberg JS) phải dùng link tương đối
 */
add_filter( 'wp_prepare_attachment_for_js', 'make_attachment_js_url_relative', 10, 3 );
function make_attachment_js_url_relative( $response, $attachment, $meta ) {
    $clean_home_url = preg_replace( '/^https?:\/\//i', '', home_url() );
    $regex = '/^https?:\/\/' . preg_quote( $clean_home_url, '/' ) . '/i';

    if ( isset( $response['url'] ) ) {
        $response['url'] = preg_replace( $regex, '', $response['url'] );
    }
    if ( isset( $response['link'] ) ) {
        $response['link'] = preg_replace( $regex, '', $response['link'] );
    }
    // Xử lý toàn bộ các size ảnh thu nhỏ (thumbnail, medium, large...)
    if ( isset( $response['sizes'] ) && is_array( $response['sizes'] ) ) {
        foreach ( $response['sizes'] as $size => $size_data ) {
            if ( isset( $size_data['url'] ) ) {
                $response['sizes'][$size]['url'] = preg_replace( $regex, '', $size_data['url'] );
            }
        }
    }
    return $response;
}

/**
 * 3. Chặn cuối khi Classic Editor render chuỗi HTML để quăng vào khung soạn thảo
 */
add_filter( 'media_send_to_editor', 'force_relative_url_on_send_to_editor', 99, 3 );
function force_relative_url_on_send_to_editor( $html, $id, $attachment ) {
    $clean_home_url = preg_replace( '/^https?:\/\//i', '', home_url() );
    // Tìm bất kỳ chuỗi nào chứa http://domain hoặc https://domain và xóa domain đó đi
    return preg_replace( '/https?:\/\/' . preg_quote( $clean_home_url, '/' ) . '/i', '', $html );
}

function vibe_code_transform_acf_images($response, $post, $request) {
    if ( ! isset( $response->data['acf'] ) ) {
        return $response;
    }

    $image_fields = ['logo', 'favicon', 'image', 'mascot'];

    foreach ( $image_fields as $field ) {
        if ( isset( $response->data['acf'][$field] ) && is_numeric( $response->data['acf'][$field] ) ) {
            $attachment_id = $response->data['acf'][$field];
            $url = wp_get_attachment_url( $attachment_id );

            if ( $url ) {
                $response->data['acf'][$field] = [
                    'id'    => (int) $attachment_id,
                    'url'   => $url, // Giữ URL đầy đủ cho Headless CMS
                    'alt'   => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
                ];
            }
        }
    }
    return $response;
}

add_action('init', function() {
    // Lấy danh sách post types có dùng REST API
    $post_types = get_post_types(['show_in_rest' => true], 'names');

    foreach ($post_types as $pt) {
        add_filter("rest_prepare_{$pt}", 'vibe_code_transform_acf_images', 10, 3);
    }
});