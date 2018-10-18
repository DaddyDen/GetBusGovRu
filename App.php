<?php
require_once 'Logger.php';

class App {
    const temp_directory = 'd:\\X\\temp\\';
    const work_directory = 'd:\\X\\';

    static function echo_log($mess = '',$only_log = false) {
        if (!$only_log)
            {echo date("d.m.y").' '.date("H:i:s").'   '.$mess.PHP_EOL;}

//        Logger::$PATH = dirname(__FILE__);
        Logger::$PATH = 'd:\\X\\';
        Logger::getLogger('log',null,$only_log)->log($mess);

    }

    static function getGUID(){
        if (function_exists('com_create_guid')){
            return com_create_guid();
        }else{
            mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
            $charid = strtoupper(md5(uniqid(rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = chr(123)// "{"
                .substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12)
                .chr(125);// "}"
            return $uuid;
        }
    }

    static function getDb() {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $db = mysqli_connect('localhost', 'root', '',  'bus');
        return $db;
    }

    static function getDateTimeFromBus($Str) {
        if (strlen($Str) > 16) {
            return date(substr((string) $Str, 0, 10) . ' ' . substr((string) $Str, 11, 8));
        } else {
            return date(substr((string) $Str, 0, 10));
        }

    }

    static function Date1MoreThenDate2Bus($sDate1,$sDate2) {
      $result = FALSE;
      $sDate1_timestemp = strtotime($sDate1);
      $sDate2_timestemp = strtotime($sDate2);
      if ($sDate1 > $sDate2) $result = TRUE;
      return $result;
    }

}
