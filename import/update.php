<?php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS",true);
define('BX_NO_ACCELERATOR_RESET', true);
define('CHK_EVENT', true);
define('BX_WITH_ON_AFTER_EPILOG', true);

$_SERVER['DOCUMENT_ROOT'] = '/home/bitrix/www';

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
require($_SERVER['DOCUMENT_ROOT'] . '/classes/Import.php');

use Classes\Importer;

$timerFile = $_SERVER['DOCUMENT_ROOT']. '/import/timer.txt';
$timer = file_get_contents($timerFile)+60*10; //10 минут
echo $_SERVER['DOCUMENT_ROOT'];
if(!file_exists($timerFile) or $timer < time()) {
    unlink($timerFile);
    file_put_contents($timerFile, time());
    
    $GLOBALS['update'] = false;
    Importer::JsonSection();
    Importer::JsonShop();
    Importer::JsonItem();
    Importer::JsonItemSection();
    Importer::JsonRemains();
    Importer::JsonPrice();
    Importer::JsonImg();
    
    if($GLOBALS['update'] == false) {
        Importer::ShopBitrix();
        //Log::write('SectionBitrix start ' , true, 'bitrix');
        Importer::SectionBitrix();
        //Log::write('SectionBitrix end ' , true, 'bitrix');
        //Log::write('ItemBitrix start ' , true, 'bitrix');
        Importer::ItemBitrix();
        //Log::write('ItemBitrix end ' , true, 'bitrix');
        //Log::write('RemainsBitrix start ' , true, 'bitrix');
        Importer::RemainsBitrix();
        //Log::write('RemainsBitrix end ' , true, 'bitrix');
        //Log::write('ImgBitrix start ' , true, 'bitrix');
        Importer::ImgBitrix();
        //Log::write('ItemBitrix end ' , true, 'bitrix');
        if (date('i') == 10 or date('i') == 30) {
            Importer::ImgBitrixIsset();
        }
        Importer::ClearLog();
    }
    unlink($timerFile);
    
    header("refresh: 2;");
    echo date('d.m.Y H:i:s');
}
else
{
    header("refresh: 2;");
    echo date('d.m.Y H:i:s');
    echo'<br>пропуск';
}
?>
