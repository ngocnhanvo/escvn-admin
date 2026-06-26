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
                $target_url = 'https://'.$domain.'/preview/'.$current_slug;
                wp_redirect( $target_url, 302 );
                exit;
            }
        }
    }
}