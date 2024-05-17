<?
/**
 * Created by PhpStorm.
 * User: Sergey Pokoev
 * www.pokoev.ru
 */
if (!check_bitrix_sessid())
	return;

use \Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);
if ($ex = $APPLICATION->GetException())
	echo CAdminMessage::ShowMessage(array(
		"TYPE" => "ERROR",
		"MESSAGE" => Loc::getMessage("MOD_INST_ERR"),
		"DETAILS" => $ex->GetString(),
		"HTML" => true,
	));
else 
	echo CAdminMessage::ShowNote(Loc::getMessage("MOD_INST_OK"));
?>
<form action="<?echo $APPLICATION->GetCurPage(); ?>">
	<input type="hidden" name="lang" value="<?echo LANG ?>">
	<input type="submit" name="" value="<?echo Loc::getMessage("MOD_BACK"); ?>">
<form>