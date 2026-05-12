<?php
if (!defined('ABSPATH')) {
	exit;
}

$is_expired = duo_obituary_is_expired($content);
$thumbnail_url = duo_obituary_thumbnail_url($content);
?>
<div id="duo-obituary-document" class="duo-obituary duo-obituary-document">
	<?php if ($is_expired): ?>
		<div class="duo-obituary-document-status"><span class="duo-obituary-badge">지난 부고</span></div><?php endif ?>
	<div class="duo-obituary-document-main">
		<div class="duo-obituary-photo">
			<?php if ($thumbnail_url): ?>
				<img src="<?php echo esc_url($thumbnail_url) ?>"
					alt="<?php echo esc_attr(duo_obituary_option($content, 'deceased_name')) ?>">
			<?php else: ?>
				<span>사진</span>
			<?php endif ?>
		</div>
		<table class="duo-obituary-detail-table">
			<tbody>
				<tr>
					<th>소속</th>
					<td><?php echo duo_obituary_display($content, 'affiliation') ?></td>
				</tr>
				<tr>
					<th>고인명</th>
					<td><?php echo duo_obituary_display($content, 'deceased_name') ?></td>
				</tr>
				<tr>
					<th>상주명</th>
					<td><?php echo nl2br(duo_obituary_display($content, 'chief_mourner')) ?></td>
				</tr>
				<tr>
					<th>입관일</th>
					<td><?php echo duo_obituary_datetime_display($content, 'coffin_date') ?></td>
				</tr>
				<tr>
					<th>발인일</th>
					<td><?php echo duo_obituary_datetime_display($content, 'funeral_date') ?></td>
				</tr>
				<tr>
					<th>장례식장</th>
					<td><?php echo duo_obituary_display($content, 'place') ?></td>
				</tr>
				<tr>
					<th>장지</th>
					<td><?php echo duo_obituary_display($content, 'burial_place') ?></td>
				</tr>
			</tbody>
		</table>
	</div>

	<div class="duo-obituary-control">
		<div class="left">
			<a href="<?php echo esc_url($url->getBoardList()) ?>"
				class="duo-obituary-button secondary"><?php echo esc_html__('List', 'kboard') ?></a>
		</div>
		<?php if ($content->isEditor() || $board->permission_write == 'all'): ?>
			<div class="right">
				<a href="<?php echo esc_url($url->getContentEditor($content->uid)) ?>"
					class="duo-obituary-button secondary"><?php echo esc_html__('Edit', 'kboard') ?></a>
				<a href="<?php echo esc_url($url->getContentRemove($content->uid)) ?>" class="duo-obituary-button secondary"
					onclick="return confirm('<?php echo esc_attr__('Are you sure you want to delete?', 'kboard') ?>');"><?php echo esc_html__('Delete', 'kboard') ?></a>
			</div>
		<?php endif ?>
	</div>
</div>
<?php
wp_enqueue_style('duo-obituary-style', "{$skin_path}/style.css", array(), duo_obituary_asset_version($skin_dir, 'style.css'));
wp_enqueue_script('duo-obituary-script', "{$skin_path}/script.js", array(), duo_obituary_asset_version($skin_dir, 'script.js'), true);
?>