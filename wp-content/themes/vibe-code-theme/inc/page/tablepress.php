<?php

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

            // Escape ký tự đặc biệt để dùng an toàn trong RegExp
            function escapeRegExp(str) {
                return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            // Tìm vị trí (start/end) của TRỌN VẸN đoạn "[table id=xxx /]" gần với vùng đã bôi đen nhất,
            // để dù người dùng chỉ bôi đen mỗi "xxx" thì vẫn xóa đúng cả dòng shortcode, không để sót "[table id=/]"
            function findFullShortcodeRange(val, tableId, approxStart, approxEnd) {
                var regex = new RegExp('\\[table\\s+id=["\']?' + escapeRegExp(tableId) + '["\']?\\s*\\/\\]', 'gi');
                var match, best = null, bestDist = Infinity;

                while ((match = regex.exec(val)) !== null) {
                    var mStart = match.index;
                    var mEnd = match.index + match[0].length;
                    var dist;

                    if (approxEnd >= mStart && approxStart <= mEnd) {
                        dist = 0; // vùng đã chọn nằm trong (hoặc chồng lấn) đoạn shortcode này
                    } else if (approxStart > mEnd) {
                        dist = approxStart - mEnd;
                    } else {
                        dist = mStart - approxEnd;
                    }

                    if (dist < bestDist) {
                        bestDist = dist;
                        best = { start: mStart, end: mEnd };
                    }
                }

                return best;
            }

            // (Chế độ Văn bản) Xóa TRỌN VẸN đoạn [table id=...] đang được chọn, cùng ảnh đi kèm NGAY SAU đoạn đó.
            // Quan trọng: khi cùng 1 bảng được chèn nhiều lần, id="tablepress-img-xxx" sẽ bị TRÙNG NHAU giữa các ảnh,
            // nên không thể chỉ dựa vào id để xóa (sẽ xóa nhầm/xóa hết mọi ảnh trùng id). Thay vào đó, ta xác định
            // đúng VỊ TRÍ của shortcode đang xóa, rồi chỉ xóa ảnh xuất hiện ngay sau vị trí đó (và không có shortcode nào khác chen giữa).
            function removeTableAndImageFromTextarea(val, tableId, selStart, selEnd) {
                var shortcodeRange = findFullShortcodeRange(val, tableId, selStart, selEnd);
                var rangesToRemove = [];
                var shortcodeEnd;

                if (shortcodeRange) {
                    rangesToRemove.push(shortcodeRange);
                    shortcodeEnd = shortcodeRange.end;
                } else {
                    rangesToRemove.push({ start: selStart, end: selEnd });
                    shortcodeEnd = selEnd;
                }

                // Tìm ảnh đi kèm: chỉ chấp nhận nếu nó xuất hiện NGAY SAU shortcode này (trước khi gặp 1 shortcode [table id=...] khác)
                var imgRegex = new RegExp('<img[^>]*\\bid=["\']tablepress-img-' + escapeRegExp(tableId) + '["\'][^>]*>\\s*', 'i');
                var searchText = val.slice(shortcodeEnd);
                var imgMatch = imgRegex.exec(searchText);

                if (imgMatch) {
                    var beforeImg = searchText.slice(0, imgMatch.index);
                    if (!/\[table\s+id=/i.test(beforeImg)) {
                        var imgStart = shortcodeEnd + imgMatch.index;
                        var imgEnd = imgStart + imgMatch[0].length;
                        rangesToRemove.push({ start: imgStart, end: imgEnd });
                    }
                }

                // Xóa từ cuối văn bản về đầu để tránh lệch offset giữa các đoạn xóa
                rangesToRemove.sort(function(a, b) { return b.start - a.start; });
                var newVal = val;
                rangesToRemove.forEach(function(r) {
                    newVal = newVal.substring(0, r.start) + newVal.substring(r.end);
                });

                return newVal;
            }

            // Tìm text node trong TinyMCE chứa TRỌN VẸN đoạn "[table id=xxx /]" gần vị trí đang chọn/click chuột phải
            function locateShortcodeTextNode(editorRef, tableId) {
                var regex = new RegExp('\\[table\\s+id=["\']?' + escapeRegExp(tableId) + '["\']?\\s*\\/\\]', 'i');
                var rng = editorRef.selection.getRng();
                var container = rng.startContainer;

                if (container.nodeType === 3 && regex.test(container.data)) {
                    return { textNode: container, match: regex.exec(container.data) };
                }

                var block = editorRef.dom.getParent(container, editorRef.dom.isBlock) || editorRef.getBody();
                var walker = editorRef.dom.doc.createTreeWalker(block, NodeFilter.SHOW_TEXT, null, false);
                var node;
                while ((node = walker.nextNode())) {
                    if (regex.test(node.data)) {
                        return { textNode: node, match: regex.exec(node.data) };
                    }
                }
                return null;
            }

            // Duyệt DOM theo đúng thứ tự tài liệu, bắt đầu từ startNode, để tìm ảnh có id trùng khớp XUẤT HIỆN SAU nó đầu tiên.
            // Cách này tránh xóa nhầm ảnh khi có nhiều bảng giống nhau (cùng id) trong cùng nội dung.
            function findFollowingImageById(editorRef, startNode, tableId) {
                if (!startNode) return null;
                var targetId = 'tablepress-img-' + tableId;
                var walker = editorRef.dom.doc.createTreeWalker(editorRef.getBody(), NodeFilter.SHOW_ELEMENT, null, false);
                var passedStart = false;
                var node;

                while ((node = walker.nextNode())) {
                    if (!passedStart) {
                        if (node === startNode) {
                            passedStart = true;
                        }
                        continue;
                    }
                    if (node.nodeName === 'IMG' && node.id === targetId) {
                        return node;
                    }
                }
                return null;
            }

            // (Chế độ Trực quan) Xóa TRỌN VẸN đoạn [table id=...] đang được chọn, cùng ảnh đi kèm đúng vị trí (không xóa nhầm ảnh trùng id)
            function removeTableAndImageFromEditor(editorRef, tableId) {
                var found = locateShortcodeTextNode(editorRef, tableId);
                var startBlock = found ? (editorRef.dom.getParent(found.textNode, editorRef.dom.isBlock) || found.textNode) : null;

                // 1. Xác định ảnh đi kèm ĐÚNG của lần xuất hiện này TRƯỚC KHI xóa chữ (để còn định vị được trong DOM)
                var imgNode = findFollowingImageById(editorRef, startBlock, tableId);

                // 2. Xóa dòng [table id=...]
                if (found) {
                    var newRng = editorRef.dom.createRng();
                    newRng.setStart(found.textNode, found.match.index);
                    newRng.setEnd(found.textNode, found.match.index + found.match[0].length);
                    editorRef.selection.setRng(newRng);
                }
                editorRef.selection.setContent('');

                // 3. Xóa đúng ảnh tương ứng
                if (imgNode) {
                    editorRef.dom.remove(imgNode);
                } else {
                    // Phòng hờ: nếu không xác định được vị trí nhưng toàn nội dung chỉ có đúng 1 ảnh khớp id thì vẫn xóa ảnh đó
                    var allImgs = editorRef.dom.select('#tablepress-img-' + tableId);
                    if (allImgs.length === 1) {
                        editorRef.dom.remove(allImgs[0]);
                    }
                }
            }

            // Tạo khung menu chuột phải custom cố định (Chỉ chạy DUY NHẤT 1 LẦN)
            var baseEditUrl = '<?php echo admin_url("admin.php?page=tablepress&action=edit&table_id="); ?>';

            // Biến lưu ngữ cảnh (context) của lần chuột phải gần nhất, dùng cho chức năng xóa
            var currentMode = null;      // 'text' hoặc 'visual'
            var currentTableId = null;
            var currentTextarea = null;
            var currentSelStart = 0;
            var currentSelEnd = 0;
            var currentEditor = null;
            var currentBookmark = null;

            var $menu = $('<div id="tp-acf-menu" style="position:fixed; display:none; background:#fff; border:1px solid #ccd0d4; box-shadow:0 4px 10px rgba(0,0,0,0.2); z-index:99999999; border-radius:4px; padding:5px 0; min-width:200px;">' +
                '<a id="tp-acf-link" href="#" target="_blank" style="display:block; padding:10px 15px; color:#0073aa; text-decoration:none; font-weight:bold; font-size:13px;">📝 Sửa bảng TablePress</a>' +
                '<a id="tp-acf-delete-link" href="#" style="display:block; padding:10px 15px; color:#d63638; text-decoration:none; font-weight:bold; font-size:13px; border-top:1px solid #eee;">🗑️ Xóa bảng TablePress</a>' +
                '</div>');
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

            // 2.3. Xử lý click vào "Xóa bảng TablePress"
            $(document).on('click', '#tp-acf-delete-link', function(e) {
                e.preventDefault();

                if (!currentTableId) {
                    hideMenu();
                    return;
                }

                var tableIdToDelete = currentTableId;
                var modeToUse = currentMode;
                var textareaRef = currentTextarea;
                var selStart = currentSelStart;
                var selEnd = currentSelEnd;
                var editorRef = currentEditor;
                var bookmarkRef = currentBookmark;

                hideMenu();

                if (!confirm('Xóa ID bảng "' + tableIdToDelete + '" và hình ảnh đi kèm (nếu có) khỏi nội dung đang soạn?\n\nThao tác này chỉ ảnh hưởng tới nội dung đang soạn ở đây, KHÔNG thay đổi dữ liệu của bảng TablePress, KHÔNG xóa link ảnh hay bất kỳ file nào trong Media Library.')) {
                    return;
                }

                // Chỉ gỡ dòng [table id=...] + ảnh id="tablepress-img-{id}" ra khỏi nội dung editor, không đụng gì tới server/dữ liệu
                if (modeToUse === 'text' && textareaRef) {
                    var newVal = removeTableAndImageFromTextarea(textareaRef.value, tableIdToDelete, selStart, selEnd);
                    textareaRef.value = newVal;
                    $(textareaRef).trigger('change');
                } else if (modeToUse === 'visual' && editorRef && bookmarkRef) {
                    editorRef.selection.moveToBookmark(bookmarkRef);
                    removeTableAndImageFromEditor(editorRef, tableIdToDelete);
                }
            });

            // 2.1. Xử lý chuột phải ở chế độ "Văn bản" (Text mode) của ACF Editor
            $(document).on('contextmenu', '.acf-field-wysiwyg textarea', function(e) {
                var selectedText = window.getSelection().toString().trim();
                var tableId = extractTableId(selectedText);

                if (tableId) {
                    e.preventDefault();

                    currentMode = 'text';
                    currentTableId = tableId;
                    currentTextarea = this;
                    currentSelStart = this.selectionStart;
                    currentSelEnd = this.selectionEnd;
                    currentEditor = null;
                    currentBookmark = null;

                    $menu.find('#tp-acf-link').attr('href', baseEditUrl + tableId).text('📝 Sửa bảng: ' + tableId);
                    $menu.find('#tp-acf-delete-link').text('🗑️ Xóa bảng: ' + tableId);
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

                                    currentMode = 'visual';
                                    currentTableId = tableId;
                                    currentTextarea = null;
                                    currentEditor = editor;
                                    currentBookmark = editor.selection.getBookmark(1);

                                    var iframeOffset = $(editor.iframeElement).offset();
                                    var top = iframeOffset.top + e.clientY - $(window).scrollTop();
                                    var left = iframeOffset.left + e.clientX;

                                    $menu.find('#tp-acf-link').attr('href', baseEditUrl + tableId).text('📝 Sửa bảng: ' + tableId);
                                    $menu.find('#tp-acf-delete-link').text('🗑️ Xóa bảng: ' + tableId);
                                    $menu.css({ top: top + 'px', left: left + 'px' }).show();
                                }
                            });
							
							editor.on('mousedown', function() {
                                hideMenu();
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
	// 1. API lấy chi tiết dữ liệu của 1 Table cụ thể qua ID
    register_rest_route( 'tablepress/v1', '/table/(?P<id>[a-zA-Z0-9_\-]+)', array(
        'methods'             => 'GET',
        'callback'            => 'get_tablepress_table_json',
        'permission_callback' => '__return_true', // Để '__return_true' nếu muốn ai cũng gọi được, hoặc cấu hình phân quyền nếu cần
    ));
	// 2. API lấy danh sách các Table có tên (hoặc ID) bắt đầu bằng một prefix
	register_rest_route('tablepress/v1', '/tables/prefix/(?P<prefix>[a-zA-Z0-9_-]+)', array(
        'methods' => 'GET',
		'permission_callback' => '__return_true',
        'callback' => function ($data) {
            global $wpdb;
			$prefix = strtolower($data['prefix']);

			// 1. Chọc thẳng vào DB lấy chuỗi JSON cấu hình
			$raw_data = $wpdb->get_var("
				SELECT option_value 
				FROM {$wpdb->options} 
				WHERE option_name = 'tablepress_tables'
			");
			
			if (!$raw_data) {
				return rest_ensure_response(array());
			}

			// 2. Giải mã chuỗi JSON thành mảng PHP (truyền true để ra dạng mảng assoc)
			$parsed_data = json_decode($raw_data, true);
			
			// Kiểm tra xem cấu trúc "table_post" có tồn tại không
			if (!isset($parsed_data['table_post']) || !is_array($parsed_data['table_post'])) {
				return rest_ensure_response(array());
			}

			$all_tables = $parsed_data['table_post']; // Đây là mảng: ["home_01" => 664, ...]
			$filtered_ids = array();

			// 3. Lọc các ID khớp với prefix của bạn
			foreach ($all_tables as $display_id => $post_id) {
				if (strpos(strtolower($display_id), $prefix) === 0) {
					$filtered_ids[] = array(
						'id'      => $display_id, // Ví dụ: "home_01"
						'post_id' => (int) $post_id // Ví dụ: 664
					);
				}
			}

			return rest_ensure_response($filtered_ids);
        },
    ));
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

