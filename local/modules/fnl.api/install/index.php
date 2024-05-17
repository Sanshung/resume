<?

include_once(dirname(__DIR__).'/lib/main.php');

use \Bitrix\Main\Localization\Loc;
use \Bitrix\Main\EventManager;
use \Bitrix\Main\ModuleManager;
use \Fnl\Api\Main;
Loc::loadMessages(__FILE__);
Class fnl_api extends CModule
{
	var $MODULE_ID = 'fnl.api';
	var $MODULE_VERSION;
	var $MODULE_VERSION_DATE;
	var $MODULE_NAME;
	var $MODULE_DESCRIPTION;
	var $MODULE_CSS;
	var $strError = '';

	function __construct()
	{
		$arModuleVersion = array();
		include(__DIR__."/version.php");
		$this->MODULE_VERSION = $arModuleVersion["VERSION"];
		$this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
		$this->MODULE_NAME = Loc::getMessage("2FNL_MODULE_NAME");
		$this->MODULE_DESCRIPTION = Loc::getMessage("2FNL_MODULE_DESC");

		$this->PARTNER_NAME = Loc::getMessage("2FNL_PARTNER_NAME");
		$this->PARTNER_URI = Loc::getMessage("2FNL_PARTNER_URI");
	}


	function DoInstall()
	{
		global $APPLICATION;
        if(Main::isVersionD7())
        {
            $this->InstallTasks();
            $this->InstallFiles();
            $this->InstallEvents();
            ModuleManager::registerModule(Main::MODULE_ID);
        }
        else
        {
            $APPLICATION->ThrowException(Loc::getMessage("2FNL_INSTALL_ERROR_VERSION"));
        }

        $APPLICATION->IncludeAdminFile(Loc::getMessage("2FNL_INSTALL_TITLE"), Main::GetPatch()."/install/step.php");
	}

	function DoUninstall()
	{
        ModuleManager::unRegisterModule(Main::MODULE_ID);
		$this->UnInstallEvents();
		$this->UnInstallFiles();
        $this->UnInstallTasks();
	}
}
?>
