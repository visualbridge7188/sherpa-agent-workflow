<?php
/**
 * Plugin Name: Quick Plugin Uploader
 * Description: 플러그인을 드래그 앤 드롭으로 즉시 업로드하고 설치합니다.
 * Version: 1.0
 * Author: Antigravity Sherpa
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 관리자 메뉴 등록
 */
add_action( 'admin_menu', 'qpu_add_admin_menu' );
function qpu_add_admin_menu() {
	$page = add_submenu_page(
		'plugins.php',
		__( '빠른 업로드', 'quick-plugin-uploader' ),
		__( '빠른 업로드', 'quick-plugin-uploader' ),
		'activate_plugins',
		'quick-plugin-uploader',
		'qpu_render_admin_page'
	);

	// 스타일 및 아이콘 명시적 로드
	add_action( 'admin_print_styles-' . $page, function() {
		wp_enqueue_style( 'dashicons' );
	});
}

/**
 * 관리자 상단 툴바에 퀵 메뉴 추가
 */
add_action( 'admin_bar_menu', 'qpu_add_admin_bar_menu', 999 );
function qpu_add_admin_bar_menu( $wp_admin_bar ) {
	if ( ! current_user_can( 'activate_plugins' ) ) return;

	$wp_admin_bar->add_node( array(
		'id'    => 'quick-upload',
		'title' => '<span class="ab-icon dashicons-upload"></span><span class="ab-label">' . __( '빠른 업로드', 'quick-plugin-uploader' ) . '</span>',
		'href'  => admin_url( 'plugins.php?page=quick-plugin-uploader' ),
		'meta'  => array( 'title' => __( '플러그인/테마 빠른 업로드', 'quick-plugin-uploader' ) )
	) );
}

/**
 * 관리자 페이지 렌더링
 */
function qpu_render_admin_page() {
	?>
	<div class="wrap qpu-wrap">
		<h1 class="wp-heading-inline"><?php _e( '빠른 업로드 (플러그인 & 테마)', 'quick-plugin-uploader' ); ?></h1>
		<hr class="wp-header-end">

		<div class="qpu-container">
			<div id="qpu-dropzone" class="qpu-dropzone">
				<div class="qpu-dropzone-inner">
					<span class="dashicons dashicons-upload"></span>
					<h2><?php _e( 'ZIP 파일을 여기에 끌어다 놓으세요', 'quick-plugin-uploader' ); ?></h2>
					<p><?php _e( '플러그인과 테마 모두 지원합니다 (자동 인식)', 'quick-plugin-uploader' ); ?></p>
					<input type="file" id="qpu-file-input" accept=".zip" style="display:none;">
					<button type="button" id="qpu-select-btn" class="button button-primary button-hero"><?php _e( '파일 선택하기', 'quick-plugin-uploader' ); ?></button>
				</div>
			</div>

			<div id="qpu-status-container" style="display:none;">
				<div class="qpu-progress-wrapper">
					<div id="qpu-progress-bar" class="qpu-progress-bar"></div>
				</div>
				<div id="qpu-status-message" class="qpu-status-message"></div>
			</div>

			<div class="qpu-options">
				<div class="qpu-option-item">
					<label class="qpu-switch">
						<input type="checkbox" id="qpu-auto-activate" checked>
						<span class="qpu-slider"></span>
					</label>
					<span class="qpu-option-label"><?php _e( '설치 후 즉시 활성화', 'quick-plugin-uploader' ); ?></span>
				</div>
				<div class="qpu-option-item muted">
					<span class="dashicons dashicons-info"></span>
					<span><?php _e( '기본 모드: 동일한 파일이 있을 경우 자동으로 덮어씁니다.', 'quick-plugin-uploader' ); ?></span>
					<input type="hidden" id="qpu-overwrite" value="1">
				</div>
			</div>
		</div>
	</div>

	<style>
		.qpu-wrap { max-width: 800px; margin-top: 20px; }
		.qpu-container { background: #fff; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); padding: 30px; border-radius: 8px; margin-top: 20px; }
		.qpu-dropzone { 
			border: 2px dashed #b4b9be; 
			border-radius: 12px; 
			padding: 60px 20px; 
			text-align: center; 
			background: #f9f9f9; 
			transition: all 0.3s ease;
			cursor: pointer;
		}
		.qpu-dropzone:hover, .qpu-dropzone.drag-over { 
			border-color: #2271b1; 
			background: #f0f6fb; 
		}
		.qpu-dropzone .dashicons-upload { font-size: 64px; width: 64px; height: 64px; color: #a7aaad; margin-bottom: 20px; }
		.qpu-dropzone:hover .dashicons-upload, .qpu-dropzone.drag-over .dashicons-upload { color: #2271b1; }
		.qpu-dropzone h2 { margin: 0 0 10px; font-weight: 600; color: #1d2327; }
		.qpu-dropzone p { color: #646970; margin-bottom: 20px; }
		
		.qpu-status-message { margin-top: 20px; padding: 15px; border-radius: 4px; font-weight: 500; }
		.qpu-status-message.success { background: #edfaef; border-left: 4px solid #46b450; color: #000; }
		.qpu-status-message.error { background: #fcf0f1; border-left: 4px solid #d63638; color: #000; }
		
		.qpu-progress-wrapper { height: 8px; background: #f0f0f1; border-radius: 4px; overflow: hidden; margin-top: 30px; }
		.qpu-progress-bar { height: 100%; background: #2271b1; width: 0%; transition: width 0.2s ease; }
		
		.qpu-options { margin-top: 30px; padding-top: 20px; border-top: 1px solid #f0f0f1; display: flex; align-items: center; justify-content: space-between; }
		.qpu-option-item { display: flex; align-items: center; }
		.qpu-option-item.muted { color: #8c8f94; font-size: 13px; }
		.qpu-option-item.muted .dashicons { font-size: 18px; width: 18px; height: 18px; margin-right: 5px; }
		.qpu-option-label { margin-left: 10px; font-weight: 500; }

		/* Premium Switch Style */
		.qpu-switch { position: relative; display: inline-block; width: 44px; height: 24px; }
		.qpu-switch input { opacity: 0; width: 0; height: 0; }
		.qpu-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 24px; }
		.qpu-slider:before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
		input:checked + .qpu-slider { background-color: #2271b1; }
		input:focus + .qpu-slider { box-shadow: 0 0 1px #2271b1; }
		input:checked + .qpu-slider:before { transform: translateX(20px); }
	</style>

	<script>
	(function($) {
		'use strict';

		$(document).ready(function() {
			const $dropzone = $('#qpu-dropzone');
			const $fileInput = $('#qpu-file-input');
			const $selectBtn = $('#qpu-select-btn');
			const $statusContainer = $('#qpu-status-container');
			const $progressBar = $('#qpu-progress-bar');
			const $statusMessage = $('#qpu-status-message');
			const $autoActivate = $('#qpu-auto-activate');
			const $overwrite = $('#qpu-overwrite');

			// 파일 선택 버튼 클릭 시 숨겨진 input 호출
			$selectBtn.on('click', function(e) {
				e.stopPropagation();
				$fileInput.click();
			});

			$dropzone.on('click', function() {
				$fileInput.click();
			});

			// 드래그 앤 드롭 이벤트
			$dropzone.on('dragover dragenter', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$dropzone.addClass('drag-over');
			});

			$dropzone.on('dragleave dragend drop', function(e) {
				e.preventDefault();
				e.stopPropagation();
				$dropzone.removeClass('drag-over');
			});

			$dropzone.on('drop', function(e) {
				const files = e.originalEvent.dataTransfer.files;
				if (files.length > 0) {
					handleFileUpload(files[0]);
				}
			});

			$fileInput.on('change', function() {
				if (this.files.length > 0) {
					handleFileUpload(this.files[0]);
				}
			});

			function handleFileUpload(file) {
				if (file.type !== 'application/zip' && !file.name.endsWith('.zip')) {
					showMessage('<?php _e( 'ZIP 파일만 업로드 가능합니다.', 'quick-plugin-uploader' ); ?>', 'error');
					return;
				}

				const formData = new FormData();
				formData.append('action', 'quick_plugin_upload');
				formData.append('plugin_zip', file);
				formData.append('auto_activate', $autoActivate.is(':checked') ? '1' : '0');
				formData.append('overwrite', $overwrite.val());
				formData.append('_ajax_nonce', '<?php echo wp_create_nonce( "qpu_upload_nonce" ); ?>');

				$statusContainer.show();
				$progressBar.css('width', '0%');
				showMessage('<?php _e( '업로드 중...', 'quick-plugin-uploader' ); ?>', '');

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: formData,
					processData: false,
					contentType: false,
					xhr: function() {
						const xhr = new window.XMLHttpRequest();
						xhr.upload.addEventListener('progress', function(e) {
							if (e.lengthComputable) {
								const percent = Math.round((e.loaded / e.total) * 100);
								$progressBar.css('width', percent + '%');
							}
						}, false);
						return xhr;
					},
					success: function(response) {
						if (response.success) {
							showMessage(response.data.message, 'success');
							$progressBar.css('width', '100%');
							if (response.data.redirect) {
								setTimeout(() => { window.location.href = response.data.redirect; }, 1500);
							}
						} else {
							showMessage(response.data.message || '<?php _e( '업로드 실패', 'quick-plugin-uploader' ); ?>', 'error');
						}
					},
					error: function() {
						showMessage('<?php _e( '서버 통신 오류가 발생했습니다.', 'quick-plugin-uploader' ); ?>', 'error');
					}
				});
			}

			function showMessage(msg, type) {
				$statusMessage.text(msg).removeClass('success error').addClass(type);
			}
		});
	})(jQuery);
	</script>
	<?php
}

/**
 * AJAX 업로드 핸들러
 */
add_action( 'wp_ajax_quick_plugin_upload', 'qpu_handle_ajax_upload' );
function qpu_handle_ajax_upload() {
	// 1. 보안 검증
	check_ajax_referer( 'qpu_upload_nonce', '_ajax_nonce' );

	if ( ! current_user_can( 'activate_plugins' ) && ! current_user_can( 'switch_themes' ) ) {
		wp_send_json_error( array( 'message' => __( '권한이 없습니다.', 'quick-plugin-uploader' ) ) );
	}

	if ( empty( $_FILES['zip_file'] ) ) {
		wp_send_json_error( array( 'message' => __( '파일이 전송되지 않았습니다.', 'quick-plugin-uploader' ) ) );
	}

	// 2. 필요 파일 로드
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
	require_once ABSPATH . 'wp-admin/includes/theme-install.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/theme.php';

	// 3. 파일 처리
	$file = $_FILES['zip_file'];
	$overrides = array( 'test_form' => false, 'mimes' => array( 'zip' => 'application/zip' ) );
	$upload = wp_handle_upload( $file, $overrides );

	if ( isset( $upload['error'] ) ) {
		wp_send_json_error( array( 'message' => $upload['error'] ) );
	}

	$file_path = $upload['file'];

	// 4. 유형 감지 (플러그인 vs 테마)
	// 기본적으로 덮어쓰기 활성화 (clear_destination = true)
	add_filter( 'upgrader_package_options', function( $options ) {
		$options['clear_destination'] = true;
		return $options;
	});

	ob_start();
	$skin = new Automatic_Upgrader_Skin();
	
	// 일단 플러그인으로 시도
	$upgrader = new Plugin_Upgrader( $skin );
	$result = $upgrader->install( $file_path );
	$type = 'plugin';

	// 플러그인 설치 실패 시 테마로 재시도 (혹은 에러 메시지 분석)
	if ( is_wp_error( $result ) || ! $result ) {
		// 테마 업그레이더로 교체
		$upgrader = new Theme_Upgrader( $skin );
		$result = $upgrader->install( $file_path );
		$type = 'theme';
	}
	ob_end_clean();

	// 임시 업로드 파일 삭제
	if ( file_exists( $file_path ) ) {
		@unlink( $file_path );
	}

	if ( is_wp_error( $result ) ) {
		wp_send_json_error( array( 'message' => $result->get_error_message() ) );
	}

	if ( ! $result ) {
		wp_send_json_error( array( 'message' => __( '설치 중 오류가 발생했습니다. 올바른 ZIP 파일인지 확인하세요.', 'quick-plugin-uploader' ) ) );
	}

	// 5. 후속 처리 (활성화 및 리다이렉트)
	$auto_activate = isset( $_POST['auto_activate'] ) && $_POST['auto_activate'] === '1';
	$message = ( $type === 'plugin' ) ? __( '플러그인이 성공적으로 설치되었습니다.', 'quick-plugin-uploader' ) : __( '테마가 성공적으로 설치되었습니다.', 'quick-plugin-uploader' );
	$redirect = ( $type === 'plugin' ) ? admin_url( 'plugins.php' ) : admin_url( 'themes.php' );

	if ( $auto_activate ) {
		if ( $type === 'plugin' ) {
			$plugin_info = $upgrader->plugin_info();
			if ( $plugin_info ) {
				activate_plugin( $plugin_info );
				$message = __( '플러그인이 설치 및 활성화되었습니다.', 'quick-plugin-uploader' );
			}
		} else {
			$theme_info = $upgrader->theme_info();
			if ( $theme_info ) {
				switch_theme( $theme_info->get_stylesheet() );
				$message = __( '테마가 설치 및 활성화되었습니다.', 'quick-plugin-uploader' );
			}
		}
	}

	wp_send_json_success( array( 
		'message' => $message,
		'redirect' => $redirect
	) );
}



