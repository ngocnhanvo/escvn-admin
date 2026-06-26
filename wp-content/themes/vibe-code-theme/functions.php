<?php
function vibe_code_enqueue_assets() {
    // Nạp toàn bộ file CSS và JS từ thư mục dist của Astro
    // Lưu ý: Sau này ông copy thư mục 'assets' từ dist vào theme này
    
    $theme_uri = get_template_directory_uri();

    // Bạn có thể đăng ký các file CSS/JS chính ở đây
    // Vì Astro thường băm nhỏ tên file (ví dụ: index.b421.js), 
    // nên cách đơn giản nhất là nhúng trực tiếp ở index.php (xem bước 3).
}
add_action('wp_enqueue_scripts', 'vibe_code_enqueue_assets');

/**
 * Ép font không chân (Sans-serif) cho toàn bộ khu vực Admin và trình soạn thảo
 */
function vcs_force_admin_sans_serif_font() {
    echo '<style>
        /* 1. Tác động vào toàn bộ giao diện Admin */
        body, #wpwrap, .wp-editor-area, .editor-styles-wrapper {
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
        }

        /* 2. Tác động riêng vào tiêu đề và các ô nhập liệu của WooCommerce */
        #titlediv #title, 
        .post-type-product .editor-post-title__block textarea,
        .wp-editor-container textarea.wp-editor-area {
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
        }

        /* 3. Sửa lỗi hiển thị trong khung soạn thảo Classic (nếu bạn dùng Classic Editor) */
        #qt_content_toolbar, .mce-content-body {
            font-family: "Segoe UI", Roboto, Helvetica, Arial, sans-serif !important;
        }
    </style>';
}
add_action('admin_head', 'vcs_force_admin_sans_serif_font');

/**
 * 4. Quan trọng: Tác động vào bên trong iframe của TinyMCE (nếu dùng Classic Editor)
 */
function vcs_tinymce_fix_font($mceInit) {
    $styles = 'body { font-family: Verdana, Arial, Helvetica, sans-serif !important; }';
    if (isset($mceInit['content_style'])) {
        $mceInit['content_style'] .= ' ' . $styles;
    } else {
        $mceInit['content_style'] = $styles;
    }
    return $mceInit;
}
add_filter('tiny_mce_before_init', 'vcs_tinymce_fix_font');

add_action('rest_api_init', function () {
    register_rest_field('product', 'price', array(
        'get_callback' => function ($post_array) {
            $product = wc_get_product($post_array['id']);
            return $product->get_price();
        },
        'update_callback' => null,
        'schema'          => null,
    ));
});

add_filter( 'rest_product_query', 'wixdev_filter_products_by_cat_slug', 10, 2 );

function wixdev_filter_products_by_cat_slug( $args, $request ) {
    // Kiểm tra xem trên URL có truyền tham số product_cat_slug không
    $cat_slug = $request->get_param( 'product_cat_slug' );
    
    if ( ! empty( $cat_slug ) ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => $cat_slug,
            ),
        );
    }
    
    return $args;
}

add_action('rest_api_init', 'register_custom_product_attributes_field');

function register_custom_product_attributes_field() {
    register_rest_field('product', 'attributes', [
        'get_callback' => 'get_custom_product_attributes',
        'update_callback' => null,
        'schema'          => null,
    ]);
}


function get_custom_product_attributes($object) {
    $product_id = $object['id'];
    $product = wc_get_product($product_id);
    
    if (!$product) {
        return [];
    }

    $attributes = [];
    foreach ($product->get_attributes() as $attribute_name => $attribute) {
        // Lấy tên hiển thị của thuộc tính
        $name = wc_attribute_label($attribute_name);
        
        // Lấy các giá trị (options) của thuộc tính
        if ($attribute->is_taxonomy()) {
            $options = wc_get_product_terms($product_id, $attribute_name, array('fields' => 'names'));
        } else {
            $options = $attribute->get_options();
        }

        $attributes[] = [
            'name'    => $name,
            'slug'    => $attribute_name,
            'options' => $options
        ];
    }

    return $attributes;
}

add_action('woocommerce_product_duplicate', 'v_headless_auto_link_via_acf', 10, 2);
function v_headless_auto_link_via_acf($duplicate_product, $original_product) {
    
    $original_id  = $original_product->get_id();
    $duplicate_id = $duplicate_product->get_id();

    // 1. Ép sản phẩm mới chuyển sang ngôn ngữ tiếng Anh
    update_field('product_lang', 'en', $duplicate_id);

    // 2. Lấy ID của sản phẩm gốc điền vào trường liên kết của sản phẩm mới
    update_field('origin_product_id', $original_id, $duplicate_id);
    
    // Thuận tiện hơn: Gắn ngược lại cho sản phẩm gốc biết nó đang có một bản dịch là ID mới này
    // (Tạo mối liên kết 2 chiều để sau này dễ đồng bộ kho)
    update_field('translated_product_id', $duplicate_id, $original_id);
}

add_action('woocommerce_reduce_order_stock', 'v_headless_sync_stock_acf');
function v_headless_sync_stock_acf($order) {
    foreach ( $order->get_items() as $item ) {
        $product_id = $item->get_product_id();
        $quantity   = $item->get_quantity();

        // Kiểm tra xem sản phẩm vừa mua là bản gốc hay bản dịch
        $origin_id     = get_field('origin_product_id', $product_id);
        $translated_id = get_field('translated_product_id', $product_id);

        // Trường hợp 1: Nếu khách mua bản tiếng Anh -> Trừ kho bản tiếng Việt gốc
        if ( $origin_id ) {
            $origin_product = wc_get_product($origin_id);
            if ( $origin_product && $origin_product->managing_stock() ) {
                wc_update_product_stock($origin_product, $quantity, 'decrease');
            }
        }

        // Trường hợp 2: Nếu khách mua bản tiếng Việt gốc -> Trừ kho bản tiếng Anh
        if ( $translated_id ) {
            $translated_product = wc_get_product($translated_id);
            if ( $translated_product && $translated_product->managing_stock() ) {
                wc_update_product_stock($translated_product, $quantity, 'decrease');
            }
        }
    }
}

// 3. TUYỆT CHIÊU: Xóa bỏ hoàn toàn ký hiệu tiền tệ (₫) và cấu hình số lẻ theo đồng tiền ACF
add_filter( 'wc_price_args', 'custom_acf_remove_currency_symbol_and_set_decimals', 99, 1 );

function custom_acf_remove_currency_symbol_and_set_decimals( $args ) {
	$post_id = get_the_id();
	if ( $post_id ) {
		$current_value = get_post_meta( $post_id, 'currency', true );
		$field_object = get_field_object( 'currency', $post_id );

		$label = $current_value; // Mặc định lấy value
		if ( $field_object && isset( $field_object['choices'][$current_value] ) ) {
			$label = $field_object['choices'][$current_value];
		}
		
		$args['price_format'] = '%2$s ' . $label;
		if ( 'en' === $current_value ) {
			$args['decimals'] = 2;
		} else {
			$args['decimals'] = 0;
		}
	}
    return $args;
}

add_filter('rest_prepare_product', 'force_acf_select_labels_universal', 10, 3);
add_filter('rest_prepare_page', 'force_acf_select_labels_universal', 10, 3);
add_filter('rest_prepare_post', 'force_acf_select_labels_universal', 10, 3);

function force_acf_select_labels_universal($response, $post, $request) {
	// 1. Kiểm tra nếu không có dữ liệu ACF thì bỏ qua luôn cho nhẹ máy
    if ( empty($response->data['acf']) || !is_array($response->data['acf']) ) {
        return $response;
    }
	
    // Kiểm tra nếu bài viết có dữ liệu ACF
    if ( isset($response->data['acf']) && !empty($response->data['acf']) ) {
        
        foreach ( $response->data['acf'] as $field_key => $value ) {
            // Lấy thông tin cấu hình gốc của Field dựa vào tên trường
            $field_info = acf_get_field($field_key);
            
            // Nếu đúng là trường select hoặc radio và có cài đặt choices
            if ( $field_info && ($field_info['type'] === 'select' || $field_info['type'] === 'radio') ) {
                $choices = $field_info['choices'];
                
                // Trường hợp chọn nhiều (Array)
                if ( is_array($value) ) {
                    $new_value = [];
                    foreach ( $value as $val ) {
                        $new_value[] = [
                            'value' => $val,
                            'label' => isset($choices[$val]) ? $choices[$val] : $val
                        ];
                    }
                    $response->data['acf'][$field_key] = $new_value;
                } 
                // Trường hợp chọn single (Chuỗi/Số)
                else if ( !empty($value) || $value === '0' || $value === 0 ) {
                    $response->data['acf'][$field_key] = [
                        'value' => $value,
                        'label' => isset($choices[$value]) ? $choices[$value] : $value
                    ];
                }
            }
        }
    }
    return $response;
}

add_action( 'admin_footer', 'custom_sync_acf_currency_by_pure_name' );

function custom_sync_acf_currency_by_pure_name() {
    global $post_type;
    
    // Chỉ chạy mã này khi đang ở màn hình chỉnh sửa sản phẩm trong Admin
    if ( 'product' === $post_type ) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            function syncCurrency() {
                // 1. Nhắm chính xác thẻ select dựa theo thuộc tính [data-name] của ACF
                var currentLang = $('.acf-field[data-name="product_lang"] select').val();
                var $currencySelect = $('.acf-field[data-name="currency"] select');

                if (currentLang && $currencySelect.length) {
                    // 2. Ép giá trị Đồng tiền nhảy theo Ngôn ngữ (vi -> vi, en -> en)
                    $currencySelect.val(currentLang).change();
                    
                    // 3. Khóa cứng ô Đồng tiền bằng CSS để user chỉ xem chứ không sửa được
                    /*$currencySelect.css({
                        'background-color': '#f0f0f0',
                        'pointer-events': 'none',
                        'touch-action': 'none'
                    });*/
                }
            }

            // Chạy kiểm tra và đồng bộ ngay khi vừa load trang sản phẩm
            setTimeout(syncCurrency, 0);

            // 4. Lắng nghe sự kiện mỗi khi user click thay đổi ô "Chọn ngôn ngữ"
            $(document).on('change', '.acf-field[data-name="product_lang"] select', function() {
                syncCurrency();
            });

            // 5. Mở khóa tạm thời lúc submit để WordPress không bị chặn lưu dữ liệu vào DB
            $('#post').on('submit', function() {
                $('.acf-field[data-name="currency"] select').css('pointer-events', 'auto');
            });
        });
        </script>
        <?php
    }
}

// Xóa ký hiệu tiền tệ (₫) hoặc ($) ở các nhãn ô nhập trong trang sửa sản phẩm
add_action( 'admin_footer', 'custom_remove_currency_symbol_from_inputs_label' );

function custom_remove_currency_symbol_from_inputs_label() {
    global $post_type;
    
    // Chỉ chạy mã này khi đang ở màn hình chỉnh sửa sản phẩm
    if ( 'product' === $post_type ) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            function removeLabelsCurrency() {
                // Nhắm vào tất cả các nhãn có chứa ký hiệu tiền tệ bọc trong ngoặc của WooCommerce
                $('.options_group label').each(function() {
                    var labelText = $(this).html();
                    
                    // Tiến hành xóa bỏ các chuỗi dạng (₫) hoặc ($) hoặc các ký tự tiền tệ khác
                    var cleanedText = labelText.replace(/\s*\([^)]*₫[^)]*\)/g, '')
                                               .replace(/\s*\([^)]*\$[^)]*\)/g, '')
                                               .replace(/\s*\(₫\)/g, '')
                                               .replace(/\s*\(currency\)/g, '');
                    
                    $(this).html(cleanedText);
                });
            }

            // Chạy ngay khi load trang và chạy lại sau 500ms để đảm bảo không bị sót
            removeLabelsCurrency();
            setTimeout(removeLabelsCurrency, 500);
        });
        </script>
        <?php
    }
}

$theme_dir = get_stylesheet_directory();
require_once $theme_dir . '/inc/acf-page.php';
require_once $theme_dir . '/inc/image.php';
// Đường dẫn đến thư mục chứa các file cần import (ví dụ: thư mục inc)
$inc_dir = $theme_dir . '/inc/page/*.php';
// glob() sẽ tự động lấy tất cả file .php và sắp xếp theo tên file (A-Z)
$files = glob($inc_dir);
if (!empty($files)) {
    foreach ($files as $file) {
        require_once $file;
    }
}
// Tắt Emoji và các link rác để tối ưu tốc độ API
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_generator');