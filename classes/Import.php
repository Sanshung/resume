<?php

namespace Classes;

class Importer
{
    const catalogIB = 7;
    
    public static function getLogJson($type = '')
    {
        switch ($type) {
            case 'item':
                $dir = 'item';
                break;
            case 'itemsection':
                $dir = 'itemsection';
                break;
            case 'price':
                $dir = 'price';
                break;
            case 'remains':
                $dir = 'remains';
                break;
            case 'section':
                $dir = 'section';
                break;
            case 'shop':
                $dir = 'shop';
                break;
            case 'сharacteristic':
                $dir = 'сharacteristic';
                break;
            case 'img':
                $dir = 'img';
                break;
            default:
                return false;
        }
        
        $_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__) . "/..");
        $DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];
        
        $fileDir = $DOCUMENT_ROOT . '/import/log/' . $dir . '/';
        $arFiles = glob($fileDir . "*.txt");
        
        return $arFiles;
    }
    
    public static function JsonSection()
    {
        $arFile = self::getLogJson('section');
        $key = 'Section';
        foreach ($arFile as $file) {
            $arResult = self::getFileArray($file, $key);
            if ( ! empty($arResult)) {
                $GLOBALS['update'] = true;
                Importer::SectionSQL($arResult);
                self::copyFile($file);
            }
        }
    }
    
    public static function JsonShop()
    {
        $arFile = self::getLogJson('shop');
        $key = 'SHOP';
        foreach ($arFile as $file) {
            $arResult = self::getFileArray($file, $key);
            if ( ! empty($arResult)) {
                $GLOBALS['update'] = true;
                Importer::ShopSQL($arResult);
                self::copyFile($file);
            }
        }
    }
    
    public static function JsonItem()
    {
        $arFile = self::getLogJson('item');
        $key = 'Item';
        foreach ($arFile as $file) {
            $arResult = self::getFileArray($file, $key);
            if ( ! empty($arResult)) {
                $GLOBALS['update'] = true;
                Importer::ItemSQL($arResult);
                self::copyFile($file);
            }
        }
    }
    
    public static function JsonItemSection()
    {
        $arFile = self::getLogJson('itemsection');
        $key = 'ItemSection';
        foreach ($arFile as $file) {
            $arResult = self::getFileArray($file, $key);
            if ( ! empty($arResult)) {
                $GLOBALS['update'] = true;
                Importer::ItemSectionSQL($arResult);
                self::copyFile($file);
            }
        }
    }
    
    public static function JsonRemains()
    {
        $arFile = self::getLogJson('remains');
        $key = 'Remains';
        foreach ($arFile as $file) {
            $arResult = self::getFileArray($file, $key);
            if ( ! empty($arResult)) {
                $GLOBALS['update'] = true;
                Importer::RemainsSQL($arResult);
                self::copyFile($file);
            }
        }
    }
    
    public static function JsonPrice()
    {
        $arFile = self::getLogJson('price');
        $key = 'Price';
        foreach ($arFile as $file) {
            $arResult = self::getFileArray($file, $key);
            if ( ! empty($arResult)) {
                $GLOBALS['update'] = true;
                Importer::PriceSQL($arResult);
                self::copyFile($file);
            }
        }
    }
    
    public static function JsonImg()
    {
        $arFile = self::getLogJson('img');
        $key = 'Img';
        foreach ($arFile as $file) {
            $arResult = self::getFileArray($file, $key);
            if ( ! empty($arResult)) {
                $GLOBALS['update'] = true;
                Importer::ImgSQL($arResult);
                self::copyFile($file);
            }
        }
    }
    
    public static function getFileArray($file, $key)
    {
        $arResult = json_decode(preg_replace('/\x{EF}\x{BB}\x{BF}/', '', file_get_contents($file)), true);
        if ( ! empty($arResult)) {
            return $arResult[$key];
        }
        return false;
    }
    
    public static function copyFile($file)
    {
        $newPatch = str_replace('/log/', '/log/_old/', $file);
        rename($file, $newPatch);
    }
    
    public static function SectionSQL($arResult)
    {
        /*
         * CREATE TABLE a_post_parser_sections
(
        ID INT NOT NULL primary key  AUTO_INCREMENT,
    UID VARCHAR(255) NOT NULL,
    NAME varchar(255) NOT NULL,
    PARENT varchar(255) NOT NULL,
    `DELETE` VARCHAR(20) NOT NULL,
    `UPDATE` VARCHAR(20) NOT NULL,
    UPDATE_TIMESTAMP VARCHAR(20) ,
    BX_UPDATE_TIMESTAMP VARCHAR(20),
    MINOST VARCHAR(20),
       SORT VARCHAR(255);
);

         */
        
        $err_mess = '';
        global $DB;
        $json['Items'] = $arResult;
        if ( ! empty($json['Items']) && count((array)$json['Items']) > 0) {
            
            Log::write('Получение разделов из таблицы для сравнения', true, 'postparser_catalog');
            $strSql = "SELECT * FROM a_post_parser_sections";
            $res = $DB->Query($strSql, false, $err_mess . __LINE__);
            while ($row = $res->GetNext()) {
                $dbItems[$row['UID']] = $row;
                if ($row['DELETE'] == false) {
                    $dbIssetItems[$row['UID']] = $row;
                }
            }
            $stat['POST_STAT']['DB_ITEMS'] = count((array)$dbItems);
            Log::write('Получено из базы: ' . count((array)$dbItems), true, 'postparser_catalog');
            Log::write('Получено из 1с: ' . count((array)$json['Items']), true, 'postparser_catalog');
            Log::write('Поиск изменений.', true, 'postparser_catalog');
            $items2add = array();
            $items2update = array();
            $arrDateEnd = array();
            $arrDateSl = array();
            $uids = array();
            foreach ($json['Items'] as $jsonItem) {
                if (in_array(trim($jsonItem['UID']), $uids)) {
                    $doubles++;
                    continue;
                }
                $uids[] = trim($jsonItem['UID']);
                $props = array();
                
                $item = array(
                    'UID'    => trim($jsonItem['UID']),
                    'NAME'   => trim($jsonItem['NAME']),
                    'PARENT' => trim($jsonItem['PARENT']),
                    'DELETE' => trim($jsonItem['DELETE']),
                    'SORT'   => trim($jsonItem['SORT']),
                    'MINOST' => trim($jsonItem['MINOST']),
                );
                if (isset($dbItems[$item['UID']])) {
                    foreach ($item as $code => $value) {
                        if ($dbItems[$item['UID']]['~' . $code] != $value) {
                            $item['UPDATE'] = 'Y';
                            //$item['UPDATES'] = $code . ': ' . $dbItems[$item['UID']]['~' . $code] . ' != ' . $value;
                            $item['UPDATE_TIMESTAMP'] = time();
                        }
                    }
                    if ($item['UPDATE'] == 'Y') {
                        $items2update[] = $item;
                        //if (count($data['UPDATES']) < 10) {
                        //$data['UPDATES'][] = $item;
                        //}
                    }
                    unset($dbItems[$item['UID']]);
                } else {
                    $item['UPDATE'] = 'Y';
                    $item['UPDATE_TIMESTAMP'] = time();
                    $items2add[$item['UID']] = $item;
                }
                unset($dbIssetItems[$item['UID']]);
            }
            
            if ( ! empty($dbIssetItems) && count((array)$dbIssetItems) > 0 && count((array)$dbIssetItems) < 10) {
                foreach ($dbIssetItems as $item) {
                    Log::write('Отключить разделы: ' . count((array)$dbIssetItems), true, 'postparser_catalog');
                    //$strSql = "UPDATE a_post_parser_sections SET `DELETE` = 1 WHERE UID = '" . $item['UID'] . "'";
                    //$DB->Query($strSql, true, $err_mess . __LINE__);
                }
            }
            if ($doubles > 0) {
                Log::write('Найдены дубли в файле в количестве: ' . $doubles, true, 'postparser_catalog');
                $stat['POST_STAT']['DOUBLES'] = $doubles;
            }
            if (count((array)$items2add) > 0 || count((array)$items2update) > 0) {
                $stat['POST_STAT']['TO_ADD'] = count((array)$items2add);
                $stat['POST_STAT']['TO_UPDATE'] = count((array)$items2update);
                Log::write('Новых: ' . count((array)$items2add) . ' | Измененных: ' . count((array)$items2update), true,
                    'postparser_catalog');
                Log::write('Обновление базы товаров.', true, 'postparser_catalog');
                $updatedItems = 0;
                $addedItems = 0;
                $deletedItems = 0;
                foreach ($items2add as $item) {
                    $arInsert = $DB->PrepareInsert("a_post_parser_sections", $item);
                    $strSql = "INSERT INTO a_post_parser_sections (" . $arInsert[0] . ") VALUES (" . $arInsert[1] . ")";
                    $DB->Query($strSql, true, $err_mess . __LINE__);
                    $addedItems++;
                }
                foreach ($items2update as $item) {
                    $arUpdate = $DB->PrepareUpdate("a_post_parser_sections", $item);
                    $strSql = "UPDATE a_post_parser_sections SET " . $arUpdate . " WHERE UID = '" . $item['UID'] . "'";
                    $DB->Query($strSql, true, $err_mess . __LINE__);
                    $updatedItems++;
                }
                
                Log::write('База обновлена. Обновлено: ' . $updatedItems . ' | Добавлено: ' . $addedItems, true,
                    'postparser_catalog');
                $stat['POST_STAT']['ADDED'] = $addedItems;
                $stat['POST_STAT']['UPDATED'] = $updatedItems;
                $data['message'] = 'База обновлена';
            } elseif (count((array)$dbItems) < 400) {
                
                if (count((array)$dbItems) > 400) {
                    $error = 1012;
                } else {
                    foreach ($dbItems as $dbItem) {
                        $item = array(
                            'DELETE'           => 1,
                            'UPDATE'           => 'Y',
                            'UPDATE_TIMESTAMP' => time()
                        );
                        $arUpdate = $DB->PrepareUpdate("a_post_parser_sections", $item);
                        $strSql = "UPDATE a_post_parser_sections SET " . $arUpdate . " WHERE UID = '" . $dbItem['UID']
                            . "'";
                        dump($strSql);
                        $DB->Query($strSql, true, $err_mess . __LINE__);
                        $deletedItems++;
                    }
                    $stat['POST_STAT']['DELETED'] = count((array)$dbItems);
                }
            } else {
                $data['message'] = 'Обновлений нет.';
                Log::write('Обновлений нет', true, 'postparser_catalog');
            }
        } else {
            $error = 1008;
        }
        print_r2($data);
        print_r2($stat);
        return $data;
        
    }
    
    public static function SectionBitrix()
    {
        global $DB;
        
        $err_mess = '';
        
        $sectionsToUpdate = [];
        $sectionsToAdd = [];
        $sectionsToDelete = [];
        
        \Bitrix\Main\Loader::includeModule('iblock');
        $bs = new CIBlockSection;
        
        Log::write('Получение разделов из таблицы для сравнения', false, 'postparserbx');
        $strSql = "SELECT * FROM a_post_parser_sections";
        $res = $DB->Query($strSql, false, $err_mess . __LINE__);
        while ($row = $res->GetNext()) {
            // И собираем массив разделов из таблички
            $sections[trim($row['UID'])] = array(
                'NAME'   => $row['NAME'],
                'PARENT' => $row['PARENT'],
                'MINOST' => $row['MINOST'],
                'ACTIVE' => $row['DELETE'] == 1 ? 'N' : 'Y',
                'SORT'   => ! empty($row['SORT']) ? $row['SORT'] : 500
            );
        }
        // Выбираем разделы из базы.
        Log::write('Получение разделов из Битрикса', false, 'postparserbx');
        $bxSectionsByID = [];
        $res = CIBlockSection::GetList(array('DEPTH_LEVEL' => 'ASC'), array('IBLOCK_ID' => self::catalogIB), false,
            array('ID', 'SORT', 'UF_MINOST', 'ACTIVE', 'NAME', 'EXTERNAL_ID', 'IBLOCK_SECTION_ID'));
        while ($section = $res->GetNext()) {
            $bxSections[$section['EXTERNAL_ID']] = array(
                'NAME'      => $section['~NAME'],
                'PARENT'    => $bxSectionsByID[$section['IBLOCK_SECTION_ID']]
                    ? $bxSectionsByID[$section['IBLOCK_SECTION_ID']] : '',
                'ACTIVE'    => $section['ACTIVE'],
                'SORT'      => $section['SORT'],
                'BXID'      => $section['ID'],
                'UF_MINOST' => $section['UF_MINOST']
            );
            $bxSectionsByID[$section['ID']] = $section['EXTERNAL_ID'];
        }
        Log::write('Ищем различия', false, 'postparserbx');
        foreach ($bxSections as $uid => $section) {
            if (isset($sections[$uid])) {
                if (
                    $sections[$uid]['NAME'] != $section['NAME'] || $sections[$uid]['PARENT'] != $section['PARENT']
                    || $sections[$uid]['ACTIVE'] != $section['ACTIVE']
                    || $sections[$uid]['SORT'] != $section['SORT']
                    || $sections[$uid]['MINOST'] != $section['UF_MINOST']
                ) {
                    $changes = [];
                    if ($sections[$uid]['NAME'] != $section['NAME']) {
                        $changes[] = 'NAME ' . $section['NAME'] . ' --> ' . $sections[$uid]['NAME'];
                    }
                    if ($sections[$uid]['PARENT'] != $section['PARENT']) {
                        $changes[] = 'PARENT ' . $section['PARENT'] . ' --> ' . $sections[$uid]['PARENT'];
                    }
                    if ($sections[$uid]['ACTIVE'] != $section['ACTIVE']) {
                        $changes[] = 'ACTIVE ' . $section['ACTIVE'] . ' --> ' . $sections[$uid]['ACTIVE'];
                    }
                    
                    Log::write('Необходимо обновить категорию ' . $section['NAME'] . ': ' . implode(', ', $changes),
                        false, 'postparserbx');
                    
                    $sectionsToUpdate[$uid] = $sections[$uid];
                }
            } else {
                if ($section['ACTIVE'] == 'Y') {
                    $sectionsToDelete[] = $uid;
                }
            }
            unset($sections[$uid]);
        }
        
        $sectionsToAdd = $sections;
        unset($sections);
        Log::write('Обновить: ' . count((array)$sectionsToUpdate) . ' | Новых: ' . count((array)$sectionsToAdd), false,
            'postparserbx');
        if (count((array)$sectionsToUpdate) > 0 or count((array)$sectionsToAdd) > 0) {
            Log::write('Разделы Обновить: ' . count((array)$sectionsToUpdate) . ' | Добавить: '
                . count((array)$sectionsToAdd), true,
                'bitrix');
        }
        if (count((array)$sectionsToAdd) == 0 && count((array)$sectionsToUpdate) == 0
            && count((array)$sectionsToDelete) == 0
        ) {
            Log::write('Все категории актуальны', false, 'postparserbx');
        }
        
        if (count((array)$sectionsToAdd) > 0) {
            Log::write('Добавление', false, 'postparserbx');
            // Пересобираем массив, чтобы всё правильно добавить
            foreach ($sectionsToAdd as $uid => $section) {
                if (empty($section['PARENT'])) {
                    unset($sectionsToAdd[$uid]);
                    $tmpsections[$uid] = $section;
                }
            }
            foreach ($sectionsToAdd as $uid => $section) {
                if ( ! isset($tmpsections[$section['PARENT']]) && isset($sectionsToAdd[$section['PARENT']])) {
                    $sectionsToAdd[$section['PARENT']]['SECTIONS'][$uid] = $section;
                    unset($sectionsToAdd[$uid]);
                }
            }
            foreach ($sectionsToAdd as $uid => $section) {
                if (isset($tmpsections[$section['PARENT']])) {
                    $tmpsections[$section['PARENT']]['SECTIONS'][$uid] = $section;
                    unset($sectionsToAdd[$uid]);
                }
            }
            foreach ($sectionsToAdd as $uid => $section) {
                $tmpsections[$uid] = $section;
            }
            $sectionsToAdd = $tmpsections;
            unset($tmpsections);
            // Пересобрали
            // Ну короче можно переделать потом на что-нибудь приличное. Но пока и так пойдёт с пивом :)
            
            
            foreach ($sectionsToAdd as $uid => $section) {
                
                if ($section['ACTIVE'] == 'N') {
                    continue;
                }
                
                $section_id = $bs->Add(array(
                    'NAME'              => $section['NAME'],
                    'UF_MINOST'         => $section['MINOST'],
                    'EXTERNAL_ID'       => $uid,
                    'CODE'              => Cutil::translit($section['NAME'], "ru",
                            array("replace_space" => "_", "replace_other" => "_")) . (! empty($section['PARENT']) ? '2'
                            : ''),
                    'IBLOCK_ID'         => self::catalogIB,
                    'IBLOCK_SECTION_ID' => ! empty($section['PARENT']) ? $bxSections[$section['PARENT']]['BXID'] : false
                ));
                if ($section_id > 0) {
                    self::UpdateItemsNewSection($uid);
                    $data['ADDED']++;
                    $bxSections[$uid] = array(
                        'NAME'   => $section['NAME'],
                        'PARENT' => ! empty($section['PARENT']) ? $bxSections[$section['PARENT']]['BXID'] : '',
                        'BXID'   => $section_id
                    );
                } else {
                    $data['ERRORS']++;
                    Log::write('<b>Ошибка</b> добавления раздела: ' . $bs->LAST_ERROR, false, 'postparserbx');
                }
                if (is_array($section['SECTIONS'])) {
                    foreach ($section['SECTIONS'] as $suid => $subsection) {
                        
                        $subsection_id = $bs->Add(array(
                            'NAME'              => $subsection['NAME'],
                            'EXTERNAL_ID'       => $suid,
                            'UF_MINOST'         => $section['MINOST'],
                            'SORT'              => $section['SORT'],
                            'CODE'              => Cutil::translit($subsection['NAME'], "ru",
                                    array("replace_space" => "_", "replace_other" => "_"))
                                . (! empty($subsection['PARENT']) ? '2' : ''),
                            'IBLOCK_ID'         => self::catalogIB,
                            'IBLOCK_SECTION_ID' => $section_id
                        ));
                        if ($subsection_id > 0) {
                            self::UpdateItemsNewSection($suid);
                            $data['ADDED']++;
                            $bxSections[$suid] = array(
                                'NAME'   => $section['NAME'],
                                'PARENT' => $uid,
                                'BXID'   => $subsection_id
                            );
                        } else {
                            Log::write('<b>Ошибка $subsection</b> добавления раздела: ' . $bs->LAST_ERROR, false,
                                'postparserbx');
                        }
                        if (count((array)$subsection['SECTIONS']) > 0) {
                            foreach ($subsection['SECTIONS'] as $ssuid => $ssubsection) {
                                $ssubsection_id = $bs->Add(array(
                                    'NAME'              => $ssubsection['NAME'],
                                    'EXTERNAL_ID'       => $ssuid,
                                    'UF_MINOST'         => $section['MINOST'],
                                    'SORT'              => $section['SORT'],
                                    'CODE'              => Cutil::translit($ssubsection['NAME'], "ru",
                                            array("replace_space" => "_", "replace_other" => "_"))
                                        . (! empty($ssubsection['PARENT']) ? '2' : ''),
                                    'IBLOCK_ID'         => self::catalogIB,
                                    'IBLOCK_SECTION_ID' => intval($subsection_id)
                                ));
                                if ($ssubsection_id > 0) {
                                    self::UpdateItemsNewSection($ssuid);
                                    $data['ADDED']++;
                                    $bxSections[$ssuid] = array(
                                        'NAME'   => $ssubsection['NAME'],
                                        'PARENT' => $suid,
                                        'BXID'   => $ssubsection_id
                                    );
                                } else {
                                    $data['ERRORS']++;
                                    Log::write('<b>Ошибка $ssubsection</b> добавления раздела: ' . $bs->LAST_ERROR,
                                        false, 'postparserbx');
                                }
                            }
                        }
                    }
                }
            }
            Log::write('Новые разделы добавлены', false, 'postparserbx');
            Log::write('Новые разделы добавлены', false, 'bitrix');
            // Добавили, надеюсь.
        }
        if (count((array)$sectionsToUpdate) > 0) {
            Log::write('Обновление разделов', false, 'postparserbx');
            //print_r2($bxSections);
            foreach ($sectionsToUpdate as $uid => $section) {
                $arFields = array(
                    'ACTIVE'            => $section['ACTIVE'],
                    'NAME'              => $section['NAME'],
                    'SORT'              => $section['SORT'],
                    'EXTERNAL_ID'       => $uid,
                    'UF_MINOST'         => $section['MINOST'],
                    //'CODE' => Cutil::translit($section['NAME'], "ru", array("replace_space"=>"_","replace_other"=>"_")),
                    'IBLOCK_SECTION_ID' => ! empty($section['PARENT']) ? intval($bxSections[$section['PARENT']]['BXID'])
                        : '',
                    'MODIFIED_BY'       => 21
                );
                
                if ($bs->Update($bxSections[$uid]['BXID'], $arFields)) {
                    $data['UPDATED']++;
                    Log::write('Обновлена категория ' . $bxSections[$uid]['BXID'] . '||' . $section['NAME'] . '  <-- '
                        . $bxSections[$uid]['NAME'], false, 'postparserbx');
                } else {
                    $data['ERRORS']++;
                    Log::write('<b>Ошибка</b> добавления раздела: ' . $bs->LAST_ERROR, false, 'postparserbx');
                }
            }
            Log::write('Обновление разделов завершено');
        }
        if (count((array)$sectionsToDelete) > 0) {
            Log::write('Деактивация разделов', false, 'postparserbx');
            $data['sectionsToDelete'] = $sectionsToDelete;
            if (count((array)$sectionsToDelete) < 100) {
                foreach ($sectionsToDelete as $uid) {
                    $data['DELETED']++;
                    $bs->Update($bxSections[$uid]['BXID'], array('ACTIVE' => 'N', 'MODIFIED_BY' => 21));
                    Log::write('Деактивация раздела ' . $bxSections[$uid]['NAME'], true, 'postparserbx');
                }
            } else {
                Log::write('Слишком много удалять, тормозим', true, 'postparserbx');
            }
        }
        Log::write('Пересортировка разделов', false, 'postparserbx');
        
        Log::write('Пересортировка завершена. Обновление завершено.', false, 'postparserbx');
        $time = microtime(true) - $start;
        $log = array(
            'TYPE'      => 'sections',
            'TIMESTART' => $timestart,
            'TIMEEND'   => time(),
            'WORKTIME'  => sprintf('%.4F', $time),
            'LOG'       => json_encode($GLOBALS['WORK_LOG']),
            'STAT'      => json_encode(array(
                'ADDED'   => intval($data['ADDED']),
                'UPDATED' => intval($data['UPDATED']),
                'DELETED' => intval($data['DELETED']),
                'ERRORS'  => intval($data['ERRORS'])
            )),
            'STATUS'    => 'ok',
        );
        $log['LOG'] = json_decode($log['LOG'], true);
        $log['STAT'] = json_decode($log['STAT'], true);
        $log['DATA'] = $data;
        //print_r2($log);
        
    }
    
    public static function UpdateItemsNewSection($sectionCode)
    {
        if (empty($sectionCode)) {
            return false;
        }
        global $DB;
        $update = array(
            'UPDATE'              => 'Y',
            'BX_UPDATE_TIMESTAMP' => time()
        );
        $arUpdate = $DB->PrepareUpdate("a_post_parser_items", $update);
        $strSql = "UPDATE a_post_parser_items SET " . $arUpdate . " WHERE SECTION = '" . $sectionCode . "'";
        $res = $DB->Query($strSql, false, $err_mess . __LINE__);
    }
    
    public static function ItemSectionSQL($arResult)
    {
        global $DB;
        $json['Items'] = $arResult;
        
        if ( ! empty($json['Items']) && count((array)$json['Items']) > 0) {
            
            Log::write('Получение товаров из таблицы для сравнения', true, 'postparser_catalog');
            $strSql = "SELECT `ID`, `UID`, `SECTION_2` FROM a_post_parser_items";
            $res = $DB->Query($strSql, false, $err_mess . __LINE__);
            while ($row = $res->GetNext()) {
                $dbItems[$row['UID']] = $row;
            }
            
            $items2add = [];
            $items2update = [];
            $json['items_new'] = [];
            foreach ($json['Items'] as $key => $jsonItem) {
                if ( ! empty($jsonItem['Item_ID']) && ! empty($jsonItem['Section_ID'])) {
                    $json['items_new'][$jsonItem['Item_ID']]['UID'] = $jsonItem['Item_ID'];
                }
                $json['items_new'][$jsonItem['Item_ID']]['SECTION_2'][] = $jsonItem['Section_ID'];
            }
            
            foreach ($json['items_new'] as $jsonItem) {
                $item = array(
                    'UID'       => trim($jsonItem['UID']),
                    'SECTION_2' => json_encode($jsonItem['SECTION_2']),
                );
                
                if (isset($dbItems[$item['UID']])) {
                    if ($dbItems[$item['UID']]['~SECTION_2'] != $item['SECTION_2']) {
                        $item['UPDATE'] = 'Y';
                        $item['UPDATE_TIMESTAMP'] = time();
                        $items2update[] = $item;
                    }
                    unset($dbItems[$item['UID']]);
                } else {
                    $item['UPDATE'] = 'Y';
                    $item['UPDATE_TIMESTAMP'] = time();
                    $items2add[$item['UID']] = $item;
                }
            }
            
            if (count((array)$items2add) > 0 || count((array)$items2update) > 0) {
                //Log::write('Новых: ' . count((array)$items2add) . ' | Измененных: ' . count((array)$items2update), true, 'postparser_catalog');
                //Log::write('Обновление базы товаров.', true, 'postparser_catalog');
                $updatedItems = 0;
                $addedItems = 0;
                
                foreach ($items2add as $item) {
                    $arInsert = $DB->PrepareInsert("a_post_parser_items", $item);
                    $strSql = "INSERT INTO a_post_parser_items (" . $arInsert[0] . ") VALUES (" . $arInsert[1] . ")";
                    $insertReturn = $DB->Query($strSql, true, $err_mess . __LINE__);
                    $addedItems++;
                }
                foreach ($items2update as $item) {
                    $arUpdate = $DB->PrepareUpdate("a_post_parser_items", $item);
                    $strSql = "UPDATE a_post_parser_items SET " . $arUpdate . " WHERE UID = '" . $item['UID'] . "'";
                    $updateReturn = $DB->Query($strSql, true, $err_mess . __LINE__);
                    $updatedItems++;
                }
                
                Log::write('База обновлена. Обновлено: ' . $updatedItems . ' | Добавлено: ' . $addedItems, true,
                    'postparser_catalog');
                $data['message'] = 'База обновлена';
            } else {
                Log::write('Обновлений нет', true, 'postparser_catalog');
            }
        }
    }
    
    public static function ItemSQL($arResult)
    {
        /*
         * CREATE TABLE a_post_parser_items
(
        ID INT NOT NULL primary key AUTO_INCREMENT,
    UID VARCHAR(255),
    NAME varchar(255),
    SECTION varchar(255),
    SECTION_2 varchar(255),
    PROPERTY text,
    DESCRIPTION text,
    `DELETE` VARCHAR(20),
    `UPDATE` VARCHAR(20),
    UPDATE_TIMESTAMP VARCHAR(20),
    BX_UPDATE_TIMESTAMP VARCHAR(20)
);
         */
        global $DB;
        $json['Items'] = $arResult;
        if ( ! empty($json['Items']) && count((array)$json['Items']) > 0) {
            
            Log::write('Получение товаров из таблицы для сравнения', true, 'postparser_catalog');
            $strSql = "SELECT `ID`, `UID`, `NAME`, `DELETE`, `DESCRIPTION`, `PROPERTY` FROM a_post_parser_items";
            $res = $DB->Query($strSql, false, $err_mess . __LINE__);
            while ($row = $res->GetNext()) {
                $dbItems[$row['UID']] = $row;
            }
            
            Log::write('Получено: ' . count((array)$dbItems), true, 'postparser_catalog');
            Log::write('Поиск изменений.', true, 'postparser_catalog');
            $items2add = [];
            $items2update = [];
            $arrDateEnd = [];
            $arrDateSl = [];
            $full = 'N';
            foreach ($json['Items'] as $jsonItem) {
                if ( ! empty($jsonItem['Item_ID'])) {
                    $jsonItem['UID'] = $jsonItem['Item_ID'];
                    unset($jsonItem['Item_ID']);
                }
                if ( ! empty($jsonItem['FULL'])) {
                    $full = $jsonItem['FULL'];
                    telegramBotImport('Items Запуск полной выгрузки');
                    continue;
                }
                if (empty(trim($jsonItem['NAME']))) {
                    continue;
                }
                /*if (in_array(trim($jsonItem['UID']), $uids)) {
                    $doubles++;
                    continue;
                }
                $uids[] = trim($jsonItem['UID']);*/
                if ( ! empty($uids[trim($jsonItem['UID'])])) {
                    $doubles++;
                    continue;
                }
                $uids[trim($jsonItem['UID'])] = 1;
                $props = array();
                
                if (trim($jsonItem['DELETE']) == 1) {
                    $jsonItem['DELETE'] = 'Y';
                }
                $item = array(
                    'UID'         => trim($jsonItem['UID']),
                    //'SECTION'     => trim($jsonItem['SECTION']),
                    'NAME'        => trim($jsonItem['NAME']),
                    'DELETE'      => trim($jsonItem['DELETE']),
                    'DESCRIPTION' => trim($jsonItem['DESCRIPTION']),
                    'PROPERTY'    => json_encode($jsonItem['PROPERTY']),
                );
                
                /*if (empty($jsonItem['PROPERTY']['section'])) {
                    unset($dbItems[$item['UID']]);
                    continue;
                }*/
                
                if (isset($dbItems[$item['UID']])) {
                    foreach ($item as $code => $value) {
                        
                        if ($dbItems[$item['UID']]['~' . $code] != $value) {
                            $item['UPDATE'] = 'Y';
                            //$item['UPDATES'] = $code . ': ' . $dbItems[$item['UID']]['~' . $code] . ' != ' . $value;
                            $item['UPDATE_TIMESTAMP'] = time();
                        }
                    }
                    if ($item['UPDATE'] == 'Y') {
                        $items2update[] = $item;
                        //if (count($data['UPDATES']) < 10) {
                        //$data['UPDATES'][] = $item;
                        //}
                    }
                    unset($dbItems[$item['UID']]);
                } else {
                    $item['UPDATE'] = 'Y';
                    $item['DELETE'] = '';
                    $item['UPDATE_TIMESTAMP'] = time();
                    $items2add[$item['UID']] = $item;
                }
            }
            
            if ($doubles > 0) {
                Log::write('Найдены дубли в файле в количестве: ' . $doubles, true, 'postparser_catalog');
            }
            
            if (count((array)$items2add) > 0 || count((array)$items2update) > 0) {
                $stat['POST_STAT']['TO_ADD'] = count((array)$items2add);
                $stat['POST_STAT']['TO_UPDATE'] = count((array)$items2update);
                Log::write('Новых: ' . count((array)$items2add) . ' | Измененных: ' . count((array)$items2update), true,
                    'postparser_catalog');
                Log::write('Обновление базы товаров.', true, 'postparser_catalog');
                $updatedItems = 0;
                $addedItems = 0;
                $deletedItems = 0;
                
                foreach ($items2add as $item) {
                    $arInsert = $DB->PrepareInsert("a_post_parser_items", $item);
                    $strSql = "INSERT INTO a_post_parser_items (" . $arInsert[0] . ") VALUES (" . $arInsert[1] . ")";
                    $insertReturn = $DB->Query($strSql, true, $err_mess . __LINE__);
                    $addedItems++;
                }
                foreach ($items2update as $item) {
                    $arUpdate = $DB->PrepareUpdate("a_post_parser_items", $item);
                    $strSql = "UPDATE a_post_parser_items SET " . $arUpdate . " WHERE UID = '" . $item['UID'] . "'";
                    $updateReturn = $DB->Query($strSql, true, $err_mess . __LINE__);
                    $updatedItems++;
                }
                
                
                $stat['POST_STAT']['DELETED'] = count((array)$dbItems);
                Log::write('База обновлена. Обновлено: ' . $updatedItems . ' | Добавлено: ' . $addedItems, true,
                    'postparser_catalog');
                telegramBotImport('Items Обновлено: ' . $updatedItems . ' | Добавлено: ' . $addedItems);
                $stat['POST_STAT']['ADDED'] = $addedItems;
                $stat['POST_STAT']['UPDATED'] = $updatedItems;
                $data['message'] = 'База обновлена';
            } else {
                $data['message'] = 'Обновлений нет.';
                Log::write('Обновлений нет', true, 'postparser_catalog');
            }
            if (count((array)$dbItems) > 0 && $full == 'Y') {
                if (count((array)$dbItems) > 15000) {
                    $error = 1012;
                } else {
                    foreach ($dbItems as $dbItem) {
                        if ($dbItem['DELETE'] == 'Y') {
                            continue;
                        }
                        $item = array(
                            'DELETE'           => 'Y',
                            'UPDATE'           => 'Y',
                            'UPDATE_TIMESTAMP' => time()
                        );
                        $arUpdate = $DB->PrepareUpdate("a_post_parser_items", $item);
                        $strSql = "UPDATE a_post_parser_items SET " . $arUpdate . " WHERE UID = '" . $dbItem['UID']
                            . "'";
                        $DB->Query($strSql, true, $err_mess . __LINE__);
                        $deletedItems++;
                    }
                    $stat['POST_STAT']['DELETED'] = count((array)$dbItems);
                }
            }
            if ($deletedItems > 0) {
                Log::write('База обновлена. Удаленно: ' . $deletedItems, true, "postparser_catalog");
            }
        } else {
            $error = 1008;
        }
        dump('ItemSQL - update');
        return $data;
    }
    
    public static function ItemBitrix()
    {
        global $DB;
        $itemUpdate = 0;
        $itemAdd = 0;
        
        \Bitrix\Main\Loader::includeModule('iblock');
        \Bitrix\Main\Loader::includeModule('catalog');
        
        $el = new \CIBlockElement;
        $ibp = new CIBlockProperty;
        
        $res = CIBlockSection::GetList(
            ['DEPTH_LEVEL' => 'ASC'],
            ['IBLOCK_ID' => self::catalogIB],
            false,
            ['ID', 'ACTIVE', 'NAME', 'EXTERNAL_ID', 'IBLOCK_SECTION_ID']
        );
        while ($section = $res->GetNext()) {
            $sections[$section['EXTERNAL_ID']] = $section['ID'];
        }
        $res = CIBlockProperty::GetList(
            ["sort" => "asc", "name" => "asc"],
            ["ACTIVE" => "Y", "IBLOCK_ID" => self::catalogIB]
        );
        while ($prop_fields = $res->GetNext()) {
            $iblockProperties[$prop_fields['CODE']] = $prop_fields;
        }
        
        $res = \CIBlockElement::GetList([], ['IBLOCK_ID' => self::catalogIB]);
        while ($item = $res->GetNext()) {
            $bxItems[$item['EXTERNAL_ID']] = $item['ID'];
        }
        
        $strSql
            = "SELECT * FROM a_post_parser_items WHERE `UPDATE` = 'Y' AND `NAME` != '' ORDER BY `UPDATE_TIMESTAMP` ASC LIMIT 100 ";
        $res = $DB->Query($strSql, false, $err_mess . __LINE__);
        while ($row = $res->GetNext()) {
            if (empty($row['NAME'])) {
                continue;
            }
            if ( ! empty($row['~SECTION_2'])) {
                $row['SECTION'] = $row['~SECTION'] = json_decode($row['~SECTION_2'], true);
            }
            //print_r2($row);
            $properties = array();
            $jsonProperties = json_decode($row['~PROPERTY'], true);
            // print_r2($jsonProperties);
            foreach ($jsonProperties as $name => $value) {
                if ($name == 'section' && ! empty($value)) {
                    continue;
                }
                $propCode = strtoupper(Cutil::translit(trim($name), "ru",
                    array("replace_space" => "_", "replace_other" => "_", 'max_len' => 47)));
                if ( ! isset($iblockProperties[$propCode])) {
                    $newPropsCount++;
                    $arFields = array(
                        "NAME"          => $name,
                        "ACTIVE"        => "Y",
                        "SORT"          => "225",
                        "CODE"          => $propCode,
                        "PROPERTY_TYPE" => "S",
                        // S - строка, N - число, F - файл, L - список, E - привязка к элементам ("LINK_IBLOCK_ID" => 180), G - привязка к группам.
                        "IBLOCK_ID"     => self::catalogIB,
                    );
                    //print_r2($arFields);
                    $ibp->Add($arFields);
                    $iblockProperties[$propCode] = $arFields;
                }
                $properties[$propCode] = $value;
            }
            
            $row['CODE'] = Cutil::translit(trim($row['NAME']), "ru",
                array("replace_space" => "_", "replace_other" => "_", 'max_len' => 100));
            
            $section = [];
            if (is_array($row['SECTION'])) {
                foreach ($row['SECTION'] as $s) {
                    if ( ! empty($sections[$s])) {
                        $section[] = $sections[$s];
                    }
                }
            } else {
                if ( ! empty($row['SECTION'])) {
                    $section[] = $sections[$row['SECTION']];
                }
            }
            
            if ( ! empty($bxItems[$row['UID']])) {
                
                //print_r2($row);
                $PRODUCT_ID = $bxItems[$row['UID']];
                $el->Update($bxItems[$row['UID']],
                    array(
                        'NAME'           => $row['~NAME'],
                        //'CODE'              => $row['CODE'],
                        'DETAIL_TEXT'    => $row['DESCRIPTION'],
                        'IBLOCK_SECTION' => $section,
                        'ACTIVE'         => $row['DELETE'] == true ? 'N' : 'Y',
                    ),
                    false,
                    true,
                    false,
                    false
                );
                $itemUpdate++;
            } else {
                $arFieldsAdd = [
                    'IBLOCK_ID'         => self::catalogIB,
                    'NAME'              => $row['~NAME'],
                    'CODE'              => $row['CODE'],
                    'EXTERNAL_ID'       => $row['UID'],
                    'DETAIL_TEXT'       => $row['DESCRIPTION'],
                    'ACTIVE'            => $row['DELETE'] == true ? 'N' : 'Y',
                    'IBLOCK_SECTION_ID' => $section,
                    'IBLOCK_SECTION'    => $section,
                ];
                
                $PRODUCT_ID = $el->Add($arFieldsAdd);
                
                if ($PRODUCT_ID == false) {
                    //print_r2($row);
                    //print_r2("Error: " . $el->LAST_ERROR);
                    Log::write('Товар не создан ' . $row['UID'] . "- Error: " . $el->LAST_ERROR, true, 'bitrix');
                    
                    if (stripos($el->LAST_ERROR, 'символьным кодом')) {
                        $arFieldsAdd['CODE'] = $arFieldsAdd['CODE'] . '_' . rand(1000, 9999);
                        $PRODUCT_ID = $el->Add($arFieldsAdd);
                        if ($PRODUCT_ID == false) {
                            Log::write('Товар не создан ' . $row['UID'] . "- Error: " . $el->LAST_ERROR, true,
                                'bitrix');
                        } else {
                            Log::write('Товар создан. Новый код ' . $arFieldsAdd['CODE'], true, 'bitrix');
                        }
                    }
                    continue;
                }
                
                CCatalogProduct::Add(array("ID" => $PRODUCT_ID, "QUANTITY" => 0));
                //print_r2($PRODUCT_ID);
                $itemAdd++;
            }
            
            \CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, self::catalogIB, $properties);
            
            $el->Update($bxItems[$row['UID']], //Для индексации поиска по артикулу
                ['NAME' => $row['~NAME']],
                false,
                true,
                false,
                false
            );
            
            $update = array(
                'UPDATE'              => 'N',
                'BX_UPDATE_TIMESTAMP' => time()
            );
            $arUpdate = $DB->PrepareUpdate("a_post_parser_items", $update);
            $strSql = "UPDATE a_post_parser_items SET " . $arUpdate . " WHERE `UID` = '" . $row['UID'] . "'";
            //print_r2($strSql);
            $resSql = $DB->Query($strSql, false, $err_mess . __LINE__);
        }
        if ($itemUpdate > 0) {
            Log::write('Товары обновлено: ' . $itemUpdate, true, 'bitrix');
        }
        if ($itemAdd > 0) {
            Log::write('Товары добавлено: ' . $itemAdd, true, 'bitrix');
        }
    }
    
    function base64_to_jpeg($base64_string, $filename)
    {
        if (empty($filename) or empty($base64_string)) {
            return false;
        }
        // open the output file for writing
        $file = $_SERVER['DOCUMENT_ROOT'] . '/upload/base64/' . $filename . '.jpg';
        $output_file = $_SERVER['DOCUMENT_ROOT'] . '/upload/base64/' . $filename . '_resize.jpg';
        if ( ! file_exists($file)) {
            $ifp = fopen($file, 'wb');
            // split the string on commas
            
            
            // we could add validation here with ensuring count( $data ) > 1
            fwrite($ifp, base64_decode($base64_string));
            
            // clean up the file resource
            fclose($ifp);
        }
        
        $arFileTmp = CFile::ResizeImageFile(
            $file,
            $output_file,
            array("width" => 1200, "height" => 900),
            BX_RESIZE_IMAGE_PROPORTIONAL_ALT,
            [],
            95
        );
        if ($arFileTmp == true) {
            unlink($file);
            return $output_file;
        } else {
            return $file;
        }
    }
    
    public static function UpdateMoreImg($base64_string, $filename, $delete = false)
    {
        if (empty($filename) or empty($base64_string)) {
            return false;
        }
        // open the output file for writing
        $output_file = $_SERVER['DOCUMENT_ROOT'] . '/upload/more_img/' . $filename . '.jpg';
        if (self::isUpdateImg($base64_string, $filename) && $delete == false) {
            unlink($output_file);
            $decode = base64_decode($base64_string);
            $ifp = fopen($output_file, 'wb');
            fwrite($ifp, $decode);
            fclose($ifp);
        } elseif (file_exists($output_file) && $delete == true) {
            unlink($output_file);
            return '';
        }
        
        return $output_file;
    }
    
    public static function checkImg($base64_string, $filename, $delete = false)
    {
        if (empty($filename) or empty($base64_string)) {
            return false;
        }
        // open the output file for writing
        $output_file = $_SERVER['DOCUMENT_ROOT'] . '/upload/check/' . $filename . '.jpg';
        if ( ! file_exists($output_file) && $delete == false) {
            $decode = base64_decode($base64_string);
            $ifp = fopen($output_file, 'wb');
            fwrite($ifp, $decode);
            fclose($ifp);
        } elseif (file_exists($output_file) && $delete == true) {
            unlink($output_file);
            return '';
        }
        
        return str_replace($_SERVER['DOCUMENT_ROOT'], '', $output_file);
    }
    
    public static function isUpdateImg($base64_string, $filename, $delete = false)
    {
        $base64_string = str_replace(["\n", "\r"], "", $base64_string);
        if (empty($filename) or empty($base64_string)) {
            return false;
        }
        // open the output file for writing
        $output_file = $_SERVER['DOCUMENT_ROOT'] . '/upload/more_img/' . $filename . '.jpg';
        
        if ( ! file_exists($output_file)) {
            return true;
        }
        if (base64_encode(file_get_contents($output_file)) == $base64_string) {
            return false;
        }
        
        return true;
    }
    
    public static function ImgSQL($arResult)
    {
        /*
         * CREATE TABLE a_post_parser_img
(
        ID INT NOT NULL primary key AUTO_INCREMENT,
    UID VARCHAR(255),
    IMG mediumtext,
    `DELETE` VARCHAR(20),
    `UPDATE` VARCHAR(20),
    UPDATE_TIMESTAMP VARCHAR(20),
    BX_UPDATE_TIMESTAMP VARCHAR(20),
    BREAK VARCHAR(20),
    NUMBER VARCHAR(20)
);
        CREATE TABLE a_post_parser_img_new
(
        ID INT NOT NULL primary key AUTO_INCREMENT,
    UID VARCHAR(255),
    IMG mediumtext,
    `DELETE` VARCHAR(20),
    `UPDATE` VARCHAR(20),
    UPDATE_TIMESTAMP VARCHAR(20),
    BX_UPDATE_TIMESTAMP VARCHAR(20),
    BREAK VARCHAR(20),
    NUMBER VARCHAR(20)
);

         */
        
        global $DB;
        $json['Items'] = $arResult;
        if ( ! empty($json['Items']) && count((array)$json['Items']) > 0) {
            $uids = [];
            /*$res = CIBlockSection::GetList([], ['IBLOCK_ID' => self::catalogIB], false);
            while ($section = $res->Fetch()) {
                $sections[$section['EXTERNAL_ID']] = $section['ID'];
            }*/
            $selectUID = [];
            foreach ($json['Items'] as $jsonItem) {
                if ( ! empty($jsonItem['Item_ID'])) {
                    $jsonItem['UID'] = $jsonItem['Item_ID'];
                }
                if ( ! in_array(trim($jsonItem['UID']), $selectUID)) {
                    $selectUID[] = $jsonItem['UID'];
                }
            }
            
            Log::write('Получение картинок из таблицы для сравнения', true, 'postparser_catalog_img');
            $strSql = "SELECT * FROM a_post_parser_img_new WHERE UID IN ('" . join('\',\'', $selectUID) . "')";
            $res = $DB->Query($strSql, false, $err_mess . __LINE__);
            while ($row = $res->GetNext()) {
                $row['NUMBER'] = empty($row['NUMBER']) ? 1 : $row['NUMBER'];
                $dbItems[$row['UID']] = $row;
            }
            $stat['POST_STAT']['1С_ITEMS'] = count((array)$selectUID);
            $stat['POST_STAT']['DB_ITEMS'] = count((array)$dbItems);
            Log::write('Получено из 1С: ' . count((array)$selectUID), true, 'postparser_catalog_img');
            Log::write('Получено из базы: ' . count((array)$dbItems), true, 'postparser_catalog_img');
            Log::write('Поиск изменений.', true, 'postparser_catalog_img');
            $items2add = [];
            $items2update = [];
            $arrDateEnd = [];
            $arrDateSl = [];
            foreach ($json['Items'] as $jsonItem) {
                if ( ! empty($jsonItem['Item_ID'])) {
                    $jsonItem['UID'] = $jsonItem['Item_ID'];
                }
                if ($jsonItem['Основной'] == true) {
                    $jsonItem['number'] = 0;
                }
                if ($jsonItem['Основной'] == false) {
                    $file = self::UpdateMoreImg($jsonItem['IMG'], $jsonItem['UID'] . '_' . $jsonItem['number'],
                        $jsonItem['DELETE']);
                    continue;
                } elseif ( ! self::isUpdateImg($jsonItem['IMG'], $jsonItem['UID'] . '_' . $jsonItem['number'])) {
                    continue;
                }
                
                $file = self::UpdateMoreImg($jsonItem['IMG'], $jsonItem['UID'] . '_' . $jsonItem['number'],
                    $jsonItem['DELETE']);
                
                /*if (in_array(trim($jsonItem['UID']), $uids)) {
                    $doubles++;
                    continue;
                }
                $uids[] = trim($jsonItem['UID']);
                */
                if ( ! empty($uids[trim($jsonItem['UID'])])) {
                    $doubles++;
                    continue;
                }
                $uids[trim($jsonItem['UID'])] = 1;
                $props = array();
                $item = array(
                    'UID'    => trim($jsonItem['UID']),
                    //'IMG'    => trim($jsonItem['IMG']),
                    'IMG'    => '',
                    'DELETE' => trim($jsonItem['DELETE']),
                    'NUMBER' => trim($jsonItem['number']),
                );
                if (isset($dbItems[$item['UID']])) {
                    /*foreach ($item as $code => $value) {
                        if ($dbItems[$item['UID']]['~' . $code] != $value) {
                            $item['UPDATE'] = 'Y';
                            $item['UPDATE_TIMESTAMP'] = time();
                        }
                    }*/
                    $item['UPDATE'] = 'Y';
                    $item['UPDATE_TIMESTAMP'] = time();
                    if ($item['UPDATE'] == 'Y') {
                        $items2update[] = $item;
                    }
                    unset($dbItems[$item['UID']]);
                } else {
                    $item['UPDATE'] = 'Y';
                    $item['UPDATE_TIMESTAMP'] = time();
                    $items2add[] = $item;
                }
            }
            
            if ($doubles > 0) {
                Log::write('Найдены дубли в файле в количестве: ' . $doubles, true, 'postparser_catalog_img');
                $stat['POST_STAT']['DOUBLES'] = $doubles;
            }
            if (count((array)$items2add) > 0 || count((array)$items2update) > 0) {
                $stat['POST_STAT']['TO_ADD'] = count((array)$items2add);
                $stat['POST_STAT']['TO_UPDATE'] = count((array)$items2update);
                Log::write('Картинки Новых: ' . count((array)$items2add) . ' | Измененных: '
                    . count((array)$items2update), true,
                    'postparser_catalog_img');
                Log::write('Обновление базы картинок.', true, 'postparser_catalog_img');
                $updatedItems = 0;
                $addedItems = 0;
                $deletedItems = 0;
                foreach ($items2add as $item) {
                    $arInsert = $DB->PrepareInsert("a_post_parser_img_new", $item);
                    $strSql = "INSERT INTO a_post_parser_img_new (" . $arInsert[0] . ") VALUES (" . $arInsert[1] . ")";
                    $DB->Query($strSql, true, $err_mess . __LINE__);
                    $addedItems++;
                }
                foreach ($items2update as $item) {
                    $arUpdate = $DB->PrepareUpdate("a_post_parser_img_new", $item);
                    $strSql = "UPDATE a_post_parser_img_new SET " . $arUpdate . " WHERE UID = '" . $item['UID'] . "'";
                    $DB->Query($strSql, true, $err_mess . __LINE__);
                    $updatedItems++;
                }
                /*if (count($dbItems) > 0) {
                    if (count($dbItems) > 1500) {
                        $error = 1012;
                    } else {
                        foreach ($dbItems as $dbItem) {
                            $item = array(
                                'ACTIVE' => 'N',
                                'UPDATE' => 'Y',
                                'UPDATE_TIMESTAMP' => time()
                            );
                            $arUpdate = $DB->PrepareUpdate("a_post_parser_img", $item);
                            $strSql = "UPDATE a_post_parser_img SET " . $arUpdate . " WHERE UID = '" . $item['UID'] . "'";
                            $DB->Query($strSql, true, $err_mess . __LINE__);
                            $deletedItems++;
                        }
                        $stat['POST_STAT']['DELETED'] = count($dbItems);
                    }
                }*/
                Log::write('База картинок обновлена. Обновлено: ' . $updatedItems . ' | Добавлено: ' . $addedItems,
                    true, 'postparser_catalog_img');
                $stat['POST_STAT']['ADDED'] = $addedItems;
                $stat['POST_STAT']['UPDATED'] = $updatedItems;
                $data['message'] = 'База картинок обновлена';
            } else {
                $data['message'] = 'Обновлений картинок нет.';
                Log::write('Обновлений картинок нет', true, 'postparser_catalog_img');
            }
        } else {
            $error = 1008;
        }
        //print_r2($data);
        //print_r2($stat);
        return $data;
    }
    
    public static function ImgBitrix()
    {
        //Log::write('Фото start', true, 'bitrix');
        $updatedItems = 0;
        $breakItems = 0;
        $el = new \CIBlockElement;
        global $DB;
        \Bitrix\Main\Loader::includeModule('iblock');
        $res = \CIBlockElement::GetList(array(), array('IBLOCK_ID' => self::catalogIB), false, false,
            ['EXTERNAL_ID', 'ID', 'IBLOCK_ID', 'NAME']);
        while ($item = $res->GetNext()) {
            $bxItems[$item['EXTERNAL_ID']] = $item['ID'];
            $bxItemsName[$item['EXTERNAL_ID']] = $item['NAME'];
        }
        
        $strSql
            = "SELECT * FROM a_post_parser_img_new WHERE `UPDATE` = 'Y' ORDER BY `BREAK` ASC LIMIT 500 "; // AND NOT `BREAK`= 'Y'
        $res = $DB->Query($strSql, false, $err_mess . __LINE__);
        while ($row = $res->GetNext()) {
            if ($row['BREAK'] == 'Y') {
                continue;
            }
            if ( ! empty($bxItems[$row['UID']])) {
                $file = $_SERVER['DOCUMENT_ROOT'] . '/upload/more_img/' . $row['UID'] . '_0.jpg';
                if (!file_exists($file)){
                    $files = glob($_SERVER['DOCUMENT_ROOT'] . '/upload/more_img/' . $row['UID'] . '*.jpg');
                    if(!empty($files[0])){
                        $file = $files[0];
                    }
                }
                if (file_exists($file)) {
                    //if ( ! empty($row['IMG'])) {
                    //$file = self::base64_to_jpeg($row['IMG'], $row['UID']);
                    if ($file != false) {
                        $arFile = CFile::MakeFileArray($file);
                        $return = $el->Update($bxItems[$row['UID']],
                            array(
                                "PREVIEW_PICTURE" => $arFile,
                                "DETAIL_PICTURE"  => $arFile
                            ),
                            false,
                            true,
                            true,
                            true
                        );
                        //unlink($file);
                        global $APPLICATION;
                        if ($ex = $APPLICATION->GetException()):
                            $strError = $ex->GetString();
                            Log::write('Фото не обновленно' . $bxItemsName[$row['UID']] . "- Error: " . $strError, true,
                                'bitrix-img-error');
                        endif;
                        
                        if ($return == true) {
                            $updateUID[] = $row['UID'];
                            $updatedItems++;
                        } else {
                            Log::write('Фото не обновленно' . $row['UID'] . "- Error: " . $el->LAST_ERROR, true,
                                'bitrix');
                            Log::write('Фото не обновленно' . $bxItemsName[$row['UID']] . "- Error: " . $el->LAST_ERROR,
                                true,
                                'bitrix-img-error');
                            $breakUID[] = $row['UID'];
                            $breakItems++;
                        }
                    }
                } else {
                    $breakUID[] = $row['UID'];
                    $breakItems++;
                }
            } else {
                $breakUID[] = $row['UID'];
                $breakItems++;
            }
            
            
        }
        if ($updatedItems > 0) {
            Log::write('Фото обновлено: ' . $updatedItems, true, 'bitrix');
        }
        
        if ($breakItems > 0) {
            Log::write('Фото пропущено: ' . $breakItems, true, 'bitrix');
        }
        $breakItems = [];
        $breakItemsCount = 0;
        $strSql = "SELECT UID FROM a_post_parser_img_new WHERE `BREAK` = 'Y'";
        $res = $DB->Query($strSql, false, $err_mess . __LINE__);
        while ($row = $res->GetNext()) {
            if ( ! empty($bxItems[$row['UID']])) {
                $file = $_SERVER['DOCUMENT_ROOT'] . '/upload/more_img/' . $row['UID'] . '_0.jpg';
                if (file_exists($file)) {
                    $row['IMG'] = true;
                }
            }
            if ( ! empty($bxItems[$row['UID']]) && $row['IMG']) {
                $breakItems[] = $row['UID'];
                
                $breakItemsCount++;
            }
        }
        
        if ( ! empty($breakItems) && count((array)$breakItems) > 0) {
            //Log::write('Фото sql start', true, 'bitrix');
            $arUpdate = $DB->PrepareUpdate("a_post_parser_img_new", array('UPDATE' => 'Y', 'BREAK' => ''));
            $strSql = "UPDATE a_post_parser_img_new SET " . $arUpdate . " WHERE UID IN ('" . join('\',\'', $breakItems)
                . "')";
            $DB->Query($strSql, true, $err_mess . __LINE__);
        }
        if ( ! empty($updateUID) && count((array)$updateUID) > 0) {
            //Log::write('Фото sql start', true, 'bitrix');
            $arUpdate = $DB->PrepareUpdate("a_post_parser_img_new",
                array('UPDATE' => 'N', 'BX_UPDATE_TIMESTAMP' => time()));
            $strSql = "UPDATE a_post_parser_img_new SET " . $arUpdate . " WHERE UID IN ('" . join('\',\'', $updateUID)
                . "')";
            $DB->Query($strSql, true, $err_mess . __LINE__);
        }
        if ( ! empty($breakUID) && count((array)$breakUID) > 0) {
            $arUpdate = $DB->PrepareUpdate("a_post_parser_img_new", array('BREAK' => 'Y'));
            $strSql = "UPDATE a_post_parser_img_new SET " . $arUpdate . " WHERE UID IN ('" . join('\',\'', $breakUID)
                . "')";
            $DB->Query($strSql, true, $err_mess . __LINE__);
        }
        
        if ($breakItemsCount > 0) {
            Log::write('Фото обновление пропущеных: ' . $breakItemsCount, true, 'bitrix');
        }
        //Log::write('Фото end', true, 'bitrix');
    }
    
    public static function ImgBitrixIsset()
    {
        //Делаем проверку картинок
        global $DB;
        \Bitrix\Main\Loader::includeModule('iblock');
        
        $updateUID = [];
        
        $strSql = "SELECT UID FROM a_post_parser_img_new";
        $res = $DB->Query($strSql, false, $err_mess . __LINE__);
        while ($row = $res->GetNext()) {
            $item[$row['UID']] = $row['UID'];
        }
        
        $res = \CIBlockElement::GetList([],
            ['IBLOCK_ID' => self::catalogIB, 'ACTIVE' => 'Y' /*, 'DETAIL_PICTURE' => false, '=XML_ID' => $item*/],
            false, false,
            ['EXTERNAL_ID', 'ID', 'IBLOCK_ID', 'DETAIL_PICTURE', 'PREVIEW_PICTURE']);
        while ($arItem = $res->GetNext()) {
            if ( ! empty($item[$arItem['EXTERNAL_ID']])
                && ( ! file_exists($_SERVER['DOCUMENT_ROOT'] . CFile::GetPath($arItem['DETAIL_PICTURE']))
                    or CFile::GetPath($arItem['DETAIL_PICTURE']) == false)
            ) {
                $el = new \CIBlockElement;
                $return = $el->Update($arItem['ID'],
                    array(
                        "PREVIEW_PICTURE" => ['del' => 'Y'],
                        "DETAIL_PICTURE"  => ['del' => 'Y']
                    ),
                    false,
                    false,
                    false,
                    false
                );
                $updateUID[] = $arItem['EXTERNAL_ID'];
                if ($s++ >= 500) {
                    break;
                }
            } elseif ( ! empty($item[$arItem['EXTERNAL_ID']])
                && ($arItem['DETAIL_PICTURE'] == false or $arItem['PREVIEW_PICTURE'] == false)
            ) {
                $el = new \CIBlockElement;
                $return = $el->Update($arItem['ID'],
                    array(
                        "PREVIEW_PICTURE" => ['del' => 'Y'],
                        "DETAIL_PICTURE"  => ['del' => 'Y']
                    ),
                    false,
                    false,
                    false,
                    false
                );
                $updateUID[] = $arItem['EXTERNAL_ID'];
                if ($s++ >= 500) {
                    break;
                }
            }
            
        }
        
        //dump($updateUID);
        /*$res = \CIBlockElement::GetList([], ['IBLOCK_ID' => self::catalogIB, 'PREVIEW_PICTURE' => false, 'XML_ID' => $item], false, false,
            ['EXTERNAL_ID', 'ID', 'IBLOCK_ID']);
        while ($item = $res->GetNext()) {
            $updateUID[] = $item['EXTERNAL_ID'];
        }*/
        
        if ( ! empty($updateUID) && count((array)$updateUID) > 0) {
            $strSql = "UPDATE `a_post_parser_img_new` SET `UPDATE` = 'Y', `BREAK` = '' WHERE UID IN ('" . join('\',\'',
                    $updateUID)
                . "')";
            $sqlResult = $DB->Query($strSql, true, $err_mess . __LINE__);
        }
    }
    
    public static function ImgCleaner()
    {
        $res = CFile::GetList(array("FILE_SIZE" => "desc"),
            array("MODULE_ID" => "iblock", 'CONTENT_TYPE' => 'image/png'));
        while ($res_arr = $res->GetNext()) {
            if ( ! file_exists($_SERVER['DOCUMENT_ROOT'] . CFile::GetPath($res_arr['ID']))) {
                dump($res_arr);
                CFile::Delete($res_arr['ID']);
                if ($s++ >= 1000) {
                    die();
                }
            }
        }
        
    }
    
    public static function RemainsSQL($arResult)
    {
        /*
         * CREATE TABLE a_post_parser_remains
(
        ID INT NOT NULL primary key  AUTO_INCREMENT,
    UID VARCHAR(255),
    SHOP_ID varchar(255),
    OLD_PRICE varchar(255),
    PRICE VARCHAR(20),
    QUANTITY VARCHAR(20),
    `UPDATE` VARCHAR(20),
    `ACTION` VARCHAR(20),
    `OLD_PRICE` VARCHAR(20),
    UPDATE_TIMESTAMP VARCHAR(20),
    BX_UPDATE_TIMESTAMP VARCHAR(20)
);*/
        global $DB;
        $json['Items'] = $arResult;
        // print_r2($json);
        if ( ! empty($json['Items']) && count((array)$json['Items']) > 0) {
            
            Log::write('остатки - Получение из таблицы для сравнения', true, 'postparser_catalog');
            $strSql = "SELECT ID, UID, SHOP_ID, QUANTITY FROM a_post_parser_remains";
            $res = $DB->Query($strSql, false, $err_mess . __LINE__);
            while ($row = $res->Fetch()) {
                $dbItems[$row['UID'] . '-' . $row['SHOP_ID']] = $row;
            }
            
            Log::write('остатки - Получено из Bitrix: ' . count((array)$dbItems), true, 'postparser_catalog');
            Log::write('остатки - Получено  из 1C: ' . count((array)$json['Items']), true, 'postparser_catalog');
            
            telegramBotImport('остатки - Получено из Bitrix: ' . count((array)$dbItems));
            telegramBotImport('остатки - Получено  из 1C: ' . count((array)$json['Items']));
            
            $items2add = [];
            $items2update = [];
            $arrDateEnd = [];
            $arrDateSl = [];
            foreach ($json['Items'] as $jsonItem) {
                if ( ! empty($jsonItem['Item_ID'])) {
                    $jsonItem['UID'] = $jsonItem['Item_ID'];
                }
                if ( ! empty($uids[trim($jsonItem['UID']) . '-' . trim($jsonItem['Shop_ID'])])) {
                    $doubles++;
                    continue;
                }
                $uids[trim($jsonItem['UID']) . '-' . trim($jsonItem['Shop_ID'])] = 1;
                $props = array();
                
                $item = array(
                    'UID'      => trim($jsonItem['Item_ID']),
                    'SHOP_ID'  => trim($jsonItem['Shop_ID']),
                    'QUANTITY' => trim($jsonItem['QUANTITY']),
                );
                $uniqID = $item['UID'] . '-' . $item['SHOP_ID'];
                if (isset($dbItems[$uniqID])) {
                    foreach ($item as $code => $value) {
                        if ($dbItems[$uniqID][$code] != $value && $code != 'ID') {
                            $item['UPDATE'] = 'Y';
                            $item['ID'] = $dbItems[$uniqID]['ID'];
                            $item['UPDATE_TIMESTAMP'] = time();
                        }
                    }
                    if ($item['UPDATE'] == 'Y') {
                        $items2update[] = $item;
                    }
                    unset($dbItems[$uniqID]);
                } else {
                    $item['UPDATE'] = 'Y';
                    $item['UPDATE_TIMESTAMP'] = time();
                    $items2add[$uniqID] = $item;
                }
            }
            
            if ($doubles > 0) {
                Log::write('остатки - Найдены дубли в файле в количестве: ' . $doubles, true, 'postparser_catalog');
            }
            if (count($items2add) > 0 || count($items2update) > 0) {
                Log::write('остатков - Новых: ' . count($items2add) . ' | Измененных: ' . count($items2update), true,
                    'postparser_catalog');
                Log::write('остатки - Обновление базы', true, 'postparser_catalog');
                $updatedItems = 0;
                $addedItems = 0;
                $deletedItems = 0;
                foreach ($items2add as $item) {
                    $arInsert = $DB->PrepareInsert("a_post_parser_remains", $item);
                    $strSql = "INSERT INTO a_post_parser_remains (" . $arInsert[0] . ") VALUES (" . $arInsert[1] . ")";
                    $DB->Query($strSql, true, $err_mess . __LINE__);
                    $addedItems++;
                }
                foreach ($items2update as $item) {
                    if ($item['ID'] > 0) {
                        $id = $item['ID'];
                        $arUpdate = $DB->PrepareUpdate("a_post_parser_remains", $item);
                        $strSql = "UPDATE a_post_parser_remains SET " . $arUpdate . " WHERE ID = " . $id . " ";
                        $DB->Query($strSql, true, $err_mess . __LINE__);
                    } else {
                        $arUpdate = $DB->PrepareUpdate("a_post_parser_remains", $item);
                        $strSql = "UPDATE a_post_parser_remains SET " . $arUpdate . " WHERE UID = '" . $item['UID']
                            . "' AND SHOP_ID = '" . $item['SHOP_ID'] . "' ";
                        $DB->Query($strSql, true, $err_mess . __LINE__);
                    }
                    
                    $updatedItems++;
                }
                Log::write('остатки- База обновлена. Обновлено: ' . $updatedItems . ' | Добавлено: '
                    . $addedItems, true,
                    'postparser_catalog');
                
                telegramBotImport('остатки - База обновлена. Обновлено: ' . $updatedItems . ' | Добавлено: '
                    . $addedItems);
                
                
            }
            if (count((array)$json['Items']) > 15000) {
                foreach ($dbItems as $dbItem) {
                    if ($dbItem['QUANTITY'] > 0) {
                        $item = array(
                            'QUANTITY'         => 0,
                            'UPDATE'           => 'Y',
                            'UPDATE_TIMESTAMP' => time()
                        );
                        $arUpdate = $DB->PrepareUpdate("a_post_parser_remains", $item);
                        $strSql = "UPDATE a_post_parser_remains SET " . $arUpdate . " WHERE ID = "
                            . $dbItem['ID']
                            . " ";
                        $DB->Query($strSql, true, $err_mess . __LINE__);
                        $deletedItems++;
                    }
                }
                if ($deletedItems > 0) {
                    telegramBotImport('остатки - обнулены. ' . $deletedItems);
                    Log::write('остатки - База обновлена. Не найдено: ' . count((array)$dbItems), true,
                        'postparser_catalog');
                    //telegramBotImport('остатки - Не найдено. ' . count((array)$dbItems));
                }
            } else {
                telegramBotImport('остатки - Обновлений нет. ');
                Log::write('остатки - Обновлений нет', true, 'postparser_catalog');
            }
        }
    }
    
    public static function PriceSQL($arResult)
    {
        global $DB;
        $json['Items'] = $arResult;
        // print_r2($json);
        if ( ! empty($json['Items']) && count((array)$json['Items']) > 0) {
            
            Log::write('цены - Получение из таблицы для сравнения', true, 'postparser_catalog');
            $strSql = "SELECT ID, UID, SHOP_ID, OLD_PRICE, PRICE, ACTION FROM a_post_parser_remains";
            $res = $DB->Query($strSql, false, $err_mess . __LINE__);
            while ($row = $res->Fetch()) {
                $dbItems[$row['UID'] . '-' . $row['SHOP_ID']] = $row;
            }
            
            Log::write('цены - Получено из Bitrix: ' . count((array)$dbItems), true, 'postparser_catalog');
            Log::write('цены - Получено  из 1C: ' . count((array)$json['Items']), true, 'postparser_catalog');
            
            telegramBotImport('цены - Получено из Bitrix: ' . count((array)$dbItems));
            telegramBotImport('цены - Получено  из 1C: ' . count((array)$json['Items']));
            
            $items2add = [];
            $items2update = [];
            $arrDateEnd = [];
            $arrDateSl = [];
            
            CModule::Includemodule('catalog');
            $ob = CCatalogStore::GetList(
                array(),
                array(),
                false,
                false,
                array("ID", "TITLE", "ACTIVE", "XML_ID", "SITE_ID")
            );
            while ($ar = $ob->GetNext()) {
                if ($ar['SITE_ID'] == 's1') {
                    $arStores['Розница Аршалы'][] = $ar['XML_ID'];
                }
                if ($ar['SITE_ID'] == 'ek') {
                    $arStores['Розница Аршалы'][] = $ar['XML_ID'];
                }
                /*if ($ar['SITE_ID'] == 'pa') {
                    $arStores['Розница Павлодар'][] = $ar['XML_ID'];
                }*/
            }
            
            foreach ($json['Items'] as $jsonItem) {
                if (empty($arStores[$jsonItem['PRICE_NAME']])) {
                    continue;
                }
                if ( ! empty($jsonItem['Item_ID'])) {
                    $jsonItem['UID'] = $jsonItem['Item_ID'];
                }
                foreach ($arStores[$jsonItem['PRICE_NAME']] as $shopID) {
                    if (empty($shopID)) {
                        continue;
                    }
                    $jsonItem['SHOP_ID'] = $shopID;
                    if ( ! empty($uids[trim($jsonItem['UID']) . '-' . trim($jsonItem['SHOP_ID'])])) {
                        $doubles++;
                        continue;
                    }
                    $uids[trim($jsonItem['UID']) . '-' . trim($jsonItem['SHOP_ID'])] = 1;
                    $props = array();
                    
                    $item = array(
                        'UID'       => trim($jsonItem['Item_ID']),
                        'SHOP_ID'   => trim($jsonItem['SHOP_ID']),
                        'PRICE'     => trim($jsonItem['PRICE']),
                        'OLD_PRICE' => trim($jsonItem['OLD_PRICE']),
                        'ACTION'    => trim($jsonItem['ACTION']),
                    );
                    $uniqID = $item['UID'] . '-' . $item['SHOP_ID'];
                    if (isset($dbItems[$uniqID])) {
                        foreach ($item as $code => $value) {
                            if ($dbItems[$uniqID][$code] != $value && $code != 'ID') {
                                $item['UPDATE'] = 'Y';
                                $item['ID'] = $dbItems[$uniqID]['ID'];
                                $item['UPDATE_TIMESTAMP'] = time();
                            }
                        }
                        if ($item['UPDATE'] == 'Y') {
                            $items2update[] = $item;
                        }
                        unset($dbItems[$uniqID]);
                    } else {
                        $item['UPDATE'] = 'Y';
                        $item['UPDATE_TIMESTAMP'] = time();
                        $items2add[$uniqID] = $item;
                    }
                }
            }
            
            if ($doubles > 0) {
                Log::write('цены - Найдены дубли в файле в количестве: ' . $doubles, true, 'postparser_catalog');
            }
            if (count($items2add) > 0 || count($items2update) > 0) {
                Log::write('остатков - Новых: ' . count($items2add) . ' | Измененных: ' . count($items2update), true,
                    'postparser_catalog');
                Log::write('цены - Обновление базы', true, 'postparser_catalog');
                $updatedItems = 0;
                $addedItems = 0;
                $deletedItems = 0;
                dump($items2add);
                foreach ($items2add as $item) {
                    $arInsert = $DB->PrepareInsert("a_post_parser_remains", $item);
                    $strSql = "INSERT INTO a_post_parser_remains (" . $arInsert[0] . ") VALUES (" . $arInsert[1] . ")";
                    $DB->Query($strSql, true, $err_mess . __LINE__);
                    $addedItems++;
                }
                foreach ($items2update as $item) {
                    if ($item['ID'] > 0) {
                        $id = $item['ID'];
                        $arUpdate = $DB->PrepareUpdate("a_post_parser_remains", $item);
                        $strSql = "UPDATE a_post_parser_remains SET " . $arUpdate . " WHERE ID = " . $id . " ";
                        $DB->Query($strSql, true, $err_mess . __LINE__);
                    } else {
                        $arUpdate = $DB->PrepareUpdate("a_post_parser_remains", $item);
                        $strSql = "UPDATE a_post_parser_remains SET " . $arUpdate . " WHERE UID = '" . $item['UID']
                            . "' AND SHOP_ID = '" . $item['SHOP_ID'] . "' ";
                        $DB->Query($strSql, true, $err_mess . __LINE__);
                    }
                    
                    $updatedItems++;
                }
                Log::write('цены- База обновлена. Обновлено: ' . $updatedItems . ' | Добавлено: '
                    . $addedItems, true,
                    'postparser_catalog');
                
                telegramBotImport('цены - База обновлена. Обновлено: ' . $updatedItems . ' | Добавлено: '
                    . $addedItems);
                
                /* if (count((array)$dbItems) < 10000) {
                     if (count((array)$dbItems) > 9000) {
                         $error = 1012;
                     } else {
                         foreach ($dbItems as $dbItem) {
                             $item = array(
                                 'QUANTITY'         => 0,
                                 'UPDATE'           => 'Y',
                                 'UPDATE_TIMESTAMP' => time()
                             );
                             $arUpdate = $DB->PrepareUpdate("a_post_parser_remains", $item);
                             $strSql = "UPDATE a_post_parser_remains SET " . $arUpdate . " WHERE ID = " . $dbItem['ID']
                                 . " ";
                             $DB->Query($strSql, true, $err_mess . __LINE__);
                             $deletedItems++;
                         }
                     }
                 }*/
                Log::write('цены - База обновлена. Не найдено: ' . count((array)$dbItems), true,
                    'postparser_catalog');
            } else {
                telegramBotImport('цены - Обновлений нет. ');
                Log::write('цены - Обновлений нет', true, 'postparser_catalog');
            }
        }
        
    }
    
    public static function RemainsBitrix()
    {
        $remainUpdate = 0;
        global $DB;
        $arStores = [];
        CModule::Includemodule('iblock');
        CModule::Includemodule('catalog');
        $ob = CCatalogStore::GetList(
            array(),
            array(),
            false,
            false,
            array("ID", "TITLE", "ACTIVE", "XML_ID", "SITE_ID")
        );
        while ($ar = $ob->GetNext()) {
            $arStore[$ar['XML_ID']] = $ar['ID'];
            $arStores[$ar['XML_ID']] = $ar;
        }
        $dbPriceType = CCatalogGroup::GetList(array("SORT" => "ASC"));
        while ($arPriceType = $dbPriceType->Fetch()) {
            //print_r2($arPriceType);
            $shopPrices[$arPriceType['XML_ID']] = $arPriceType['ID'];
        }
        $res = \CIBlockElement::GetList(array(), array('IBLOCK_ID' => self::catalogIB), false, false,
            ['ID', 'IBLOCK_ID', 'EXTERNAL_ID']);
        while ($item = $res->GetNext()) {
            $bxItems[$item['EXTERNAL_ID']] = $item['ID'];
        }
        //print_r2($shopPrices);
        $updateUID = [];
        $res
            = $DB->Query("SELECT * FROM `a_post_parser_remains` WHERE `UPDATE` = 'Y' ORDER BY `UPDATE_TIMESTAMP` ASC LIMIT 2000 ",
            true, $err_mess . __LINE__);
        while ($item = $res->GetNext()) {
            if ($item['UID'] != '' && ! empty($bxItems[$item['UID']])) {
                $updateUID[] = $item['UID'];
            }
            $setUpdateUID[] = $item['UID'];
            $remainUpdate++;
        }
        if ( ! empty($setUpdateUID)) {
            $update = array(
                'UPDATE'              => 'N',
                'BX_UPDATE_TIMESTAMP' => time()
            );
            $arUpdate = $DB->PrepareUpdate("a_post_parser_remains", $update);
            $strSql = "UPDATE a_post_parser_remains SET " . $arUpdate . " WHERE  UID IN ('" . join('\',\'',
                    $setUpdateUID)
                . "')";
            $resSql = $DB->Query($strSql, false, $err_mess . __LINE__);
        }
        if ( ! empty($updateUID)) {
            $res
                = $DB->Query("SELECT * FROM `a_post_parser_remains` WHERE UID IN ('" . join('\',\'', $updateUID)
                . "')",
                true, $err_mess . __LINE__);
            while ($item = $res->GetNext()) {
                if ($item['UID'] != '' && ! empty($bxItems[$item['UID']])) {
                    //print_r2($item);
                    if ($arStores[$item['SHOP_ID']]['ACTIVE'] == 'N') {
                        $item["QUANTITY"] = 0;
                        $item['PRICE'] = 0;
                    }
                    $arFieldsPrice = array(
                        "PRODUCT_ID"       => $bxItems[$item['UID']],
                        "CATALOG_GROUP_ID" => $shopPrices[$item['SHOP_ID']],
                        "PRICE"            => $item['PRICE'],
                        "CURRENCY"         => 'KZT'
                    );
                    $requestPrice = CPrice::GetList(array(),
                        array(
                            "PRODUCT_ID"       => $bxItems[$item['UID']],
                            "CATALOG_GROUP_ID" => $shopPrices[$item['SHOP_ID']] //цена города
                        ));
                    if ($price = $requestPrice->Fetch()) {
                        if ($item['PRICE'] == false) {
                            CPrice::Delete($price['ID']);
                        } else {
                            CPrice::Update($price["ID"], $arFieldsPrice);
                        }
                    } elseif ($item['PRICE'] > 0) {
                        //print_r2($arFieldsPrice);
                        CPrice::Add($arFieldsPrice);
                    }
                    
                    //if($item['PRICE'] >0) { //ставим базовую цену
                    $arFieldsPrice = array(
                        "PRODUCT_ID"       => $bxItems[$item['UID']],
                        "CATALOG_GROUP_ID" => 1,
                        "PRICE"            => $item['PRICE'],
                        "CURRENCY"         => 'KZT'
                    );
                    $requestPrice = CPrice::GetList(array(),
                        array("PRODUCT_ID" => $bxItems[$item['UID']], "CATALOG_GROUP_ID" => 1)); //базовая цена
                    if ($price = $requestPrice->Fetch()) {
                        if ($item['PRICE'] == false) {
                            CPrice::Delete($price['ID']);
                        } else {
                            CPrice::Update($price["ID"], $arFieldsPrice);
                        }
                    } elseif ($item['PRICE'] > 0) {
                        CPrice::Add($arFieldsPrice);
                    }
                    //}
                    // Добавим количество на складах
                    
                    $storageID = false;
                    $storageCount = $item["QUANTITY"];
                    
                    $requestStorage = \Bitrix\Catalog\StoreProductTable::getlist(array(
                        'filter' => array(
                            "=PRODUCT_ID" => $bxItems[$item['UID']],
                            '=STORE_ID'   => $arStore[$item['SHOP_ID']]
                        ),
                        'select' => array('AMOUNT', 'STORE_ID', 'ID'),
                    ));
                    if ($arrStorage = $requestStorage->Fetch()) {
                        $storageID = $arrStorage["ID"];
                    }
                    
                    $arFieldsStorage = array(
                        "PRODUCT_ID" => $bxItems[$item['UID']],
                        "STORE_ID"   => $arStore[$item['SHOP_ID']],
                        "AMOUNT"     => intval($item["QUANTITY"]),
                    );
                    //print_r2($arFieldsStorage);
                    if ($storageID) {
                        CCatalogStoreProduct::Update($storageID, $arFieldsStorage);
                        //CCatalogProduct::add(array("ID" => $bxItems[$item['UID']], "QUANTITY" => $item["QUANTITY"]));
                    } else {
                        CCatalogStoreProduct::Add($arFieldsStorage);
                        //CCatalogProduct::add(array("ID" => $bxItems[$item['UID']], "QUANTITY" => $item["QUANTITY"]));
                    }
                    
                    
                }
            }
        }
        $updateCatalog = [];
        $arPropStore = [];
        $arUpdatePrice = [];
        if ( ! empty($updateUID)) {
            $res = $DB->Query("SELECT * FROM `a_post_parser_remains`  WHERE UID IN ('" . join('\',\'', $updateUID)
                . "')", true, $err_mess . __LINE__);
            while ($item = $res->GetNext()) {
                if ($arStores[$item['SHOP_ID']]['ACTIVE'] == 'N') {
                    $item["QUANTITY"] = 0;
                    $item["PRICE"] = '';
                    $item["OLD_PRICE"] = '';
                } else {
                    $arPropStore[$item['UID']]['STORE_' . $arStores[$item['SHOP_ID']]['SITE_ID']]
                        += intval($item["QUANTITY"]);
                    $arPropStore[$item['UID']]['ACTION_' . $arStores[$item['SHOP_ID']]['SITE_ID']] = $item["ACTION"];
                    $arPropStore[$item['UID']]['OLD_PRICE_' . $arStores[$item['SHOP_ID']]['SITE_ID']]
                        = $item["OLD_PRICE"];
                }
                $updateCatalog[$item['UID']] += $item["QUANTITY"];
                
                //максимальная цена города
                if (
                    $arUpdatePrice[$item['UID']]['PRICE_' . $arStores[$item['SHOP_ID']]['SITE_ID']] == false or
                    $arUpdatePrice[$item['UID']]['PRICE_' . $arStores[$item['SHOP_ID']]['SITE_ID']]
                    < intval($item["PRICE"])
                ) {
                    $arUpdatePrice[$item['UID']]['PRICE_' . $arStores[$item['SHOP_ID']]['SITE_ID']]
                        = intval($item["PRICE"]);
                }
            }
            foreach ($updateCatalog as $uid => $quantity) {
                CCatalogProduct::add(array("ID" => $bxItems[$uid], "QUANTITY" => $quantity));
            }
            
            foreach ($arPropStore as $uid => $prop) {
                foreach ($prop as $PROPERTY_CODE => $PROPERTY_VALUE) {
                    \CIBlockElement::SetPropertyValuesEx($bxItems[$uid], 7, [$PROPERTY_CODE => $PROPERTY_VALUE]);
                }
            }
            //новая базовая цена для городов //
            foreach ($arUpdatePrice as $uid => $prices) {
                foreach ($prices as $priceCode => $price) {
                    if ( ! empty($priceCode) && $shopPrices[$priceCode] > 0) {
                        $arFieldsPrice = array(
                            "PRODUCT_ID"       => $bxItems[$uid],
                            "CATALOG_GROUP_ID" => $shopPrices[$priceCode],
                            "PRICE"            => $price,
                            "CURRENCY"         => 'KZT'
                        );
                        $requestPrice = CPrice::GetList([],
                            [
                                "PRODUCT_ID"       => $bxItems[$uid],
                                "CATALOG_GROUP_ID" => $arFieldsPrice['CATALOG_GROUP_ID']
                            ]
                        );
                        if ($arPrice = $requestPrice->Fetch()) {
                            CPrice::Update($arPrice["ID"], $arFieldsPrice);
                        } else {
                            CPrice::Add($arFieldsPrice);
                        }
                    }
                }
            }
            //новая базовая цена для городов
        }
        if ($remainUpdate > 0) {
            //Log::write('------------------------', true, 'bitrix');
            Log::write('Остатки и цены обновлено: ' . $remainUpdate, true, 'bitrix');
            //Log::write('------------------------', true, 'bitrix');
        }
    }
    
    public static function ShopSQL($arResult)
    {
        /*
         * CREATE TABLE a_post_parser_shop
(
        ID INT NOT NULL primary key  AUTO_INCREMENT,
    UID varchar(255),
    NAME varchar(255),
    `UPDATE` VARCHAR(20),
    `DELETE` VARCHAR(20),
    UPDATE_TIMESTAMP VARCHAR(20),
    BX_UPDATE_TIMESTAMP VARCHAR(20)
);*/
        
        global $DB;
        $json['Items'] = $arResult;
        $uids = [];
        //print_r2($json);
        if ( ! empty($json['Items']) && count((array)$json['Items']) > 0) {
            /*$res = CIBlockSection::GetList([], ['IBLOCK_ID' => self::catalogIB], false);
            while ($section = $res->Fetch()) {
                $sections[$section['EXTERNAL_ID']] = $section['ID'];
            }*/
            
            Log::write('Получение магазинов из таблицы для сравнения', true, 'postparser_catalog');
            $strSql = "SELECT * FROM a_post_parser_shop ";
            $res = $DB->Query($strSql, false, $err_mess . __LINE__);
            while ($row = $res->GetNext()) {
                $dbItems[$row['UID']] = $row;
            }
            $stat['POST_STAT']['DB_ITEMS'] = count((array)$dbItems);
            Log::write('Получено: ' . count((array)$dbItems), true, 'postparser_catalog');
            //Log::write('Поиск изменений.', true, 'postparser_catalog');
            $items2add = [];
            $items2update = [];
            $arrDateEnd = [];
            $arrDateSl = [];
            foreach ($json['Items'] as $jsonItem) {
                if (in_array(trim($jsonItem['SHOP_ID']), $uids)) {
                    $doubles++;
                    continue;
                }
                $uids[] = trim($jsonItem['UID']);
                $props = array();
                
                $item = array(
                    'UID'  => trim($jsonItem['SHOP_ID']),
                    'NAME' => trim($jsonItem['NAME']),
                );
                if (isset($dbItems[$item['UID']])) {
                    foreach ($item as $code => $value) {
                        if ($dbItems[$item['UID']]['~' . $code] != $value) {
                            $item['UPDATE'] = 'Y';
                            //$item['UPDATES'] = $code . ': ' . $dbItems[$item['UID']]['~' . $code] . ' != ' . $value;
                            $item['UPDATE_TIMESTAMP'] = time();
                        }
                    }
                    if ($item['UPDATE'] == 'Y') {
                        $items2update[] = $item;
                        //if (count($data['UPDATES']) < 10) {
                        //$data['UPDATES'][] = $item;
                        //}
                    }
                    unset($dbItems[$item['UID']]);
                } else {
                    $item['UPDATE'] = 'Y';
                    $item['UPDATE_TIMESTAMP'] = time();
                    $items2add[$item['UID']] = $item;
                }
            }
            
            if ($doubles > 0) {
                Log::write('Найдены дубли в файле в количестве: ' . $doubles, true, 'postparser_catalog');
                $stat['POST_STAT']['DOUBLES'] = $doubles;
            }
            if (count((array)$items2add) > 0 || count((array)$items2update) > 0) {
                $stat['POST_STAT']['TO_ADD'] = count((array)$items2add);
                $stat['POST_STAT']['TO_UPDATE'] = count((array)$items2update);
                Log::write('Новых: ' . count((array)$items2add) . ' | Измененных: ' . count((array)$items2update), true,
                    'postparser_catalog');
                Log::write('Обновление базы товаров.', true, 'postparser_catalog');
                $updatedItems = 0;
                $addedItems = 0;
                $deletedItems = 0;
                foreach ($items2add as $item) {
                    $arInsert = $DB->PrepareInsert("a_post_parser_shop", $item);
                    $strSql = "INSERT INTO a_post_parser_shop (" . $arInsert[0] . ") VALUES (" . $arInsert[1] . ")";
                    $DB->Query($strSql, true, $err_mess . __LINE__);
                    $addedItems++;
                }
                foreach ($items2update as $item) {
                    $arUpdate = $DB->PrepareUpdate("a_post_parser_shop", $item);
                    $strSql = "UPDATE a_post_parser_shop SET " . $arUpdate . " WHERE UID = '" . $item['UID'] . "'";
                    $DB->Query($strSql, true, $err_mess . __LINE__);
                    $updatedItems++;
                }
                if (count((array)$dbItems) > 0) {
                    if (count((array)$dbItems) > 15000) {
                        $error = 1012;
                    } else {
                        foreach ($dbItems as $dbItem) {
                            $item = array(
                                'ACTIVE'           => 'N',
                                'UPDATE'           => 'Y',
                                'UPDATE_TIMESTAMP' => time()
                            );
                            $arUpdate = $DB->PrepareUpdate("a_post_parser_shop", $item);
                            $strSql = "UPDATE a_post_parser_shop SET " . $arUpdate . " WHERE UID = '" . $item['UID']
                                . "'";
                            $DB->Query($strSql, true, $err_mess . __LINE__);
                            $deletedItems++;
                        }
                        $stat['POST_STAT']['DELETED'] = count((array)$dbItems);
                    }
                }
                Log::write('База обновлена. Обновлено: ' . $updatedItems . ' | Добавлено: ' . $addedItems, true,
                    'postparser_catalog');
                $stat['POST_STAT']['ADDED'] = $addedItems;
                $stat['POST_STAT']['UPDATED'] = $updatedItems;
                $data['message'] = 'База обновлена';
            } else {
                $data['message'] = 'Обновлений нет.';
                Log::write('Обновлений нет', true, 'postparser_catalog');
            }
        } else {
            $error = 1008;
        }
        //print_r2($data);
        //print_r2($stat);
        return $data;
        
    }
    
    public static function ShopBitrix()
    {
        global $DB;
        \Bitrix\Main\Loader::includeModule('catalog');
        $res = $DB->Query("SELECT * FROM `a_post_parser_shop`  WHERE `UPDATE` = 'Y' ", true, $err_mess . __LINE__);
        while ($item = $res->GetNext()) {
            if ($item['UID'] == '1d78dac2-c4bd-11e0-85fb-002197a3c18e' or $item['UID']
                == '95482f5a-41b0-11e6-ae82-0cc47a004a9b'
            ) {
                $item['DELETE'] = 1;
            }
            $ob = CCatalogStore::GetList(
                array(),
                array('XML_ID' => $item['UID']),
                false,
                false,
                array("ID", "TITLE", "ACTIVE")
            );
            if ($ar = $ob->GetNext()) {
                $ID = CCatalogStore::Update($ar['ID'],
                    array("ADDRESS" => $item['NAME'], "ACTIVE" => $item['DELETE'] == 1 ? 'N' : 'Y'));
            } else {
                $arFields = array(
                    "ADDRESS" => $item['NAME'],
                    "ACTIVE"  => $item['DELETE'] == 1 ? 'N' : 'Y',
                    "XML_ID"  => $item['UID'],
                );
                $ID = CCatalogStore::Add($arFields);
            }
            
            $dbPriceType = CCatalogGroup::GetList(array("SORT" => "ASC"), array('XML_ID' => $item['UID']));
            if ($arPriceType = $dbPriceType->Fetch()) {
                CCatalogGroup::Update($arPriceType['ID'],
                    array(
                        "USER_LANG" => array(
                            "ru" => $item['NAME'],
                        ),
                        "NAME"      => $item['UID'],
                        "ACTIVE"    => $item['DELETE'] == 1 ? 'N' : 'Y'
                    )
                );
            } else {
                $arFields = array(
                    "NAME"           => $item['UID'],
                    "SORT"           => 100,
                    "XML_ID"         => $item['UID'],
                    "USER_GROUP"     => array(1, 2),   // видят цены члены групп 2 и 4
                    "USER_GROUP_BUY" => array(1, 2),  // покупают по этой цене
                    // только члены группы 2
                    "USER_LANG"      => array(
                        "ru" => $item['NAME'],
                    )
                );
                $ID = CCatalogGroup::Add($arFields);
            }
            $update = array(
                'UPDATE'              => 'N',
                'BX_UPDATE_TIMESTAMP' => time()
            );
            $arUpdate = $DB->PrepareUpdate("a_post_parser_shop", $update);
            $strSql = "UPDATE a_post_parser_shop SET " . $arUpdate . " WHERE `UID` = '" . $item['UID'] . "'";
            //print_r2($strSql);
            $resSql = $DB->Query($strSql, false, $err_mess . __LINE__);
        }
    }
    
    public static function Orders()
    {
        //print_r($_REQUEST);
        $beginDate = isset($_REQUEST['b_date']) ? $_REQUEST['b_date'] : null;
        $endDate = isset($_REQUEST['e_date']) ? $_REQUEST['e_date'] : null;
        //проверка на ошибки запрроса
        if ($beginDate == null) {
            $errors[] = 'Не указана дата начала выгрузки';
        } elseif ($beginDate == 0) {
            $errors[] = 'Неверный формат даты начала выгрузки';
        }
        
        if ($endDate == null) {
            $errors[] = 'Не указана дата окончания выгрузки';
        } elseif ($endDate == 0) {
            $errors[] = 'Неверный формат даты окончания выгрузки';
        }
        
        if ( ! empty($errors)) {    //если с наличием и форматом данных все ОК, проверяем их величины
            if ($beginDate > $endDate) {
                $errors[] = 'Дата начала выгрузки больше даты окончания';
            }
        }
        if ( ! empty($errors)) {
            return array('error' => $errors);
        }
        
        \Bitrix\Main\Loader::includeModule('sale');
        \Bitrix\Main\Loader::includeModule('catalog');
        
        $obPayType = CSalePaySystem::GetList();
        while ($ar = $obPayType->Fetch()) {
            $arPayType[$ar['ID']] = $ar['NAME'];
        }
        global $DB;
        //формируем фильтр заказов по дате создания
        $beginDateTime = $beginDate;
        $endDateTime = $endDate + 60 * 60 * 24;
        if ($endDateTime > ($beginDateTime + 86400 * 8)) {
            //$beginDateTime = $endDateTime - 86400 * 8;
        }
        $bDate = date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), $beginDateTime);
        $eDate = date($DB->DateFormatToPHP(CSite::GetDateFormat("SHORT")), $endDateTime);
        
        $res = CSaleStatus::GetList(array(), array());
        while ($status = $res->GetNext()) {
            $statuses[$status['ID']] = $status['NAME'];
        }
        
        $arFilter = array(
            ">=DATE_INSERT" => $bDate,
            "<=DATE_INSERT" => $eDate,
            //"DELIVERY_ID" => 10
        );
        //print_r($arFilter);
        $dbOrders = CSaleOrder::GetList(array("ID" => "ASC"), $arFilter);
        $orders = array();
        //print_r2($arFilter);
        while ($arOrders = $dbOrders->Fetch()) {
            // print_r2($arOrders);
            $order = array(
                'ORDER_ID'       => $arOrders['ACCOUNT_NUMBER'],
                'DATE'           => $arOrders['DATE_INSERT'],
                'STATUS'         => $arOrders['STATUS_ID'],
                'STATUS_TEXT'    => $statuses[$arOrders['STATUS_ID']],
                'SUM'            => $arOrders['PRICE'],
                'PRICE_DELIVERY' => 0,
                //'PRICE_COURIER' => 0,
                //'DISCOUNT' => $arOrders['DISCOUNT_VALUE'],
                'STATUS_PAY'     => $arOrders['PAYED'],
                'SUM_PAYMENT'    => $arOrders['SUM_PAID'],
                'BASKET'         => array(),
                'USER_TYPE'      => $arOrders['PERSON_TYPE_ID'] == 1 ? 'Физическое лицо' : 'Юридическое лицо',
                'USER_PROP'      => array(),
                'COMMENTS'       => $arOrders['COMMENTS'],
                'PAY_SYSTEM'     => $arPayType[$arOrders['PAY_SYSTEM_ID']]
            );
            
            if ($arOrders['PRICE_DELIVERY'] == 3000) {
                $order['PRICE_DELIVERY'] = $arOrders['PRICE_DELIVERY'];
            } else {
                if ($arOrders['PRICE_DELIVERY'] == 1000) {
                    $order['PRICE_COURIER'] = $arOrders['PRICE_DELIVERY'];
                }
            }
            
            $dbOrderProps = CSaleOrderPropsValue::GetList(
                array("SORT" => "ASC"),
                array("ORDER_ID" => $arOrders['ID'], '!CODE' => 'LOCATION')
            );
            while ($arOrderProps = $dbOrderProps->GetNext()):
                $order['USER_PROP'][$arOrderProps['CODE']] = $arOrderProps['VALUE'];
                //echo "<pre>"; print_r($arOrderProps); echo "</pre>";
            endwhile;
            //отбираем товары с фильтром по id заказа
            $dbBasketItems = CSaleBasket::GetList(array("NAME" => "ASC", "ID" => "ASC"),
                array("ORDER_ID" => $arOrders['ID']));
            while ($arItem = $dbBasketItems->Fetch()) {
                //получаем основные свойства
                $bxItem = \CIBlockElement::GetByID($arItem['PRODUCT_ID'])->Fetch();
                //print_r2($arItem);
                $order['BASKET'][] = array(
                    'ID'             => $bxItem['XML_ID'],
                    'NAME'           => $bxItem['NAME'],
                    'QUANTITY'       => $arItem['QUANTITY'],
                    'PRICE'          => $arItem['PRICE'],
                    'DISCOUNT_PRICE' => $arItem['DISCOUNT_PRICE'],
                );
            }
            $orders[] = $order;
        }
        //print_r2($orders);
        return $orders;
    }
    
    public static function ClearLog()
    {
        $maxTime = 7 * 24 * 60 * 60; //7 дней
        $maxTimeImg = 1 * 24 * 60 * 60; //1 дней
        $rootDir = $_SERVER['DOCUMENT_ROOT'];
        $arFolder = [
            $rootDir . '/import/log/_old/img/',
            $rootDir . '/import/log/_old/item/',
            $rootDir . '/import/log/_old/remains/',
            $rootDir . '/import/log/_old/section/',
            $rootDir . '/import/log/_old/shop/',
            $rootDir . '/import/log/_old/сharacteristic/',
            $rootDir . '/import/log/_old/price/',
            $rootDir . '/import/log/_old/itemsection/',
        ];
        foreach ($arFolder as $folderName) {
            if (file_exists($folderName)) {
                foreach (new DirectoryIterator($folderName) as $fileInfo) {
                    if ($fileInfo->isDot()) {
                        continue;
                    }
                    if ($fileInfo->isFile() && time() - $fileInfo->getCTime() >= $maxTime) {
                        unlink($fileInfo->getRealPath());
                    }
                    if ($folderName == $rootDir . '/import/log/_old/img/' && $fileInfo->isFile()
                        && time() - $fileInfo->getCTime() >= $maxTimeImg
                    ) {
                        unlink($fileInfo->getRealPath());
                    }
                }
            }
        }
    }
    
    public static function ClearDublePrice()
    {
        \Bitrix\Main\Loader::includeModule('catalog');
        $arIssetProduct = [];
        $requestPrice = CPrice::GetList(['ID' => 'ASC'],
            [
                //"CATALOG_GROUP_ID" => 34
            ]
        );
        while ($arPrice = $requestPrice->Fetch()) {
            if ( ! empty($arIssetProduct[$arPrice['PRODUCT_ID']][$arPrice["CATALOG_GROUP_ID"]])) {
                $s++;
                CPrice::Delete($arPrice['ID']);
            } elseif ($arPrice['PRICE'] == 0) {
                CPrice::Delete($arPrice['ID']);
                $s2++;
            }
            $arIssetProduct[$arPrice['PRODUCT_ID']][$arPrice["CATALOG_GROUP_ID"]] = 1;
        }
        dump(['дублей' => $s, 'Цены = 0' => $s2]);
    }
    
    public static function CheckPrice($element_uid)
    {
        global $DB;
        \Bitrix\Main\Loader::includeModule('catalog');
        $fileDir = '/home/bitrix/www/import/log/remains/';
        $issetShop = [];
        $arInFile = [];
        $arInSql = [];
        $arShopBitrix = [];
        
        $ob = CCatalogStore::GetList(
            [],
            [],
            false,
            false,
            ["ID", "ADDRESS", "XML_ID", "ACTIVE"]
        );
        while ($ar = $ob->GetNext()) {
            $arShopBitrix[$ar['XML_ID']] = $ar['ADDRESS'];
        }
        
        $strSql
            = "SELECT UID, OLD_PRICE, PRICE, SHOP_ID, QUANTITY, `UPDATE` FROM a_post_parser_remains WHERE UID = '$element_uid'";
        $res = $DB->Query($strSql, false, $err_mess . __LINE__);
        while ($row = $res->Fetch()) {
            $arInSql[$row['SHOP_ID']] = $row;
            $issetShop[$row['SHOP_ID']] = $row['UID'];
            $dbItems[$row['UID'] . '-' . $row['SHOP_ID']] = $row;
        }
        $arFiles = glob($fileDir . "*.txt");
        $func = function ($b, $a) {
            return filemtime($a) - filemtime($b);
        };
        usort($arFiles, $func);
        foreach ($arFiles as $file) {
            if (empty($issetShop)) {
                break;
            }
            $arResultFile = json_decode(file_get_contents($file), true);
            if ( ! empty($arResultFile['Remains']) && count((array)$arResultFile['Remains']) > 0) {
                foreach ($arResultFile['Remains'] as $item) {
                    if ( ! empty($issetShop[$item['SHOP_ID']]) && $issetShop[$item['SHOP_ID']] == $item['Item_ID']) {
                        $item['FILE'] = str_replace($fileDir, '', $file);
                        $arInFile[$item['SHOP_ID']] = $item;
                        unset($issetShop[$item['SHOP_ID']]);
                    }
                }
            }
        }
        return ['BITRIX' => $arInSql, '1C' => $arInFile, 'SHOP' => $arShopBitrix];
    }
    
    public static function CheckPriceDate($element_uid)
    {
        global $DB;
        \Bitrix\Main\Loader::includeModule('catalog');
        $fileDir = '/home/bitrix/www/import/log/_old/remains/';
        $issetShop = [];
        $arInFile = [];
        $arInSql = [];
        $arShopBitrix = [];
        
        $ob = CCatalogStore::GetList(
            [],
            [],
            false,
            false,
            ["ID", "ADDRESS", "XML_ID", "ACTIVE"]
        );
        while ($ar = $ob->GetNext()) {
            $arShopBitrix[$ar['XML_ID']] = $ar['ADDRESS'];
        }
        
        $strSql
            = "SELECT UID, OLD_PRICE, PRICE, SHOP_ID, QUANTITY, `UPDATE` FROM a_post_parser_remains WHERE UID = '$element_uid'";
        $res = $DB->Query($strSql, false, $err_mess . __LINE__);
        while ($row = $res->Fetch()) {
            $arInSql[$row['SHOP_ID']] = $row;
            $issetShop[$row['SHOP_ID']] = $row['UID'];
            $dbItems[$row['UID'] . '-' . $row['SHOP_ID']] = $row;
        }
        $arFiles = glob($fileDir . "*.txt");
        $func = function ($b, $a) {
            return filemtime($a) - filemtime($b);
        };
        usort($arFiles, $func);
        foreach ($arFiles as $file) {
            $date = date('d.m.Y', strtotime(str_replace(['.txt', $fileDir], '', $file)));
            $date = str_replace(['.txt', $fileDir], '', $file);
            
            if (empty($issetShop)) {
                break;
            }
            $arResultFile = json_decode(file_get_contents($file), true);
            if ( ! empty($arResultFile['Remains']) && count((array)$arResultFile['Remains']) > 0) {
                foreach ($arResultFile['Remains'] as $item) {
                    if ( ! empty($issetShop[$item['Shop_ID']]) && $issetShop[$item['Shop_ID']] == $item['Item_ID']) {
                        $item['FILE'] = str_replace($fileDir, '', $file);
                        $arInFile[$date][$item['Shop_ID']] = $item;
                        //unset($issetShop[$item['SHOP_ID']]);
                    }
                }
            }
        }
        
        return ['BITRIX' => $arInSql, '1C' => $arInFile, 'SHOP' => $arShopBitrix];
    }
    
    public static function findLog($uid, $type = 'img')
    {
        $dir = '';
        $arReturn = [];
        if ($type == 'img') {
            $dir = 'img';
        }
        $fileDir = '/home/bitrix/www/import/log/' . $dir . '/';
        $arFiles = glob($fileDir . "*.txt");
        $func = function ($b, $a) {
            return filemtime($a) - filemtime($b);
        };
        usort($arFiles, $func);
        if ( ! empty($dir) && ! empty($uid)) {
            foreach ($arFiles as $file) {
                $arResult = file_get_contents($file);
                if (strpos($arResult, $uid)) {
                    $arResultFile = json_decode($arResult, true);
                    $img = [];
                    foreach ($arResultFile['Img'] as $arItem) {
                        if ($arItem['UID'] == $uid) {
                            $img[] = [
                                'src'      => self::checkImg($arItem['IMG'], $arItem['UID'] . '_' . $arItem['number']),
                                'delete'   => $arItem['DELETE'],
                                'number'   => $arItem['number'],
                                'Основной' => $arItem['Основной'],
                            ];
                        }
                    }
                    $arReturn[] = [
                        'img'  => $img,
                        'file' => str_replace('/home/bitrix/www', '', $file)
                    ];
                    break;
                }
            }
        }
        return $arReturn;
    }
    
    public static function clearDubleItem()
    {
        \Bitrix\Main\Loader::includeModule("iblock");
        global $DB;
        
        $arIsset = [];
        $arUnset = [];
        
        $arFilter = array(
            "IBLOCK_ID" => self::catalogIB,
            "!XML_ID"   => false,
        );
        $arSelect = array("ID", "NAME", "XML_ID");
        
        $rsElements = \CIBlockElement::GetList(['XML_ID' => 'ASC', 'DATE_CREATE' => 'ASC'], $arFilter, false,
            false, $arSelect);
        while ($arElement = $rsElements->Fetch()) {
            if ( ! empty($arIsset[$arElement["XML_ID"]])) {
                $arUnset[$arElement["XML_ID"]][] = $arElement['ID'];
            }
            $arIsset[$arElement["XML_ID"]]++;
            if(count($arUnset) > 20){
                break;
            }
        }
        
        $delete = 0;
        foreach ($arUnset as $xml_id => $ids) {
            $item = ['UPDATE' => 'Y'];
            $arUpdate = $DB->PrepareUpdate("a_post_parser_remains", $item);
            $strSql = "UPDATE a_post_parser_remains SET " . $arUpdate . " WHERE UID = '" . $xml_id . "' ";
            $DB->Query($strSql, true, $err_mess . __LINE__);
            
            $arUpdate = $DB->PrepareUpdate("a_post_parser_img_new", $item);
            $strSql = "UPDATE a_post_parser_img_new SET " . $arUpdate . " WHERE UID = '" . $xml_id . "' ";
            $DB->Query($strSql, true, $err_mess . __LINE__);
            
            $arUpdate = $DB->PrepareUpdate("a_post_parser_items", $item);
            $strSql = "UPDATE a_post_parser_items SET " . $arUpdate . " WHERE UID = '" . $xml_id . "' ";
            $DB->Query($strSql, true, $err_mess . __LINE__);
            
            foreach ($ids as $id) {
                $DB->StartTransaction();
                if ( ! \CIBlockElement::Delete($id)) {
                    $strWarning .= 'Error!';
                    dump($strWarning);
                    $DB->Rollback();
                } else {
                    dump('удален ' . $id);
                    $DB->Commit();
                }
                $delete++;
                
            }
        }
    }
}