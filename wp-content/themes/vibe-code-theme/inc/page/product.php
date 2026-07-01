<?php
// --- 1. ĐĂNG KÝ TRƯỜNG TAX_DATA ---
register_rest_field('product', 'tax_data', [
    'get_callback' => function($post_array) {
        $product = wc_get_product($post_array['id']);
        if (!$product) return null;

        $tax_class = $product->get_tax_class(); 
        $tax_status = $product->get_tax_status();
        $tax_rate_percent = 0;

        if ($tax_status === 'taxable') {
            $tax_rates = WC_Tax::get_rates($tax_class);
            if (!empty($tax_rates)) {
                $first_rate = reset($tax_rates);
                $tax_rate_percent = isset($first_rate['rate']) ? floatval($first_rate['rate']) : 0;
            }
        }
        
        return [
            'tax_status'  => $tax_status,
            'tax_class'   => $tax_class,
            'tax_percent' => $tax_rate_percent
        ];
    },
    'schema' => null,
]);

// --- 2. ĐĂNG KÝ TRƯỜNG PRICE_REGISTER ---
register_rest_field('product', 'price_register', [
    'get_callback' => function($post_array) {
        $product = wc_get_product($post_array['id']);
        if (!$product) return null;
        
        $current_product_id = $product->get_id(); // ID của sản phẩm hiện tại đang gọi API
        
        // 1. Tạo filter tạm thời ép SQL dùng LIKE 'DKTM-%' để ăn Index
        $prefix_filter = function($where, $wp_query) {
            global $wpdb;
            $where = str_replace(
                "LIKE '%" . $wpdb->esc_like('DKTM-') . "%'", 
                "LIKE '" . $wpdb->esc_like('DKTM-') . "%'", 
                $where
            );
            return $where;
        };

        // 2. Kích hoạt filter
        add_filter('posts_where', $prefix_filter, 10, 2);

        // 3. Thực hiện truy vấn SQL lấy ID coupon cực nhẹ
        $coupon_query = new WP_Query([
            'posts_per_page' => -1,
            'post_type'      => 'shop_coupon',
            'post_status'    => 'publish',
            's'              => 'DKTM-',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        // 4. Hủy bỏ filter ngay lập tức
        remove_filter('posts_where', $prefix_filter, 10);

        $coupon_ids = $coupon_query->posts;
        
        // Lấy giá bán hiện tại của sản phẩm
        $current_price = floatval($product->get_price()); 
        $discount_amount = 0;

        // 5. Duyệt qua danh sách ID để tính toán giảm giá
		foreach ($coupon_ids as $coupon_id) {
			
			// Đọc trực tiếp danh sách ID sản phẩm được áp dụng từ Meta Data
			$allowed_product_ids_raw = get_post_meta($coupon_id, 'product_ids', true);
			
			if (!empty($allowed_product_ids_raw)) {
				$allowed_product_ids = is_array($allowed_product_ids_raw) 
					? array_map('intval', $allowed_product_ids_raw) 
					: array_map('intval', explode(',', $allowed_product_ids_raw));

				if (!in_array($current_product_id, $allowed_product_ids, true)) {
					continue;
				}
			}
			
			$coupon_code = get_the_title($coupon_id);
			$coupon = new WC_Coupon($coupon_code);
			
			// --- THAY THẾ KHU VỰC IS_VALID() BẰNG CHECK ĐIỀU KIỆN THỦ CÔNG CHO API ---
			$expiry_date = $coupon->get_date_expires();
			$current_time = current_time('timestamp'); // Lấy thời gian hiện tại của website

			// Nếu coupon có hạn sử dụng VÀ thời gian hiện tại đã vượt quá hạn sử dụng -> Bỏ qua
			if ( $expiry_date && $current_time > $expiry_date->getTimestamp() ) {
				continue;
			}

			// Nếu thỏa mãn (còn hạn hoặc không giới hạn ngày), tiến hành tính toán luôn
			$discount_type = $coupon->get_discount_type();
			$coupon_amount = floatval($coupon->get_amount());

			if ($discount_type === 'fixed_product') {
				$discount_amount += $coupon_amount;
			} elseif ($discount_type === 'percent') {
				$discount_amount += ($current_price * ($coupon_amount / 100));
			}
		}
        
        // Giá đăng ký cuối cùng
        $price_register = max(0, $current_price - $discount_amount);
        
        return $price_register;
    },
    'schema' => null,
]);