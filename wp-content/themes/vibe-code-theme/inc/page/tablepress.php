<?php
/// CỔNG AJAX NỘI BỘ WP: Trả về link ảnh nhanh cho trình soạn thảo
add_action('wp_ajax_get_tablepress_image_preview', function() {
    $table_id = isset($_POST['table_id']) ? sanitize_text_field($_POST['table_id']) : '';
    if (empty($table_id)) {
        wp_send_json_error();
    }
    $meta = get_option( 'tablepress_meta_' . $table_id, [] );
    $image_url = $meta['image'] ?? '';
    wp_send_json_success(['image' => $image_url]);
});

add_action('acf/input/admin_footer', 'tablepress_acf_quick_edit');
function tablepress_acf_quick_edit() {
    ?>
    <script type="text/javascript">
    (function($) {
        if (typeof acf === 'undefined') return;

        var hasWysiwyg = false;

        // 1. VÒNG GỬI XE: Kiểm tra xem trang này thực sự có ô WYSIWYG nào không
        acf.addAction('new_field', function(field) {
            if (field.get('type') === 'wysiwyg') {
                hasWysiwyg = true; 
            }
        });

        // 2. CHỈ CHẠY KHI ĐÃ XÁC NHẬN CÓ WYSIWYG VÀ TRANG ĐÃ LOAD XONG
        $(document).ready(function() {
            // Nếu không có ô WYSIWYG nào thì dừng toàn bộ, không tạo menu, không gán event
            if (!hasWysiwyg) return;

            // Hàm phân tích chuỗi bôi đen để tìm ID TablePress
            function extractTableId(text) {
                if (!text) return null;
                var longRegex = /\[table\s+id=["']?([a-zA-Z0-9_\-]+)["']?\s*\/\]/i;
                var match = longRegex.exec(text);
                if (match && match[1]) return match[1];

                if (/^[a-zA-Z0-9_\-]+$/.test(text)) return text;
                return null;
            }

            // Tạo khung menu chuột phải custom cố định (Chỉ chạy DUY NHẤT 1 LẦN)
            var baseEditUrl = '<?php echo admin_url("admin.php?page=tablepress&action=edit&table_id="); ?>';
            var $menu = $('<div id="tp-acf-menu" style="position:fixed; display:none; background:#fff; border:1px solid #ccd0d4; box-shadow:0 4px 10px rgba(0,0,0,0.2); z-index:99999999; border-radius:4px; padding:5px 0; min-width:200px;"><a id="tp-acf-link" href="#" target="_blank" style="display:block; padding:10px 15px; color:#0073aa; text-decoration:none; font-weight:bold; font-size:13px;">📝 Sửa bảng TablePress</a></div>');
            $('body').append($menu);
			
			// Hàm ẩn menu chung
            function hideMenu() {
                if ($menu && $menu.is(':visible')) {
                    $menu.hide();
                }
            }
			
            // Ẩn menu khi click ra ngoài
            $(document).on('mousedown', function(e) {
                if (!$menu.is(e.target) && $menu.has(e.target).length === 0) {
                    hideMenu();
                }
            });

            // 2.1. Xử lý chuột phải ở chế độ "Văn bản" (Text mode) của ACF Editor
            $(document).on('contextmenu', '.acf-field-wysiwyg textarea', function(e) {
                var selectedText = window.getSelection().toString().trim();
                var tableId = extractTableId(selectedText);

                if (tableId) {
                    e.preventDefault();
                    $menu.find('#tp-acf-link').attr('href', baseEditUrl + tableId).text('📝 Sửa bảng: ' + tableId);
                    $menu.css({ top: e.clientY + 'px', left: e.clientX + 'px' }).show();
                }
            });

            // 2.2. Xử lý chuột phải ở chế độ "Trực quan" (Visual mode - TinyMCE Iframe)
            setTimeout(function() {
                if (typeof tinyMCE !== 'undefined') {
                    tinyMCE.editors.forEach(function(editor) {
                        var $editorEl = $(editor.getElement());
                        if ($editorEl.closest('.acf-field-wysiwyg').length > 0) {
                            
                            editor.on('contextmenu', function(e) {
                                var selectedText = editor.selection.getContent({format: 'text'}).trim();
                                var tableId = extractTableId(selectedText);

                                if (tableId) {
                                    e.preventDefault();
                                    
                                    var iframeOffset = $(editor.iframeElement).offset();
                                    var top = iframeOffset.top + e.clientY - $(window).scrollTop();
                                    var left = iframeOffset.left + e.clientX;

                                    $menu.find('#tp-acf-link').attr('href', baseEditUrl + tableId).text('📝 Sửa bảng: ' + tableId);
                                    $menu.css({ top: top + 'px', left: left + 'px' }).show();
                                }
                            });
							
							editor.on('mousedown', function() {
                                hideMenu();
                            });

                            var cachedImages = {};
                            function loadTableImage(tableId, callback) {
                                if (cachedImages[tableId] !== undefined) {
                                    return callback(cachedImages[tableId]);
                                }
                                $.ajax({
                                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                                    method: 'POST',
                                    data: {
                                        action: 'get_tablepress_image_preview',
                                        table_id: tableId
                                    },
                                    success: function(response) {
                                        var url = (response && response.success) ? response.data.image : '';
                                        cachedImages[tableId] = url;
                                        callback(url);
                                    },
                                    error: function() {
                                        cachedImages[tableId] = '';
                                        callback('');
                                    }
                                });
                            }

                            // 1. ĐỊNH NGHĨA HÀM QUÉT ẢNH (Truyền editor vào trực tiếp để xử lý khi load trang)
                            function scanShortcodes(ed) {
                                if (!ed || typeof ed.getBody !== 'function') return;
                                var body = ed.getBody();
                                var $body = $(body);

                                // Duyệt qua từng dòng văn bản toàn bài
                                $body.find('p, div, h1, h2, h3, h4, h5, h6').each(function() {
                                    var $line = $(this);
                                    
                                    if ($line.closest('.tp-preview-block').length > 0) return;

                                    var $tempClone = $line.clone();
                                    $tempClone.find('.tp-preview-block').remove();
                                    var lineText = $tempClone.text().trim();

                                    var regexLine = /\[table\s+id=["']?([a-zA-Z0-9_\-]+)["']?\s*\/\]/i;
                                    var matchLine = regexLine.exec(lineText);

                                    if (matchLine && matchLine[1]) {
                                        var tableId = matchLine[1];

                                        // BƯỚC THAY ĐỔI CỐT LÕI: Nếu dòng có sẵn khối ảnh của ID này rồi (Do Paste vào) -> GIỮ NGUYÊN, THOÁT LUÔN
                                        var $existingPreview = $line.find('.tp-preview-block[data-id="' + tableId + '"]');
                                        if ($existingPreview.length > 0) return; 

                                        // Chỉ dọn các khối ảnh lạc loài của ID KHÁC nếu có (Xử lý khi người dùng đổi ID bảng)
                                        $line.find('.tp-preview-block').not('[data-id="' + tableId + '"]').remove();

                                        if ($line.attr('data-loading') === 'true') return;
                                        $line.attr('data-loading', 'true');

                                        loadTableImage(tableId, function(url) {
                                            $line.removeAttr('data-loading');

                                            if ($line.find('.tp-preview-block[data-id="' + tableId + '"]').length > 0) return;

                                            if (url && url.trim() !== '') {
                                                var $previewBlock = $('<div class="tp-preview-block" data-id="' + tableId + '" contenteditable="false" ' +
                                                    'style="margin: 10px 0; padding: 5px; background: #fff; border: 1px solid #ccd0d4; border-radius: 4px; display: table; max-width: 100%; ' +
                                                    'user-select: none; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; pointer-events: none;">' +
                                                    '<img class="tp-live-preview" src="' + url + '" style="display:block; max-height:150px; width:auto; object-fit:contain; pointer-events: none;" />' +
                                                    '</div>');
                                                $line.append($previewBlock);
                                            }
                                        });
                                    } else {
                                        if ($line.find('.tp-preview-block').length > 0) {
                                            $line.find('.tp-preview-block').remove();
                                        }
                                        $line.removeAttr('data-loading');
                                    }
                                });
                            }

                            // 2. GÁN SỰ KIỆN KHI NGƯỜI DÙNG THAO TÁC GÕ PHÍM
                            editor.on('KeyUp Change NodeChange', function() {
                                scanShortcodes(editor);
                            });
                            
                            // 3. XỬ LÝ CHO INIT: Ép chạy quét ngay lập tức dựa theo trạng thái sẵn sàng của TinyMCE
                            if (editor.initialized) {
                                scanShortcodes(editor);
                            } else {
                                editor.on('init', function() {
                                    scanShortcodes(editor);
                                });
                            }

                            editor.on('contextmenu click', function(e) {
                                if ($(e.target).hasClass('tp-live-preview') || $(e.target).closest('.tp-preview-block').length > 0) {
                                    e.preventDefault();
                                    e.stopPropagation();
                                    return false;
                                }
                            });

                            editor.on('copy', function(e) {
                                var selectedHtml = editor.selection.getContent({format: 'html'});
                                
                                // Nếu trong vùng bôi đen copy có dính khối ảnh preview
                                if (selectedHtml && selectedHtml.indexOf('tp-preview-block') !== -1) {
                                    e.preventDefault(); // Chặn hành vi copy mặc định dính file ảnh lỗi
                                    
                                    var $tempDiv = $('<div>').html(selectedHtml);
                                    // Lọc bỏ sạch sẽ khối ảnh ra khỏi bộ nhớ tạm
                                    $tempDiv.find('.tp-preview-block').remove();
                                    
                                    var cleanHtml = $tempDiv.html();
                                    if (e.clipboardData) {
                                        // Chỉ lưu lại chữ thô kèm shortcode sạch vào bộ nhớ
                                        e.clipboardData.setData('text/html', cleanHtml);
                                        var cleanText = $('<div>').html(cleanHtml).text();
                                        e.clipboardData.setData('text/plain', cleanText);
                                    }
                                }
                            });
                            
                        }
                    });
                }
            }, 2000);
        });

    })(jQuery);
    </script>
    <?php
}

add_action('admin_head', 'tablepress_inject_fields_by_jquery');
function tablepress_inject_fields_by_jquery() {
    global $pagenow;

    if ( 'admin.php' === $pagenow && isset($_GET['page']) && $_GET['page'] === 'tablepress' && isset($_GET['action']) && $_GET['action'] === 'edit' ) {
        
        $table_id = isset($_GET['table_id']) ? sanitize_text_field($_GET['table_id']) : '';
        if ( !empty($table_id) ) {
			wp_enqueue_media();
            $meta = get_option( 'tablepress_meta_' . $table_id, [] );
			$val_api = $meta['api'] ?? '';
			$val_image = $meta['image'] ?? '';
            ?>
            <script type="text/javascript">
            (function() {
                // 1. Lưu lại hàm fetch gốc của trình duyệt
                const originalFetch = window.fetch;

                // 2. Ghi đè window.fetch để chặn đường request
                window.fetch = async function(...args) {
                    let [resource, config] = args;

                    // Kiểm tra nếu request gửi đi có chứa body dạng chuỗi hoặc URLSearchParams và là request lưu của TablePress
                    if (config && config.body) {
                        let bodyStr = '';
                        
                        if (typeof config.body === 'string') {
                            bodyStr = config.body;
                        } else if (config.body instanceof URLSearchParams) {
                            bodyStr = config.body.toString();
                        }

                        // Đúng request lưu bảng của TablePress dựa theo Payload thực tế của bạn
                        if (bodyStr.indexOf('action=tablepress_save_table') !== -1) {
                            
                            // Lấy giá trị thực tế từ 2 ô input
                            const currentAAA = document.getElementById('js_custom_api') ? document.getElementById('js_custom_api').value : '';
                            const currentBBB = document.getElementById('js_custom_image') ? document.getElementById('js_custom_image').value : '';

                            // Nối thêm dữ liệu AAA và BBB vào body
                            const additionalData = `&custom_api=${encodeURIComponent(currentAAA)}&custom_image=${encodeURIComponent(currentBBB)}`;
                            
                            if (typeof config.body === 'string') {
                                config.body += additionalData;
                            } else if (config.body instanceof URLSearchParams) {
                                config.body = new URLSearchParams(bodyStr + additionalData);
                            }
                        }
                    }

                    // Tiếp tục thực thi hàm fetch gốc với tham số đã được bổ sung dữ liệu
                    return originalFetch.apply(this, args);
                };
            })();

            jQuery(window).on('load', function() {
                var $ = jQuery;
                
                // 3. Render giao diện 2 ô input ra màn hình Edit
                var htmlForm = `
					<div id="tablepress-custom-meta-fields">
						<div class="inside" style="padding: 15px;">
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row" style="width:150px;">liên kết API:</th>
										<td><input type="text" name="custom_api" id="js_custom_api" value="<?php echo esc_attr($val_api); ?>" class="large-text" style="width:100%;" /></td>
									</tr>
									<tr>
										<th scope="row">Hình ảnh:</th>
										<td>
											<div style="display:flex; gap:10px; align-items:center;">
												<input type="text" name="custom_image" id="js_custom_image" value="<?php echo esc_attr($val_image); ?>" class="large-text" style="flex-grow:1; margin:0;" />
												<button type="button" id="js_btn_upload_image" class="button button-secondary">Chọn ảnh</button>
											</div>
										</td>
									</tr>
								</tbody>
							</table>
						</div>
					</div>`;
                
                var $editForm = $('#tablepress_edit-table-information').first();
                if ($editForm.length > 0) {
                    $editForm.append(htmlForm);
					
					// 4. XỬ LÝ SỰ KIỆN CLICK NÚT SELECTION FILE
					$(document).on('click', '#js_btn_upload_image', function(e) {
						e.preventDefault();

						// Khởi tạo khung Media
						var frame = wp.media({
							title: 'Chọn hoặc tải hình ảnh lên',
							button: { text: 'Sử dụng hình ảnh này' },
							multiple: false // Chỉ cho phép chọn 1 file
						});

						// Khi người dùng chọn xong ảnh và bấm nút quyết định
						frame.on('select', function() {
							var attachment = frame.state().get('selection').first().toJSON();
							var fullUrl = attachment.url; // URL tuyệt đối ban đầu từ WP

							// Sử dụng Regex để cắt bỏ giao thức + tên miền, giữ lại đường dẫn tương đối từ dấu gạch chéo đầu tiên (Vd: /wp-content/...)
							var relativeUrl = fullUrl.replace(/^https?:\/\/[^\/]+/, '');

							// Điền đường dẫn tương đối vào ô text input
							$('#js_custom_image').val(relativeUrl);
						});

						// Mở popup Media lên
						frame.open();
					});
                }
            });
            </script>
            <?php
        }
    }
}

add_action('wp_ajax_tablepress_save_table', function() {

    if (empty($_POST['tablepress']['id'])) {
        return;
    }

    $table_id = sanitize_text_field(
        $_POST['tablepress']['id']
    );

    update_option(
        'tablepress_meta_' . $table_id,
        [
            'api' => sanitize_text_field($_POST['custom_api'] ?? ''),
            'image' => sanitize_text_field($_POST['custom_image'] ?? ''),
        ]
    );

}, 1);

add_action( 'rest_api_init', 'register_tablepress_json_api_endpoint' );

function register_tablepress_json_api_endpoint() {
    register_rest_route( 'tablepress/v1', '/table/(?P<id>[a-zA-Z0-9_\-]+)', array(
        'methods'             => 'GET',
        'callback'            => 'get_tablepress_table_json',
        'permission_callback' => '__return_true', // Để '__return_true' nếu muốn ai cũng gọi được, hoặc cấu hình phân quyền nếu cần
    ) );
}

function get_tablepress_table_json( $data ) {
    // 1. Kiểm tra class TablePress có tồn tại không
    if ( ! class_exists( 'TablePress' ) ) {
        return new WP_Error( 'tablepress_missing', 'Plugin TablePress chưa được kích hoạt', array( 'status' => 500 ) );
    }

    $table_id = $data['id'];

    try {
        // 2. Gọi Model của TablePress để load dữ liệu bảng
        $table = TablePress::$model_table->load( $table_id );
        
        if ( is_wp_error( $table ) || empty( $table ) ) {
            return new WP_Error( 'table_not_found', 'Không tìm thấy bảng yêu cầu', array( 'status' => 404 ) );
        }

        // 3. Lấy ra mảng dữ liệu các hàng/cột (data) và các custom fields (options/visibility) nếu cần
        $table_data = $table['data']; 

        // Lấy thêm 2 trường custom (api và image) bạn đã lưu ở bước trước (nếu có)
        $meta = get_option( 'tablepress_meta_' . $table_id, [] );
        
        // 4. Trả về cấu trúc JSON mong muốn
		$table_data = $table['data'];

		$keys   = $table_data[0] ?? [];
		$labels = $table_data[1] ?? [];

		/**
		 * Build fields
		 */
		$fields = [];

		foreach ($keys as $index => $key) {
			$fields[] = [
				'key'   => $key,
				'label' => $labels[$index] ?? $key,
			];
		}

		/**
		 * Build items
		 */
		$items = [];

		for ($i = 2; $i < count($table_data); $i++) {

			$row = $table_data[$i];

			$item = [];

			foreach ($keys as $index => $key) {
				$item[$key] = $row[$index] ?? '';
			}

			$items[] = $item;
		}
        
		// 4. Gom dữ liệu cần trả về vào mảng
        $response_data = [
            'success' => true,
            'id' => $table_id,
            'component' => $meta['component'] ?? '',
            'title' => $table['name'],
            'description' => $table['description'],
            'fields' => $fields,
            'items' => $items,
            'settings' => [
                'columns' => $meta['columns'] ?? 4,
            ],
            'meta' => [
                'api'   => $meta['api'] ?? '',
                'image' => $meta['image'] ?? '',
            ]
        ];

        // Trả về JSON chuẩn tiếng Việt và ngắt luồng xử lý an toàn
        wp_send_json( $response_data, 200, JSON_UNESCAPED_UNICODE );

    } catch ( Exception $e ) {
        return new WP_Error( 'server_error', $e->getMessage(), array( 'status' => 500 ) );
    }
}

