<?php
if(!defined('ABSPATH')){
	exit;
}

$search_target = isset($_GET['duo_obituary_target']) ? sanitize_key(wp_unslash($_GET['duo_obituary_target'])) : 'deceased_mourner';
$search_keyword = isset($_GET['duo_obituary_keyword']) ? sanitize_text_field(wp_unslash($_GET['duo_obituary_keyword'])) : '';
$items = array();
while($content = $list->hasNext()){
	$items[] = $content;
}
?>
<div id="duo-obituary-list" class="duo-obituary duo-obituary-list">
	<div class="duo-obituary-list-header">
		<div class="duo-obituary-total">Total <?php echo number_format($list->total)?>건</div>
		<form class="duo-obituary-search" method="get" action="<?php echo esc_url($url->toString())?>">
			<?php echo $url->set('pageid', '1')->set('mod', 'list')->set('target', '')->set('keyword', '')->toInput()?>
			<label><input type="radio" name="duo_obituary_target" value="deceased_name"<?php checked($search_target, 'deceased_name')?>> 고인명</label>
			<label><input type="radio" name="duo_obituary_target" value="chief_mourner"<?php checked($search_target, 'chief_mourner')?>> 상주명</label>
			<label><input type="radio" name="duo_obituary_target" value="deceased_mourner"<?php checked($search_target, 'deceased_mourner')?>> 고인명 + 상주명</label>
			<input type="search" name="duo_obituary_keyword" value="<?php echo esc_attr($search_keyword)?>">
			<button type="submit" class="duo-obituary-button primary">검색</button>
		</form>
	</div>

	<div class="duo-obituary-table-wrap">
		<table class="duo-obituary-table">
			<colgroup>
				<col class="duo-col-affiliation">
				<col class="duo-col-deceased">
				<col class="duo-col-mourner">
				<col class="duo-col-date">
				<col class="duo-col-date">
				<col class="duo-col-date">
				<col class="duo-col-place">
				<col class="duo-col-burial">
			</colgroup>
			<thead>
				<tr>
					<th>소속</th>
					<th>고인명</th>
					<th>상주명</th>
					<th>별세일</th>
					<th>입관일</th>
					<th>발인일</th>
					<th>장소</th>
					<th>장지</th>
				</tr>
			</thead>
			<tbody>
				<?php if(!$items):?>
					<tr><td colspan="8" class="duo-obituary-empty">현재 부고 소식이 없습니다.</td></tr>
				<?php endif?>
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
						<td><?php echo duo_obituary_datetime_display($content, 'death_date')?></td>
						<td><?php echo duo_obituary_datetime_display($content, 'coffin_date')?></td>
						<td><?php echo duo_obituary_datetime_display($content, 'funeral_date')?></td>
						<td class="duo-obituary-ellipsis"><?php echo duo_obituary_display($content, 'place')?></td>
						<td class="duo-obituary-ellipsis"><?php echo duo_obituary_display($content, 'burial_place')?></td>
					</tr>
				<?php endforeach?>
			</tbody>
		</table>
	</div>

	<div class="duo-obituary-mobile-list">
		<?php if(!$items):?>
			<div class="duo-obituary-empty">현재 부고 소식이 없습니다.</div>
		<?php endif?>
		<?php foreach($items as $content):?>
			<?php
			$document_url = $url->getDocumentURLWithUID($content->uid);
			$is_expired = duo_obituary_is_expired($content);
			?>
			<a class="duo-obituary-mobile-item <?php echo $is_expired ? 'is-expired' : ''?>" href="<?php echo esc_url($document_url)?>">
				<div class="duo-obituary-mobile-title">
					<span><?php echo duo_obituary_display($content, 'deceased_name')?></span>
					<?php if($is_expired):?><span class="duo-obituary-badge">지난 부고</span><?php endif?>
				</div>
				<div class="duo-obituary-mobile-line">상주명 <?php echo duo_obituary_display($content, 'chief_mourner')?></div>
				<div class="duo-obituary-mobile-meta">
					<span>소속 <?php echo duo_obituary_display($content, 'affiliation')?></span>
					<span>발인일 <?php echo duo_obituary_datetime_display($content, 'funeral_date')?></span>
					<span>장소 <?php echo duo_obituary_display($content, 'place')?></span>
				</div>
			</a>
		<?php endforeach?>
	</div>

	<div class="duo-obituary-pagination">
		<ul><?php echo kboard_pagination($list->page, $list->total, $list->rpp)?></ul>
	</div>

	<?php if($board->isWriter()):?>
		<div class="duo-obituary-control">
			<div></div>
			<div class="right"><a href="<?php echo esc_url($url->getContentEditor())?>" class="duo-obituary-button primary">부고 등록</a></div>
		</div>
	<?php endif?>
</div>
<?php
wp_enqueue_style('duo-obituary-style', "{$skin_path}/style.css", array(), duo_obituary_asset_version($skin_dir, 'style.css'));
wp_enqueue_script('duo-obituary-script', "{$skin_path}/script.js", array(), duo_obituary_asset_version($skin_dir, 'script.js'), true);
?>
