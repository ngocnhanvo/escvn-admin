<?php
add_action( 'template_redirect', 'redirect_api_domain_to_frontend' );

function redirect_api_domain_to_frontend() {
    // Kiểm tra nếu đang truy cập ngoài Front-end (không phải trang Admin backend)
    if ( ! is_admin() ) {
        
        // Lấy domain hiện tại đang chạy
        $current_host = $_SERVER['HTTP_HOST'] ?? '';
        
        // Nếu đúng là đang chạy trên domain API
        if ( $current_host ) {
            
            global $wp;
            // Lấy slug của request hiện tại (ví dụ: gioi-thieu)
            $current_slug = $wp->request ?? ''; 
            
            // Nếu slug trống (trang chủ), hoặc chỉ định đích danh 'gioi-thieu'
			if ( $current_slug ) {
				$domain = get_site_domain();
				
				// Tự động xác định giao thức: nếu là localhost hoặc không phải SSL thì dùng http
				$protocol = ( is_ssl() || strpos( $domain, 'localhost' ) === false ) ? 'https://' : 'http://';
				
				// Nếu bạn muốn ép buộc tất cả localhost dùng http, có thể viết rõ ràng hơn:
				if ( strpos( $domain, 'localhost' ) !== false ) {
					$protocol = 'http://';
				} else {
					$protocol = is_ssl() ? 'https://' : 'http://';
				}

				$target_url = $protocol . $domain . '/preview/' . $current_slug;
				
				wp_redirect( $target_url, 302 );
				exit;
			}
        }
    }
}