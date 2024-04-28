<?php

namespace Classes\Moisklad;

use Bitrix\Main\Diag\Debug;
use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Classes\Moisklad\HL;
use \Bitrix\Main\Config\Option;

class GetDemand
{
    const SITE_ID = 's1';
    
    public static function getByID($id = '')
    {
        $arResult = [];
        $arResult = Request::request('demand/' . $id);
        $arResult['order'] = Request::request($arResult['customerOrder']['meta']['href']);
        
        return $arResult;
    }
    
    public static function getAll($limit = 100)
    {
        $arResult = [];
        
        $offset = Option::get('main', 'pk_exchange_demand', 0, self::SITE_ID);
        
        $arGet = [
            'limit=' . $limit,
            'offset=' . $offset,
            //'filter=id=45f30491-11af-11ee-0a80-01b40017de9a',
        ];
        
        $arResult = Request::request('demand', $arGet);
        
        if (empty($arResult['rows'])) {
            $offset = $limit = 0;
        }
        Option::set('main', 'pk_exchange_demand', ($offset + $limit), self::SITE_ID);
        
        return $arResult['rows'];
    }
    
    
    public static function updateHL()
    {
        $arIsset = [];
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_TYPE' => 6]);
        $arHl = $hl->getList();
        foreach ($arHl as $itemHl) {
            $arIsset[$itemHl['UF_ID']] = $itemHl;
        }
        
        $arResult = self::getAll(1000);
        
        foreach ($arResult as $item) {
            if ($arIsset[$item['id']]['UF_DATE_UPDATE'] != $item['updated']) {
                $arFields = [
                    'UF_ID'          => $item['id'],
                    'UF_TYPE'        => 6,
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
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_UPDATE' => 1, 'UF_TYPE' => 6]);
        $hl->limit(50);
        $arHl = $hl->getList();
        foreach ($arHl as $itemHl) :
            self::updateDemandBitrix($itemHl);
        endforeach;
    }
    
    public static function updateDemandBitrix($itemHl)
    {
        $arResult = self::getByID($itemHl['UF_ID']);
        
        $arResult['order_id'] = str_replace(
            'https://api.moysklad.ru/api/remap/1.2/entity/customerorder/',
            '',
            $arResult['customerOrder']['meta']['href']
        );
        
        if ( ! empty($arResult['order']['state'])) {
            $arResult['bitrix_status'] = GetOrders::getBitrixStatus($arResult['order']['state']['meta']['href']);
        }
        
        $arResult['bitrix_order_id'] = self::getBitrixOrderID($arResult['order_id']);
        
        if ($arResult['bitrix_order_id'] > 0) {
            Loader::includeModule("sale");
            $order = \Bitrix\Sale\Order::load($arResult['bitrix_order_id']);
            
            if ($order) {
                $basket = $order->getBasket();
                if (empty($basket)) {
                    $basket = Basket::create(SITE_ID);
                }
            } else {
                ErrorHandler::errorWrite($itemHl['ID'], "Заказ не найден");
                return "Заказ не найден";
            }
            
            $newBasketData = GetOrders::getProducts($arResult['positions']['meta']['href']);
            
            $isUpdate = false;
            // Сравниваем текущую корзину с новыми данными
            foreach ($newBasketData as $newItem) {
                
                $basketItem = self::getExistsItem($basket, $newItem['ID']);
                
                if ($basketItem) {
                    if ($basketItem->getQuantity() != $newItem['QUANTITY']) {
                        $basketItem->setField('QUANTITY', $newItem['QUANTITY']);
                        $isUpdate = true;
                    }
                    if ($basketItem->getPrice() != $newItem['PRICE']) {
                        $basketItem->setField('PRICE', $newItem['PRICE']);
                        $basketItem->setField('CUSTOM_PRICE', 'Y');
                        $isUpdate = true;
                    }
                } else {
                    $item = $basket->createItem('catalog', $newItem['ID']);
                    $item->setFields([
                        'NAME'         => $newItem['NAME'],
                        'QUANTITY'     => $newItem['QUANTITY'],
                        'PRICE'        => $newItem['PRICE'],
                        'CURRENCY'     => 'RUB',
                        'LID'          => self::SITE_ID,
                        'CUSTOM_PRICE' => 'Y',
                        'WEIGHT' => 0,
                    ]);
                    $isUpdate = true;
                }
            }
            
            // Удаляем лишние товары из корзины
            foreach ($basket as $basketItem) {
                $productId = $basketItem->getProductId();
                $found = false;
                
                foreach ($newBasketData as $newItem) {
                    if ($newItem['ID'] == $productId) {
                        $found = true;
                        break;
                    }
                }
                
                if ( ! $found) {
                    $basketItem->delete();
                    $isUpdate = true;
                }
            }
            $basketItems = $basket->getBasketItems();
            
            if ($arResult['bitrix_status'] != $order->getField('STATUS_ID')) {
                $order->setField('STATUS_ID', $arResult['bitrix_status']);
                $isUpdate = true;
            }
            
            //оплаты
            $arResult['order']['payedSum'] = $arResult['order']['payedSum'] /100;
            if($arResult['order']['payedSum'] > 0) {
                $paymentCollection = $order->getPaymentCollection();
                $onePayment = $paymentCollection[0];
                if($paymentCollection->getPaidSum() != $arResult['order']['payedSum'])
                {
                    $onePayment->setField('SUM', $arResult['order']['payedSum']);
                    $onePayment->setPaid("Y");
                    $isUpdate = true;
                }
            }
            
            if ($isUpdate && !empty($order)) {
                try {
                    $r = $order->save();
                    $order->doFinalAction(true);
                    if ( ! $r->isSuccess()) {
                        ErrorHandler::errorWrite($itemHl['ID'], $r->getErrorMessages());
                    } else {
                        self::setHLOrder($itemHl['ID'], $arResult['bitrix_order_id'], $itemHl['UF_ID']);
                    }
                } catch (\Bitrix\Main\SystemException $e) {
                    ErrorHandler::errorWrite($itemHl['ID'], $e->getMessage().' order id='.$arResult['bitrix_order_id']);
                }
            }
            else{
                self::setHLOrder($itemHl['ID'], $arResult['bitrix_order_id'], $itemHl['UF_ID']);
            }
        }
        else{
            ErrorHandler::errorWrite($itemHl['ID'], 'Заказа не найден');
        }
        
    }
    
    public static function getExistsItem($basket, $id)
    {
        $basketItems = $basket->getBasketItems();
        foreach ($basket as $basketItem) {
            if ($basketItem->getField('PRODUCT_ID') == $id) {
                return $basketItem;
            }
        }
        return [];
    }
    
    public static function getBitrixOrderID($id)
    {
        $hl = new HL\HL(8);
        $hl->setFilter(['UF_ID' => $id, 'UF_TYPE' => 5]);
        $arHl = $hl->getList();
        return $arHl[0]['UF_ID_BITRIX'];
    }
    
    
    public static function setHLOrder($hlID = 0, $orderID, $skaldUID)
    {
        $arFields = [
            'UF_ID_BITRIX' => $orderID,
            'UF_UPDATE'    => 0,
            'UF_ID'        => $skaldUID,
            'UF_TYPE'      => 6
        ];
        
        $hl = new HL\HL(8);
        
        if ($hlID > 0) {
            $hl->update($hlID, $arFields);
        } else {
            $hl->add($arFields);
        }
    }
}