<?php
if(PHP_SAPI !== 'cli'){
	exit(1);
}

$root = dirname(__DIR__);
$mode = $argv[1] ?? '';

function ok($condition, $message){
	if(!$condition){
		fwrite(STDERR, "FAIL: {$message}\n");
		exit(1);
	}
}

function contains_text($haystack, $needle, $message){
	ok(strpos($haystack, $needle) !== false, $message);
}

if($mode === 'plugin'){
	define('ABSPATH', $root . '/');
	define('KBOARD_VERSION', '6.0');

	function add_action($hook, $callback){ return true; }
	function add_filter($hook, $callback){ return true; }
	function plugin_dir_path($file){ return dirname($file) . '/'; }
	function plugin_dir_url($file){ return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/'; }
	function esc_html__($text, $domain = null){ return $text; }
	function esc_html($text){ return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
	function current_user_can($capability){ return true; }

	class KBoardSkin {}

	require $root . '/plugins/duo-obituary-kboard/duo-obituary-kboard.php';

	ok(duo_obituary_kboard_is_kboard_active(), 'KBoard active check should pass when KBOARD_VERSION and KBoardSkin exist.');
	$skins = duo_obituary_kboard_register_skin(array());
	ok(isset($skins['Duo 부고알림']), 'Plugin should register the same key KBoard stores from skin item name.');
	ok($skins['Duo 부고알림']->name === 'Duo 부고알림', 'Registered skin should expose Korean display name.');
	ok(is_dir($skins['Duo 부고알림']->dir), 'Registered skin directory should exist.');
	ok(is_file($skins['Duo 부고알림']->dir . '/list.php'), 'Registered skin directory should contain list.php.');
	contains_text($skins['Duo 부고알림']->url, '/skins/duo-obituary-kboard', 'Registered skin URL should point to packaged skin.');

	exit(0);
}

if($mode === 'plugin-inactive'){
	define('ABSPATH', $root . '/');

	function add_action($hook, $callback){ return true; }
	function add_filter($hook, $callback){ return true; }
	function plugin_dir_path($file){ return dirname($file) . '/'; }
	function plugin_dir_url($file){ return 'https://example.test/wp-content/plugins/' . basename(dirname($file)) . '/'; }
	function esc_html__($text, $domain = null){ return $text; }
	function esc_html($text){ return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8'); }
	function current_user_can($capability){ return true; }

	require $root . '/plugins/duo-obituary-kboard/duo-obituary-kboard.php';

	ok(!duo_obituary_kboard_is_kboard_active(), 'KBoard active check should fail without KBoard.');
	ok(duo_obituary_kboard_register_skin(array('default' => 'skin')) === array('default' => 'skin'), 'Skin registration should no-op without KBoard.');

	ob_start();
	duo_obituary_kboard_dependency_notice();
	$notice = ob_get_clean();
	contains_text($notice, 'KBoard 플러그인', 'Inactive dependency notice should mention KBoard plugin.');

	exit(0);
}

if($mode === 'skin'){
	define('ABSPATH', $root . '/');

	function add_action($hook, $callback, $priority = 10, $args = 1){ return true; }
	function add_filter($hook, $callback, $priority = 10, $args = 1){ return true; }
	function current_time($type){
		return strtotime('2026-05-07 10:00:00');
	}
	function current_user_can($capability){
		return !empty($GLOBALS['duo_test_can_manage']);
	}
	function wp_unslash($value){ return $value; }
	function sanitize_text_field($value){ return trim(strip_tags((string)$value)); }
	function sanitize_key($value){ return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$value)); }
	function esc_sql($value){ return addslashes((string)$value); }
	function esc_html($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
	function esc_attr($value){ return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
	function wp_json_encode($payload, $options = 0){ return json_encode($payload, $options); }
	function wp_parse_args($args, $defaults = array()){ return array_merge($defaults, is_array($args) ? $args : array()); }
	function kboard_xssfilter($value){ return $value; }
	function kboard_safeiframe($value){ return $value; }
	function trailingslashit($value){ return rtrim($value, '/') . '/'; }
	function get_option($key, $default = false){ return $default; }
	function update_option($key, $value, $autoload = null){ return true; }
	function get_current_user_id(){ return 1; }
	function kboard_sanitize_csv_field($value){ return $value; }
	function kboard_keyword(){ return isset($_GET['keyword']) ? sanitize_text_field($_GET['keyword']) : ''; }

	class DuoSmokeWpdb {
		public $prefix = 'wp_';
		public $option_values = array();
		public $queries = array();
		public $last_get_results_query = '';
		public function esc_like($text){
			return addcslashes($text, '_%\\');
		}
		public function prepare($query, ...$args){
			foreach($args as $arg){
				if(strpos($query, '%d') !== false){
					$query = preg_replace('/%d/', (string)intval($arg), $query, 1);
				}
				else{
					$query = preg_replace('/%s/', "'" . addslashes((string)$arg) . "'", $query, 1);
				}
			}
			return $query;
		}
		public function get_var($query){
			if(strpos($query, 'kboard_board_option') !== false && preg_match("/content_uid`='([0-9]+)'.*option_key`='([^']+)'/s", $query, $matches)){
				$content_uid = intval($matches[1]);
				$option_key = $matches[2];
				return $this->option_values[$content_uid][$option_key] ?? '';
			}
			return 'duo-obituary-kboard';
		}
		public function query($query){
			$this->queries[] = $query;
			if(preg_match("/INSERT INTO `wp_kboard_board_option` .* VALUES \\(([0-9]+), '([^']+)', '([^']*)'\\)/", $query, $matches)){
				$this->option_values[intval($matches[1])][$matches[2]] = stripslashes($matches[3]);
			}
			if(preg_match("/DELETE FROM `wp_kboard_board_option` WHERE `content_uid`=([0-9]+) AND `option_key`='([^']+)'/", $query, $matches)){
				unset($this->option_values[intval($matches[1])][$matches[2]]);
			}
			return true;
		}
		public function get_results($query){
			$this->last_get_results_query = $query;
			return array();
		}
	}
	$GLOBALS['wpdb'] = new DuoSmokeWpdb();

	class KBoard {
		public $skin = 'Duo 부고알림';
		public $board_id = 0;
		public function __construct($board_id = 0){
			$this->board_id = intval($board_id);
		}
	}

	class KBContentOption {
		public $content_uid;
		public $row = array();
		public function __construct($content_uid = 0){
			$this->content_uid = intval($content_uid);
		}
		public function __get($key){
			return $this->row[$key] ?? '';
		}
	}

	require $root . '/skin/duo-obituary-kboard/functions.php';

	$content = (object)array('option' => (object)array(
		'affiliation' => '',
		'deceased_name' => '홍길동',
		'chief_mourner' => "홍상주\n홍가족",
		'death_date' => '2026-05-07 09:30',
		'funeral_date' => '2026-05-06 08:00',
	));

	ok(duo_obituary_display($content, 'affiliation') === '-', 'Empty display values should render as hyphen.');
	ok(duo_obituary_datetime_display($content, 'death_date') === '2026-05-07 09:30', 'Datetime values should render as YYYY-MM-DD HH:mm.');
	ok(duo_obituary_is_expired($content) === true, 'Funeral date before current date should be expired.');

	$GLOBALS['wpdb']->option_values[77]['deceased_name'] = 'DB고인';
	$db_content = (object)array('uid' => 77);
	ok(duo_obituary_display($db_content, 'deceased_name') === 'DB고인', 'Display should fall back to KBoard option table when content option object is empty.');

	$content->option->funeral_date = '2026-05-07 06:00';
	ok(duo_obituary_is_expired($content) === false, 'Funeral date on current date should not be expired yet.');

	$expired_content = (object)array('option' => (object)array('funeral_date' => '2026-05-06 08:00'));
	$active_content = (object)array('option' => (object)array('funeral_date' => '2026-05-09 08:00'));
	$latest_items = duo_obituary_active_latest_items(array($expired_content, $active_content));
	ok(count($latest_items) === 1 && $latest_items[0] === $active_content, 'Latest template should remove expired obituaries before rendering.');

	$_POST = array(
		'action' => 'kboard_editor_execute',
		'kboard_option_affiliation' => '대한상공회',
		'kboard_option_deceased_name' => '홍길동',
		'kboard_option_death_date' => '2026-05-07T09:30',
		'kboard_option_chief_mourner' => '홍상주',
		'kboard_option_funeral_date' => '2026-05-09T08:00',
	);
	ok(duo_obituary_build_title_from_post() === '대한상공회/홍길동/2026-05-07 09:30', 'Title should be generated from affiliation, deceased name, and death date.');
	$board = new KBoard(1);
	$saved_content = (object)array();
	duo_obituary_save_posted_options(123, 1, $saved_content, $board);
	ok($GLOBALS['wpdb']->option_values[123]['deceased_name'] === '홍길동', 'Insert/update hook should directly save deceased name option.');
	ok($GLOBALS['wpdb']->option_values[123]['funeral_date'] === '2026-05-09 08:00', 'Insert/update hook should directly save normalized funeral date option.');

	$data = array('title' => 'old', 'content' => 'body');
	$filtered = duo_obituary_filter_insert_update_data($data, 1);
	ok($filtered['title'] === '대한상공회/홍길동/2026-05-07 09:30', 'Editor save data should receive generated title.');
	contains_text($filtered['content'], 'duo-obituary-payload', 'Editor save data should keep a hidden payload backup.');
	contains_text($filtered['content'], '홍길동', 'Hidden payload should include searchable plain text.');
	$payload = duo_obituary_decode_payload($filtered['content']);
	ok($payload['deceased_name'] === '홍길동', 'Hidden payload should contain deceased name.');
	$payload_content = (object)array('content' => $filtered['content']);
	ok(duo_obituary_option($payload_content, 'deceased_name') === '홍길동', 'Display/edit values should fall back to hidden payload when option rows are missing.');

	$_POST = array();
	$unchanged = duo_obituary_filter_insert_update_data($data, 1);
	ok($unchanged === $data, 'Non-editor KBoard updates should not be rewritten.');

	ok(duo_obituary_is_skin('duo-obituary-kboard'), 'Internal skin slug should be recognized.');
	ok(duo_obituary_is_skin('Duo 부고알림'), 'KBoard stored Korean skin name should be recognized.');

	$list = (object)array('board' => (object)array('skin' => 'Duo 부고알림'));
	$GLOBALS['duo_test_can_manage'] = false;
	$_GET = array('keyword' => '홍');
	$from = duo_obituary_add_query_joins('`wp_kboard_board_content`', 1, $list);
	foreach (array('duo_affiliation', 'duo_deceased_name', 'duo_chief_mourner', 'duo_death_date', 'duo_coffin_date', 'duo_funeral_date', 'duo_place', 'duo_burial_place') as $alias) {
		contains_text($from, $alias, "List query should join {$alias} option.");
	}

	$where = duo_obituary_query_where('1=1', 1, $list);
	ok(strpos($where, ">= '2026-05-07'") === false, 'Public list query should show expired obituaries.');
	foreach (array('duo_affiliation', 'duo_deceased_name', 'duo_chief_mourner', 'duo_death_date', 'duo_coffin_date', 'duo_funeral_date', 'duo_place', 'duo_burial_place') as $alias) {
		contains_text($where, "{$alias}.`option_value` LIKE", "Integrated search should include {$alias}.");
	}
	$kboard_default_keyword_where = "`wp_kboard_board_content`.`board_id`='1' AND (`wp_kboard_board_content`.`title` LIKE '%홍%' OR `wp_kboard_board_content`.`content` LIKE '%홍%')";
	$expanded_keyword_where = duo_obituary_query_where($kboard_default_keyword_where, 1, $list);
	contains_text($expanded_keyword_where, "`wp_kboard_board_content`.`content` LIKE '%홍%') OR (duo_affiliation", 'Option search should broaden KBoard default keyword search instead of requiring both content and option matches.');

	$latest_list = (object)array('board' => (object)array('skin' => 'Duo 부고알림'), 'is_latest' => true);
	$latest_where = duo_obituary_query_where('1=1', 1, $latest_list);
	contains_text($latest_where, "DATE(STR_TO_DATE(duo_funeral_date.`option_value`, '%Y-%m-%d %H:%i')) >= '2026-05-07'", 'Latest query should hide expired obituaries.');
	contains_text($latest_where, 'duo_place.`option_value` LIKE', 'Latest search should include funeral hall option.');
	contains_text($latest_where, 'duo_burial_place.`option_value` LIKE', 'Latest search should include burial place option.');

	$manual_latest_list = (object)array('is_latest' => true);
	$manual_latest_where = duo_obituary_query_where('1=1', 1, $manual_latest_list);
	contains_text($manual_latest_where, "DATE(STR_TO_DATE(duo_funeral_date.`option_value`, '%Y-%m-%d %H:%i')) >= '2026-05-07'", 'Latest query should hide expired obituaries even when latest.php creates a fresh list without board metadata.');

	$GLOBALS['duo_test_can_manage'] = true;
	$admin_where = duo_obituary_query_where('1=1', 1, $list);
	ok(strpos($admin_where, ">= '2026-05-07'") === false, 'Manager query should not hide expired obituaries.');

	$orderby = duo_obituary_query_orderby('date DESC', 1, $list);
	contains_text($orderby, "STR_TO_DATE(duo_funeral_date.`option_value`, '%Y-%m-%d %H:%i') DESC", 'List order should sort by later funeral date first.');
	ok(duo_obituary_latest_rpp(5, 1, $latest_list) === 100, 'Latest list should request enough rows for rolling.');

	ok(duo_obituary_export_selected_columns(array('uid', 'deceased_name', 'bad')) === array('uid', 'deceased_name'), 'Export column selection should keep only allowed columns.');
	ok(duo_obituary_export_normalize_months('999') === 'all', 'Export months should fall back to all for invalid values.');
	ok(duo_obituary_export_start_date('3') === '20260207100000', 'Export month filter should use written date relative to current time.');
	ok(duo_obituary_csv_safe('=SUM(A1:A2)') === "'=SUM(A1:A2)", 'CSV export should guard formula-like values.');
	$labels = duo_obituary_export_columns();
	ok($labels['deceased_name'] === '고인명' && $labels['funeral_date'] === '발인일', 'Export headers should use Korean labels.');
	duo_obituary_export_rows(7, array('months' => '3'));
	contains_text($GLOBALS['wpdb']->last_get_results_query, "c.`date` >= '20260207100000'", 'Export month filter should constrain written date when no posts are selected.');
	duo_obituary_export_rows(7, array('months' => '3', 'uids' => array(10, 11)));
	contains_text($GLOBALS['wpdb']->last_get_results_query, 'c.`uid` IN (10,11)', 'Export selected rows should constrain selected post IDs.');
	ok(strpos($GLOBALS['wpdb']->last_get_results_query, "c.`date` >=") === false, 'Export selected rows should ignore month filter.');

	$style = file_get_contents($root . '/skin/duo-obituary-kboard/style.css');
	$script = file_get_contents($root . '/skin/duo-obituary-kboard/script.js');
	contains_text($style, '.is-rolling .is-rolling-container', 'Latest rolling viewport height should only apply while rolling.');
	ok(!preg_match('/^\.is-rolling-container\s*\{[^}]*height:\s*260px;/m', $style), 'Latest container should not have unconditional fixed height.');
	contains_text($style, 'min-height: 52px;', 'Latest rows should have a minimum row height instead of clipping wrapped content.');
	ok(!preg_match('/\.duo-obituary-mobile-latest-table td\s*\{[^}]*\n\t\theight:\s*54px;/m', $style), 'Mobile latest cells should not force a clipping fixed height.');
	contains_text($script, 'getBoundingClientRect().height', 'Rolling distance should use actual visible row heights.');

	exit(0);
}

fwrite(STDERR, "Usage: php tests/smoke.php plugin|plugin-inactive|skin\n");
exit(1);
