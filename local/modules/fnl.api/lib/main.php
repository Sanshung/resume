<?php

namespace Fnl\Api;

class main
{
    const MODULE_ID = 'fnl.api';

    public static function GetPatch($notDocumentRoot=false)
    {
        if($notDocumentRoot)
            return str_ireplace($_SERVER["DOCUMENT_ROOT"],'',dirname(__DIR__));
        else
            return dirname(__DIR__);
    }

    public static function isVersionD7()
    {
        return CheckVersion(SM_VERSION, '14.00.00');
    }
}
