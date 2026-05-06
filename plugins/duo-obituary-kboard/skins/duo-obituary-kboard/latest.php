<?php
if(!defined('ABSPATH')){
	exit;
}

/**
 * [중요] KBoard 쇼트코드의 5개 제한을 무시하기 위해 새로운 리스트 객체를 생성합니다.
 */
$board_id = $list->board_id;
$latest_list = new KBContentList($board_id);
$latest_list->is_latest = true; // 최신글 모드 활성화 (날짜 필터 작동을 위함)
$latest_list->rpp(100)->getList();

$items = array();
while($content = $latest_list->hasNext()){
	$items[] = $content;
}
$should_roll = count($items) > 5;

// 최신글 검색용
$latest_search_keyword = isset($_GET['keyword']) ? sanitize_text_field(wp_unslash($_GET['keyword'])) : '';
?>

<div id="duo-obituary-latest" class="duo-obituary duo-obituary-latest <?php echo $should_roll ? 'is-rolling' : ''?>" data-interval="3000">
	<div class="duo-obituary-loading-overlay" style="display:none;"><div class="duo-obituary-spinner"></div></div>

	<!-- 공용 검색창 -->
	<div class="duo-obituary-latest-search-container mobile-only">
		<form class="duo-obituary-latest-search-form" onsubmit="return false;">
			<div class="duo-obituary-search-group">
				<input type="search" name="keyword" value="<?php echo esc_attr($latest_search_keyword)?>" placeholder="고인명 또는 상주명 검색" class="duo-obituary-latest-search-input">
				<button type="button" class="duo-obituary-button primary duo-obituary-latest-search-submit">검색</button>
			</div>
		</form>
	</div>


	<!-- 데스크탑 테이블 -->
	<div class="duo-obituary-latest-desktop">
		<table class="duo-obituary-latest-table header-table">
			<colgroup>
				<col class="duo-latest-col-affiliation">
				<col class="duo-latest-col-deceased">
				<col class="duo-latest-col-mourner">
				<col class="duo-latest-col-date">
				<col class="duo-latest-col-place">
			</colgroup>
			<thead>
				<tr>
					<th>소속</th>
					<th>고인명</th>
					<th>상주명</th>
					<th>발인일</th>
					<th>장소</th>
				</tr>
			</thead>
		</table>
		<div class="duo-obituary-latest-table-wrap is-rolling-container">
			<table class="duo-obituary-latest-table body-table is-rolling-target">
				<colgroup>
					<col class="duo-latest-col-affiliation">
					<col class="duo-latest-col-deceased">
					<col class="duo-latest-col-mourner">
					<col class="duo-latest-col-date">
					<col class="duo-latest-col-place">
				</colgroup>
				<tbody>
					<tr class="duo-obituary-no-results" style="display:none;"><td colspan="5" class="duo-obituary-empty">검색 결과가 없습니다.</td></tr>
					<?php foreach($items as $content):?>
						<?php
						$document_url = $url->getDocumentURLWithUID($content->uid);
						$is_expired = duo_obituary_is_expired($content);
						?>
						<tr class="<?php echo $is_expired ? 'is-expired' : ''?>">
							<td><?php echo duo_obituary_display($content, 'affiliation')?></td>
							<td class="duo-obituary-name">
								<a href="<?php echo esc_url($document_url)?>"><?php echo duo_obituary_display($content, 'deceased_name')?></a>
								<?php if($is_expired):?><span class="duo-obituary-badge">지난 부고</span><?php endif?>
							</td>
							<td class="duo-obituary-ellipsis"><a href="<?php echo esc_url($document_url)?>"><?php echo duo_obituary_display($content, 'chief_mourner')?></a></td>
							<td><?php echo duo_obituary_datetime_display($content, 'funeral_date')?></td>
							<td class="duo-obituary-ellipsis"><?php echo duo_obituary_display($content, 'place')?></td>
						</tr>
					<?php endforeach?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- 모바일 테이블 -->
	<div class="duo-obituary-mobile-latest">
		<table class="duo-obituary-mobile-latest-table header-table">
			<colgroup>
				<col style="width: 25%;">
				<col style="width: 30%;">
				<col style="width: 45%;">
			</colgroup>
			<thead>
				<tr>
					<th>소속</th>
					<th>고인명</th>
					<th>발인일</th>
				</tr>
			</thead>
		</table>

		<div class="duo-obituary-mobile-latest-table-wrap is-rolling-container">
			<table class="duo-obituary-mobile-latest-table body-table is-rolling-target">
				<colgroup>
					<col style="width: 25%;">
					<col style="width: 30%;">
					<col style="width: 45%;">
				</colgroup>
				<tbody>
					<tr class="duo-obituary-no-results" style="display:none;"><td colspan="3" class="duo-obituary-empty">검색 결과가 없습니다.</td></tr>
					<?php foreach($items as $content):?>
						<?php
						$document_url = $url->getDocumentURLWithUID($content->uid);
						$is_expired = duo_obituary_is_expired($content);
						?>
						<tr class="<?php echo $is_expired ? 'is-expired' : ''?>">
							<td><?php echo duo_obituary_display($content, 'affiliation')?></td>
							<td class="duo-obituary-name">
								<a href="<?php echo esc_url($document_url)?>"><?php echo duo_obituary_display($content, 'deceased_name')?></a>
							</td>
							<td><?php echo duo_obituary_datetime_display($content, 'funeral_date')?></td>
						</tr>
					<?php endforeach?>
				</tbody>
			</table>
		</div>
	</div>



</div>
<?php
wp_enqueue_style('duo-obituary-style', "{$skin_path}/style.css", array(), duo_obituary_asset_version($skin_dir, 'style.css'));
wp_enqueue_script('duo-obituary-script', "{$skin_path}/script.js", array(), duo_obituary_asset_version($skin_dir, 'script.js'), true);
?>
