<?php
if(!defined('ABSPATH')){
	exit;
}

// 이중 로딩 방지 가드
if(defined('DUO_OBITUARY_FUNCTIONS_LOADED')){
	return;
}
define('DUO_OBITUARY_FUNCTIONS_LOADED', true);

// 로드 확인을 위한 디버그 로그
error_log('[Duo Obituary] functions.php loaded successfully');

if(!defined('DUO_OBITUARY_KBOARD_SKIN')){
	define('DUO_OBITUARY_KBOARD_SKIN', 'duo-obituary-kboard');
}
if(!defined('DUO_OBITUARY_KBOARD_SKIN_NAME')){
	define('DUO_OBITUARY_KBOARD_SKIN_NAME', 'Duo 부고알림');
}

function duo_obituary_is_skin($skin_name){
	return in_array($skin_name, array(DUO_OBITUARY_KBOARD_SKIN, DUO_OBITUARY_KBOARD_SKIN_NAME), true);
}

function duo_obituary_fields(){
	return array(
		'affiliation' => '소속',
		'deceased_name' => '고인명',
		'chief_mourner' => '상주명',
		'death_date' => '별세일',
		'coffin_date' => '입관일',
		'funeral_date' => '발인일',
		'place' => '장소',
		'burial_place' => '장지',
	);
}

function duo_obituary_asset_version($skin_dir, $file){
	$path = trailingslashit($skin_dir) . ltrim($file, '/');
	if(file_exists($path)){
		return (string)filemtime($path);
	}
	return defined('DUO_OBITUARY_KBOARD_VERSION') ? DUO_OBITUARY_KBOARD_VERSION : '1.4.1';
}

function duo_obituary_option_keys(){
	return array_keys(duo_obituary_fields());
}

function duo_obituary_collect_posted_payload(){
	$payload = array();
	foreach(duo_obituary_option_keys() as $key){
		$payload[$key] = duo_obituary_posted_option_value($key);
	}
	return $payload;
}

function duo_obituary_json_encode($payload){
	if(function_exists('wp_json_encode')){
		return wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
	}
	return json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function duo_obituary_encode_payload($payload){
	$json = duo_obituary_json_encode($payload);
	if(!$json){
		return '';
	}
	// 검색 가능하도록 평문 텍스트를 포함 (화면에는 보이지 않음)
	$searchable_text = implode(' ', array_values($payload));
	return '<div class="duo-obituary-payload" title="' . esc_attr(base64_encode($json)) . '" style="display:none !important;">' . esc_html($searchable_text) . '</div>';
}

function duo_obituary_decode_payload($content){
	if(!$content || !preg_match('/<div[^>]+class=["\'][^"\']*duo-obituary-payload[^"\']*["\'][^>]+title=["\']([^"\']+)["\'][^>]*><\/div>/i', (string)$content, $matches)){
		return array();
	}

	$json = base64_decode($matches[1], true);
	if(!$json){
		return array();
	}

	$payload = json_decode($json, true);
	return is_array($payload) ? $payload : array();
}

function duo_obituary_option($content, $key){
	if(!$content){
		return '';
	}

	// Stage 1: KBContentOption 에서 직접 읽기
	// 주의: KBContentOption에는 __isset()이 없으므로 isset() 사용 불가.
	// __get()을 직접 호출하여 값을 가져온다.
	if(is_object($content->option)){
		$val = $content->option->{$key}; // __get() 트리거
		if(is_string($val) && trim($val) !== ''){
			return trim($val);
		}
		if(is_array($val) && !empty($val)){
			return $val;
		}
	}

	// Stage 2: content 필드의 payload에서 읽기
	// 주의: KBContent에도 __isset()이 없으므로 empty() 대신 직접 접근.
	$raw_content = $content->content; // __get() 트리거
	if($raw_content && is_string($raw_content) && $raw_content !== ''){
		$payload = duo_obituary_decode_payload($raw_content);
		if(isset($payload[$key])){
			$value = trim((string)$payload[$key]);
			if($value !== ''){
				return $value;
			}
		}
	}

	// Stage 3: DB 직접 쿼리
	// 주의: $content->uid도 __get()으로 접근해야 한다.
	$uid = $content->uid; // __get() 트리거
	if($uid && intval($uid) > 0){
		global $wpdb;
		$content_uid = intval($uid);
		$option_key = esc_sql(sanitize_key($key));
		$fallback = $wpdb->get_var("SELECT `option_value` FROM `{$wpdb->prefix}kboard_board_option` WHERE `content_uid`='{$content_uid}' AND `option_key`='{$option_key}' ORDER BY `uid` DESC LIMIT 1");
		if($fallback !== null && $fallback !== ''){
			return trim((string)$fallback);
		}
	}

	return '';
}

function duo_obituary_display($content, $key){
	$value = duo_obituary_option($content, $key);
	return $value !== '' ? esc_html($value) : '-';
}

function duo_obituary_datetime_display($content, $key){
	$value = duo_obituary_option($content, $key);
	if($value === ''){
		return '-';
	}

	$timestamp = strtotime($value);
	if(!$timestamp){
		return esc_html($value);
	}
	return esc_html(date('Y-m-d H:i', $timestamp));
}

function duo_obituary_current_date(){
	return date('Y-m-d', current_time('timestamp'));
}

function duo_obituary_is_expired($content){
	$value = duo_obituary_option($content, 'funeral_date');
	if(!$value){
		return false;
	}

	$timestamp = strtotime($value);
	if(!$timestamp){
		return false;
	}

	return date('Y-m-d', $timestamp) < duo_obituary_current_date();
}

function duo_obituary_can_manage(){
	return current_user_can('manage_kboard') || current_user_can('manage_options');
}

function duo_obituary_is_target_list($list){
	if(!$list){
		return false;
	}

	if(isset($list->board) && isset($list->board->skin) && duo_obituary_is_skin($list->board->skin)){
		return true;
	}

	if(!empty($list->is_latest) && isset($list->latest['type']) && $list->latest['type'] === 'latestview' && !empty($list->latest['id'])){
		global $wpdb;
		$latestview_id = intval($list->latest['id']);
		$skin = $wpdb->get_var("SELECT `skin` FROM `{$wpdb->prefix}kboard_board_latestview` WHERE `uid`='{$latestview_id}'");
		return duo_obituary_is_skin($skin);
	}

	return false;
}

function duo_obituary_sql_datetime($alias = 'duo_funeral_date'){
	return "STR_TO_DATE({$alias}.`option_value`, '%Y-%m-%d %H:%i')";
}

function duo_obituary_add_query_joins($from, $board_id, $list){
	global $wpdb;

	if(!duo_obituary_is_target_list($list)){
		return $from;
	}

	$content_table = "{$wpdb->prefix}kboard_board_content";
	$option_table = "{$wpdb->prefix}kboard_board_option";

	$joins = array();
	if(strpos($from, 'duo_funeral_date') === false){
		$joins[] = "LEFT JOIN `{$option_table}` AS duo_funeral_date ON `{$content_table}`.`uid`=duo_funeral_date.`content_uid` AND duo_funeral_date.`option_key`='funeral_date'";
	}

	return trim($from . ' ' . implode(' ', $joins));
}

add_filter('kboard_list_from', 'duo_obituary_add_query_joins', 10, 3);
add_filter('kboard_latest_from', 'duo_obituary_add_query_joins', 10, 3);

function duo_obituary_select_count($select_count, $board_id, $list){
	if(!duo_obituary_is_target_list($list)){
		return $select_count;
	}

	global $wpdb;
	return "COUNT(DISTINCT `{$wpdb->prefix}kboard_board_content`.`uid`)";
}

add_filter('kboard_list_select_count', 'duo_obituary_select_count', 10, 3);
add_filter('kboard_latest_select_count', 'duo_obituary_select_count', 10, 3);

function duo_obituary_query_where($where, $board_id, $list){
	global $wpdb;

	if(!duo_obituary_is_target_list($list)){
		return $where;
	}

	$conditions = array();
	$current_date = esc_sql(duo_obituary_current_date());
	$funeral_dt = duo_obituary_sql_datetime();
	$funeral_date = "DATE({$funeral_dt})";

	// 일반 사용자는 항상 현재/미래 부고만 봄
	// 최신글(Latest) 목록은 관리자라도 현재/미래 부고만 봄 (롤링 가독성 및 목적 준수)
	if(!duo_obituary_can_manage() || !empty($list->is_latest)){
		$conditions[] = "({$funeral_date} >= '{$current_date}')";
	}


	if($conditions){
		$where = '(' . $where . ') AND ' . implode(' AND ', $conditions);
	}

	return $where;
}

add_filter('kboard_list_where', 'duo_obituary_query_where', 10, 3);
add_filter('kboard_latest_where', 'duo_obituary_query_where', 10, 3);

function duo_obituary_query_orderby($orderby, $board_id, $list){
	if(!duo_obituary_is_target_list($list)){
		return $orderby;
	}

	global $wpdb;

	$current_date = esc_sql(duo_obituary_current_date());
	$content_table = "{$wpdb->prefix}kboard_board_content";
	$funeral_dt = duo_obituary_sql_datetime();
	$funeral_date = "DATE({$funeral_dt})";

	if(duo_obituary_can_manage()){
		return "CASE WHEN {$funeral_date} >= '{$current_date}' THEN 0 ELSE 1 END ASC, CASE WHEN {$funeral_date} >= '{$current_date}' THEN {$funeral_dt} END ASC, CASE WHEN {$funeral_date} < '{$current_date}' THEN {$funeral_dt} END DESC, `{$content_table}`.`uid` DESC";
	}

	return "{$funeral_dt} ASC, `{$content_table}`.`uid` DESC";
}

add_filter('kboard_list_orderby', 'duo_obituary_query_orderby', 10, 3);
add_filter('kboard_latest_orderby', 'duo_obituary_query_orderby', 10, 3);

function duo_obituary_latest_rpp($rpp, $board_id, $list){
	if(duo_obituary_is_target_list($list) && !empty($list->is_latest)){
		return 100;
	}
	return $rpp;
}
add_filter('kboard_list_rpp', 'duo_obituary_latest_rpp', 10, 3);

function duo_obituary_normalize_datetime($value){
	$value = trim((string)$value);
	if($value === ''){
		return '';
	}

	$timestamp = strtotime($value);
	if(!$timestamp){
		return false;
	}

	return date('Y-m-d H:i', $timestamp);
}

function duo_obituary_build_title_from_post(){
	$parts = array();
	$affiliation = isset($_POST['kboard_option_affiliation']) ? sanitize_text_field(wp_unslash($_POST['kboard_option_affiliation'])) : '';
	$deceased_name = isset($_POST['kboard_option_deceased_name']) ? sanitize_text_field(wp_unslash($_POST['kboard_option_deceased_name'])) : '';
	$death_date = isset($_POST['kboard_option_death_date']) ? duo_obituary_normalize_datetime(wp_unslash($_POST['kboard_option_death_date'])) : '';

	foreach(array($affiliation, $deceased_name, $death_date) as $part){
		if($part !== '' && $part !== false){
			$parts[] = $part;
		}
	}

	return $parts ? implode('/', $parts) : $deceased_name;
}

function duo_obituary_validate_and_prepare_post($content, $board){
	if(!$board || !duo_obituary_is_skin($board->skin)){
		return;
	}

	$required = array('deceased_name', 'chief_mourner', 'funeral_date');
	foreach($required as $key){
		$value = isset($_POST["kboard_option_{$key}"]) ? trim((string)wp_unslash($_POST["kboard_option_{$key}"])) : '';
		if($value === ''){
			die("<script>alert('필수 항목을 입력해 주세요.');history.go(-1);</script>");
		}
	}

	foreach(array('death_date', 'coffin_date', 'funeral_date') as $key){
		if(!isset($_POST["kboard_option_{$key}"])){
			continue;
		}

		$normalized = duo_obituary_normalize_datetime(wp_unslash($_POST["kboard_option_{$key}"]));
		if($normalized === false){
			die("<script>alert('날짜와 시간을 올바르게 입력해 주세요.');history.go(-1);</script>");
		}
		$_POST["kboard_option_{$key}"] = $normalized;
	}

	$content->title = duo_obituary_build_title_from_post();
	$content->content = duo_obituary_encode_payload(duo_obituary_collect_posted_payload());
}

add_action('kboard_pre_content_execute', 'duo_obituary_validate_and_prepare_post', 10, 2);

function duo_obituary_posted_option_value($key){
	$post_key = "kboard_option_{$key}";
	if(!isset($_POST[$post_key])){
		return '';
	}

	$value = wp_unslash($_POST[$post_key]);
	if(in_array($key, array('death_date', 'coffin_date', 'funeral_date'), true)){
		$normalized = duo_obituary_normalize_datetime($value);
		return $normalized !== false ? $normalized : '';
	}

	return is_array($value) ? '' : trim((string)$value);
}

function duo_obituary_save_posted_options($content_uid, $board_id, $content, $board){
	if(!$board || !duo_obituary_is_skin($board->skin)){
		return;
	}
	if(!duo_obituary_has_posted_options()){
		return;
	}

	foreach(duo_obituary_option_keys() as $key){
		duo_obituary_save_option_value($content_uid, $key, duo_obituary_posted_option_value($key));
	}

	if(class_exists('KBContentOption')){
		$content->option = new KBContentOption($content_uid);
	}
}

add_action('kboard_document_insert', 'duo_obituary_save_posted_options', 20, 4);
add_action('kboard_document_update', 'duo_obituary_save_posted_options', 20, 4);

function duo_obituary_has_posted_options(){
	foreach(duo_obituary_option_keys() as $key){
		if(isset($_POST["kboard_option_{$key}"])){
			return true;
		}
	}
	return false;
}

function duo_obituary_save_option_value($content_uid, $key, $value){
	global $wpdb;

	$content_uid = intval($content_uid);
	$key = sanitize_key($key);
	if(!$content_uid || !$key){
		return;
	}

	$table = "{$wpdb->prefix}kboard_board_option";
	$wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE `content_uid`=%d AND `option_key`=%s", $content_uid, $key));

	$value = is_array($value) ? '' : trim((string)$value);
	if($value === ''){
		return;
	}

	$wpdb->query($wpdb->prepare("INSERT INTO `{$table}` (`content_uid`, `option_key`, `option_value`) VALUES (%d, %s, %s)", $content_uid, $key, $value));
}

function duo_obituary_filter_insert_update_data($data, $board_id){
	$board = new KBoard($board_id);
	if(!duo_obituary_is_skin($board->skin)){
		return $data;
	}
	if(!duo_obituary_has_posted_options()){
		return $data;
	}

	$data['title'] = duo_obituary_build_title_from_post();
	$data['content'] = duo_obituary_encode_payload(duo_obituary_collect_posted_payload());

	return $data;
}

add_filter('kboard_insert_data', 'duo_obituary_filter_insert_update_data', 10, 2);
add_filter('kboard_update_data', 'duo_obituary_filter_insert_update_data', 10, 2);

function duo_obituary_build_title_from_row($row){
	$parts = array();
	foreach(array('affiliation', 'deceased_name', 'death_date') as $key){
		if(!empty($row[$key])){
			$parts[] = $row[$key];
		}
	}
	return $parts ? implode('/', $parts) : ($row['deceased_name'] ?? '부고');
}

function duo_obituary_sample_rows(){
	return array(
		array('affiliation' => '특실 1호', 'deceased_name' => '신옥선', 'chief_mourner' => '김홍기, 김두기, 김윤, 김창래, 김아영, 이은항, 정동진', 'death_date' => '2026-05-07 06:20', 'coffin_date' => '2026-05-08 09:00', 'funeral_date' => '2026-05-09 06:20', 'place' => '천안공원묘원', 'burial_place' => '천안공원묘원'),
		array('affiliation' => '3호실', 'deceased_name' => '유민형', 'chief_mourner' => '유승우, 허철, 유승현, 박수희, 허재석, 허서연, 유지우', 'death_date' => '2026-05-07 09:40', 'coffin_date' => '2026-05-08 11:00', 'funeral_date' => '2026-05-09 09:40', 'place' => '서울시립승화원', 'burial_place' => '벽제'),
		array('affiliation' => '4호실', 'deceased_name' => '정래원', 'chief_mourner' => '임원길, 정남훈, 임재서, 임재윤, 이석순', 'death_date' => '2026-05-07 08:00', 'coffin_date' => '2026-05-08 10:30', 'funeral_date' => '2026-05-10 08:00', 'place' => '의왕하늘쉼터', 'burial_place' => '의왕하늘쉼터'),
		array('affiliation' => '5호실', 'deceased_name' => '최춘녀', 'chief_mourner' => '박진훈, 박진형, 이상황, 박진숙, 이란송, 이경준, 박설아', 'death_date' => '2026-05-07 12:00', 'coffin_date' => '2026-05-08 15:00', 'funeral_date' => '2026-05-10 12:00', 'place' => '용미리 추모공원', 'burial_place' => '용미리'),
		array('affiliation' => '6호실', 'deceased_name' => '김채규', 'chief_mourner' => '김민철, 김정학, 김세령, 신미화', 'death_date' => '2026-05-07 17:00', 'coffin_date' => '2026-05-08 18:00', 'funeral_date' => '2026-05-10 17:00', 'place' => '연세대학교 장례식장', 'burial_place' => '파주 동화경모공원'),
		array('affiliation' => '7호실', 'deceased_name' => '박정희', 'chief_mourner' => '박도윤, 박서연, 김민준, 김하린', 'death_date' => '2026-05-08 05:30', 'coffin_date' => '2026-05-09 09:30', 'funeral_date' => '2026-05-11 05:30', 'place' => '서울성모병원 장례식장', 'burial_place' => '분당추모공원'),
		array('affiliation' => '8호실', 'deceased_name' => '이영호', 'chief_mourner' => '이준석, 이수민, 최현아, 이도현', 'death_date' => '2026-05-08 13:10', 'coffin_date' => '2026-05-09 14:00', 'funeral_date' => '2026-05-11 13:10', 'place' => '아산병원 장례식장', 'burial_place' => '양평 별그리다'),
		array('affiliation' => '9호실', 'deceased_name' => '한미자', 'chief_mourner' => '한지훈, 한세영, 오민수, 오윤정', 'death_date' => '2026-05-09 07:45', 'coffin_date' => '2026-05-10 10:00', 'funeral_date' => '2026-05-12 07:45', 'place' => '고려대학교 안암병원', 'burial_place' => '서울추모공원'),
		array('affiliation' => '10호실', 'deceased_name' => '오상훈', 'chief_mourner' => '오재민, 오서진, 윤태경, 윤하늘', 'death_date' => '2026-05-09 16:20', 'coffin_date' => '2026-05-10 17:00', 'funeral_date' => '2026-05-12 16:20', 'place' => '강남세브란스병원', 'burial_place' => '용인공원'),
		array('affiliation' => '11호실', 'deceased_name' => '장순애', 'chief_mourner' => '장민호, 장유진, 서지완, 서하윤', 'death_date' => '2026-05-10 10:15', 'coffin_date' => '2026-05-11 11:00', 'funeral_date' => '2026-05-13 10:15', 'place' => '국립중앙의료원', 'burial_place' => '인천가족공원'),
	);
}

function duo_obituary_seed_sample_posts($builder){
	if(!duo_obituary_can_manage() || !$builder || empty($builder->board) || !duo_obituary_is_skin($builder->board->skin) || !class_exists('KBContent')){
		return;
	}

	$board_id = !empty($builder->board_id) ? intval($builder->board_id) : (!empty($builder->board->id) ? intval($builder->board->id) : 0);
	if(!$board_id || get_option("duo_obituary_seeded_board_{$board_id}")){
		return;
	}

	foreach(duo_obituary_sample_rows() as $row){
		$content = new KBContent();
		$content->setBoardID($board_id);
		$content->member_uid = get_current_user_id();
		$content->member_display = '관리자';
		$content->title = duo_obituary_build_title_from_row($row);
		$content->content = duo_obituary_encode_payload($row);
		$content->search = 1;
		$content->status = '';
		$uid = $content->insertContent();
		if($uid){
			foreach(duo_obituary_option_keys() as $key){
				duo_obituary_save_option_value($uid, $key, $row[$key] ?? '');
			}
		}
	}

	update_option("duo_obituary_seeded_board_{$board_id}", '1', false);
}

add_action('kboard_skin_header', 'duo_obituary_seed_sample_posts', 5);

/**
 * content 필드에 payload가 있지만 option 테이블에 데이터가 없는 레코드를 복구한다.
 * 관리자가 게시판을 열 때 한 번만 실행된다.
 */
function duo_obituary_repair_missing_options($builder){
	if(!duo_obituary_can_manage() || !$builder || empty($builder->board) || !duo_obituary_is_skin($builder->board->skin)){
		return;
	}

	$board_id = !empty($builder->board_id) ? intval($builder->board_id) : (!empty($builder->board->id) ? intval($builder->board->id) : 0);
	if(!$board_id || get_option("duo_obituary_repaired_board_{$board_id}")){
		return;
	}

	global $wpdb;
	$content_table = "{$wpdb->prefix}kboard_board_content";
	$option_table = "{$wpdb->prefix}kboard_board_option";

	// board_id에 해당하는 모든 content 중 option이 하나도 없는 레코드 찾기
	$rows = $wpdb->get_results($wpdb->prepare(
		"SELECT c.`uid`, c.`content` FROM `{$content_table}` c
		 LEFT JOIN `{$option_table}` o ON c.`uid` = o.`content_uid`
		 WHERE c.`board_id` = %d AND o.`uid` IS NULL",
		$board_id
	));

	$repaired = 0;
	foreach($rows as $row){
		// content 필드에서 payload 복구 시도
		$payload = duo_obituary_decode_payload($row->content);
		if($payload){
			foreach(duo_obituary_option_keys() as $key){
				if(isset($payload[$key]) && trim((string)$payload[$key]) !== ''){
					duo_obituary_save_option_value($row->uid, $key, $payload[$key]);
				}
			}
			$repaired++;
		}
	}

	if($repaired > 0){
		error_log("[Duo Obituary] Repaired {$repaired} posts for board {$board_id}");
	}

	update_option("duo_obituary_repaired_board_{$board_id}", '1', false);
}

/**
 * 기존 게시물의 content 필드에 검색 가능한 평문 텍스트를 추가한다.
 */
function duo_obituary_repair_searchable_content($builder){
	if(!duo_obituary_can_manage() || !$builder || empty($builder->board) || !duo_obituary_is_skin($builder->board->skin)){
		return;
	}

	$board_id = !empty($builder->board_id) ? intval($builder->board_id) : (!empty($builder->board->id) ? intval($builder->board->id) : 0);
	if(!$board_id || get_option("duo_obituary_search_repaired_board_{$board_id}")){
		return;
	}

	global $wpdb;
	$content_table = "{$wpdb->prefix}kboard_board_content";
	$results = $wpdb->get_results($wpdb->prepare("SELECT `uid`, `content` FROM `{$content_table}` WHERE `board_id`=%d", $board_id));

	foreach($results as $row){
		$payload = duo_obituary_decode_payload($row->content);
		if($payload){
			$new_content = duo_obituary_encode_payload($payload);
			if($new_content !== $row->content){
				$wpdb->update($content_table, array('content' => $new_content), array('uid' => $row->uid));
			}
		}
	}

	update_option("duo_obituary_search_repaired_board_{$board_id}", '1', false);
}

add_action('kboard_skin_header', 'duo_obituary_repair_missing_options', 5);
add_action('kboard_skin_header', 'duo_obituary_repair_searchable_content', 6);


function duo_obituary_thumbnail_url($content){
	if(!$content || !$content->thumbnail_file){
		return '';
	}
	return $content->getThumbnail(800, 1000);
}
