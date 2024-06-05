<?

if (!check_bitrix_sessid())
    return;


if ($ex = $APPLICATION->GetException())
    echo CAdminMessage::ShowMessage(array(
        "TYPE" => "ERROR",
        "MESSAGE" => 'не установлен',
        "DETAILS" => $ex->GetString(),
        "HTML" => true,
    ));
else
    echo CAdminMessage::ShowNote('Установлен');
?>
<form action="<?echo $APPLICATION->GetCurPage(); ?>">
    <input type="hidden" name="lang" value="<?echo LANG ?>">
    <input type="submit" name="" value="<?echo 'Назад'; ?>">
    <form>