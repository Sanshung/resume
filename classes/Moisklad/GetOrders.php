<?php

namespace Classes\Moisklad;

use Bitrix\Iblock\ElementTable;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Classes\Moisklad\HL;

class GetOrders
{
    
    const SITE_ID = 's1';
    static $arStatus
        = [
            'Не готов'                           => 'NG',
            'Пока в брони'                       => 'PB',
            'Готов к сборке'                     => 'GS',
            'Частично отгружен'                  => 'CO',
            'Отгружен'                           => 'F',
            'Доставлен, принят'                  => 'DP',
            'Частично принят'                    => 'CP',
            'Отказано в приемке'                 => 'OP',
            'Необходима дополнительная доставка' => 'ND',
        ];
    
    public static function getStatus($id = '')
    {
        $arResult = [];
        
        $arResult = Request::request('customerorder/' . $id);
        
        if ( ! empty($arResult['state'])) {
            $arResult = self::getBitrixStatus($arResult['state']['meta']['href']);
        }
        
        return $arResult;
    }
    
    public static function getBitrixStatus($state)
    {
        $arResult = Request::request($state);
        if ( ! empty($arResult)) {
            return self::$arStatus[$arResult['name']];
        } else {
            return 'N';
        }
    }
    
    public static function getAll($limit = 100)
    {
        $arResult = [];
        
        $offset = Option::get('main', 'pk_exchange_orders', 0, self::SITE_ID);
        
        $arGet = [
            'limit=' . $limit,
            'offset=' . $offset,
            //'filter=id=45f30491-11af-11ee-0a80-01b40017de9a',
        ];
        
        $arResult = Request::request('customerorder', $arGet);
        
        if (empty($arResult['rows'])) {
            $offset = $limit = 0;
        }
        Option::set('main', 'pk_exchange_orders', ($offset + $limit), self::SITE_ID);
        
        return $arResult['rows'];
    }
    
    public static function updateHL()
    {
        $arIsset = [];
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_TYPE' => 5]);
        $arHl = $hl->getList();
        foreach ($arHl as $itemHl) {
            $arIsset[$itemHl['UF_ID']] = $itemHl;
        }
        
        $arResult = self::getAll(1000);
        
        foreach ($arResult as $item) {
            if ($arIsset[$item['id']]['UF_DATE_UPDATE'] != $item['updated']) {
                $arFields = [
                    'UF_ID'          => $item['id'],
                    'UF_TYPE'        => 5,
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
    
    public static function updateBitrix()
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');
        
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_UPDATE' => 1, 'UF_TYPE' => 5]); //, 'UF_ID' => '006004c4-2a60-11ee-0a80-0b1b00018873'
        $hl->limit(50);
        $arHl = $hl->getList();
        foreach ($arHl as $itemHl) :
            self::createOrderBitrix($itemHl);
        endforeach;
    }
    
    public static function findOrderBitrix($xmlID = '')
    {
        $ob = \Bitrix\Sale\OrderTable::getList(['filter' => ['XML_ID' => $xmlID]]);
        if ($ar = $ob->fetch()) {
            return $ar['ID'];
        } else {
            return false;
        }
    }
    
    public static function findHlOrderBitrix($id)
    {
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_ID_BITRIX' => $id, 'UF_TYPE' => 5]);
        $arHl = $hl->getList();
        return $arHl[0]['UF_ID_BITRIX'];
    }
    
    public static function createOrderBitrix($itemHl)
    {
        if ($itemHl['UF_ID_BITRIX'] > 0) {
            self::setHLOrder($itemHl['ID'], $itemHl['UF_ID_BITRIX'], $itemHl['UF_ID']);
            return false;
        }
        $orderID = self::findOrderBitrix($itemHl['UF_ID']);
        
        if ($orderID > 0) {
            self::setHLOrder($itemHl['ID'], $orderID, $itemHl['UF_ID']);
            return false;
        }
        
        $arResult = [];
        
        $arResult = Request::request('customerorder/' . $itemHl['UF_ID']);
        
        $arResult['bitrix_status'] = self::getStatus($itemHl['UF_ID']);
        
        $arResult['counterparty_id'] = str_replace(
            'https://api.moysklad.ru/api/remap/1.2/entity/counterparty/',
            '',
            $arResult['agent']['meta']['href']
        );
        
        $arResult['USER_ID'] = self::issetCounterparty($arResult['counterparty_id']);
        
        if ( ! $arResult['USER_ID']) {
            echo 'no Counterparty';
            ErrorHandler::errorWrite($itemHl['ID'], 'no find Counterparty ' . $arResult['counterparty_id']);
            return false;
        }
        
        
        $products = self::getProducts($arResult['positions']['meta']['href']);
        
        $basket = Basket::create(SITE_ID);
        
        foreach ($products as $product) {
            if ( ! empty($product["ID"])) {
                $item = $basket->createItem("catalog", $product["ID"]);
            }
            unset($product["ID"]);
            $item->setFields($product);
        }
        
        $order = \Bitrix\Sale\Order::create(SITE_ID, $arResult['USER_ID']);
        $order->setPersonTypeId(1);
        $order->setBasket($basket);
        
        $shipmentCollection = $order->getShipmentCollection();
        $shipment = $shipmentCollection->createItem(
            \Bitrix\Sale\Delivery\Services\Manager::getObjectById(2)
        );
        
        $shipmentItemCollection = $shipment->getShipmentItemCollection();
        
        /** @var Sale\BasketItem $basketItem */
        
        foreach ($basket as $basketItem) {
            $item = $shipmentItemCollection->createItem($basketItem);
            $item->setQuantity($basketItem->getQuantity());
        }
        
        $paymentCollection = $order->getPaymentCollection();
        $payment = $paymentCollection->createItem(
            \Bitrix\Sale\PaySystem\Manager::getObjectById(2)
        );
        $payment->setField("SUM", $order->getPrice());
        $payment->setField("CURRENCY", $order->getCurrency());
        
        // Устанавливаем свойства
        $propertyCollection = $order->getPropertyCollection();
        $addressProp = $propertyCollection->getAddress();
        $addressProp->setValue($arResult['shipmentAddress']);
        
        if ($arResult['shipmentAddressFull']['comment']) {
            $order->setField('USER_DESCRIPTION',
                $arResult['shipmentAddressFull']['comment']); // Устанавливаем поля комментария покупателя
        }
        
        $order->setField('STATUS_ID', $arResult['bitrix_status']);
        $order->setField("IS_SYNC_B24", 1);
        $order->setField("VERSION_1C", 1);
        
        $result = $order->save();
        if ($result->isSuccess()) {
            var_dump([$itemHl['ID'], $result->getId(), $itemHl['UF_ID']]);
            self::setHLOrder($itemHl['ID'], $result->getId(), $itemHl['UF_ID']);
        } else {
            var_dump('error');
            ErrorHandler::errorWrite($itemHl['ID'], $result->getErrorMessages());
        }
    }
    
    public static function issetCounterparty($id)
    {
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_TYPE' => 4, 'UF_ID' => $id]);
        $hl->limit(1);
        $arHl = $hl->getList();
        
        return $arHl[0]['UF_ID_BITRIX'];
    }
    
    public static function getProducts($positionsUrl)
    {
        $arResult = Request::request($positionsUrl);
        $products = [];
        
        foreach ($arResult['rows'] as $item) {
            $arProduct = Request::request($item['meta']['href']);
            $productXmlID = str_replace(
                'https://api.moysklad.ru/api/remap/1.2/entity/product/',
                '',
                $arProduct['assortment']['meta']['href']
            );
            
            $arProductBitrix = self::productBitrixFind($productXmlID);
            
            if (empty($arProductBitrix['NAME'])) {
                $arProduct = Request::request($arProduct['assortment']['meta']['href']);
                $arProductBitrix['NAME'] = $arProduct['name'];
                $arProductBitrix['ID'] = mb_substr(preg_replace('/[^0-9]/', '', $productXmlID), 0, 10);
            }
            
            $products[] = [
                'ID'           => $arProductBitrix['ID'],
                'NAME'         => $arProductBitrix['NAME'],
                'PRICE'        => $item['price'] / 100,
                'CURRENCY'     => 'RUB',
                'CUSTOM_PRICE' => 'Y',
                'QUANTITY'     => $item['quantity']
            ];
        }
        return $products;
    }
    
    public static function productBitrixFind($xml_id)
    {
        Loader::includeModule('iblock');
        
        $ob = ElementTable::getList([
            'filter' => ['XML_ID' => $xml_id],
            'select' => ['ID', 'NAME']
        ]);
        
        if ($ar = $ob->fetch()) {
            return $ar;
        }
        
    }
    
    public static function setHLOrder($hlID = 0, $orderID, $skaldUID)
    {
        $arFields = [
            'UF_ID_BITRIX' => $orderID,
            'UF_UPDATE'    => 0,
            'UF_ID'        => $skaldUID,
            'UF_TYPE'      => 5
        ];
        
        $hl = new HL\HL(8);
        
        if ($hlID > 0) {
            $hl->update($hlID, $arFields);
        } else {
            $hl->add($arFields);
        }
    }
    
    public static function deleteCopyHLOrder()
    {
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_TYPE' => 5, '>UF_ID_BITRIX' => 0]);
        $hl->orderBy(['UF_ID_BITRIX' => 'asc']);
        //$hl->limit(10000);
        $arHL = $hl->getList();
        $arIsset = [];
        foreach ($arHL as $item) {
            if(empty($item['UF_ID_BITRIX']))
                continue;
            if ( ! empty($arIsset[$item['UF_ID_BITRIX']])) {
                self::deleteOrder($item['UF_ID_BITRIX']);
            }
            $arIsset[$item['UF_ID_BITRIX']] = $item['UF_ID_BITRIX'];
        }
    }
    
    public static function deleteCopyOrder()
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');
        
        if ($_SESSION['ORDER_END'] == false) {
            $_SESSION['ORDER_END'] = 71;
        }
        
        $ob = \Bitrix\Sale\OrderTable::getList(['filter' => ['>ID' => $_SESSION['ORDER_END']], 'limit' => 1000]);
        while ($ar = $ob->fetch()) {
            
            $hl = new HL\HL(8);
            $hl->setFilter([
                'UF_TYPE'      => 5,
                'UF_ID_BITRIX' => $ar['ID']
            ]); //, 'UF_ID' => '006004c4-2a60-11ee-0a80-0b1b00018873'
            $hl->limit(5);
            $arHl = $hl->getList();
            if (empty($arHl)) {
                self::deleteOrder($ar['ID']);
            }
            $_SESSION['ORDER_END'] = $ar['ID'];
        }
    }
    
    private static function deleteOrder($id)
    {
        Loader::includeModule('sale');
        Loader::includeModule('catalog');
        $order = \Bitrix\Sale\Order::load($id);
        
        //отменяем оплаты если есть
        $paymentCollection = $order->getPaymentCollection();
        if ($paymentCollection->isPaid()) {
            foreach ($paymentCollection as $payment) {
                $payment->setReturn("Y");
            }
        }
        
        //отменяем отгрузки если есть
        $shipmentCollection = $order->getShipmentCollection();
        if ($shipmentCollection->isShipped()) {
            $shipment = $shipmentCollection->getItemById($shipmentCollection[0]->getField("ID"));
            $res = $shipment->setField("DEDUCTED", "N");
            
        }
        
        $order->save();
        
        $res_delete = \Bitrix\Sale\Order::delete($id);
        
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_TYPE' => 5, 'UF_ID_BITRIX'=> $id]);
        $hl->limit(10);
        $arHL = $hl->getList();
        $arIsset = [];
        foreach ($arHL as $item) {
            $hl->delete($item['ID']);
        }
        
        $hl->setFilter(['UF_TYPE' => 6, 'UF_ID_BITRIX'=> $id]);
        $hl->limit(10);
        $arHL = $hl->getList();
        $arIsset = [];
        foreach ($arHL as $item) {
            $hl->delete($item['ID']);
        }
    }
    
    public static function reindex()
    {
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_TYPE' => 5]);
        $arHL = $hl->getList();
        $arIsset = [];
        foreach ($arHL as $item) {
            $hl->update($item['ID'], ['UF_UPDATE' => 1]);
        }
    }
}