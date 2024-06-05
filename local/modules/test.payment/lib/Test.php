<?php

namespace Test\Payment;

use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Query\Expression;
class Test
{
    //1. На выборку всех ненулевых оплаченных счетов верхнего уровня за последнюю неделю
    public static function getPaidInvoicesLastWeek()
    {
        $lastWeek = new Type\DateTime();
        $lastWeek->add('-1 week');
        
        $result = PaymentsTable::getList([
            'filter' => [
                '>=CREATED' => $lastWeek,
                'STATUS' => 1,
                '>INVOICE_SUM' => 0,
                '!PARENT_ID' => null,
            ]
        ]);
        
        return $result->fetchAll();
    }
    
    //2. На выборку всех родительских счетов, имеющих не более трёх дочерних счетов (без глубины)
    public static function getParentInvoicesWithMaxThreeChildren()
    {
        $subQuery = new Query(PaymentsTable::getEntity());
        $subQuery->addSelect('PARENT_ID')
            ->addSelect(new ExpressionField('CHILD_COUNT', 'COUNT(*)'))
            ->whereNotNull('PARENT_ID')
            ->group('PARENT_ID');
        
        $subQuery->addFilter('<=CHILD_COUNT', 3);
        
        $query = self::query()
            ->registerRuntimeField('CHILD_COUNT',
                new ExpressionField(
                    'CHILD_COUNT',
                    '(SELECT COUNT(*) FROM bx_api_payments WHERE PARENT_ID = %s)',
                    'ID'
                )
            )
            ->setSelect(['*'])
            ->setFilter([
                'CHILD_COUNT' => [1, 2, 3],
                'PARENT_ID' => null,
            ]);
        
        return $query->exec()->fetchAll();
    }
    
    //3. На выборку всех счетов нижнего уровня (т.е. не имеющих дочерних)
    public static function getLeafInvoices()
    {
        $query = self::query()
            ->registerRuntimeField(
                'CHILD_COUNT',
                new ExpressionField(
                    'CHILD_COUNT',
                    '(SELECT COUNT(*) FROM bx_api_payments WHERE PARENT_ID = %s)',
                    'ID'
                )
            )
            ->setSelect(['*'])
            ->setFilter(['CHILD_COUNT' => 0]);
        
        return $query->exec()->fetchAll();
    }
    
    //1. SELECT id FROM bx_api_payments WHERE transaction_id = ?;
    public static function test1($id){
        $result = PaymentsTable::getList([
            'filter' => [
                'transaction_id' => $id,
            ]
        ]);
        
        return $result->fetchAll();
    }
    //2. SELECT * FROM bx_api_payments WHERE invoice_id = ?
    public static function test2($id){
        $result = PaymentsTable::getList([
            'filter' => [
                'invoice_id' => $id,
            ]
        ]);
        
        return $result->fetchAll();
    }
    //3. SELECT payment_data FROM bx_api_payments WHERE contact_id = ?
    public static function test3($id){
        $result = PaymentsTable::getList([
            'filter' => [
                'contact_id' => $id,
            ],
            'select' => ['payment_data']
        ]);
        
        return $result->fetchAll();
    }
    //4. UPDATE bx_api_payments SET status = ?, transaction_id = ?, payment_data = ?, updated = now() WHERE invoice_id = ? OR parent_id = ?
    public static function test4($status, $transaction_id, $payment_data, $invoice_id, $parent_id = 0){
        $filter = [
            'LOGIC' => 'OR',
            '=INVOICE_ID' => $invoice_id,
            '=PARENT_ID' => $parent_id,
        ];

        $result = PaymentsTable::getList([
            'filter' => $filter,
        ]);

        while ($row = $result->fetch()) {
            $resultUpdate = PaymentsTable::update($row['ID'],[
                'STATUS' => $status,
                'TRANSACTION_ID' => $transaction_id,
                'PAYMENT_DATA' => $payment_data,
                'UPDATED' => new DateTime(),
            ]);
        }
    }
}