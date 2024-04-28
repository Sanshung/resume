<?php

namespace Classes\Moisklad;

use Bitrix\Main\Config\Option;
use Classes\Moisklad\HL;
use Bitrix\Main;
use Bitrix\Main\Security\Random;
use Bitrix\Main\UserPhoneAuthTable;

class Counterparty
{
    const SITE_ID = 's1';
    public static $arPrice
        = [
            ''                                     => '',
            '617f6405-86b7-42b4-96aa-76edb3028a3a' => 1, //Скидка 5
            'ec4afc2d-1850-4dd7-94ee-3ae5d385ad16' => 2, //Скидка 7
            'fcd455c3-ac22-4bf9-92b3-dbfe7b1fc8ae' => 3, //Скидка 10
        ];
    public static $arPriceGroup
        = [
            '617f6405-86b7-42b4-96aa-76edb3028a3a' => 8, //Скидка 5
            'ec4afc2d-1850-4dd7-94ee-3ae5d385ad16' => 9, //Скидка 7
            'fcd455c3-ac22-4bf9-92b3-dbfe7b1fc8ae' => 10, //Скидка 10
        ];
    
    public static function findByEmail($email)
    {
        $arResult = [];
        
        $arGet = [
            'limit=1',
            'filter=email=' . $email,
        ];
        
        $arResult = Request::request('counterparty', $arGet);
        
        return $arResult['rows'][0]['id'];
    }
    
    public static function getByID($id = '')
    {
        $arResult = [];
        
        $arResult = Request::request('counterparty/' . $id);
        if ( ! empty($arResult)) {
            $arAccount = Request::request('counterparty/' . $id . '/accounts');
            $arResult['accounts'] = $arAccount['rows'][0];
            
            $employeeId = str_replace('https://api.moysklad.ru/api/remap/1.2/entity/employee/', '',
                $arResult['owner']['meta']['href']);
            $arEmployee = Request::request('employee/' . $employeeId);
            $arResult['employee'] = $arEmployee;
        }
        
        foreach ($arResult['attributes'] as $item) {
            $arResult['attributes_new'][$item['name']] = $item['value'];
        }
        
        return $arResult;
    }
    
    public static function getAll($limit = 10)
    {
        $arResult = [];
        
        $offset = Option::get('main', 'pk_exchange_counterparty', 0, self::SITE_ID);
        
        $arGet = [
            'limit=' . $limit,
            'offset=' . $offset,
            'filter=email!=false',
        ];
        
        $arResult = Request::request('counterparty', $arGet);
        
        if (empty($arResult['rows'])) {
            $offset = $limit = 0;
        }
        Option::set('main', 'pk_exchange_counterparty', ($offset + $limit), self::SITE_ID);
        
        return $arResult['rows'];
    }
    
    public static function OnAfterUserAddHandler(&$arResult)
    {
        if (empty($arResult['XML_ID'])
            && $GLOBALS['updateMoisklad'] == false
        ) { //Не отправляем запрос если добавлен из мойсклад
            self::OnAfterUserHandler($arResult);
        }
    }
    
    public static function OnAfterUserUpdateHandler(&$arResult)
    {
        self::OnAfterUserHandler($arResult);
    }
    
    public static function OnAfterUserHandler($arResult)
    {
        if ($arResult['UF_EDIT'] == 1) {
            return '';
        }
        if ($GLOBALS['updateMoisklad'] == true) {
            //Main\Diag\Debug::writeToFile(date('d.m.Y H:i:s').' Отмена обновления','Moisklad', 'log.txt');
            return '';
        }
        //Main\Diag\Debug::writeToFile(date('d.m.Y H:i:s').' обновление','Moisklad', 'log.txt');
        
        $arUserFields = [
            'id'               => $arResult['ID'],
            'email'            => $arResult['EMAIL'],
            'name'             => $arResult['NAME'],
            'phone'            => $arResult['PHONE_NUMBER'],
            'legalAddressFull' => [
                'city'       => $arResult['PERSONAL_CITY'],
                'postalCode' => $arResult['PERSONAL_ZIP'],
                'street'     => $arResult['PERSONAL_STREET']
            ],
            'description'      => $arResult['UF_COMMENT'],
            'inn'              => $arResult['UF_INN'],
            'legalTitle'       => $arResult['UF_COMPANY'],
            'legalAddress'     => $arResult['UF_URADDRESS'],
            'kpp'              => $arResult['UF_KPP'],
            'okpo'             => $arResult['UF_OKPO'],
            'ogrn'             => $arResult['UF_OGRN'],
            'attributes'       => [
                [
                    "meta"  => [
                        "href"      => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/metadata/attributes/5b74fbee-4185-11ee-0a80-034900063c35",
                        "type"      => "attributemetadata",
                        "mediaType" => "application/json"
                    ],
                    "name"  => "ЕГАИС",
                    "type"  => "string",
                    "value" => $arResult['UF_EGAIS']
                ]
            ],
            'accounts'         => [
                [
                    'bankName'             => $arResult['UF_BANK'],
                    'correspondentAccount' => $arResult['UF_KS'],
                    'accountNumber'        => $arResult['UF_RS'],
                    'bic'                  => $arResult['UF_BIK'],
                ]
            ],
        ];
        //$egais
        /*
            'UF_PRICE'         => self::$arPrice[$arResult['priceType']['externalCode']],
            'UF_MANAGER_ID'    => $arResult['employee']['id'],
            'UF_MANAGER_PHONE' => $arResult['employee']['phone'],
            'UF_MANAGER_NAME'  => $arResult['employee']['shortFio'],
         */
        self::getUser($arUserFields);
    }
    
    public static function getUser($arFields)
    {
        $id = '';
        if (empty($arFields['email'])) {
            //throw new \Exception('Не указан email - Local\Profitkit\Moisklad\Counterparty::getUser '.$arFields['email']);
            return '';
        }
        
        if ( ! empty($arFields['XML_ID'])) {
            $id = $arFields['XML_ID'];
        } else {
            $id = self::findByEmail($arFields['email']);
        }
        if ( ! empty($id)) {
            $arAccountID = self::getAccountId($id);
            $arFields['accounts'][0]['id'] = $arAccountID;
            $arFields['accounts'][0]['accountId'] = $id;
            return self::send($arFields, '/' . $id);
        } else {
            return self::send($arFields);
        }
    }
    
    public static function getAccountId($id)
    {
        $arResult = Request::request('counterparty/' . $id . '/accounts/');
        if ( ! empty($arResult['rows'][0]['id'])) {
            return $arResult['rows'][0]['id'];
        } else {
            return '';
        }
    }
    
    public static function send($arFields = [], $id = '')
    {
        if ( ! empty($arFields)) {
            
            $userID = $arFields['id'];
            unset($arFields['id']);
            
            if ( ! empty($id)) {
                $arResult = Request::request('counterparty' . $id, [], $arFields, 'PUT');
            } else {
                $arResult = Request::request('counterparty', [], $arFields);
            }
            
            if ( ! empty($arResult['id'])) {
                
                $hl = new HL\HL(8);
                $hl->setFilter(['UF_ID' => $arResult['id']]);
                $hl->limit(1);
                $arHl = $hl->getList();
                
                $arHlFields = [
                    'UF_ID'          => $arResult['id'],
                    'UF_TYPE'        => 4,
                    'UF_DATE_UPDATE' => $arResult['updated'],
                    'UF_UPDATE'      => 0,
                    'UF_ID_BITRIX'   => $userID
                ];
                
                if (empty($arHl[0])) {
                    $hl->add($arHlFields);
                } else {
                    $hl->update($arHl[0]['ID'], $arFields);
                }
                
                $GLOBALS['updateMoisklad'] = true;
                $obUser = new \CUser();
                $obUser->Update($userID, ['XML_ID' => $arResult['id']]);
            }
            return $arResult['id'];
        } else {
            return '';
        }
    }
    
    public static function updateHL()
    {
        $arIsset = [];
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_TYPE' => 4]);
        $arHl = $hl->getList();
        foreach ($arHl as $itemHl) {
            $arIsset[$itemHl['UF_ID']] = $itemHl;
        }
        $arResult = self::getAll(1000);
        foreach ($arResult as $item) {
            if ( /*! empty($item['email']) &&*/
                $arIsset[$item['id']]['UF_DATE_UPDATE'] != $item['updated']
            ) { //проверка по емаил временно отключена
                $arFields = [
                    'UF_ID'          => $item['id'],
                    'UF_TYPE'        => 4,
                    'UF_DATE_UPDATE' => $item['updated'],
                    'UF_UPDATE'      => 1
                ];
                if (empty($arIsset[$item['id']])) {
                    $hl->add($arFields);
                } else {
                    $hl->update($arIsset[$item['id']]['ID'], $arFields);
                }
            }
        }
    }
    
    public static function resetUpdateHL()
    {
        $arIsset = [];
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_TYPE' => 4]);
        $arHl = $hl->getList();
        foreach ($arHl as $itemHl) {
            $hl->update($itemHl['ID'], ['UF_UPDATE' => 1]);
        }
    }
    
    public static function updateBitrix()
    {
        $arTransParams = array(
            "max_len"               => 100,
            "change_case"           => 'L', // 'L' - toLower, 'U' - toUpper, false - do not change
            "replace_space"         => '-',
            "replace_other"         => '-',
            "delete_repeat_replace" => true
        );
        
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_UPDATE' => 1, 'UF_TYPE' => 4]);
        $hl->limit(50);
        $arHl = $hl->getList();
        
        foreach ($arHl as $itemHl) :
            
            $arResult = self::getByID($itemHl['UF_ID']);
            
            if ( ! empty($arResult)) {
                $obUser = new \CUser();
                
                $arResult['contact_name'] = self::getName($itemHl['UF_ID']);
                if (empty($arResult['contact_name'])) {
                    $arResult['contact_name'] = $arResult['name'];
                }
                
                if ( ! empty($arResult['email'])) {
                    if (self::findUserEmail($arResult['email'], $itemHl['UF_ID_BITRIX'])) {
                        $arResult['email'] = '';
                    }
                }
                
                if (empty($arResult['email'])) {
                    $arResult['email'] = Random::getString(8, true) . '_' . \CUtil::translit($arResult['contact_name'],
                            "ru", $arTransParams)
                        . '@abcadmin.ru';
                }
                
                $arResult['phone'] = str_replace(['‒', '+'], ['', ''], $arResult['phone']);
                
                $arResult['phone'] = UserPhoneAuthTable::normalizePhoneNumber($arResult['phone']);
                
                if ( ! self::findUserPhone($arResult['phone'], $itemHl['UF_ID_BITRIX'])) {
                    $arResult['login_phone'] = $arResult['phone'];
                }
                
                $arUserGroup = [5, 3, 4];
                if (self::$arPriceGroup[$arResult['priceType']['externalCode']]) {
                    $arUserGroup[] = self::$arPriceGroup[$arResult['priceType']['externalCode']];
                }
                
                if (empty($arResult['ogrn']) && ! empty($arResult['ogrnip'])) {
                    $arResult['ogrn'] = $arResult['ogrnip'];
                }
                
                $arUserFields = [
                    'ACTIVE'             => $arResult['attributes_new']['Авторизация'] == 1 ? 'Y' : 'N',
                    'XML_ID'             => $arResult['id'],
                    'LOGIN'              => $arResult['email'],
                    'EMAIL'              => $arResult['email'],
                    'NAME'               => $arResult['name'],
                    'GROUP_ID'           => $arUserGroup,
                    'PHONE_NUMBER'       => $arResult['login_phone'],
                    'PERSONAL_PHONE'     => $arResult['phone'],
                    'PERSONAL_MOBILE'    => $arResult['phone'],
                    'PERSONAL_CITY'      => $arResult['legalAddressFull']['city'],
                    'PERSONAL_ZIP'       => $arResult['legalAddressFull']['postalCode'],
                    'PERSONAL_NOTES'     => $arResult['actualAddressFull']['comment'],
                    'PERSONAL_STREET'    => $arResult['legalAddressFull']['street'] . ', ' .
                        $arResult['legalAddressFull']['house'] . ', ' .
                        $arResult['legalAddressFull']['apartment'],
                    'UF_EGAIS'           => self::egais($arResult),
                    'UF_INN'             => $arResult['inn'],
                    'UF_NAME'            => $arResult['name'],
                    'UF_COMPANY'         => $arResult['legalTitle'],
                    'UF_URADDRESS'       => $arResult['legalAddress'],
                    'UF_KPP'             => $arResult['kpp'],
                    'UF_OKPO'            => $arResult['okpo'],
                    'UF_BIK'             => $arResult['accounts']['bic'],
                    'UF_OGRN'            => $arResult['ogrn'],
                    'UF_BANK'            => $arResult['accounts']['bankName'],
                    'UF_KS'              => $arResult['accounts']['correspondentAccount'],
                    'UF_RS'              => $arResult['accounts']['accountNumber'],
                    'UF_COMMENT'         => $arResult['description'],
                    'UF_PRICE'           => self::$arPrice[$arResult['priceType']['externalCode']],
                    'UF_MANAGER_ID'      => $arResult['employee']['id'],
                    'UF_MANAGER_PHONE'   => $arResult['employee']['phone'],
                    'UF_MANAGER_NAME'    => $arResult['employee']['shortFio'],
                    'UF_G_ADDRESS'       => $arResult['actualAddress'],
                    'UF_COMMENT_ADDRESS' => $arResult['actualAddressFull']['comment'],
                ];
                
                if (empty($arResult['phone'])) {
                    unset($arUserFields['PHONE_NUMBER']);
                }
                
                $GLOBALS['updateMoisklad'] = true;
                
                if ($itemHl['UF_ID_BITRIX'] == 1 or $itemHl['UF_ID_BITRIX'] == 2) {
                    $hl->update($itemHl['ID'], ['UF_UPDATE' => 0]);
                    continue;
                }
                
                if ($itemHl['UF_ID_BITRIX'] > 0) {
                    $itemHl['UF_UPDATE'] = 0;
                    $obUser->Update($itemHl['UF_ID_BITRIX'], $arUserFields);
                    if ($obUser->LAST_ERROR) {
                        ErrorHandler::errorWrite($itemHl['ID'],
                            $obUser->LAST_ERROR . ' ' . $arResult['email'] . ' ' . $arResult['phone']);
                    } else {
                        $itemHl['UF_ERROR'] = 0;
                        $hl->update($itemHl['ID'], $itemHl);
                        if ($arResult['attributes_new']['Авторизация'] == 1
                            && $arResult['attributes_new']['Оповестить пользователя'] == 1
                        ) {
                            \CUser::SendUserInfo($itemHl['UF_ID_BITRIX'], 's1',
                                "Аккаунт активирован и вы можете авторизоваться в личном кабинете.");
                            
                            if ( ! empty($arResult['attributes_new']['Пароль'])) {
                                $password = self::password($arResult);
                                $arUserFields = ["PASSWORD" => $password, "CONFIRM_PASSWORD" => $password];
                                $obUser->Update($itemHl['UF_ID_BITRIX'], $arUserFields);
                            }
                        }
                    }
                } else {
                    $password = self::password($arResult);
                    $arUserFields["PASSWORD"] = $password;
                    $arUserFields["CONFIRM_PASSWORD"] = $password;
                    $arUserFields["CONFIRM_CODE"] = Random::getString(8, true);
                    
                    $userAdd = $obUser->Add($arUserFields);
                    
                    $itemHl['UF_ID_BITRIX'] = $userAdd;
                    $itemHl['UF_UPDATE'] = 0;
                    $itemHl['UF_ERROR'] = 0;
                    
                    if ($obUser->LAST_ERROR) {
                        ErrorHandler::errorWrite($itemHl['ID'],
                            $obUser->LAST_ERROR . ' ' . $arResult['email'] . ' ' . $arResult['phone']);
                    }
                    
                    if ($userAdd) {
                        $hl->update($itemHl['ID'], $itemHl);
                    }
                }
            }
        
        endforeach;
    }
    
    public static function findUserEmail($email, $id = 0)
    {
        $ob = \CUser::GetList([], [], ['EMAIL' => $email, '!ID' => $id]);
        if ($arUser = $ob->Fetch()) {
            return true;
        } else {
            return false;
        }
    }
    
    public static function findUserPhone($phone, $id = 0)
    {
        $ob = \CUser::GetList([], ['PHONE_NUMBER' => $phone, '!ID' => $id]);
        if ($arUser = $ob->Fetch()) {
            return true;
        } else {
            return false;
        }
    }
    
    public static function getName($id)
    {
        $arContacts = Request::request('counterparty/' . $id . '/contactpersons/');
        
        return (string)$arContacts['rows'][0]['name'];
    }
    
    public static function egais($arResult)
    {
        $egais = '';
        if ( ! empty($arResult['attributes_new']['ЕГАИС'])) {
            $egais = $arResult['attributes_new']['ЕГАИС'];
        } else {
            $substring = "ЕГАИС";
            $pos = strpos($arResult['actualAddressFull']['comment'], $substring);
            $pos2 = strpos($arResult['description'], $substring);
            if ($pos !== false) {
                $egais = trim(substr_replace($arResult['actualAddressFull']['comment'], '', $pos,
                    strlen($substring) + 1));
            } elseif (2 !== false) {
                $egais = trim(substr_replace($arResult['description'], '', $pos,
                    strlen($substring) + 1));
            }
        }
        
        return $egais;
    }
    
    public static function password($arResult)
    {
        $password = Random::getString(8, true);
        if ( ! empty($arResult['attributes_new']['Пароль'])) {
            $password = $arResult['attributes_new']['Пароль'];
        }
        return $password;
    }
    
    public static function reindex()
    {
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_TYPE' => 4]);
        $arHL = $hl->getList();
        $arIsset = [];
        foreach ($arHL as $item) {
            $hl->update($item['ID'], ['UF_UPDATE' => 1]);
        }
    }
}