<?php
if (!defined('ABSPATH')) {
	exit;
}

// 이중 로딩 방지 가드
if (defined('DUO_OBITUARY_FUNCTIONS_LOADED')) {
	return;
}
define('DUO_OBITUARY_FUNCTIONS_LOADED', true);

if (!defined('DUO_OBITUARY_KBOARD_SKIN')) {
	define('DUO_OBITUARY_KBOARD_SKIN', 'duo-obituary-kboard');
}
if (!defined('DUO_OBITUARY_KBOARD_SKIN_NAME')) {
	define('DUO_OBITUARY_KBOARD_SKIN_NAME', 'Duo 부고알림');
}

function duo_obituary_is_skin($skin_name)
{
	return in_array($skin_name, array(DUO_OBITUARY_KBOARD_SKIN, DUO_OBITUARY_KBOARD_SKIN_NAME), true);
}

function duo_obituary_fields()
{
	return array(
		'affiliation' => '소속',
		'deceased_name' => '고인명',
		'chief_mourner' => '상주명',
		'death_date' => '별세일',
		'coffin_date' => '입관일',
		'funeral_date' => '발인일',
		'place' => '장례식장',
		'burial_place' => '장지',
	);
}

function duo_obituary_asset_version($skin_dir, $file)
{
	$path = trailingslashit($skin_dir) . ltrim($file, '/');
	if (file_exists($path)) {
		return (string) filemtime($path);
	}
	return defined('DUO_OBITUARY_KBOARD_VERSION') ? DUO_OBITUARY_KBOARD_VERSION : '1.4.5';
}

function duo_obituary_option_keys()
{
	return array_keys(duo_obituary_fields());
}

function duo_obituary_content_prop($content, $prop)
{
	if (!$content || !is_object($content)) {
		return '';
	}
	if ($content instanceof stdClass && !property_exists($content, $prop)) {
		return '';
	}
	return $content->{$prop};
}

function duo_obituary_collect_posted_payload()
{
	$payload = array();
	foreach (duo_obituary_option_keys() as $key) {
		$payload[$key] = duo_obituary_posted_option_value($key);
	}
	return $payload;
}

function duo_obituary_json_encode($payload)
{
	if (function_exists('wp_json_encode')) {
		return wp_json_encode($payload, JSON_UNESCAPED_UNICODE);
	}
	return json_encode($payload, JSON_UNESCAPED_UNICODE);
}

function duo_obituary_encode_payload($payload)
{
	$json = duo_obituary_json_encode($payload);
	if (!$json) {
		return '';
	}
	$searchable_text = implode(' ', array_values($payload));
	return '<div class="duo-obituary-payload" title="' . esc_attr(base64_encode($json)) . '" style="display:none !important;">' . esc_html($searchable_text) . '</div>';
}

function duo_obituary_decode_payload($content)
{
	if (!$content || !preg_match('/<div[^>]+class=["\'][^"\']*duo-obituary-payload[^"\']*["\'][^>]+title=["\']([^"\']+)["\'][^>]*>.*?<\/div>/is', (string) $content, $matches)) {
		return array();
	}

	$json = base64_decode($matches[1], true);
	if (!$json) {
		return array();
	}

	$payload = json_decode($json, true);
	return is_array($payload) ? $payload : array();
}

function duo_obituary_option($content, $key)
{
	if (!$content) {
		return '';
	}

	// Stage 1: KBContentOption 에서 직접 읽기
	// 주의: KBContentOption에는 __isset()이 없으므로 isset() 사용 불가.
	// __get()을 직접 호출하여 값을 가져온다.
	$option = duo_obituary_content_prop($content, 'option');
	if (is_object($option)) {
		$val = $option->{$key}; // __get() 트리거
		if (is_string($val) && trim($val) !== '') {
			return trim($val);
		}
		if (is_array($val) && !empty($val)) {
			return $val;
		}
	}

	// Stage 2: content 필드의 payload에서 읽기
	// 주의: KBContent에도 __isset()이 없으므로 empty() 대신 직접 접근.
	$raw_content = duo_obituary_content_prop($content, 'content'); // __get() 트리거
	if ($raw_content && is_string($raw_content) && $raw_content !== '') {
		$payload = duo_obituary_decode_payload($raw_content);
		if (isset($payload[$key])) {
			$value = trim((string) $payload[$key]);
			if ($value !== '') {
				return $value;
			}
		}
	}

	// Stage 3: DB 직접 쿼리
	// 주의: $content->uid도 __get()으로 접근해야 한다.
	$uid = duo_obituary_content_prop($content, 'uid'); // __get() 트리거
	if ($uid && intval($uid) > 0) {
		global $wpdb;
		$content_uid = intval($uid);
		$option_key = esc_sql(sanitize_key($key));
		$fallback = $wpdb->get_var("SELECT `option_value` FROM `{$wpdb->prefix}kboard_board_option` WHERE `content_uid`='{$content_uid}' AND `option_key`='{$option_key}' ORDER BY `uid` DESC LIMIT 1");
		if ($fallback !== null && $fallback !== '') {
			return trim((string) $fallback);
		}
	}

	return '';
}

function duo_obituary_display($content, $key)
{
	$value = duo_obituary_option($content, $key);
	return $value !== '' ? esc_html($value) : '-';
}

function duo_obituary_datetime_display($content, $key)
{
	$value = duo_obituary_option($content, $key);
	if ($value === '') {
		return '-';
	}

	$timestamp = strtotime($value);
	if (!$timestamp) {
		return esc_html($value);
	}
	return esc_html(date('Y-m-d H:i', $timestamp));
}

function duo_obituary_current_date()
{
	return date('Y-m-d', current_time('timestamp'));
}

function duo_obituary_is_expired($content)
{
	$value = duo_obituary_option($content, 'funeral_date');
	if (!$value) {
		return false;
	}

	$timestamp = strtotime($value);
	if (!$timestamp) {
		return false;
	}

	return date('Y-m-d', $timestamp) < duo_obituary_current_date();
}

function duo_obituary_active_latest_items($items)
{
	if (!is_array($items)) {
		return array();
	}

	$active_items = array();
	foreach ($items as $item) {
		if (!duo_obituary_is_expired($item)) {
			$active_items[] = $item;
		}
	}
	return $active_items;
}

function duo_obituary_can_manage()
{
	return current_user_can('manage_kboard') || current_user_can('manage_options');
}

function duo_obituary_is_target_list($list, $board_id = 0)
{
	if (!$list) {
		return false;
	}

	if (isset($list->board) && isset($list->board->skin) && duo_obituary_is_skin($list->board->skin)) {
		return true;
	}

	if (!empty($list->is_latest) && isset($list->latest['type']) && $list->latest['type'] === 'latestview' && !empty($list->latest['id'])) {
		global $wpdb;
		$latestview_id = intval($list->latest['id']);
		$skin = $wpdb->get_var("SELECT `skin` FROM `{$wpdb->prefix}kboard_board_latestview` WHERE `uid`='{$latestview_id}'");
		return duo_obituary_is_skin($skin);
	}

	$board_id = intval($board_id);
	if ($board_id > 0 && class_exists('KBoard')) {
		$board = new KBoard($board_id);
		if (isset($board->skin) && duo_obituary_is_skin($board->skin)) {
			return true;
		}
	}

	return false;
}

function duo_obituary_sql_datetime($alias = 'duo_funeral_date')
{
	return "STR_TO_DATE({$alias}.`option_value`, '%Y-%m-%d %H:%i')";
}

function duo_obituary_search_option_aliases()
{
	return array(
		'affiliation' => 'duo_affiliation',
		'deceased_name' => 'duo_deceased_name',
		'chief_mourner' => 'duo_chief_mourner',
		'death_date' => 'duo_death_date',
		'coffin_date' => 'duo_coffin_date',
		'funeral_date' => 'duo_funeral_date',
		'place' => 'duo_place',
		'burial_place' => 'duo_burial_place',
	);
}

function duo_obituary_add_query_joins($from, $board_id, $list)
{
	global $wpdb;

	if (!duo_obituary_is_target_list($list, $board_id)) {
		return $from;
	}

	$content_table = "{$wpdb->prefix}kboard_board_content";
	$option_table = "{$wpdb->prefix}kboard_board_option";

	$joins = array();
	foreach (duo_obituary_search_option_aliases() as $option_key => $alias) {
		if (strpos($from, $alias) === false) {
			$joins[] = "LEFT JOIN `{$option_table}` AS {$alias} ON `{$content_table}`.`uid`={$alias}.`content_uid` AND {$alias}.`option_key`='{$option_key}'";
		}
	}

	return trim($from . ' ' . implode(' ', $joins));
}

add_filter('kboard_list_from', 'duo_obituary_add_query_joins', 10, 3);
add_filter('kboard_latest_from', 'duo_obituary_add_query_joins', 10, 3);

function duo_obituary_select_count($select_count, $board_id, $list)
{
	if (!duo_obituary_is_target_list($list, $board_id)) {
		return $select_count;
	}

	global $wpdb;
	return "COUNT(DISTINCT `{$wpdb->prefix}kboard_board_content`.`uid`)";
}

add_filter('kboard_list_select_count', 'duo_obituary_select_count', 10, 3);
add_filter('kboard_latest_select_count', 'duo_obituary_select_count', 10, 3);

function duo_obituary_current_keyword()
{
	if (function_exists('kboard_keyword')) {
		return trim((string) kboard_keyword());
	}
	return isset($_GET['keyword']) ? trim(sanitize_text_field(wp_unslash($_GET['keyword']))) : '';
}

function duo_obituary_search_where($keyword)
{
	global $wpdb;

	$keyword = trim((string) $keyword);
	if ($keyword === '') {
		return '';
	}

	$like = '%' . $wpdb->esc_like($keyword) . '%';
	$conditions = array();
	foreach (duo_obituary_search_option_aliases() as $alias) {
		$conditions[] = $wpdb->prepare("{$alias}.`option_value` LIKE %s", $like);
	}

	return '(' . implode(' OR ', $conditions) . ')';
}

function duo_obituary_expand_keyword_where($where, $search_where)
{
	if ($search_where === '') {
		return $where;
	}

	$content_table_pattern = '`[^`]+kboard_board_content`';
	$operator_pattern = '(?:NOT LIKE|LIKE|!=|=|>=|>|<=|<)';
	$value_pattern = "'(?:\\\\'|[^'])*'";
	$keyword_pattern = '/\(' .
		$content_table_pattern . '\.`title`\s+' . $operator_pattern . '\s+' . $value_pattern .
		'\s+OR\s+' . $content_table_pattern . '\.`content`\s+' . $operator_pattern . '\s+' . $value_pattern .
		'(?:\s+OR\s+' . $content_table_pattern . '\.`member_display`\s+' . $operator_pattern . '\s+' . $value_pattern . ')?' .
		'\)/';

	$expanded = preg_replace($keyword_pattern, '($0 OR ' . $search_where . ')', $where, 1, $count);
	return $count ? $expanded : $where;
}

function duo_obituary_query_where($where, $board_id, $list)
{
	global $wpdb;

	if (!duo_obituary_is_target_list($list, $board_id)) {
		return $where;
	}

	$conditions = array();
	$current_date = esc_sql(duo_obituary_current_date());
	$funeral_dt = duo_obituary_sql_datetime();
	$funeral_date = "DATE({$funeral_dt})";

	if (!empty($list->is_latest)) {
		$conditions[] = "({$funeral_date} >= '{$current_date}')";
	}

	// 키워드 검색은 KBoard 자체 검색 메커니즘 사용 (target='', keyword=검색어)
	// content 필드에 payload 텍스트(고인명, 상주명 등)가 포함되어 자동 검색됨
	$search_where = duo_obituary_search_where(duo_obituary_current_keyword());
	if ($search_where !== '') {
		$expanded_where = duo_obituary_expand_keyword_where($where, $search_where);
		if ($expanded_where !== $where) {
			$where = $expanded_where;
		} else {
			$conditions[] = $search_where;
		}
	}

	if ($conditions) {
		$where = '(' . $where . ') AND ' . implode(' AND ', $conditions);
	}

	return $where;
}

add_filter('kboard_list_where', 'duo_obituary_query_where', 10, 3);
add_filter('kboard_latest_where', 'duo_obituary_query_where', 10, 3);

function duo_obituary_query_orderby($orderby, $board_id, $list)
{
	if (!duo_obituary_is_target_list($list, $board_id)) {
		return $orderby;
	}

	global $wpdb;

	$current_date = esc_sql(duo_obituary_current_date());
	$content_table = "{$wpdb->prefix}kboard_board_content";
	$funeral_dt = duo_obituary_sql_datetime();

	return "{$funeral_dt} DESC, `{$content_table}`.`uid` DESC";
}

add_filter('kboard_list_orderby', 'duo_obituary_query_orderby', 10, 3);
add_filter('kboard_latest_orderby', 'duo_obituary_query_orderby', 10, 3);

function duo_obituary_latest_rpp($rpp, $board_id, $list)
{
	if (duo_obituary_is_target_list($list, $board_id) && !empty($list->is_latest)) {
		return 100;
	}
	return $rpp;
}

add_filter('kboard_list_rpp', 'duo_obituary_latest_rpp', 10, 3);

function duo_obituary_normalize_datetime($value)
{
	$value = trim((string) $value);
	if ($value === '') {
		return '';
	}

	$timestamp = strtotime($value);
	if (!$timestamp) {
		return false;
	}

	return date('Y-m-d H:i', $timestamp);
}

function duo_obituary_build_title_from_post()
{
	$parts = array();
	$affiliation = isset($_POST['kboard_option_affiliation']) ? sanitize_text_field(wp_unslash($_POST['kboard_option_affiliation'])) : '';
	$deceased_name = isset($_POST['kboard_option_deceased_name']) ? sanitize_text_field(wp_unslash($_POST['kboard_option_deceased_name'])) : '';
	$death_date = isset($_POST['kboard_option_death_date']) ? duo_obituary_normalize_datetime(wp_unslash($_POST['kboard_option_death_date'])) : '';

	foreach (array($affiliation, $deceased_name, $death_date) as $part) {
		if ($part !== '' && $part !== false) {
			$parts[] = $part;
		}
	}

	return $parts ? implode('/', $parts) : $deceased_name;
}

function duo_obituary_validate_and_prepare_post($content, $board)
{
	if (!$board || !duo_obituary_is_skin($board->skin)) {
		return;
	}

	$required = array('deceased_name', 'chief_mourner', 'funeral_date');
	foreach ($required as $key) {
		$value = isset($_POST["kboard_option_{$key}"]) ? trim((string) wp_unslash($_POST["kboard_option_{$key}"])) : '';
		if ($value === '') {
			die("<script>alert('필수 항목을 입력해 주세요.');history.go(-1);</script>");
		}
	}

	foreach (array('death_date', 'coffin_date', 'funeral_date') as $key) {
		if (!isset($_POST["kboard_option_{$key}"])) {
			continue;
		}

		$normalized = duo_obituary_normalize_datetime(wp_unslash($_POST["kboard_option_{$key}"]));
		if ($normalized === false) {
			die("<script>alert('날짜와 시간을 올바르게 입력해 주세요.');history.go(-1);</script>");
		}
		$_POST["kboard_option_{$key}"] = $normalized;
	}

	$content->title = duo_obituary_build_title_from_post();
	$content->content = duo_obituary_encode_payload(duo_obituary_collect_posted_payload());
}

add_action('kboard_pre_content_execute', 'duo_obituary_validate_and_prepare_post', 10, 2);

function duo_obituary_posted_option_value($key)
{
	$post_key = "kboard_option_{$key}";
	if (!isset($_POST[$post_key])) {
		return '';
	}

	$value = wp_unslash($_POST[$post_key]);
	if (in_array($key, array('death_date', 'coffin_date', 'funeral_date'), true)) {
		$normalized = duo_obituary_normalize_datetime($value);
		return $normalized !== false ? $normalized : '';
	}

	return is_array($value) ? '' : trim((string) $value);
}

function duo_obituary_save_posted_options($content_uid, $board_id, $content, $board)
{
	if (!$board || !duo_obituary_is_skin($board->skin)) {
		return;
	}
	if (!duo_obituary_has_posted_options()) {
		return;
	}

	foreach (duo_obituary_option_keys() as $key) {
		duo_obituary_save_option_value($content_uid, $key, duo_obituary_posted_option_value($key));
	}

	if (class_exists('KBContentOption')) {
		$content->option = new KBContentOption($content_uid);
	}
}

add_action('kboard_document_insert', 'duo_obituary_save_posted_options', 20, 4);
add_action('kboard_document_update', 'duo_obituary_save_posted_options', 20, 4);

function duo_obituary_has_posted_options()
{
	foreach (duo_obituary_option_keys() as $key) {
		if (isset($_POST["kboard_option_{$key}"])) {
			return true;
		}
	}
	return false;
}

function duo_obituary_save_option_value($content_uid, $key, $value)
{
	global $wpdb;

	$content_uid = intval($content_uid);
	$key = sanitize_key($key);
	if (!$content_uid || !$key) {
		return;
	}

	$table = "{$wpdb->prefix}kboard_board_option";
	$wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE `content_uid`=%d AND `option_key`=%s", $content_uid, $key));

	$value = is_array($value) ? '' : trim((string) $value);
	if ($value === '') {
		return;
	}

	$wpdb->query($wpdb->prepare("INSERT INTO `{$table}` (`content_uid`, `option_key`, `option_value`) VALUES (%d, %s, %s)", $content_uid, $key, $value));
}

function duo_obituary_filter_insert_update_data($data, $board_id)
{
	$board = new KBoard($board_id);
	if (!duo_obituary_is_skin($board->skin)) {
		return $data;
	}
	if (!duo_obituary_has_posted_options()) {
		return $data;
	}

	$data['title'] = duo_obituary_build_title_from_post();
	$data['content'] = duo_obituary_encode_payload(duo_obituary_collect_posted_payload());

	return $data;
}

add_filter('kboard_insert_data', 'duo_obituary_filter_insert_update_data', 10, 2);
add_filter('kboard_update_data', 'duo_obituary_filter_insert_update_data', 10, 2);

function duo_obituary_build_title_from_row($row)
{
	$parts = array();
	foreach (array('affiliation', 'deceased_name', 'death_date') as $key) {
		if (!empty($row[$key])) {
			$parts[] = $row[$key];
		}
	}
	return $parts ? implode('/', $parts) : ($row['deceased_name'] ?? '부고');
}

function duo_obituary_sample_rows()
{
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

function duo_obituary_seed_sample_posts($builder)
{
	if (!duo_obituary_can_manage() || !$builder || empty($builder->board) || !duo_obituary_is_skin($builder->board->skin) || !class_exists('KBContent')) {
		return;
	}

	$board_id = !empty($builder->board_id) ? intval($builder->board_id) : (!empty($builder->board->id) ? intval($builder->board->id) : 0);
	if (!$board_id || get_option("duo_obituary_seeded_board_{$board_id}")) {
		return;
	}

	foreach (duo_obituary_sample_rows() as $row) {
		$content = new KBContent();
		$content->setBoardID($board_id);
		$content->member_uid = get_current_user_id();
		$content->member_display = '관리자';
		$content->title = duo_obituary_build_title_from_row($row);
		$content->content = duo_obituary_encode_payload($row);
		$content->search = 1;
		$content->status = '';
		$uid = $content->insertContent();
		if ($uid) {
			foreach (duo_obituary_option_keys() as $key) {
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
function duo_obituary_repair_missing_options($builder)
{
	if (!duo_obituary_can_manage() || !$builder || empty($builder->board) || !duo_obituary_is_skin($builder->board->skin)) {
		return;
	}

	$board_id = !empty($builder->board_id) ? intval($builder->board_id) : (!empty($builder->board->id) ? intval($builder->board->id) : 0);
	if (!$board_id || get_option("duo_obituary_repaired_board_{$board_id}")) {
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
	foreach ($rows as $row) {
		// content 필드에서 payload 복구 시도
		$payload = duo_obituary_decode_payload($row->content);
		if ($payload) {
			foreach (duo_obituary_option_keys() as $key) {
				if (isset($payload[$key]) && trim((string) $payload[$key]) !== '') {
					duo_obituary_save_option_value($row->uid, $key, $payload[$key]);
				}
			}
			$repaired++;
		}
	}

	if ($repaired > 0) {
		error_log("[Duo Obituary] Repaired {$repaired} posts for board {$board_id}");
	}

	update_option("duo_obituary_repaired_board_{$board_id}", '1', false);
}

add_action('kboard_skin_header', 'duo_obituary_repair_missing_options', 6);

function duo_obituary_repair_searchable_content($builder)
{
	if (!duo_obituary_can_manage() || !$builder || empty($builder->board) || !duo_obituary_is_skin($builder->board->skin)) {
		return;
	}

	$board_id = !empty($builder->board_id) ? intval($builder->board_id) : (!empty($builder->board->id) ? intval($builder->board->id) : 0);
	if (!$board_id || get_option("duo_obituary_search_repaired_board_{$board_id}")) {
		return;
	}

	global $wpdb;
	$content_table = "{$wpdb->prefix}kboard_board_content";
	$results = $wpdb->get_results($wpdb->prepare("SELECT `uid`, `content` FROM `{$content_table}` WHERE `board_id`=%d", $board_id));

	foreach ($results as $row) {
		$payload = duo_obituary_decode_payload($row->content);
		if ($payload) {
			$new_content = duo_obituary_encode_payload($payload);
			if ($new_content !== $row->content) {
				$wpdb->update($content_table, array('content' => $new_content), array('uid' => $row->uid));
			}
		}
	}

	update_option("duo_obituary_search_repaired_board_{$board_id}", '1', false);
}

add_action('kboard_skin_header', 'duo_obituary_repair_searchable_content', 7);

function duo_obituary_export_columns()
{
	return array(
		'uid' => '게시글 ID',
		'date' => '작성일',
		'status' => '상태',
		'affiliation' => '소속',
		'deceased_name' => '고인명',
		'chief_mourner' => '상주명',
		'death_date' => '별세일',
		'coffin_date' => '입관일',
		'funeral_date' => '발인일',
		'place' => '장례식장',
		'burial_place' => '장지',
		'title' => '제목',
	);
}

function duo_obituary_export_default_columns()
{
	return array_keys(duo_obituary_export_columns());
}

function duo_obituary_export_month_options()
{
	return array(
		'all' => '전체',
		'1' => '최근 1개월',
		'3' => '최근 3개월',
		'6' => '최근 6개월',
		'12' => '최근 12개월',
	);
}

function duo_obituary_export_normalize_months($months)
{
	$months = sanitize_key($months);
	return array_key_exists($months, duo_obituary_export_month_options()) ? $months : 'all';
}

function duo_obituary_export_start_date($months)
{
	$months = duo_obituary_export_normalize_months($months);
	if ($months === 'all') {
		return '';
	}
	return date('YmdHis', strtotime("-{$months} month", current_time('timestamp')));
}

function duo_obituary_export_selected_columns($columns)
{
	$available = duo_obituary_export_columns();
	if (!is_array($columns)) {
		$columns = $columns ? array($columns) : array();
	}

	$selected = array();
	foreach ($columns as $column) {
		$column = sanitize_key($column);
		if (isset($available[$column])) {
			$selected[] = $column;
		}
	}

	return $selected ? array_values(array_unique($selected)) : duo_obituary_export_default_columns();
}

function duo_obituary_export_selected_uids($uids)
{
	if (is_string($uids)) {
		$uids = preg_split('/\s*,\s*/', $uids, -1, PREG_SPLIT_NO_EMPTY);
	}
	if (!is_array($uids)) {
		return array();
	}

	$selected = array();
	foreach ($uids as $uid) {
		$uid = intval($uid);
		if ($uid > 0) {
			$selected[] = $uid;
		}
	}
	return array_values(array_unique($selected));
}

function duo_obituary_export_rows($board_id, $args = array())
{
	global $wpdb;

	$board_id = intval($board_id);
	if (!$board_id) {
		return array();
	}

	$args = wp_parse_args($args, array(
		'months' => 'all',
		'uids' => array(),
		'limit' => 0,
	));

	$content_table = "{$wpdb->prefix}kboard_board_content";
	$option_table = "{$wpdb->prefix}kboard_board_option";
	$where = array($wpdb->prepare('c.`board_id`=%d', $board_id), "c.`status`!='trash'", "c.`parent_uid`='0'");

	$start_date = duo_obituary_export_start_date($args['months']);
	$uids = duo_obituary_export_selected_uids($args['uids']);
	if ($uids) {
		$where[] = 'c.`uid` IN (' . implode(',', array_map('intval', $uids)) . ')';
	} else if ($start_date) {
		$where[] = $wpdb->prepare('c.`date` >= %s', $start_date);
	}

	$limit = intval($args['limit']);
	$limit_sql = $limit > 0 ? " LIMIT {$limit}" : '';
	$field_sql = array();
	foreach (duo_obituary_option_keys() as $key) {
		$safe_key = esc_sql($key);
		$field_sql[] = "MAX(CASE WHEN o.`option_key`='{$safe_key}' THEN o.`option_value` END) AS `{$safe_key}`";
	}

	$sql = "
		SELECT c.`uid`, c.`title`, c.`content`, c.`date`, c.`status`, " . implode(', ', $field_sql) . "
		FROM `{$content_table}` c
		LEFT JOIN `{$option_table}` o ON c.`uid`=o.`content_uid`
		WHERE " . implode(' AND ', $where) . "
		GROUP BY c.`uid`
		ORDER BY STR_TO_DATE(MAX(CASE WHEN o.`option_key`='funeral_date' THEN o.`option_value` END), '%Y-%m-%d %H:%i') DESC, c.`uid` DESC
		{$limit_sql}
	";

	$results = $wpdb->get_results($sql);
	$rows = array();
	foreach ($results as $row) {
		$rows[] = duo_obituary_export_normalize_row($row);
	}
	return $rows;
}

function duo_obituary_export_normalize_row($row)
{
	$content = (object) array(
		'uid' => $row->uid ?? 0,
		'title' => $row->title ?? '',
		'content' => $row->content ?? '',
		'option' => new stdClass(),
	);

	foreach (duo_obituary_option_keys() as $key) {
		$content->option->{$key} = isset($row->{$key}) ? (string) $row->{$key} : '';
	}

	$funeral_date = duo_obituary_option($content, 'funeral_date');
	$normalized = array(
		'uid' => intval($row->uid ?? 0),
		'date' => duo_obituary_export_format_kboard_date($row->date ?? ''),
		'status' => duo_obituary_is_expired($content) ? '지난 부고' : '진행중',
		'title' => (string) ($row->title ?? ''),
	);

	foreach (duo_obituary_option_keys() as $key) {
		$normalized[$key] = duo_obituary_option($content, $key);
	}
	$normalized['funeral_date'] = $funeral_date;

	return $normalized;
}

function duo_obituary_export_format_kboard_date($date)
{
	$date = trim((string) $date);
	if ($date === '') {
		return '';
	}
	$timestamp = strtotime($date);
	if (!$timestamp && preg_match('/^\d{14}$/', $date)) {
		$timestamp = strtotime(substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2) . ' ' . substr($date, 8, 2) . ':' . substr($date, 10, 2) . ':' . substr($date, 12, 2));
	}
	return $timestamp ? date('Y-m-d H:i:s', $timestamp) : $date;
}

function duo_obituary_csv_safe($value)
{
	if (is_array($value)) {
		$value = implode(', ', $value);
	}
	$value = (string) $value;
	if (function_exists('kboard_sanitize_csv_field')) {
		$value = kboard_sanitize_csv_field($value);
	}
	if (preg_match('/^[=+\-@]/', $value)) {
		$value = "'" . $value;
	}
	return $value;
}

function duo_obituary_render_export_setting($html, $board_meta, $board_id)
{
	$board = new KBoard($board_id);
	if (!duo_obituary_is_skin($board->skin)) {
		return $html;
	}

	$columns = duo_obituary_export_columns();
	$rows = duo_obituary_export_rows($board_id, array('limit' => 50));
	$nonce = wp_create_nonce('duo_obituary_export_csv');
	$action_url = admin_url('admin-post.php');
	$panel_id = 'duo-obituary-export-' . intval($board_id);

	ob_start();
	?>
	<div id="<?php echo esc_attr($panel_id) ?>" class="duo-obituary-admin-export"
		style="margin-top:20px; padding:20px; border:1px solid #dcdcde; background:#fff;">
		<h2 style="margin-top:0;">Duo 부고알림 Export</h2>
		<p class="description">표시된 최근 50건에서 필요한 게시글을 선택하거나, 작성일 기준 기간을 선택해 CSV로 내려받을 수 있습니다.</p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row">작성일 기간</th>
					<td>
						<select class="duo-obituary-export-months">
							<?php foreach (duo_obituary_export_month_options() as $value => $label): ?>
								<option value="<?php echo esc_attr($value) ?>"><?php echo esc_html($label) ?></option>
							<?php endforeach ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">CSV 칼럼</th>
					<td>
						<?php foreach ($columns as $key => $label): ?>
							<label style="display:inline-block; margin:0 14px 8px 0;">
								<input type="checkbox" class="duo-obituary-export-column" value="<?php echo esc_attr($key) ?>"
									checked>
								<?php echo esc_html($label) ?>
							</label>
						<?php endforeach ?>
					</td>
				</tr>
			</tbody>
		</table>
		<div style="max-height:420px; overflow:auto; border:1px solid #dcdcde; margin-top:12px;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th style="width:44px;"><input type="checkbox" class="duo-obituary-export-check-all"></th>
						<th>작성일</th>
						<th>상태</th>
						<th>소속</th>
						<th>고인명</th>
						<th>상주명</th>
						<th>발인일</th>
						<th>장례식장</th>
						<th>장지</th>
					</tr>
				</thead>
				<tbody>
					<?php if (!$rows): ?>
						<tr>
							<td colspan="9">Export할 부고 게시글이 없습니다.</td>
						</tr>
					<?php endif ?>
					<?php foreach ($rows as $row): ?>
						<tr>
							<td><input type="checkbox" class="duo-obituary-export-uid"
									value="<?php echo esc_attr($row['uid']) ?>"></td>
							<td><?php echo esc_html($row['date']) ?></td>
							<td><?php echo esc_html($row['status']) ?></td>
							<td><?php echo esc_html($row['affiliation'] ?: '-') ?></td>
							<td><strong><?php echo esc_html($row['deceased_name'] ?: '-') ?></strong></td>
							<td><?php echo esc_html($row['chief_mourner'] ?: '-') ?></td>
							<td><?php echo esc_html($row['funeral_date'] ?: '-') ?></td>
							<td><?php echo esc_html($row['place'] ?: '-') ?></td>
							<td><?php echo esc_html($row['burial_place'] ?: '-') ?></td>
						</tr>
					<?php endforeach ?>
				</tbody>
			</table>
		</div>
		<p style="margin-top:14px;">
			<button type="button" class="button button-primary duo-obituary-export-selected">선택/필터 Export</button>
			<button type="button" class="button duo-obituary-export-all">전체 Export</button>
		</p>
	</div>
	<script>
		(function () {
			const panel = document.getElementById('<?php echo esc_js($panel_id) ?>');
			if (!panel) return;
			const actionUrl = <?php echo wp_json_encode($action_url) ?>;
			const nonce = <?php echo wp_json_encode($nonce) ?>;
			const boardId = <?php echo intval($board_id) ?>;
			const collectColumns = function () {
				return Array.from(panel.querySelectorAll('.duo-obituary-export-column:checked')).map(function (input) { return input.value; });
			};
			const collectUids = function () {
				return Array.from(panel.querySelectorAll('.duo-obituary-export-uid:checked')).map(function (input) { return input.value; });
			};
			const go = function (useSelected, forceAll) {
				const params = new URLSearchParams();
				params.set('action', 'duo_obituary_export_csv');
				params.set('board_id', String(boardId));
				params.set('duo_obituary_export_nonce', nonce);
				params.set('months', forceAll ? 'all' : panel.querySelector('.duo-obituary-export-months').value);
				collectColumns().forEach(function (column) { params.append('columns[]', column); });
				if (useSelected) {
					const uids = collectUids();
					if (uids.length) {
						params.set('uids', uids.join(','));
					}
				}
				window.location.href = actionUrl + '?' + params.toString();
			};
			panel.querySelector('.duo-obituary-export-check-all')?.addEventListener('change', function (event) {
				panel.querySelectorAll('.duo-obituary-export-uid').forEach(function (input) { input.checked = event.target.checked; });
			});
			panel.querySelector('.duo-obituary-export-selected')?.addEventListener('click', function () { go(true, false); });
			panel.querySelector('.duo-obituary-export-all')?.addEventListener('click', function () { go(false, true); });
		})();
	</script>
	<?php
	return $html . ob_get_clean();
}

add_filter('kboard_extends_setting', 'duo_obituary_render_export_setting', 10, 3);

function duo_obituary_export_csv()
{
	if (!duo_obituary_can_manage()) {
		wp_die('권한이 없습니다.');
	}
	check_admin_referer('duo_obituary_export_csv', 'duo_obituary_export_nonce');

	$board_id = isset($_GET['board_id']) ? intval($_GET['board_id']) : 0;
	$board = new KBoard($board_id);
	if (!$board_id || !duo_obituary_is_skin($board->skin)) {
		wp_die('Duo 부고알림 게시판이 아닙니다.');
	}

	$columns = duo_obituary_export_selected_columns($_GET['columns'] ?? array());
	$months = isset($_GET['months']) ? duo_obituary_export_normalize_months(wp_unslash($_GET['months'])) : 'all';
	$uids = isset($_GET['uids']) ? duo_obituary_export_selected_uids(wp_unslash($_GET['uids'])) : array();
	$rows = duo_obituary_export_rows($board_id, array(
		'months' => $months,
		'uids' => $uids,
	));
	$labels = duo_obituary_export_columns();
	$filename = 'duo-obituary-board-' . $board_id . '-' . date('YmdHis', current_time('timestamp')) . '.csv';

	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Pragma: no-cache');
	header('Expires: 0');

	@ob_clean();
	@flush();

	$csv = fopen('php://output', 'w');
	fprintf($csv, chr(0xEF) . chr(0xBB) . chr(0xBF));
	fputcsv($csv, array_map(function ($column) use ($labels) {
		return $labels[$column];
	}, $columns));

	foreach ($rows as $row) {
		$line = array();
		foreach ($columns as $column) {
			$line[] = duo_obituary_csv_safe($row[$column] ?? '');
		}
		fputcsv($csv, $line);
	}

	fclose($csv);
	exit;
}

add_action('admin_post_duo_obituary_export_csv', 'duo_obituary_export_csv');

function duo_obituary_thumbnail_url($content)
{
	if (!$content || !duo_obituary_content_prop($content, 'thumbnail_file')) {
		return '';
	}
	return $content->getThumbnail(800, 1000);
}
