<?
require($_SERVER['DOCUMENT_ROOT'] . '/classes/Import.php');

use Classes\Importer;

$timerFileRemains = $_SERVER['DOCUMENT_ROOT'] . '/import/timer-remains.txt';
$timerRemains = file_get_contents($timerFileRemains) + 60 * 60; //60 минут
$timerFile = $_SERVER['DOCUMENT_ROOT'] . '/import/timer.txt';


file_put_contents($timerFile, time());

$input = file_get_contents("php://input");
$ar = json_encode([$_REQUEST, 'IP' => $_SERVER['REMOTE_ADDR']]);
$arResult = json_decode(preg_replace('/\x{EF}\x{BB}\x{BF}/', '', $input), true);

if($_SERVER['REMOTE_ADDR'] != '5.9.112.40' && empty($arResult['Img']))
{
    die('ip access denied');
}

file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/all/' . date('d.m.Y H:i:s') . '_request.txt', $ar);
if ( ! empty($arResult['Item'])) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/item/' . date('d.m.Y H:i:s') . '.txt', $input);
} elseif ( ! empty($arResult['Characteristic'])) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/characteristic/' . date('d.m.Y H:i:s') . '.txt', $input);
} elseif ( ! empty($arResult['ItemSection'])) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/itemsection/' . date('d.m.Y H:i:s') . '.txt', $input);
} elseif ( ! empty($arResult['Price'])) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/price/' . date('d.m.Y H:i:s') . '.txt', $input);
} elseif ( ! empty($arResult['Img'])) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/img/' . date('d.m.Y H:i:s') . '.txt', $input);
} elseif ( ! empty($arResult['Remains'])) {
    // file_put_contents($timerFileRemains, time());
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/remains/' . date('d.m.Y H:i:s') . '.txt', $input);
} elseif ( ! empty($arResult['SHOP'])) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/shop/' . date('d.m.Y H:i:s') . '.txt', $input);
} elseif ( ! empty($arResult['Characteristic'])) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/сharacteristic/' . date('d.m.Y H:i:s') . '.txt', $input);
} elseif ( ! empty($arResult['Section'])) {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/section/' . date('d.m.Y H:i:s') . '.txt', $input);
} else {
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/import/log/all/' . date('d.m.Y H:i:s') . '.txt', $input);
}
if ( ! empty($_REQUEST['metod'] == 'orders')) {
    define("NO_KEEP_STATISTIC", true);
    define("NOT_CHECK_PERMISSIONS", true);
    define('BX_NO_ACCELERATOR_RESET', true);
    define('CHK_EVENT', true);
    define('BX_WITH_ON_AFTER_EPILOG', true);
    
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
    ini_set('memory_limit', '4028M');
    $ar = Importer::Orders();
    echo json_encode($ar);
} else {
    echo 'ok';
}

unlink($timerFile);
