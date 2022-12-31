<?
require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/prolog_before.php");
$img = Bitrix\Main\Config\Option::get('git.module', "turn_header_img");
$text = Bitrix\Main\Config\Option::get('git.module', "turn_header_text");
$btn_text = Bitrix\Main\Config\Option::get('git.module', "turn_header_btn_text");
$btn_link = Bitrix\Main\Config\Option::get('git.module', "turn_header_btn_link");
?>
<div class="queueTop__footer js_dop_turn_data">
	<div class="container-uni">
		<div class="reloadPlan pos-rel">
			<? if ($USER->isAdmin()): ?>
				<div class="admin-view">
					<div class="btn show-more">?</div>
					<div class="more-text">
						Данное спец предложение формируется в центральном <a href="https://git-site.ru/bitrix/admin/settings.php?mid=git.module&lang=ru" target="_blank">модуле сайта</a>
					</div>
				</div>
			<? endif ?>
			<? if ($img): ?>
				<div class="reloadPlan__img">
					<div class="reloadPlan__ico">
						<img src="/local/templates/.default/img/icon.png" alt="">
					</div>
				</div>
			<? endif ?>
			<div class="reloadPlan__desc">
				<? if ($text): ?>
					<div class="reloadPlan__text">
						<?=$text ?>
					</div>
				<? endif ?>
				<? if ($btn_link || $btn_text): ?>
					<a <?=($btn_link) ? "href='" . $btn_link . "'" : "" ?> class="unisent-btn  call--reload"><?=$btn_text ?></a>
				<? endif ?>
			</div>
		</div>
	</div>
</div>