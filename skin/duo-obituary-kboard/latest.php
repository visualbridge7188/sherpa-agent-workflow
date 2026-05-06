<?php
if(!defined('ABSPATH')){
	exit;
}

$items = array();
while($content = $list->hasNext()){
	$items[] = $content;
}
$should_roll = count($items) > 5;
?>
<div id="duo-obituary-latest" class="duo-obituary duo-obituary-latest <?php echo $should_roll ? 'is-rolling' : ''?>" data-interval="3000">
	<div class="duo-obituary-latest-table-wrap">
		<table class="duo-obituary-latest-table">
			<thead>
				<tr>
					<th>소속</th>
					<th>고인명</th>
					<th>상주명</th>
					<th>별세일</th>
					<th>장소</th>
				</tr>
			</thead>
			<tbody>
				<?php if(!$items):?>
					<tr><td colspan="5" class="duo-obituary-empty">현재 부고 소식이 없습니다.</td></tr>
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
						<td class="duo-obituary-ellipsis"><?php echo duo_obituary_display($content, 'place')?></td>
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
</div>
<?php
wp_enqueue_style('duo-obituary-style', "{$skin_path}/style.css", array(), duo_obituary_asset_version($skin_dir, 'style.css'));
wp_enqueue_script('duo-obituary-script', "{$skin_path}/script.js", array(), duo_obituary_asset_version($skin_dir, 'script.js'), true);
?>
