<?php
// 1. Giữ nguyên nút "Thêm tài liệu" hoạt động ổn định của bạn
function esc_them_nut_tai_lieu_media_button() {
    global $pagenow;
    if ( in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) {
        echo '<a href="#TB_inline?width=600&height=550&inlineId=esc-legal-form-popup" class="thickbox button" style="padding-left: 5px;"><span class="dashicons dashicons-media-document" style="vertical-align: text-top; margin-top: 1px; line-height: 16px"></span> Thêm tài liệu pháp lý</a>';
    }
}
add_action( 'media_buttons', 'esc_them_nut_tai_lieu_media_button' );

// 2. Form Popup chỉ làm nhiệm vụ chèn mới vào cuối .blog-posts như cũ
function esc_render_legal_form_popup() {
    global $pagenow;
    if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) return;
    ?>
	<style>
		#TB_ajaxContent { width: auto !important; height: auto !important; }
	</style>
    <div id="esc-legal-form-popup" style="display:none; width: inherit; height:inherit;">
        <div style="padding:15px;">
            <h3>Thêm tài liệu pháp lý mới</h3>
            <hr>
            <div style="margin-bottom: 12px;">
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Tiêu đề văn bản:</label>
                <input type="text" id="esc_legal_title" style="width:100%;" placeholder="Ví dụ: Nghị định số 15/2026/NĐ-CP của Chính phủ...">
            </div>
            <div style="margin-bottom: 12px;">
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Nội dung:</label>
                <textarea id="esc_legal_short" style="width:100%;" rows="3" placeholder="Số hiệu, ngày ban hành, trích yếu sơ lược..."></textarea>
            </div>
            <div style="margin-bottom: 15px;">
                <label style="font-weight:bold; display:block; margin-bottom:5px;">Tập tin hoặc Link đính kèm:</label>
                <input type="text" id="esc_legal_file" style="width:100%;" placeholder="http://localhost:10034/wp-content/uploads/...">
            </div>
            <button type="button" id="esc_insert_legal_btn" class="button button-primary button-large">Chèn vào cuối bài viết</button>
        </div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('#esc_insert_legal_btn').on('click', function() {
            var title = $('#esc_legal_title').val();
            var short_desc = $('#esc_legal_short').val();
			var short_desc_formatted = short_desc.replaceAll('\n', '<br>');
            var file_url = $('#esc_legal_file').val();

            if(!title) {
                alert('Vui lòng nhập tiêu đề văn bản!');
                return;
            }
			
			if(file_url.replaceAll(' ', '') != '') {
				file_url = `<p class="post-excerpt" style="margin: 0 0 16px 0; color: #4a5568; text-align: justify; white-space: pre-line;"><a style="display: inline-block; margin-top: 8px; color: #ffffff; background-color: #2baab1; padding: 4px 12px; text-decoration: none; border-radius: 4px; font-weight: 600;" href="${file_url}" target="_blank" rel="noopener">Xem chi tiết</a></p>`;
			}
            var new_section = `
<article class="esc-section-item" style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); display: flex; flex-direction: column; position: relative;">
<div class="post-content">
<h2 class="entry-title" style="margin-top: 0; margin-bottom: 12px; line-height: 1.4; font-weight: bold;">${title}</h2>
<p class="post-excerpt" style="margin: 0 0 16px 0; color: #4a5568; text-align: justify; white-space: pre-line;">${short_desc_formatted}</p>
${file_url}
</div>
</article>
			`;
			
			

			var current_html = '';
            if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
				current_html = tinymce.activeEditor.getContent();
			} else {
				current_html = $('#content').val() || '';
			}

			var $wrapper = $('<div>').html(current_html);
			if ($wrapper.find('.blog-posts').length > 0) {
				$wrapper.find('.blog-posts').append(new_section);
			} else {
				$wrapper.html('<div class="blog-posts">' + $wrapper.html() + new_section + '</div>');
			}

			var final_html = $wrapper.html();
            
            if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
				tinymce.activeEditor.setContent(final_html);
			} else {
				$('#content').val(final_html);
			}

			$('#esc_legal_title, #esc_legal_short, #esc_legal_file').val('');
			tb_remove();
        });
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'esc_render_legal_form_popup' );

// 3. TỰ ĐỘNG TẠO NÚT XÓA KHI HOVER TRONG KHUNG SOẠN THẢO (KHÔNG ẢNH HƯỞNG ĐẾN VIỆC SỬA CHỮ)
function esc_legal_item_hover_delete_action() {
    global $pagenow;
    if ( ! in_array( $pagenow, array( 'post.php', 'post-new.php' ) ) ) return;
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        
        function applyHoverDeleteLogic(ed) {
            // 1. Thêm CSS hiển thị nút xóa vào Iframe của Trình soạn thảo
            ed.on('init', function() {
                var doc = ed.getDoc();
                if (doc && !$(doc).find('#esc-hover-delete-style').length) {
                    $(doc).find('head').append(
                        '<style id="esc-hover-delete-style">' +
                        '  .esc-section-item { position: relative !important; transition: all 0.2s; }' +
                        '  .esc-section-item:hover { border-color: #d94f4f !important; box-shadow: 0 0 0 2px #d94f4f !important; }' +
                        '  .esc-delete-container { position: absolute; top: 15px; right: 15px; z-index: 99999; display: none; }' +
                        '  .esc-section-item:hover .esc-delete-container { display: block !important; }' +
                        '  .esc-inline-delete-btn { background: #d94f4f !important; color: #fff !important; border: none !important; padding: 6px 14px !important; border-radius: 4px !important; font-size: 13px !important; font-weight: bold !important; cursor: pointer !important; line-height: 1 !important; box-shadow: 0 2px 4px rgba(0,0,0,0.2) !important; }' +
                        '  .esc-inline-delete-btn:hover { background: #b32d2d !important; }' +
                        '</style>'
                    );
                }
                injectDeleteButtons(ed);
            });

            // Lắng nghe thay đổi nội dung để tự thêm lại nút Xóa nếu bị mất
            ed.on('NodeChange Change SetContent KeyUp', function() {
                injectDeleteButtons(ed);
            });
        }

        // Tạo nút bấm lơ lửng cho từng bài viết pháp lý
        function injectDeleteButtons(ed) {
            var $body = $(ed.getBody());
            $body.find('.esc-section-item').each(function() {
                var $item = $(this);
                // Nếu chưa có nút xóa thì chèn vào đầu article
                if (!$item.find('.esc-delete-container').length) {
                    var deleteHtml = 
                        '<div class="esc-hidden-relese esc-delete-container" contenteditable="false">' +
                        '  <button type="button" class="esc-inline-delete-btn">Xóa mục này</button>' +
                        '</div>';
                    $item.prepend(deleteHtml);
                }
            });

            // Bắt sự kiện click vào nút xóa lơ lửng
            $body.off('click', '.esc-inline-delete-btn').on('click', '.esc-inline-delete-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (confirm('Bạn có chắc chắn muốn xóa văn bản pháp lý này không?')) {
                    $(this).closest('.esc-section-item').remove();
                    ed.execCommand('mceRepaint'); // Ép trình soạn thảo cập nhật lại mã HTML sạch
                }
            });
        }

        // Khởi chạy kích hoạt khi TinyMCE đã sẵn sàng (Hỗ trợ Classic Block của Gutenberg)
        if (typeof tinymce !== 'undefined') {
            if (tinymce.activeEditor) {
                applyHoverDeleteLogic(tinymce.activeEditor);
            }
            // Lắng nghe sự kiện nạp editor mới nếu đổi tab hoặc load chậm
            tinymce.on('AddEditor', function(e) {
                applyHoverDeleteLogic(e.editor);
            });
        }
    });
    </script>
    <?php
}
add_action( 'admin_footer', 'esc_legal_item_hover_delete_action' );