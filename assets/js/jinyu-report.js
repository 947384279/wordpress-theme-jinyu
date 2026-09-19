(function ($) {
	'use strict';

	// 收集控制台错误（仅当用户勾选「附带」并发送时才外发，不主动上报）
	window.__jinyu_console_errors__ = [];
	window.addEventListener('error', function (ev) {
		window.__jinyu_console_errors__.push(
			(ev.message || '') + ' @ ' + (ev.filename || '') + ':' + (ev.lineno || 0)
		);
		if (window.__jinyu_console_errors__.length > 50) {
			window.__jinyu_console_errors__.shift();
		}
	});

	$(function () {
		var $btn = $('#jinyu-rpt-send');
		if (!$btn.length) {
			return;
		}
		var $status = $('#jinyu-rpt-status');

		$btn.on('click', function (e) {
			e.preventDefault();
			var msg = $.trim($('#jinyu-rpt-msg').val());
			if (!msg) {
				$status.text('请先填写内容');
				return;
			}
			var payload = {};
			if ($('#jinyu-rpt-console').is(':checked')) {
				payload.console = (window.__jinyu_console_errors__ || []).slice(-20);
			}
			$btn.prop('disabled', true);
			$status.text('发送中…');
			$.post(
				JINYU_REPORT.ajax,
				{
					action: 'jinyu_send_report',
					nonce: JINYU_REPORT.nonce,
					type: $('#jinyu-rpt-type').val(),
					message: msg,
					extra: payload
				},
				function (r) {
					$status.text(r && r.msg ? r.msg : (r && r.ok ? '已上报' : '上报失败'));
					if (r && r.ok) {
						$('#jinyu-rpt-msg').val('');
					}
				},
				'json'
			).fail(function () {
				$status.text('网络错误');
			}).always(function () {
				$btn.prop('disabled', false);
			});
		});
	});
})(jQuery);
