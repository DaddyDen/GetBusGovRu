<?PHP

require_once 'App.php';
require_once 'Bus.php';
$db = App::getDb();
if (!$db) {
    printf("Невозможно подключиться к базе данных. Код ошибки: %s\n", mysqli_connect_error());
    exit;
}
$regions = [];
for ($index = 1; $index < count($argv); $index++) {
    array_push($regions, $argv[$index]);
}
//$regions = ['38000000000','17000000000','78000000000'];
//$regions = ['17000000000'];

$GetGI = false;
$GetAR = false;
$GetF721 = false;
$GetF730 = false;
$GetF737 = true;
$GetStateTask = false;

$NewAndFresh = 0;
$OldAndSkip = 0;

foreach ($regions as $cur_reg) {
    $cur_reg_arr = [$cur_reg];

    if ($GetGI == true) {
        /* Получаем GeneralInfo */
        $Bus = new BusGetData();
        $filesz = $Bus->GetFilesFromFTP(Bus::GeneralInfo_ftp_directory, App::work_directory . Bus::GeneralInfo_local_directory, $cur_reg_arr);
        $files = $Bus->UnZipFiles();


        $step = 0;
        $count = sizeof($files);
        if ($count > 0) {
            $db = App::getDb();
            $db->query("START TRANSACTION;");
        }
        foreach ($files as $xml) {
            $step ++;
            $percent = (int) ($step / $count * 100);
            $str = "     => Читаю файлы GeneralInfo [" . $cur_reg . "]: $step из $count ( $percent %)               \r";
            echo $str;
            App::echo_log($str . '[' . $xml . ']', true);

            //$db = App::getDb();
            $geninfo = new BusGeneralInfo($db);
            $geninfo->FillFromXML($xml);

            if ($geninfo->Save()) {
                $NewAndFresh++;
            } else {
                $OldAndSkip++;
            }
            unset($geninfo);
        }
        if ($count > 0) {
            App::echo_log('Выполняю COMMIT', false);
            $db->query("COMMIT;");
        }
        $Bus->DelTempFiles();
    }

    if ($GetAR == true) {
        $Bus = new BusGetData();
        $filesz = $Bus->GetFilesFromFTP(Bus::ActivityResult_ftp_directory, App::work_directory . Bus::ActivityResult_local_directory, $cur_reg_arr);
        $files = $Bus->UnZipFiles();

        //$NewAndFresh    = 0;
        //$OldAndSkip     = 0;
        $step = 0;
        $count = sizeof($files);
        if ($count > 0) {
            $db = App::getDb();
            $db->query("START TRANSACTION;");
        }
        foreach ($files as $xml) {
            $step ++;
            $percent = (int) ($step / $count * 100);
            $str = "     => Читаю файлы ActivityResult [" . $cur_reg . "]: $step из $count ( $percent %)               \r";
            echo $str;
            App::echo_log($str . '[' . $xml . ']', true);

            //$db = App::getDb();
            $ActivityResult = new BusActivityResult($db);
            $ActivityResult->FillFromXML($xml);

            if ($ActivityResult->Save()) {
                $NewAndFresh++;
            } else {
                $OldAndSkip++;
            }
            unset($ActivityResult);
        }
        if ($count > 0) {
            App::echo_log('Выполняю COMMIT', false);
            $db->query("COMMIT;");
        }
        $Bus->DelTempFiles();
    }

    if ($GetF721 == true) {
        /* 721 форма */
        $Bus = new BusGetData();
        $filesz = $Bus->GetFilesFromFTP(Bus::BalanceF0503721_ftp_directory, App::work_directory . Bus::BalanceF0503721_local_directory, $cur_reg_arr);
        $files = $Bus->UnZipFiles();

        //$NewAndFresh    = 0;
        //$OldAndSkip     = 0;
        $step = 0;
        $count = sizeof($files);
        if ($count > 0) {
            $db = App::getDb();
            $db->query("START TRANSACTION;");
        }
        foreach ($files as $xml) {
            $step ++;
            $percent = (int) ($step / $count * 100);
            $str = "     => Читаю файлы annualBalanceF0503721 [" . $cur_reg . "]: $step из $count ( $percent %)               \r";
            echo $str;
            App::echo_log($str . '[' . $xml . ']', true);

            //$db = App::getDb();
            $BalanceF0503721 = new BusBalanceF0503721($db);
            $BalanceF0503721->FillFromXML($xml);

            if ($BalanceF0503721->Save()) {
                $NewAndFresh++;
            } else {
                $OldAndSkip++;
            }
            unset($BalanceF0503721);
            // if  ( $step > 1 ) {
            //     break;
            // }
        }
        if ($count > 0) {
            App::echo_log('Выполняю COMMIT', false);
            $db->query("COMMIT;");
        }
        $Bus->DelTempFiles();
    }

    if ($GetF730 == true) {
        /* 730 форма */
        $Bus = new BusGetData();
        $filesz = $Bus->GetFilesFromFTP(Bus::BalanceF0503730_ftp_directory, App::work_directory . Bus::BalanceF0503730_local_directory, $cur_reg_arr);
        $files = $Bus->UnZipFiles();

        //$NewAndFresh    = 0;
        //$OldAndSkip     = 0;
        $step = 0;
        $count = sizeof($files);
        if ($count > 0) {
            $db = App::getDb();
            $db->query("START TRANSACTION;");
        }
        foreach ($files as $xml) {
            $step ++;
            $percent = (int) ($step / $count * 100);
            $str = "     => Читаю файлы annualBalanceF0503730 [" . $cur_reg . "]: $step из $count ( $percent %)               \r";
            echo $str;
            App::echo_log($str . '[' . $xml . ']', true);

            //
            //$db = App::getDb();
            $BalanceF0503730 = new BusBalanceF0503730($db);
            $BalanceF0503730->FillFromXML($xml);

            if ($BalanceF0503730->SaveFast()) {
                $NewAndFresh++;
            } else {
                $OldAndSkip++;
            }
            unset($BalanceF0503730);
            //if  ( $step > 1 ) {
            //    break;
            //}
        }

        if ($count > 0) {
            App::echo_log('Выполняю COMMIT', false);
            $db->query("COMMIT;");
        }
        $Bus->DelTempFiles();
    }


    if ($GetF737 == true) {
        /* 737 форма */
        $Bus = new BusGetData();
        $filesz = $Bus->GetFilesFromFTP(Bus::BalanceF0503737_ftp_directory, App::work_directory . Bus::BalanceF0503737_local_directory, $cur_reg_arr);
        $files = $Bus->UnZipFiles();
        // $files = ['d:\X\annualBalance0503737_all_20180503103436_071(11).xml','d:\X\annualBalance0503737_all_20180503103436_071(100).xml'];
        //$NewAndFresh    = 0;
        //$OldAndSkip     = 0;
        $step = 0;
        $count = sizeof($files);
        if ($count > 0) {
            $db = App::getDb();
            //$db->query("SET AUTOCOMMIT=0;");
            //$db->query("START TRANSACTION;");
            //$mess = 'START TRANSACTION;';
            //echo date("d.m.y").' '.date("H:i:s").'   '.$mess.PHP_EOL.PHP_EOL;
        }
        foreach ($files as $xml) {
            $step ++;
            $percent = (int) ($step / $count * 100);
            $str = "     => Читаю файлы annualBalanceF0503737 [" . $cur_reg . "]: $step из $count ( $percent %)               \r";
            echo $str;
            App::echo_log($str . '[' . $xml . ']', true);

            //$db = App::getDb();
            $BalanceF0503737 = new BusBalanceF0503737($db);
            $BalanceF0503737->FillFromXML($xml);
            //print_r($BalanceF0503737);
            //print_r($BalanceF0503737->income);
            //exit;
//            $mess = 'START SAVE';
//            echo date("d.m.y").' '.date("H:i:s").'   '.$mess.PHP_EOL.PHP_EOL;
            $res = $BalanceF0503737->SaveFast();
            if ($res) {
                $NewAndFresh++;
            } else {
                $OldAndSkip++;
            }
  //          $mess = 'UNSET';
//            echo date("d.m.y").' '.date("H:i:s").'   '.$mess.PHP_EOL.PHP_EOL;
            unset($BalanceF0503737);
            //   if  ( $step > 0 ) {
            //       break;
            //    }
        }
        if ($count > 0) {
            //App::echo_log('Выполняю COMMIT', false);
            //$db->query("COMMIT;");
            //$db->query("SET AUTOCOMMIT=1;");
        }
        $Bus->DelTempFiles();
    }

    if ($GetStateTask == true) {
        /* Госзадания */
        $Bus = new BusGetData();
        $filesz = $Bus->GetFilesFromFTP(Bus::StateTask_ftp_directory, App::work_directory . Bus::StateTask_local_directory, $cur_reg_arr);
        $files = $Bus->UnZipFiles();
        //$files = [ 'D:\X\temp\289_18_stateTask_all_20180526235955_010.xml'];



        $step = 0;
        $count = sizeof($files);
        if ($count > 0) {
            $db = App::getDb();
            $db->query("START TRANSACTION;");
        }
        foreach ($files as $xml) {
            $step ++;
            $percent = (int) ($step / $count * 100);
            $str = "     => Читаю файлы StateTask [" . $cur_reg . "]: $step из $count ( $percent %)               \r";
            echo $str;
            App::echo_log($str . '[' . $xml . ']', true);

            //$db = App::getDb();
            $StateTask = new BusStateTask($db);
            $StateTask->FillFromXML($xml);

            //print_r($StateTask);


            if ($StateTask->Save()) {
                $NewAndFresh++;
            } else {
                $OldAndSkip++;
            }
            unset($StateTask);
        }
        if ($count > 0) {
            App::echo_log('Выполняю COMMIT', false);
            $db->query("COMMIT;");
        }
        $Bus->DelTempFiles();
    }
}



App::echo_log('Zip новых файлов скачали: ' . (sizeof($filesz)));
App::echo_log('Xml файлов извлекли: ' . (sizeof($files)));
App::echo_log('Устаревшие сведения в файлах: ' . $OldAndSkip);
