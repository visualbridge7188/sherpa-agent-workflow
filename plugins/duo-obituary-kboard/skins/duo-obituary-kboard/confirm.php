<?php
if(!defined('ABSPATH')){
	exit;
}
?>
<div id="duo-obituary-confirm" class="duo-obituary">
	<form method="post" action="<?php echo esc_url($url->getConfirmExecute($content->uid))?>">
		<input type="hidden" name="uid" value="<?php echo intval($content->uid)?>">
		<input type="password" name="password" placeholder="<?php echo esc_attr__('Password', 'kboard')?>">
		<button type="submit" class="duo-obituary-button primary"><?php echo esc_html__('Confirm', 'kboard')?></button>
	</form>
</div>
<?php wp_enqueue_style('duo-obituary-style', "{$skin_path}/style.css", array(), duo_obituary_asset_version($skin_dir, 'style.css')); ?>
