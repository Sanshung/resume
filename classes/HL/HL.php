<?php

namespace Classes\HL;

use Bitrix\Main\Web\Json;
use CUserFieldEnum;
use CUserTypeEntity;
use Bitrix\Highloadblock\HighloadBlockTable as HLBT;

class HL
{
    protected $filter = [];
    protected $select = [];
    protected $order = [];
    protected $offset = false;
    protected $limit = false;
    protected $hl;
    protected $hlId;
    protected $convertEnumIdToValue = false;
    protected $enumFields = [];
    protected $skipEmptyFields = false;
    
    
    public function __construct($hl_id)
    {
        $this->hlId = $hl_id;
        $this->hl = self::getEntityDataClass($hl_id);
    }
    
    public static function getEntityDataClass($HlBlockId)
    {
        if (empty($HlBlockId) || $HlBlockId < 1) return false;
        
        \Bitrix\Main\Loader::includeModule('highloadblock');
        
        $HLBlock = HLBT::getById($HlBlockId)->fetch();
        $entity = HLBT::compileEntity($HLBlock);
        return $entity->getDataClass();
    }
    
    /**
     * @param array $filter
     * @return HL
     */
    public function setFilter(array $filter): HL
    {
        $this->filter = $filter;
        return $this;
    }
    
    /**
     * @param array $filter
     * @return HL
     */
    public function addFilter(array $filter): HL
    {
        return $this->setFilter(array_merge($filter, $this->filter));
    }
    
    /**
     * @param array $select
     * @return HL
     */
    public function setSelect(array $select): HL
    {
        $this->select = $select;
        return $this;
    }
    
    public function limit($limit): HL
    {
        $this->limit = $limit;
        return $this;
    }
    
    public function offset($offset): HL
    {
        $this->offset = $offset;
        return $this;
    }
    
    public function orderBy(array $order): HL
    {
        $this->order = $order;
        return $this;
    }
    
    public function reset(){
        $this->filter = [];
        $this->select = [];
        $this->limit = false;
        $this->offset = false;
        $this->convertEnumIdToValue = false;
    }
    
    public function add($arFields){
        
        return $this->hl::add($arFields);
    }
    
    public function update($primary_id, $arFields){
        return $this->hl::update($primary_id, $arFields);
    }
    public function getList(&$nav=false){
        
        if (!$this->hl) return false;
        
        $count_total= false;
        if ($nav)
            $count_total =true;
        $select = $this->select ?: ['*'];
        $filter = $this->filter;
        $order = $this->order ?: ['ID' => 'ASC'];
        $limit = $this->limit ?: false;
        $offset = $this->offset ?: false;
        $elements = $this->hl::getList(
            [
                'filter' => $filter,
                'select' => $select,
                'order' => $order,
                'limit' => $limit,
                'offset' => $offset,
                "count_total" => $count_total,
            ]
        );
        $items = [];
        if ($nav) {
            $nav->setRecordCount($elements->getCount());
        }
        
        while ($element = $elements->Fetch()) {
            
            if($this->convertEnumIdToValue && $this->enumFields){
                foreach (array_keys($element) as $fieldName){
                    if(isset($this->enumFields[$fieldName])){
                        $element['_ORIGINAL_ENUM'][$fieldName] = $element[$fieldName];
                        if(is_array($element[$fieldName])){
                            foreach ($element[$fieldName] as $key => $val){
                                $element[$fieldName][$key] = $this->getEnumValue($fieldName, $val);
                            }
                        }
                        else{
                            $element[$fieldName] = $this->getEnumValue($fieldName, $element[$fieldName]);
                        }
                    }
                }
                $items[] = $element;
            }else{
                $items[] = $element;
            }
            
            if($this->skipEmptyFields){
                foreach ($items as $key => $item){
                    foreach ($item as $field => $value){
                        if(empty($value)) unset($items[$key][$field]);
                    }
                }
            }
            
        }
        
        $this->reset();
        
        if (empty($items)) return false;
        
        return $items;
    }
    
    public function getRow(){
        if (!$this->hl) return false;
        
        $select = $this->select ?: ['*'];
        $filter = $this->filter;
        $order = $this->order ?: ['ID' => 'ASC'];
        $item = $this->hl::getRow(
            [
                'filter' => $filter,
                'select' => $select,
                'order' => $order,
            ]
        );
        $this->reset();
        return $item;
    }
    
    public function getBy(array $filter){
        $this->setFilter($filter);
        return $this->getRow();
    }
    
    public function getListJson() : string{
        return Json::encode($this->getList());
    }
    
    public function convertEnum() : HL{
        $this->convertEnumIdToValue = true;
        
        $this->enumFields = $this->getEnumFieldsList();
        
        return $this;
    }
    
    public function skipEmptyFields(){
        $this->skipEmptyFields = true;
        
        return $this;
    }
    
    public function getEnumFieldsList(){
        $rsData = CUserTypeEntity::GetList( array($by=>$order), array('USER_TYPE_ID' => 'enumeration') );
        $fields = [];
        while($arRes = $rsData->Fetch())
        {
            $fields[$arRes['FIELD_NAME']] = $arRes;
        }
        if(empty($fields)) return false;
        
        return $fields;
    }
    
    public function getEnumValue($fieldName, $fieldValue){
        $fields = CUserFieldEnum::GetList([], ['USER_FIELD_NAME' => $fieldName, 'ID' => $fieldValue]);
        while($field = $fields->GetNext()){
            return $field['VALUE'];
        }
    }
    
    public function delete($primary_id)
    {
        return $this->hl::delete($primary_id);
    }
    
    
}