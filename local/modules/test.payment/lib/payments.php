<?php

namespace Test\Payment;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\FloatField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\Validators\LengthValidator;
use Bitrix\Main\Type\DateTime;
use \Bitrix\Main\Entity;

/**
 * Class PaymentsTable
 *
 * Fields:
 * <ul>
 * <li> id int mandatory
 * <li> deal_id string(256) mandatory
 * <li> invoice_id string(256) mandatory
 * <li> contact_id int mandatory
 * <li> transaction_id string(100) mandatory
 * <li> status int optional default 0
 * <li> invoice_sum double optional default 0.00
 * <li> created datetime optional default current datetime
 * <li> updated datetime optional default '0000-00-00 00:00:00'
 * <li> parent_id int optional
 * <li> payment_data unknown optional
 * </ul>
 *
 * @package Test\Payment
 **/
class PaymentsTable extends DataManager
{
    /**
     * Returns DB table name for entity.
     *
     * @return string
     */
    public static function getTableName()
    {
        return 'bx_api_payments';
    }
    
    /**
     * Returns entity map definition.
     *
     * @return array
     */
    public static function getMap()
    {
        return [
            new IntegerField(
                'id',
                [
                    'primary'      => true,
                    'autocomplete' => true,
                    'title'        => Loc::getMessage('PAYMENTS_ENTITY_ID_FIELD'),
                ]
            ),
            new StringField(
                'deal_id',
                [
                    'required'   => true,
                    'validation' => function () {
                        return [
                            new LengthValidator(null, 256),
                        ];
                    },
                    'title'      => Loc::getMessage('PAYMENTS_ENTITY_DEAL_ID_FIELD'),
                ]
            ),
            new StringField(
                'invoice_id',
                [
                    'primary'    => true,
                    'validation' => function () {
                        return [
                            new LengthValidator(null, 256),
                        ];
                    },
                    'title'      => Loc::getMessage('PAYMENTS_ENTITY_INVOICE_ID_FIELD'),
                ]
            ),
            new IntegerField(
                'contact_id',
                [
                    'required' => true,
                    'title'    => Loc::getMessage('PAYMENTS_ENTITY_CONTACT_ID_FIELD'),
                ]
            ),
            new StringField(
                'transaction_id',
                [
                    'required'   => true,
                    'validation' => function () {
                        return [
                            new LengthValidator(null, 100),
                        ];
                    },
                    'title'      => Loc::getMessage('PAYMENTS_ENTITY_TRANSACTION_ID_FIELD'),
                ]
            ),
            // -- Enum[0-4], CREATED = 0; PAYED = 1; DENIED = 2; IS_BEING_PROCESSED = 3; DELETED = 4;
            new IntegerField(
                'status',
                [
                    'default' => 0,
                    'title'   => Loc::getMessage('PAYMENTS_ENTITY_STATUS_FIELD'),
                ]
            ),
            new FloatField(
                'invoice_sum',
                [
                    'default' => 0.00,
                    'title'   => Loc::getMessage('PAYMENTS_ENTITY_INVOICE_SUM_FIELD'),
                ]
            ),
            new DatetimeField(
                'created',
                [
                    'default' => function () {
                        return new DateTime();
                    },
                    'title'   => Loc::getMessage('PAYMENTS_ENTITY_CREATED_FIELD'),
                ]
            ),
            new DatetimeField(
                'updated',
                [
                    'default' => '0000-00-00 00:00:00',
                    'title'   => Loc::getMessage('PAYMENTS_ENTITY_UPDATED_FIELD'),
                ]
            ),
            new IntegerField(
                'parent_id',
                [
                    'title' => Loc::getMessage('PAYMENTS_ENTITY_PARENT_ID_FIELD'),
                ]
            ),
            new DatetimeField(
                'payment_data',
                [
                    'title' => Loc::getMessage('PAYMENTS_ENTITY_PAYMENT_DATA_FIELD'),
                ]
            ),
        ];
    }
    
    //- добавления новой записи (deal_id, invoice_id, invoice_sum, contact_id), при конфликте по invoice_id обновлять invoice_sum и поле updated
    public static function onBeforeUpdate(Entity\Event $event)
    {
        $result = new Entity\EventResult;
        $data = $event->getParameter("fields");
        
        if (isset($data['invoice_id'])) {
            
            $existingInvoice = self::getList([
                'filter' => ['INVOICE_ID' => $data['invoice_id']],
                'limit' => 1,
            ])->fetch();
            
            if ($existingInvoice) {
                // Если запись существует, обновляем invoice_sum и updated
                self::update($existingInvoice['ID'], [
                    'INVOICE_SUM' => $data['invoice_sum'] + $existingInvoice['invoice_sum'],
                    'UPDATED' => new Type\DateTime(),
                ]);
            }
        }
        
        return $result;
    }
}