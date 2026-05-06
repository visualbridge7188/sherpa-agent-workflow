<?php
/**
 * Plugin Name: Duo 부고알림 KBoard 스킨
 * Description: KBoard에 Duo 부고알림 전용 스킨을 추가합니다.
 * Version: 1.4.1
 * Author: Duo
 * Text Domain: duo-obituary-kboard
 */

if(!defined('ABSPATH')){
	exit;
}

define('DUO_OBITUARY_KBOARD_VERSION', '1.4.1');
define('DUO_OBITUARY_KBOARD_SKIN', 'duo-obituary-kboard');
define('DUO_OBITUARY_KBOARD_SKIN_NAME', 'Duo 부고알림');
define('DUO_OBITUARY_KBOARD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DUO_OBITUARY_KBOARD_PLUGIN_URL', plugin_dir_url(__FILE__));

add_action('admin_notices', 'duo_obituary_kboard_dependency_notice');
add_filter('kboard_skin_list', 'duo_obituary_kboard_register_skin');
add_filter('kboard_skin_latestview_list', 'duo_obituary_kboard_register_skin');

// KBoard init(priority 5)보다 먼저 스킨 functions.php를 로드한다.
// 이렇게 해야 스킨 이름 매칭 실패 시에도 save/read hooks가 확실히 등록된다.
add_action('init', 'duo_obituary_kboard_load_skin_functions', 4);

function duo_obituary_kboard_load_skin_functions(){
	if(!duo_obituary_kboard_is_kboard_active()){
		return;
	}
	$functions_file = DUO_OBITUARY_KBOARD_PLUGIN_DIR . 'skins/' . DUO_OBITUARY_KBOARD_SKIN . '/functions.php';
	if(file_exists($functions_file)){
		require_once $functions_file;
	}
}

function duo_obituary_kboard_is_kboard_active(){
	return defined('KBOARD_VERSION') && class_exists('KBoardSkin');
}

function duo_obituary_kboard_dependency_notice(){
	if(duo_obituary_kboard_is_kboard_active()){
		return;
	}
	if(!current_user_can('activate_plugins')){
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo esc_html__('Duo 부고알림 KBoard 스킨을 사용하려면 KBoard 플러그인을 먼저 설치하고 활성화해 주세요.', 'duo-obituary-kboard');
	echo '</p></div>';
}

function duo_obituary_kboard_register_skin($skins){
	if(!duo_obituary_kboard_is_kboard_active()){
		return $skins;
	}

	$skin_dir = DUO_OBITUARY_KBOARD_PLUGIN_DIR . 'skins/' . DUO_OBITUARY_KBOARD_SKIN;
	if(!is_dir($skin_dir)){
		return $skins;
	}

	$skin = new stdClass();
	$skin->name = DUO_OBITUARY_KBOARD_SKIN_NAME;
	$skin->dir = $skin_dir;
	$skin->url = DUO_OBITUARY_KBOARD_PLUGIN_URL . 'skins/' . DUO_OBITUARY_KBOARD_SKIN;
	$skins[DUO_OBITUARY_KBOARD_SKIN_NAME] = $skin;

	return $skins;
}
