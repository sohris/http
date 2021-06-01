<?php

namespace Sohris\Http;

use Exception;

class Utils
{
    private static $config_files = array();

    public static function getConfigFiles(string $config)
    {
        if (!isset(self::$config_files[$config])) {

            $file = file_get_contents("./configs/$config.json");

            if (empty($file))
                throw new Exception("Empty config '$config'");

            self::$config_files[$config] = json_decode($file, true);
        }
        
        return self::$config_files[$config];
    }
}
