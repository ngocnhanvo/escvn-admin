jQuery(document).ready(function ($) {

    let pollingInterval = null;
    let timerInterval   = null;
    let pollingCount    = 0;
    let buildStartTime  = null;
    const MAX_POLLING   = 60; // tối đa 5 phút (mỗi 5 giây)

    // ── Hàm bổ trợ: Định dạng thời gian và ngày ────────────────
    function getFormattedResult(durationInSeconds) {
        const h = Math.floor(durationInSeconds / 3600);
		const m = Math.floor((durationInSeconds % 3600) / 60);
		const s = durationInSeconds % 60;
		
		let durStr = "";
		if (h > 0) durStr += h + " giờ ";
		if (m > 0 || h > 0) durStr += m + " phút ";
		if (h === 0) durStr += s + " giây";

		const now = new Date();
		const dateStr = String(now.getDate()).padStart(2, '0') + '/' +
						String(now.getMonth() + 1).padStart(2, '0') + '/' +
						now.getFullYear() + ' ' + 
						String(now.getHours()).padStart(2, '0') + ':' + 
						String(now.getMinutes()).padStart(2, '0'); // Thêm HH:mm ở đây

		return `sau ${durStr.trim()} vào ngày ${dateStr}`;
    }

    // ── Đồng hồ đếm thời gian build ──────────────────────────
    function startTimer() {
        buildStartTime = Date.now();
        timerInterval = setInterval(function () {
            const secs  = Math.floor((Date.now() - buildStartTime) / 1000);
            const mm    = String(Math.floor(secs / 60)).padStart(2, '0');
            const ss    = String(secs % 60).padStart(2, '0');
            $('#vcs-timer').text('⏱ ' + mm + ':' + ss);
        }, 1000);
    }

    function stopTimer() {
        clearInterval(timerInterval);
    }

    // ── Tạo toast nếu chưa có ────────────────────────────────
    function ensureToast() {
        if (!$('#vcs-toast').length) {
            $('body').append(`
                <div id="vcs-toast" style="position:fixed;bottom:30px;right:30px;padding:16px 24px;border-radius:8px;font-size:15px;font-weight:bold;color:#fff;z-index:99999;display:none;box-shadow:0 4px 20px rgba(0,0,0,0.3);max-width:350px;">
                    <div id="vcs-timer" style="font-size:12px;opacity:0.8;margin-bottom:4px;"></div><div id="vcs-toast-msg"></div>
                    <div id="vcs-toast-close" style="margin-top:8px;font-size:12px;opacity:0.7;cursor:pointer;text-align:right;display:none;">✕ Đóng</div>
                </div>
            `);
            $(document).on('click', '#vcs-toast-close', function() {
                $('#vcs-toast').fadeOut(300);
            });
        }
    }

    function showToast(message, type) {
        ensureToast();
        const colors = { success: '#00a854', error: '#e53935', loading: '#1976d2' };
        $('#vcs-toast')
            .css('background', colors[type] || '#333')
            .stop(true)
            .fadeIn(300);
        $('#vcs-toast-msg').html(message); // Dùng .html để nhận thẻ <br>

        if (type === 'loading') {
            $('#vcs-toast-close').hide();
        } else {
            $('#vcs-toast-close').show();
        }
    }

    function updateStatus(icon, text, progressPercent) {
        if (!$('#vcs-build-status').length) return;
        $('#vcs-build-status').show();
        $('#vcs-status-icon').text(icon);
        $('#vcs-status-text').text(text);

        if (progressPercent !== undefined) {
            $('#vcs-progress-bar').show();
            $('#vcs-progress-fill').css('width', progressPercent + '%');
        }
    }

    // ── Polling check build status ────────────────────────────
    function startPolling() {
		if (pollingInterval) clearInterval(pollingInterval);
        pollingCount = 0;
        pollingInterval = setInterval(function () {
            pollingCount++;

            if (pollingCount > MAX_POLLING) {
                clearInterval(pollingInterval);
                updateStatus('⚠️', 'Hết thời gian chờ. Vui lòng kiểm tra Cloudflare Dashboard.', 100);
                //showToast('⚠️ Timeout — kiểm tra Cloudflare Dashboard', 'error');
                enableBuildBtn();
                return;
            }

            const fakeProgress = Math.min(90, pollingCount * 2);
            $('#vcs-progress-fill').css('width', fakeProgress + '%');

            $.ajax({
                url: vcsBuild.ajax_url,
                method: 'POST',
                data: {
                    action: 'vcs_check_build_status',
                    nonce: vcsBuild.nonce,
                },
                success: function (response) {
                    if (!response.success) return;

                    const { status, message } = response.data;
                    const totalSecs = Math.floor((Date.now() - buildStartTime) / 1000);
                    const resultSuffix = getFormattedResult(totalSecs);

                    if (status === 'success') {
                        clearInterval(pollingInterval);
                        stopTimer();
                        
                        const finalMsg = `✅ Hoàn thành ${resultSuffix}`;
                        updateStatus('✅', finalMsg, 100);
                        //showToast(finalMsg, 'success');
                        $('#vcs-timer').text('⏱ Hoàn thành');
                        enableBuildBtn();

                    } else if (status === 'error') {
                        clearInterval(pollingInterval);
                        stopTimer();

                        const finalMsg = `❌ Lỗi ${resultSuffix}`;
                        const dashUrl = response.data.dashboard_url || '';
                        
                        updateStatus('❌', finalMsg, 100);
                        //showToast(finalMsg, 'error');
                        $('#vcs-timer').text('⏱ Đã dừng');
                        
                        if (dashUrl) {
                            $('#vcs-toast-msg').append('<br><a href="' + dashUrl + '" target="_blank" style="color:#fff;text-decoration:underline;font-size:13px;">👉 Xem log chi tiết trên Cloudflare</a>');
                        }
                        enableBuildBtn();

                    } else {
                        updateStatus('⏳', message, fakeProgress);
                        //showToast(message, 'loading');
                    }
                },
                error: function () { }
            });
        }, 5000);
    }

    function disableBuildBtn() {
        $('#vcs-build-btn-main').prop('disabled', true).text('⏳ Đang build...');
        //$('.vcs-toolbar-build-btn a').css('opacity', '0.6').text('⏳ Đang build...');
    }

    function enableBuildBtn() {
        $('#vcs-build-btn-main').prop('disabled', false).text('🚀 Build Website Ngay');
        //$('.vcs-toolbar-build-btn a').css('opacity', '1').text('🚀 Build Website');
    }

    $(document).on('click', '#vcs-build-btn-main', function (e) {
        e.preventDefault();
        triggerBuild();
    });

    function triggerBuild() {
        if ($('#vcs-build-btn-main').prop('disabled')) return;

        disableBuildBtn();
        updateStatus('🚀', 'Đang gửi yêu cầu build đến Cloudflare...', 5);
        //showToast('🚀 Đang kích hoạt build...', 'loading');
        startTimer();

        $.ajax({
            url: vcsBuild.ajax_url,
            method: 'POST',
            data: {
                action: 'vcs_trigger_build',
                nonce: vcsBuild.nonce,
            },
            success: function (response) {
                if (response.success) {
                    updateStatus('⏳', 'Build đã được kích hoạt! Đang chờ Cloudflare xử lý...', 10);
                    //showToast('⏳ Build đã bắt đầu, đang theo dõi...', 'loading');
                    startPolling();
                } else {
                    updateStatus('❌', response.data.message || 'Có lỗi xảy ra.', 0);
                    //showToast('❌ ' + (response.data.message || 'Có lỗi xảy ra.'), 'error');
                    enableBuildBtn();
                }
            },
            error: function () {
                updateStatus('❌', 'Không thể kết nối đến server. Vui lòng thử lại.', 0);
                //showToast('❌ Lỗi kết nối server', 'error');
                enableBuildBtn();
            }
        });
    }
});