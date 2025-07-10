<?
//globals
global $APPLICATION;

//append paysystems text edit icon (because after ajax request id resets)
if($APPLICATION->GetShowIncludeAreas()){
    $APPLICATION->IncludeString("", array(array(
		"URL" => "/bitrix/admin/fileman_file_edit.php?path=%2Fsect_detail_paysystems.php",
		"SRC" => SITE_TEMPLATE_PATH."/images/paysystems_edit.png",
		"ALT" => GetMessage("AJAX_PAYSYSTEMS_EDIT_TITLE")
	)));
}

?>