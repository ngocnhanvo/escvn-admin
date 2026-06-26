jQuery(document).ready(function ($) {

    let timerInterval   = null;
    let buildStartTime  = null;

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
                        String(now.getMinutes()).padStart(2, '0');

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

    function disableBuildBtn() {
        $('#vcs-build-btn-main').prop('disabled', true).text('⏳ Đang gửi yêu cầu...');
    }

    function enableBuildBtn() {
        $('#vcs-build-btn-main').prop('disabled', false).text('🚀 Build Website Ngay');
    }

    $(document).on('click', '#vcs-build-btn-main', function (e) {
        e.preventDefault();
        triggerBuild();
    });

    function triggerBuild() {
        if ($('#vcs-build-btn-main').prop('disabled')) return;

        disableBuildBtn();
        updateStatus('🚀', 'Đang gửi yêu cầu kích hoạt đến GitHub Actions...', 10);
        startTimer();

        $.ajax({
            url: vcsBuild.ajax_url,
            method: 'POST',
            data: {
                action: 'vcs_trigger_build',
                nonce: vcsBuild.nonce,
            },
            success: function (response) {
                stopTimer();
                if (response.success) {
                    const totalSecs = Math.floor((Date.now() - buildStartTime) / 1000);
                    const resultSuffix = getFormattedResult(totalSecs);
                    const finalMsg = `✅ Đã kích hoạt GitHub Actions thành công ${resultSuffix}. Quy trình build đang chạy ngầm trên Git.`;
                    
                    updateStatus('✅', finalMsg, 100);
                    $('#vcs-timer').text('⏱ Đã gửi xong');
                    enableBuildBtn();
                } else {
                    const errorMsg = response.data.message || 'Có lỗi xảy ra khi gọi GitHub API.';
                    updateStatus('❌', 'Thất bại: ' + errorMsg, 0);
                    $('#vcs-timer').text('⏱ Đã dừng');
                    enableBuildBtn();
                }
            },
            error: function () {
                stopTimer();
                updateStatus('❌', 'Không thể kết nối đến server WordPress. Vui lòng thử lại.', 0);
                $('#vcs-timer').text('⏱ Đã dừng');
                enableBuildBtn();
            }
        });
    }
});