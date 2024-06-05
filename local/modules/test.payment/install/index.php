<?

use \Bitrix\Main;
use \Bitrix\Main\ModuleManager;
use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\Loader;
use \Bitrix\Main\Entity\Base;
use \Bitrix\Main\Application;
use \Bitrix\Main\Config\Option;

Loc::loadMessages(__FILE__);

class test_payment extends CModule
{
    public function __construct()
    {
        $arModuleVersion = array();
        include(__DIR__ . "/version.php");
        $this->MODULE_ID = 'test.payment';
        $this->MODULE_VERSION = $arModuleVersion["VERSION"];
        $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        $this->MODULE_NAME = Loc::getMessage("PAYMENT_MODULE_NAME");
        $this->MODULE_DESCRIPTION = Loc::getMessage("PAYMENT_MODULE_DESCRIPTION");
        
        $this->PARTNER_NAME = Loc::getMessage("PAYMENT_MODULE_PARTNER_NAME");
        $this->PARTNER_URI = '';
    }
    
    public function isVersionD7()
    {
        return CheckVersion(\Bitrix\Main\ModuleManager::getVersion('main'), '14.00.00');
    }
    public function GetPath($notDocumentRoot = false)
    {
        if ($notDocumentRoot) {
            return str_ireplace(Application::getDocumentRoot(), '', dirname(__DIR__));
        } else {
            return dirname(__DIR__);
        }
    }
    
    
    public function DoInstall()
    {
        global $APPLICATION;
        if ($this->isVersionD7()) {
            ModuleManager::registerModule($this->MODULE_ID);
           $this->InstallDB();
            $this->InstallEvents();
            $this->InstallFiles();
        } else {
            $APPLICATION->ThrowException('ERROR_VERSION');
        }
        
        $APPLICATION->IncludeAdminFile('Установка модуля', $this->GetPath() . "/install/step.php");
    }
    
    public function DoUninstall()
    {
        global $APPLICATION;
        
        $this->UnInstallDB();
        $this->UnInstallEvents();
        $this->UnInstallFiles();
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
    
    function InstallDB()
    {
        Loader::includeModule($this->MODULE_ID);
        
        if(!Application::getConnection(\Test\Payment\PaymentsTable::getConnectionName())->isTableExists(
            Base::getInstance('\Test\Payment\PaymentsTable')->getDBTableName()
        )
        )
        {
            Base::getInstance('\Test\Payment\PaymentsTable')->createDbTable();
        }
    }
    
    function UnInstallDB()
    {
        Loader::includeModule($this->MODULE_ID);
        
        Application::getConnection(\Test\Payment\PaymentsTable::getConnectionName())->
        queryExecute('drop table if exists '.Base::getInstance('\Test\Payment\PaymentsTable')->getDBTableName());
        
        Option::delete($this->MODULE_ID);
    }
    
    
    public function InstallEvents()
    {
        // Add event handlers if necessary
    }
    
    public function UnInstallEvents()
    {
        // Remove event handlers if necessary
    }
    
    public function InstallFiles()
    {
        // Copy files if necessary
    }
    
    public function UnInstallFiles()
    {
        // Remove files if necessary
    }
}

?>