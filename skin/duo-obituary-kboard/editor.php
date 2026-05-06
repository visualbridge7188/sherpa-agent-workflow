<?php
if(!defined('ABSPATH')){
	exit;
}

$values = array();
foreach(array_keys(duo_obituary_fields()) as $key){
	$values[$key] = duo_obituary_option($content, $key);
}
$auto_title = $content->title ? $content->title : implode('/', array_filter(array($values['affiliation'], $values['deceased_name'], $values['death_date'])));
?>
<div id="duo-obituary-editor" class="duo-obituary duo-obituary-editor">
	<form class="kboard-form duo-obituary-form" method="post" action="<?php echo esc_url($url->getContentEditorExecute())?>" enctype="multipart/form-data" onsubmit="return duoObituaryBeforeSubmit(this) && (typeof kboard_editor_execute !== 'function' || kboard_editor_execute(this));">
		<?php $skin->editorHeader($content, $board)?>
		<input type="hidden" id="duo-obituary-title" name="title" value="<?php echo esc_attr($auto_title ? $auto_title : '부고')?>">
		<input type="hidden" name="kboard_content" value="">

		<div class="duo-obituary-field duo-obituary-field-photo">
			<label for="duo-obituary-thumbnail">사진</label>
			<div class="duo-obituary-field-control">
				<?php if($content->thumbnail_file):?>
					<div class="duo-obituary-current-file"><?php echo esc_html($content->thumbnail_name)?></div>
				<?php endif?>
				<input type="file" id="duo-obituary-thumbnail" name="thumbnail" accept="image/*">
			</div>
		</div>

		<div class="duo-obituary-field">
			<label for="duo-affiliation">소속</label>
			<input type="text" id="duo-affiliation" name="kboard_option_affiliation" value="<?php echo esc_attr($values['affiliation'])?>">
		</div>

		<div class="duo-obituary-field required">
			<label for="duo-deceased-name">고인명 <span>*</span></label>
			<input type="text" id="duo-deceased-name" name="kboard_option_deceased_name" value="<?php echo esc_attr($values['deceased_name'])?>" required>
		</div>

		<div class="duo-obituary-field required">
			<label for="duo-chief-mourner">상주명 <span>*</span></label>
			<textarea id="duo-chief-mourner" name="kboard_option_chief_mourner" rows="4" required><?php echo esc_textarea($values['chief_mourner'])?></textarea>
		</div>

		<div class="duo-obituary-field-grid">
			<div class="duo-obituary-field">
				<label for="duo-death-date">별세일</label>
				<input type="datetime-local" id="duo-death-date" name="kboard_option_death_date" value="<?php echo esc_attr(str_replace(' ', 'T', $values['death_date']))?>">
			</div>
			<div class="duo-obituary-field">
				<label for="duo-coffin-date">입관일</label>
				<input type="datetime-local" id="duo-coffin-date" name="kboard_option_coffin_date" value="<?php echo esc_attr(str_replace(' ', 'T', $values['coffin_date']))?>">
			</div>
			<div class="duo-obituary-field required">
				<label for="duo-funeral-date">발인일 <span>*</span></label>
				<input type="datetime-local" id="duo-funeral-date" name="kboard_option_funeral_date" value="<?php echo esc_attr(str_replace(' ', 'T', $values['funeral_date']))?>" required>
			</div>
		</div>

		<div class="duo-obituary-field">
			<label for="duo-place">장소</label>
			<input type="text" id="duo-place" name="kboard_option_place" value="<?php echo esc_attr($values['place'])?>">
		</div>

		<div class="duo-obituary-field">
			<label for="duo-burial-place">장지</label>
			<input type="text" id="duo-burial-place" name="kboard_option_burial_place" value="<?php echo esc_attr($values['burial_place'])?>">
		</div>

		<?php if(!is_user_logged_in()):?>
			<div class="duo-obituary-field required">
				<label for="kboard-input-password"><?php echo esc_html__('Password', 'kboard')?> <span>*</span></label>
				<input type="password" id="kboard-input-password" name="password" value="<?php echo esc_attr($content->password)?>" required>
			</div>
		<?php endif?>

		<div class="duo-obituary-control">
			<div class="left">
				<?php if($content->uid):?>
					<a href="<?php echo esc_url($url->getDocumentURLWithUID($content->uid))?>" class="duo-obituary-button secondary"><?php echo esc_html__('Back', 'kboard')?></a>
				<?php endif?>
				<a href="<?php echo esc_url($url->getBoardList())?>" class="duo-obituary-button secondary"><?php echo esc_html__('List', 'kboard')?></a>
			</div>
			<div class="right">
				<?php if($board->isWriter()):?>
					<button type="submit" class="duo-obituary-button primary">저장</button>
				<?php endif?>
			</div>
		</div>
	</form>
</div>
<?php
wp_enqueue_style('duo-obituary-style', "{$skin_path}/style.css", array(), duo_obituary_asset_version($skin_dir, 'style.css'));
wp_enqueue_script('duo-obituary-script', "{$skin_path}/script.js", array(), duo_obituary_asset_version($skin_dir, 'script.js'), true);
?>
