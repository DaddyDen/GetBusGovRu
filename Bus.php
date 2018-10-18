<?php

class Bus extends App {

    const GeneralInfo_ftp_directory = '/GeneralInfo/';
    const GeneralInfo_local_directory = 'GeneralInfo\\';
    const ActivityResult_ftp_directory = '/ActivityResult/';
    const ActivityResult_local_directory = 'ActivityResult\\';
    const BalanceF0503721_ftp_directory = '/annualBalanceF0503721/';
    const BalanceF0503721_local_directory = 'annualBalanceF0503721\\';
    const BalanceF0503730_ftp_directory = '/annualBalanceF0503730/';
    const BalanceF0503730_local_directory = 'annualBalanceF0503730\\';
    const BalanceF0503737_ftp_directory = '/annualBalanceF0503737/';
    const BalanceF0503737_local_directory = 'annualBalanceF0503737\\';
    const StateTask_ftp_directory = '/StateTask/';
    const StateTask_local_directory = 'StateTask\\';

}

class BusGetData extends Bus {

    var $ftp_server = 'ftp.bus.gov.ru';
    var $ftp_user_name = 'gmuext';
    var $ftp_user_pass = 'YctTa34AdOPyld2';
    var $zip_files = [];
    var $array_of_regions;
    var $temp_files = [];
    var $GUID;

    function __construct($GUID = '') {
        if ($GUID == '') {
            $this->GUID = App::getGUID();
        }
        //App::echo_log("Создан объект: ".$this->GUID);
    }

    public function GetFilesFromFTP($remote_directory, $local_directory, $array_of_regions) {

        $files_for_download = [];
        $conn_id = ftp_connect($this->ftp_server);
        if (!$conn_id) {
            echo("Не смог подключиться к $this->ftp_server");
            exit;
        } else {
            $login = ftp_login($conn_id, $this->ftp_user_name, $this->ftp_user_pass);
            // проверка соединения
            if ((!$login)) {
                App::echo_log('======== ОШИБКА ПОДКЛЮЧЕНИЯ ========');
                App::echo_log("Не удалось установить соединение с FTP-сервером!");
                App::echo_log("Попытка подключения к серверу $this->ftp_server была произведена под именем $this->ftp_user_name");
                exit;
            } else {
                App::echo_log("Установлено соединение с FTP сервером $this->ftp_server под именем $this->ftp_user_name");
                ftp_pasv($conn_id, true);

                if (sizeof($array_of_regions) == 0) {
                    ftp_chdir($conn_id, $remote_directory);
                    App::echo_log("Считываю список регионов в каталоге: $remote_directory");
                    App::echo_log("Готовлю список файлов для закачки");
                    $array_of_regions = ftp_nlist($conn_id, '');
                }
                if (sizeof($array_of_regions) <> 0) {
                    $this->array_of_regions = $array_of_regions;
                    $size_of_all_files = 0;
                    $size_of_downloads = 0;
                    if (!file_exists($local_directory)) {
                        mkdir($local_directory, 700, true);
                    }
                    foreach ($array_of_regions as $region) {
                        //echo $remote_directory.$region;
                        ftp_chdir($conn_id, $remote_directory . $region);
                        App::echo_log("Работаю с каталогом региона: " . $region);
                        $files = ftp_nlist($conn_id, '*all*');
                        foreach ($files as $file) {

                            $destination_file = $local_directory . $region . '_' . $file;
                            $size = ftp_size($conn_id, $file);

                            if (file_exists($destination_file) == true and $size == filesize($destination_file)) {
                                App::echo_log("Файл уже скачан " . $file);
                            } else {
                                $size_of_all_files = $size_of_all_files + $size;
                                array_push($files_for_download, ['file' => $remote_directory . $region . '/' . $file, 'size' => $size, 'localfile' => $destination_file]);
                            }
                            array_push($this->zip_files, $destination_file);
                        }
                        ftp_chdir($conn_id, $remote_directory);
                    }
                    App::echo_log("Регионов просмотрено: " . (sizeof($array_of_regions)));
                    App::echo_log("Всего файлов: " . (sizeof($files_for_download)) . "    Размер: " . (round($size_of_all_files / 1024 / 1024, 1)) . " Мб.");

                    $f = ftp_nlist($conn_id, '');
                    $n = 0;
                    foreach ($files_for_download as $file) {
                        if (ftp_get($conn_id, $file['localfile'], $file['file'], FTP_BINARY)) {
                            $n++;
                            $size_of_downloads = $size_of_downloads + $file['size'];
                            App::echo_log("$n из " . (sizeof($files_for_download)) . ". Скачал файл " . $file['file'] . ' Размер ' . (round($file['size'] / 1024 / 1024, 1)) . " Мб. ( " . (round($size_of_downloads / $size_of_all_files * 100) . "% )"));
                        } else {
                            App::echo_log("Ошибка при скачивании файла " . $file['file'] . " в " . $file['localfile'] . " произошла проблема");
                        }
                    }
                }
                //print_r($files_for_download);
                //print_r($array_of_regions);
                //print_r($f);
                return $files_for_download;
            }
        }
    }

    public function UnZipFiles($array_of_files = [], $local_directory = '', $template = 'xml') {
        $extracted_files = [];
        if (sizeof($array_of_files) == 0) {
            $array_of_files = $this->zip_files;
        }
        if (sizeof($array_of_files) == 0) {
            App::echo_log('UnZip. Передан пустой список файлов.');
            return [];
        }
        if ($local_directory == '') {
            $local_directory = App::temp_directory;
        }
        $step = 0;
        $cout_extracted_files = 0;
        $count = sizeof($array_of_files);
        foreach ($array_of_files as $file) {
            $step ++;
            $percent = (int) ($step / $count * 100);
            $str = "     => UnZip. Файл : $step из $count ( $percent %).               \r";
            echo $str;
            App::echo_log($str . '[' . $file . ']', true);

            if (file_exists($file)) {
                $zip = new ZipArchive;
                $files_to_extract = [];

                if ($zip->open($file) === TRUE) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        $filename = $zip->getNameIndex($i);
                        if (preg_match('#\.(' . $template . ')$#i', $filename)) {

                            $zip->extractTo($local_directory, $filename);
                            //App::echo_log('       Извлечён '.$filename);

                            $destination_file = $local_directory . $step . '_' . $i . '_' . $filename;
                            rename($local_directory . $filename, $destination_file);
                            $cout_extracted_files ++;
                            array_push($extracted_files, $destination_file);
                            array_push($this->temp_files, $destination_file);
                        }
                    }
                    $zip->close();
                }
            } else {
                App::echo_log('UnZip. Файла [' . $file . '] не существует');
            }
        }
        //array_push($this->temp_files,$extracted_files);
        //print_r($this->temp_files);
        App::echo_log('UnZip. Всего файлов извлечено: ' . $cout_extracted_files . '                                                            ');
        return $extracted_files;
    }

    public function DelTempFiles() {
        $count = sizeof($this->temp_files);
        $step = 0;
        foreach ($this->temp_files as $file) {
            $step ++;
            $percent = (int) ($step / $count * 100);
            $str = "     => Удаляю временные файлы : $step из $count ( $percent %).               \r";
            echo $str;
            App::echo_log($str . '[' . $file . ']', true);
            try {
                if (file_exists($file)) {
                    unlink($file);
                }
            } catch (Exception $e) {
                App::echo_log($e->getMessage());
            }
        }
        App::echo_log("DelTemp. Удалили временные файлы:  $count");
    }

}

class BusNsi extends Bus {

    public $id = 0;
    public $nsi;
    public $db;
    public $array = [];

    function __construct($db, $nsi, $array) {
        $this->array = $array;
        $this->nsi = $nsi;
        $this->db = $db;
        $sql = 'SELECT id FROM bus_' . $this->nsi . ' WHERE 1=1';
        foreach ($this->array as $key => $value) {
            if (strtoupper(gettype($value)) == 'INTEGER' or strtoupper(gettype($value)) == 'DOUBLE') {
                $sql = $sql . ' AND ' . $key . '=' . $value;
            } else {
                $sql = $sql . ' AND ' . $key . '="' . mysqli_real_escape_string($this->db,$value) . '"';
            }
        }
        $stmt = $this->db->prepare($sql);
        //print($sql.PHP_EOL);

        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->store_result();

        if ($stmt->fetch())
            $this->id = $id;

        $stmt->close();

    }

    function Save($OnlyInsert = false) {

        if ($this->id == 0) {
            $sql = 'INSERT INTO bus_' . $this->nsi . ' ';
            $sql1 = '(';
            $sql2 = ') VALUES (';
            foreach ($this->array as $key => $value) {
                $sql1 = $sql1 . $key . ',';
                if (strtoupper(gettype($value)) == 'INTEGER' or strtoupper(gettype($value)) == 'DOUBLE') {
                    $sql2 = $sql2 . $value . ',';
                } else {
                    $sql2 = $sql2 . '"' . mysqli_real_escape_string($this->db, $value) . '",';
                }
            }
            $sql1 = substr($sql1, 0, -1);
            $sql2 = substr($sql2, 0, -1);
            $sql2 = $sql2 . ')';
            $sql = $sql . $sql1 . $sql2;
            //print($sql.PHP_EOL);
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $this->id = mysqli_insert_id($this->db);
            $stmt->close();
        } else {
            if (!$OnlyInsert) {
                $sql = 'UPDATE bus_' . $this->nsi . ' SET';
                foreach ($this->array as $key => $value) {
                    if (strtoupper(gettype($value)) == 'INTEGER' or strtoupper(gettype($value)) == 'DOUBLE') {
                        $sql = $sql . ' ' . $key . '=' . $value . ',';
                    } else {
                        $sql = $sql . ' ' . $key . '="' . mysqli_real_escape_string($this->db, $value) . '",';
                    }
                }
                $sql = substr($sql, 0, -1);
                $sql = $sql . ' WHERE id = ' . $this->id;
                //print($sql.PHP_EOL);
                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $stmt->close();
            }
        }


        return $this->id;
    }

}


class BusRbs extends Bus {

    public $id = 0;
    public $fullName;
    public $db;

    function __construct($db, $fullName = 'Пустое наименование') {

        $this->fullName = trim($fullName);
        $this->db = $db;


        $sql = 'SELECT id FROM bus_rbs WHERE fullName = ? ORDER BY id LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('s', $this->fullName);

        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->store_result();

        if ($stmt->fetch())
            $this->id = $id;

        $stmt->close();
    }

    function Save() {
        $this->fullName = trim($this->fullName);
        if ($this->id == 0) {
            $sql = 'INSERT INTO bus_rbs (fullName) VALUES ( ?)';
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('s', $this->fullName);
            $stmt->execute();
            $this->id = mysqli_insert_id($this->db);
        } else {
            $stmt = $this->db->prepare('UPDATE bus_rbs SET fullName = ? WHERE id = ?');
            $stmt->bind_param('si', $this->fullName, $this->id);
            $stmt->execute();
        }
        $stmt->close();
        return $this->id;
    }

}

class BusGeneralInfo extends Bus {

    public $id = 0;
    public $fullName;
    public $inn;
    public $kpp;
    public $regNum;
    public $shortName;
    public $ogrn;
    public $orgType; //03-БУ, 08-КУ, 10-АУ
    public $okfs;
    public $okopf;
    public $okpo;
    public $oktmo;
    public $okved;
    public $institutionType;
    public $ppo;
    public $www;
    public $eMail;
    public $versionNumber;
    public $rbs;
    public $changeDate;
    public $db;

    function __construct($db, $inn = '', $regNum = '',$kpp = '',$fileName = '') {
        $sql = "SQL для поиска не сформирован.";
        $this->db = $db;
        if ($regNum != '') {
            $sql = "SELECT id,changeDate FROM bus_GeneralInfo WHERE regNum = ? ORDER BY id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('s', $inn);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            if ($stmt->fetch()) {
                $this->id = $id;
            } else {
                $this->id = 0;
            }
            $stmt->store_result();
        }
        if ($this->id == 0 && $inn != '' && $kpp != '') {
            $sql = "SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? AND kpp = ? ORDER BY id LIMIT 1";

            $stmt = $this->db->prepare($sql);
                $stmt->bind_param('ss', $inn, $kpp);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            if ($stmt->fetch()) {
                $this->id = $id;
            } else {
                $this->id = 0;
            }
            $stmt->store_result();
        }
        if ($this->id == 0 && $inn != '' && $kpp != '') {
            $sql = "SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? AND kpp LIKE ? ORDER BY id LIMIT 1";
            $kpp = substr($kpp,0,2).'%';
            $stmt->bind_param('ss', $inn, $kpp);

            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            if ($stmt->fetch()) {
                $this->id = $id;
            } else {
                $this->id = 0;
            }
            $stmt->store_result();
        }

        if ($this->id == 0 && $inn != '') {
            $sql = "SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? ORDER BY id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('s', $inn);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            if ($stmt->fetch()) {
                $this->id = $id;
            } else {
                $this->id = 0;
            }
            $stmt->store_result();
        }

        if ($this->id == 0 && $fileName != '') {
            APP::echo_log("Искали так:".PHP_EOL.$sql.PHP_EOL."ИНН=".$inn.PHP_EOL."КПП=".$kpp.PHP_EOL."regNum=".$regNum.PHP_EOL.$fileName.PHP_EOL);
        }
    }

    public function FillFromXML($xml_file) {

        $bus = simplexml_load_file($xml_file);

        $ns2 = $bus->getNamespaces(true);

        $body = $bus->children($ns2['ns2'])->body;


        //$ns2 = $body->getNamespaces(true);

        $position = $body->position->children($ns2['']);

        //echo $str.PHP_EOL;
        //$timestemp = strtotime($str);
        //echo date('Y-m-d',$timestemp).PHP_EOL;
        //$this->changeDate = $timestemp;//$position->changeDate;
        $this->changeDate = APP::getDateTimeFromBus($position->changeDate);

        $this->versionNumber = (string) $position->versionNumber;
        $initiator = $position->initiator;



        $this->fullName = (string) $initiator->fullName;
        $this->inn = (string) $initiator->inn;
        $this->kpp = (string) $initiator->kpp;
        $this->regNum = (string) $initiator->regNum;

        $main = $position->main;
        $this->shortName = (string) $main->shortName;
        $this->ogrn = (string) $main->ogrn;
        $this->orgType = (string) $main->orgType;
        $rbs_fullName = "Пустое наименование";
        if (isset($main->rbs->fullName)) {
            $rbs_fullName = $main->rbs->fullName;
        } elseif (isset($position->other->founder->fullName)) {
            $rbs_fullName = $position->other->founder->fullName;
        }

        $rbs = new BusRbs($this->db,$rbs_fullName);
        $rbs->Save();

        $this->rbs = $rbs->id;

        $classifier = $main->classifier->children('');

        $this->okfs[(string) $classifier->okfs->code] = (string) $classifier->okfs->name;
        $this->okopf[(string) $classifier->okopf->code] = (string) $classifier->okopf->name;
        $this->okpo[(string) $classifier->okpo->code] = (string) $classifier->okpo->name;
        $this->oktmo[(string) $classifier->oktmo->code] = (string) $classifier->oktmo->name;

        //print_r($classifier);


        foreach ($classifier as $param => $value) {
            if ($param == 'okved') {
                if ((string) $value->type == 'C') {
                    $this->okved[(string) $value->code] = (string) $value->name;
                }
            }
        }
        foreach ($classifier as $param => $value) {
            if ($param == 'okved') {
                if ((string) $value->type != 'C') {
                    $this->okved[(string) $value->code] = (string) $value->name;
                }
            }
        }

        $additional = $position->additional->children('');
        $this->institutionType[(string) $additional->institutionType->code] = (string) $additional->institutionType->name;
        $this->ppo = (string) $additional->ppo->name;
        $this->phone = (string) $additional->phone;
        $this->www = (string) $additional->www;
        $this->eMail = (string) $additional->eMail;
        //print_r($this);
    }

    public function Save() {
        $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_GeneralInfo WHERE regNum = ? ORDER BY id LIMIT 1');
        $stmt->bind_param('s', $this->regNum);
        $stmt->execute();
        $stmt->bind_result($id, $changeDate);
        $stmt->store_result();
        if ($stmt->fetch()) {
            $this->id = $id;
            $old_time = $changeDate;
        } else {
            $this->id = 0;
        }

        $old_data_in_file = FALSE;

        if (isset($old_time)) {
            $old_timestemp = strtotime($old_time);
            $new_timestemp = strtotime($this->changeDate);
            if ($new_timestemp < $old_timestemp)
                $old_data_in_file = TRUE;
        }
        if ($this->id != 0) {
            //echo "old_time=$old_time".PHP_EOL;
            //echo "newtime=$this->changeDate".PHP_EOL;
            //echo "id=$this->id".PHP_EOL;
        }

        if (!$old_data_in_file or $this->id == 0) {

            $array = [];
            $array['code'] = key($this->oktmo);
            $array['name'] = trim($this->oktmo[key($this->oktmo)]);

            $oktmo = new BusNsi($this->db, 'oktmo', $array );
            $oktmo_id = $oktmo->Save(true);

            $array = [];
            $array['code'] = key($this->institutionType);
            $array['name'] = trim($this->institutionType[key($this->institutionType)]);

            $institutionType = new BusNsi($this->db, 'institutionType', $array);
            $institutionType_id = $institutionType->Save(true);

            $array = [];
            $array['code'] = key($this->okfs);
            $array['name'] = trim($this->okfs[key($this->okfs)]);

            $okfs = new BusNsi($this->db, 'okfs', $array);
            $okfs_id = $okfs->Save(true);

            $array = [];
            $array['code'] = key($this->okopf);
            $array['name'] = trim($this->okopf[key($this->okopf)]);

            $okopf = new BusNsi($this->db, 'okopf',$array);
            $okopf_id = $okopf->Save(true);

            if ($this->id == 0) {
                //echo 'INSERT'.PHP_EOL;

                $sql = 'INSERT INTO bus_GeneralInfo (fullName,regNum,inn,kpp,shortName,ogrn,orgType,okfs,okopf,oktmo,institutionType,ppo,www,eMail,rbs,versionNumber,changeDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('sssssssiiiisssiss', $this->fullName, $this->regNum, $this->inn, $this->kpp, $this->shortName, $this->ogrn, $this->orgType, $okfs_id, $okopf_id, $oktmo_id, $institutionType_id, $this->ppo, $this->www, $this->eMail, $this->rbs, $this->versionNumber, $this->changeDate);

                $stmt->execute();
                //print_r($stmt);
                $this->id = mysqli_insert_id($this->db);
                //echo "id=$this->id".PHP_EOL;
                $this->SaveOkveds();
            } else {
                //echo 'UPDATE'.PHP_EOL;
                $stmt = $this->db->prepare('UPDATE bus_GeneralInfo SET fullName = ?,regNum = ?, inn = ?,kpp = ?,shortName = ?,ogrn = ?,orgType = ?,okfs = ?,okopf = ?,oktmo = ?,institutionType = ?,ppo = ?,www = ?,eMail = ?,rbs = ?,versionNumber = ?,changeDate = ? WHERE id = ?');
                $stmt->bind_param('sssssssiiiisssissi', $this->fullName, $this->regNum,$this->inn, $this->kpp, $this->shortName, $this->ogrn, $this->orgType, $okfs_id, $okopf_id, $oktmo_id, $institutionType_id, $this->ppo, $this->www, $this->eMail,$this->rbs, $this->versionNumber, $this->changeDate, $this->id);
                $stmt->execute();
                $this->SaveOkveds();
            }
        } else {

            return FALSE;
        }
        return TRUE;
    }

    public function SaveOkveds() {
        if (sizeof($this->okved) > 0) {

            $sql = "DELETE FROM bus_GeneralInfo_okved WHERE GeneralInfo = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->bind_param('i', $this->id);
            $stmt->execute();
            $i = 0;
            foreach ($this->okved as $code => $name) {
                $i++;
                if ($i == 1) {
                    $main = 1;
                } else {
                    $main = 0;
                }
                $array = [];
                $array['code'] = $code;
                $array['name'] = trim($name);


                $okved = new BusNsi($this->db, 'okved', $array);
                $okved_id = $okved->Save(true);

                $sql = "INSERT INTO bus_GeneralInfo_okved (GeneralInfo,Okved,main) VALUES (?,?,?)";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('iii', $this->id, $okved_id, $main);
                $stmt->execute();
            }
        }
    }

}

class BusActivityResult extends Bus {

    public $regNum;
    public $fileName;
    public $kpp;
    public $inn;
    public $id = 0;
    public $GeneralInfo = 0;
    public $reportYear = 0;
    public $staff_beginYear = 0;
    public $staff_endYear = 0;
    public $staff_averageSalary = 0;
    public $versionNumber;
    public $changeDate;
    public $db;

    function __construct($db, $inn = '', $kpp = '') {
        $this->db = $db;
        $this->inn = $inn;
        $this->kpp = $kpp;
        if ($inn != '') {
            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('s', $this->inn);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            $this->GeneralInfo = $id;
        }
    }

    public function FillFromXML($xml_file) {
        $bus = simplexml_load_file($xml_file);
        $ns2 = $bus->getNamespaces(true);
        $body = $bus->children($ns2['ns2'])->body;
        $position = $body->position->children($ns2['']);
        $this->fileName = $xml_file;
        $this->changeDate = APP::getDateTimeFromBus($position->changeDate);
        $this->versionNumber = (string) $position->versionNumber;
        $initiator = $position->initiator;
        $this->inn = (string) $initiator->inn;
        $this->kpp = (string) $initiator->kpp;
        $this->regNum = (string) $initiator->regNum;
        $GeneralInfo = new BusGeneralInfo($this->db, $this->inn,$this->regNum,$this->kpp,$this->fileName);
        $this->GeneralInfo = $GeneralInfo->id;

        $this->reportYear = $position->reportYear;
        $staff = $position->staff;
        $this->staff_beginYear = $staff->beginYear;
        $this->staff_endYear = $staff->endYear;
        $this->staff_averageSalary = $staff->averageSalary;


        //print_r($GeneralInfo);
        unset($GeneralInfo);
    }

    public function Save() {
        if ($this->GeneralInfo > 0) {
            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_ActivityResult WHERE GeneralInfo = ? and reportYear = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('si', $this->GeneralInfo, $this->reportYear);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            if ($stmt->fetch()) {
                $this->id = $id;
                $old_time = $changeDate;
            } else {
                $this->id = 0;
            }

            $old_data_in_file = FALSE;

            if (isset($old_time)) {
                $old_timestemp = strtotime($old_time);
                $new_timestemp = strtotime($this->changeDate);
                if ($new_timestemp < $old_timestemp) {
                    $old_data_in_file = TRUE;
                }
            }


            if ((!$old_data_in_file or $this->id == 0) and $this->GeneralInfo > 0) {
                if ($this->id == 0) {

                    $sql = 'INSERT INTO bus_ActivityResult (GeneralInfo,reportYear,staff_beginYear,staff_endYear,staff_averageSalary,versionNumber,changeDate) VALUES (?, ?, ?, ?, ?, ?, ?)';
                    $stmt = $this->db->prepare($sql);
                    $stmt->bind_param('iidddss', $this->GeneralInfo, $this->reportYear, $this->staff_beginYear, $this->staff_endYear, $this->staff_averageSalary, $this->versionNumber, $this->changeDate);
                    $stmt->execute();
                    //print_r($stmt);
                    $this->id = mysqli_insert_id($this->db);
                    //echo "id=$this->id".PHP_EOL;
                } else {
                    //echo 'UPDATE'.PHP_EOL;
                    $stmt = $this->db->prepare('UPDATE bus_ActivityResult SET GeneralInfo = ?,reportYear = ?,staff_beginYear = ?,staff_endYear = ?,staff_averageSalary = ?,versionNumber = ?,changeDate = ? WHERE id = ?');
                    $stmt->bind_param('iidddssi', $this->GeneralInfo, $this->reportYear, $this->staff_beginYear, $this->staff_endYear, $this->staff_averageSalary, $this->versionNumber, $this->changeDate, $this->id);
                    $stmt->execute();
                }
            } else {
                return FALSE;
            }
            return TRUE;
        } else {
            App::echo_log("[ALARM] Не нашёл информацию в файле $xml_file об организации с реквизитами $this->inn $this->regNum");
        }
    }

}

class BusBalanceF0503721 extends Bus {

    public $id = 0;
    public $filename = '';
    public $kpp;
    public $inn;
    public $GeneralInfo = 0;
    public $formationPeriod = 0;
    public $versionNumber;
    public $changeDate;
    public $income = [];
    public $expense = [];
    public $nonFinancialAssets = [];
    public $financialAssets = [];
    public $db;

    function __construct($db, $inn = '', $kpp = '') {
        $this->db = $db;
        $this->inn = $inn;
        $this->kpp = $kpp;
        if ($inn != '') {
            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('s', $this->inn);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            $this->GeneralInfo = $id;
        }
    }

    public function FillFromXML($xml_file) {
        $this->filename = $xml_file;
        $bus = simplexml_load_file($xml_file);
        $ns2 = $bus->getNamespaces(true);
        $body = $bus->children($ns2['ns2'])->body;
        $position = $body->position->children($ns2['']);

        $this->changeDate = APP::getDateTimeFromBus($position->changeDate);
        $this->versionNumber = (string) $position->versionNumber;
        $initiator = $position->initiator;
        $this->inn = (string) $initiator->inn;
        $this->kpp = (string) $initiator->kpp;
        $this->regNum = (string) $initiator->regNum;
        $GeneralInfo = new BusGeneralInfo($this->db, $this->inn,$this->regNum,$this->kpp,$xml_file);
        $this->GeneralInfo = $GeneralInfo->id;
        $this->formationPeriod = (int) $position->formationPeriod;
        $this->income = $position->income;
        $this->expense = $position->expense;
        $this->nonFinancialAssets = $position->nonFinancialAssets;
        $this->financialAssets = $position->financialAssets;

        //print_r($this);
        unset($GeneralInfo);
    }

    private function insertRow($page, $name, $code, $analyticCode, $targetFunds, $services, $stateTaskFunds, $revenueFunds, $total, $parent) {

        $array = [];
        $array['code'] = $code;
        $array['name'] = trim($name);
        $lineCode = new BusNsi($this->db, 'Balance_codes', $array);
        $lineCode_id = $lineCode->Save(true);


        $sql = 'INSERT INTO bus_BalanceF0503721_records (Balance,page,lineCode,analyticCode,targetFunds,services,stateTaskFunds,revenueFunds,total,parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('iiisdddddi', $this->id, $page, $lineCode_id, $analyticCode, $services, $targetFunds, $stateTaskFunds, $revenueFunds, $total, $parent);
        $stmt->execute();
        $id = mysqli_insert_id($this->db);
        return $id;
    }

    public function Save() {
        //print_r($this);
        if ($this->GeneralInfo > 0 and $this->formationPeriod > 0) {

            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_BalanceF0503721 WHERE GeneralInfo = ? and formationPeriod = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('si', $this->GeneralInfo, $this->formationPeriod);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            if ($stmt->fetch()) {
                $old_time = $changeDate;
                $this->id = $id;
            } else {
                $this->id = 0;
                $old_time = '1900-01-01 00:00:01';
            }

            $old_data_in_file = FALSE;

            if (isset($old_time)) {
                $old_timestemp = strtotime($old_time);
                $new_timestemp = strtotime($this->changeDate);
                if ($new_timestemp < $old_timestemp) {
                    $old_data_in_file = TRUE;
                }
            }
            if ((!$old_data_in_file) and $this->GeneralInfo > 0) {
                /* отчистим предыдущую информацию */
                /*$stmt = $this->db->prepare('DELETE FROM bus_BalanceF0503721_records WHERE Balance = ?');
                $stmt->bind_param('i', $this->id);
                $stmt->execute();*/
                $stmt = $this->db->prepare('DELETE FROM bus_BalanceF0503721 WHERE id = ?');
                $stmt->bind_param('i', $this->id);
                $stmt->execute();




                $sql = 'INSERT INTO bus_BalanceF0503721 (GeneralInfo,changeDate,formationPeriod,versionNumber) VALUES (?, ?, ?, ?)';
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('isis', $this->GeneralInfo, $this->changeDate, $this->formationPeriod, $this->versionNumber);
                $stmt->execute();
                $this->id = mysqli_insert_id($this->db);
                /* income */

                for ($page = 1; $page <= 4; $page++) {
                    $parent = 0;
                    if ($page == 1) {
                        $arr = $this->income->reportItem;
                    } elseif ($page == 2) {
                        $arr = $this->expense->reportItem;
                    } elseif ($page == 3) {
                        $arr = $this->nonFinancialAssets->reportItem;
                    } elseif ($page == 4) {
                        $arr = $this->financialAssets->reportItem;
                    }
                    foreach ($arr as $ReportItem) {

                        if (isset($ReportItem->name)) {
                            $name = trim((string) $ReportItem->name);
                        } else {
                            $name = '<Пустое наименование>';
                        }
                        if (isset($ReportItem->lineCode)) {
                            $code = trim((string) $ReportItem->lineCode);
                        } else {
                            $code = 'XXX';
                        }

                        if (isset($ReportItem->analyticCode)) {
                            $analyticCode = trim((string) $ReportItem->analyticCode);
                        } else {
                            $analyticCode = 'XXX';
                        }
                        if (isset($ReportItem->targetFunds)) {
                            $targetFunds = (double) $ReportItem->targetFunds;
                        } else {
                            $targetFunds = 0;
                        }
                        if (isset($ReportItem->services)) {
                            $services = (double) $ReportItem->services;
                        } else {
                            $services = 0;
                        }

                        if (isset($ReportItem->stateTaskFunds)) {
                            $stateTaskFunds = (double) $ReportItem->stateTaskFunds;
                        } else {
                            $stateTaskFunds = 0;
                        }
                        if (isset($ReportItem->revenueFunds)) {
                            $revenueFunds = (double) $ReportItem->revenueFunds;
                        } else {
                            $revenueFunds = 0;
                        }
                        if (isset($ReportItem->total)) {
                            $total = (double) $ReportItem->total;
                        } else {
                            $total = 0;
                        }
                        $parent = $this->insertRow($page, $name, $code, $analyticCode, $targetFunds, $services, $stateTaskFunds, $revenueFunds, $total, 0);

                        if (isset($ReportItem->reportSubItem)) {
                            //print_r($ReportItem->reportSubItem);
                            foreach ($ReportItem->reportSubItem as $reportSubItem) {
                                if (isset($ReportItem->name)) {
                                    $name = trim((string) $reportSubItem->name);
                                } else {
                                    $name = '<Пустое наименование>';
                                }
                                if (isset($ReportItem->lineCode)) {
                                    $code = trim((string) $reportSubItem->lineCode);
                                } else {
                                    $code = 'XXX';
                                }
                                if (isset($ReportItem->analyticCode)) {
                                    $analyticCode = trim((string) $reportSubItem->analyticCode);
                                } else {
                                    $analyticCode = 'XXX';
                                }
                                if (isset($ReportItem->targetFunds)) {
                                    $targetFunds = (double) $reportSubItem->targetFunds;
                                } else {
                                    $targetFunds = 0;
                                }
                                if (isset($ReportItem->services)) {
                                    $services = (double) $reportSubItem->services;
                                } else {
                                    $services = 0;
                                }
                                if (isset($ReportItem->stateTaskFunds)) {
                                    $stateTaskFunds = (double) $reportSubItem->stateTaskFunds;
                                } else {
                                    $stateTaskFunds = 0;
                                }
                                if (isset($ReportItem->revenueFunds)) {
                                    $revenueFunds = (double) $reportSubItem->revenueFunds;
                                } else {
                                    $revenueFunds = 0;
                                }
                                if (isset($ReportItem->total)) {
                                    $total = (double) $reportSubItem->total;
                                } else {
                                    $total = 0;
                                }
                                $sub = $this->insertRow($page, $name, $code, $analyticCode, $targetFunds, $services, $stateTaskFunds, $revenueFunds, $total, $parent);
                                //echo $sub;
                            }
                        }
                    }
                }
            }

            return TRUE;
        } else {
            //print_r($this);
            APP::echo_log("[ALARM] Для файла $this->filename нет информации о ГОДЕ или владельце.");
            return FALSE;
        }
    }

}

class BusBalanceF0503730 extends Bus {

    public $id = 0;
    public $filename = '';
    public $kpp;
    public $inn;
    public $GeneralInfo = 0;
    public $formationPeriod = 0;
    public $versionNumber;
    public $changeDate;
    public $nonFinancialAssets = [];
    public $financialAssets = [];
    public $commitments = [];
    public $financialResult = [];
    public $db;

    function __construct($db, $inn = '', $kpp = '') {
        $this->db = $db;
        $this->inn = $inn;
        $this->kpp = $kpp;
        if ($inn != '') {
            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('s', $this->inn);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            $this->GeneralInfo = $id;
        }
    }

    public function FillFromXML($xml_file) {
        $this->filename = $xml_file;
        $bus = simplexml_load_file($xml_file);
        $ns2 = $bus->getNamespaces(true);
        $body = $bus->children($ns2['ns2'])->body;
        $position = $body->position->children($ns2['']);
        $this->changeDate = APP::getDateTimeFromBus($position->changeDate);
        $this->versionNumber = (string) $position->versionNumber;
        $initiator = $position->initiator;
        $this->inn = (string) $initiator->inn;
        $this->kpp = (string) $initiator->kpp;
        $this->regNum = (string) $initiator->regNum;
        $GeneralInfo = new BusGeneralInfo($this->db, $this->inn,$this->regNum,$this->kpp,$xml_file);
        $this->GeneralInfo = $GeneralInfo->id;
        $this->formationPeriod = (int) $position->formationPeriod;
        $this->nonFinancialAssets = $position->nonFinancialAssets;
        $this->financialAssets = $position->financialAssets;
        $this->commitments = $position->commitments;
        $this->financialResult = $position->financialResult;

        //print_r($this);
        unset($GeneralInfo);
    }

    private function insertRow($page, $name, $code, $analyticCode, $targetFundsStartYear, $targetFundsEndYear, $stateTaskFundsStartYear, $stateTaskFundsEndYear, $revenueFundsStartYear, $revenueFundsEndYear, $totalStartYear, $totalEndYear, $parent) {
        $array = [];
        $array['code'] = $code;
        $array['name'] = trim($name);
        $lineCode = new BusNsi($this->db, 'Balance_codes', $array);
        $lineCode_id = $lineCode->Save(true);


        $sql = 'INSERT INTO bus_BalanceF0503730_records (Balance,page,lineCode,analyticCode,'
                . 'targetFundsStartYear,targetFundsEndYear,stateTaskFundsStartYear,stateTaskFundsEndYear,'
                . 'revenueFundsStartYear,revenueFundsEndYear,totalStartYear,totalEndYear,parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('iiisddddddddi', $this->id, $page, $lineCode_id, $analyticCode, $targetFundsStartYear, $targetFundsEndYear, $stateTaskFundsStartYear, $stateTaskFundsEndYear, $revenueFundsStartYear, $revenueFundsEndYear, $totalStartYear, $totalEndYear, $parent);
        $stmt->execute();
        $id = mysqli_insert_id($this->db);
        return $id;
    }

    public function Save() {
        //print_r($this);
        if ($this->GeneralInfo > 0 and $this->formationPeriod > 0) {

            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_BalanceF0503730 WHERE GeneralInfo = ? and formationPeriod = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('si', $this->GeneralInfo, $this->formationPeriod);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            if ($stmt->fetch()) {
                $old_time = $changeDate;
                $this->id = $id;
            } else {
                $this->id = 0;
                $old_time = '1900-01-01 00:00:01';
            }

            $old_data_in_file = FALSE;

            if (isset($old_time)) {
                $old_timestemp = strtotime($old_time);
                $new_timestemp = strtotime($this->changeDate);
                if ($new_timestemp < $old_timestemp) {
                    $old_data_in_file = TRUE;
                }
            }
            if ((!$old_data_in_file) and $this->GeneralInfo > 0) {
                /* отчистим предыдущую информацию */
                /*$stmt = $this->db->prepare('DELETE FROM bus_BalanceF0503730_records WHERE Balance = ?');
                $stmt->bind_param('i', $this->id);
                $stmt->execute();*/
                $stmt = $this->db->prepare('DELETE FROM bus_BalanceF0503730 WHERE id = ?');
                $stmt->bind_param('i', $this->id);
                $stmt->execute();


                $sql = 'INSERT INTO bus_BalanceF0503730 (GeneralInfo,changeDate,formationPeriod,versionNumber) VALUES (?, ?, ?, ?)';
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('isis', $this->GeneralInfo, $this->changeDate, $this->formationPeriod, $this->versionNumber);
                $stmt->execute();
                $this->id = mysqli_insert_id($this->db);
                /* income */

                for ($page = 1; $page <= 4; $page++) {
                    $parent = 0;
                    if ($page == 1) {
                        $arr = $this->nonFinancialAssets->reportItem;
                    } elseif ($page == 2) {
                        $arr = $this->financialAssets->reportItem;
                    } elseif ($page == 3) {
                        $arr = $this->commitments->reportItem;
                    } elseif ($page == 4) {
                        $arr = $this->financialResult->reportItem;
                    }

                    foreach ($arr as $ReportItem) {

                        if (isset($ReportItem->name)) {
                            $name = trim((string) $ReportItem->name);
                        } else {
                            $name = '<Пустое наименование>';
                        }
                        if (isset($ReportItem->lineCode)) {
                            $code = trim((string) $ReportItem->lineCode);
                        } else {
                            $code = 'XXX';
                        }
                        if (isset($ReportItem->analyticCode)) {
                            $analyticCode = trim((string) $ReportItem->analyticCode);
                        } else {
                            $analyticCode = 'XXX';
                        }

                        if (isset($ReportItem->targetFundsStartYear)) {
                            $targetFundsStartYear = (double) $ReportItem->targetFundsStartYear;
                        } else {
                            $targetFundsStartYear = 0;
                        }
                        if (isset($ReportItem->targetFundsEndYear)) {
                            $targetFundsEndYear = (double) $ReportItem->targetFundsEndYear;
                        } else {
                            $targetFundsEndYear = 0;
                        }

                        if (isset($ReportItem->stateTaskFundsStartYear)) {
                            $stateTaskFundsStartYear = (double) $ReportItem->stateTaskFundsStartYear;
                        } else {
                            $stateTaskFundsStartYear = 0;
                        }
                        if (isset($ReportItem->stateTaskFundsEndYear)) {
                            $stateTaskFundsEndYear = (double) $ReportItem->stateTaskFundsEndYear;
                        } else {
                            $stateTaskFundsEndYear = 0;
                        }
                        if (isset($ReportItem->revenueFundsStartYear)) {
                            $revenueFundsStartYear = (double) $ReportItem->revenueFundsStartYear;
                        } else {
                            $revenueFundsStartYear = 0;
                        }
                        if (isset($ReportItem->revenueFundsEndYear)) {
                            $revenueFundsEndYear = (double) $ReportItem->revenueFundsEndYear;
                        } else {
                            $revenueFundsEndYear = 0;
                        }
                        if (isset($ReportItem->totalStartYear)) {
                            $totalStartYear = (double) $ReportItem->totalStartYear;
                        } else {
                            $totalStartYear = 0;
                        }
                        if (isset($ReportItem->totalEndYear)) {
                            $totalEndYear = (double) $ReportItem->totalEndYear;
                        } else {
                            $totalEndYear = 0;
                        }
                        $parent = $this->insertRow($page, $name, $code, $analyticCode, $targetFundsStartYear, $targetFundsEndYear, $stateTaskFundsStartYear, $stateTaskFundsEndYear, $revenueFundsStartYear, $revenueFundsEndYear, $totalStartYear, $totalEndYear, 0);

                        if (isset($ReportItem->reportSubItem)) {
                            //print_r($ReportItem->reportSubItem);
                            foreach ($ReportItem->reportSubItem as $reportSubItem) {
                                if (isset($ReportItem->name)) {
                                    $name = trim((string) $reportSubItem->name);
                                } else {
                                    $name = '<Пустое наименование>';
                                }
                                if (isset($ReportItem->lineCode)) {
                                    $code = trim((string) $reportSubItem->lineCode);
                                } else {
                                    $code = 'XXX';
                                }
                                if (isset($ReportItem->analyticCode)) {
                                    $analyticCode = trim((string) $reportSubItem->analyticCode);
                                } else {
                                    $analyticCode = 'XXX';
                                }
                                if (isset($ReportItem->targetFundsStartYear)) {
                                    $targetFundsStartYear = (double) $reportSubItem->targetFundsStartYear;
                                } else {
                                    $targetFundsStartYear = 0;
                                }
                                if (isset($ReportItem->targetFundsEndYear)) {
                                    $targetFundsEndYear = (double) $reportSubItem->targetFundsEndYear;
                                } else {
                                    $targetFundsEndYear = 0;
                                }

                                if (isset($ReportItem->stateTaskFundsStartYear)) {
                                    $stateTaskFundsStartYear = (double) $reportSubItem->stateTaskFundsStartYear;
                                } else {
                                    $stateTaskFundsStartYear = 0;
                                }
                                if (isset($ReportItem->stateTaskFundsEndYear)) {
                                    $stateTaskFundsEndYear = (double) $reportSubItem->stateTaskFundsEndYear;
                                } else {
                                    $stateTaskFundsEndYear = 0;
                                }
                                if (isset($ReportItem->revenueFundsStartYear)) {
                                    $revenueFundsStartYear = (double) $reportSubItem->revenueFundsStartYear;
                                } else {
                                    $revenueFundsStartYear = 0;
                                }
                                if (isset($ReportItem->revenueFundsEndYear)) {
                                    $revenueFundsEndYear = (double) $reportSubItem->revenueFundsEndYear;
                                } else {
                                    $revenueFundsEndYear = 0;
                                }
                                if (isset($ReportItem->totalStartYear)) {
                                    $totalStartYear = (double) $reportSubItem->totalStartYear;
                                } else {
                                    $totalStartYear = 0;
                                }
                                if (isset($ReportItem->totalEndYear)) {
                                    $totalEndYear = (double) $reportSubItem->totalEndYear;
                                } else {
                                    $totalEndYear = 0;
                                }
                                $parent = $this->insertRow($page, $name, $code, $analyticCode, $targetFundsStartYear, $targetFundsEndYear, $stateTaskFundsStartYear, $stateTaskFundsEndYear, $revenueFundsStartYear, $revenueFundsEndYear, $totalStartYear, $totalEndYear, $parent);
                                //echo $sub;
                            }
                        }
                    }
                }
            }

            return TRUE;
        } else {
            //print_r($this);
            APP::echo_log("[ALARM] Для файла $this->filename нет информации о ГОДЕ или владельце.");
            return FALSE;
        }
    }

    private function insertRowFast($id,$page, $name, $code, $analyticCode, $targetFundsStartYear, $targetFundsEndYear, $stateTaskFundsStartYear, $stateTaskFundsEndYear, $revenueFundsStartYear, $revenueFundsEndYear, $totalStartYear, $totalEndYear, $parent) {
        $array = [];
        $array['code'] = $code;
        $array['name'] = trim($name);
        $lineCode = new BusNsi($this->db, 'Balance_codes', $array);
        $lineCode_id = $lineCode->Save(true);

        $statement = '('.$id.','.$this->id.','.$page.','.$lineCode_id.',"'.$this->db->real_escape_string($analyticCode).'",'.$targetFundsStartYear.','.$targetFundsEndYear.','.$stateTaskFundsStartYear.','.$stateTaskFundsEndYear.','.$revenueFundsStartYear.','.$revenueFundsEndYear.','.$totalStartYear.','.$totalEndYear.','.$parent.')';
        return $statement;
    }

    public function SaveFast() {

        if ($this->GeneralInfo > 0 and $this->formationPeriod > 0) {
            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_BalanceF0503730 WHERE GeneralInfo = ? and formationPeriod = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('si', $this->GeneralInfo, $this->formationPeriod);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            if ($stmt->fetch()) {
                $changeDate_in_db = $changeDate;
                $this->id = $id;
                APP::echo_log("В базе уже есть отчет от ".$changeDate_in_db,true);
            } else {
                $this->id = 0;
                $changeDate_in_db = '1900-01-01 00:00:01';
                APP::echo_log("В базе нет отчета.",true);
            }
            $old_data_in_file = App::Date1MoreThenDate2Bus($changeDate_in_db,$this->changeDate);
            if ((!$old_data_in_file) and $this->GeneralInfo > 0) {
                $stmt = $this->db->prepare('DELETE FROM bus_BalanceF0503730 WHERE id = ?');
                $stmt->bind_param('i', $this->id);
                $stmt->execute();

                $sql = 'INSERT INTO bus_BalanceF0503730 (GeneralInfo,changeDate,formationPeriod,versionNumber) VALUES (?, ?, ?, ?)';
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('isis', $this->GeneralInfo, $this->changeDate, $this->formationPeriod, $this->versionNumber);
                $stmt->execute();
                $this->id = mysqli_insert_id($this->db);

                $stmt = $this->db->prepare('SELECT MAX(id) AS ID FROM bus_BalanceF0503730_records');
                $stmt->execute();
                $stmt->bind_result($id);
                $stmt->store_result();
                $new_id = 1;
                if ($stmt->fetch()) {
                    $new_id = $id + 1;
                }
                $statements = [];


                for ($page = 1; $page <= 4; $page++) {
                    $parent = 0;
                    if ($page == 1) {
                        $arr = $this->nonFinancialAssets->reportItem;
                    } elseif ($page == 2) {
                        $arr = $this->financialAssets->reportItem;
                    } elseif ($page == 3) {
                        $arr = $this->commitments->reportItem;
                    } elseif ($page == 4) {
                        $arr = $this->financialResult->reportItem;
                    }

                    foreach ($arr as $ReportItem) {

                        if (isset($ReportItem->name)) {
                            $name = trim((string) $ReportItem->name);
                        } else {
                            $name = '<Пустое наименование>';
                        }
                        if (isset($ReportItem->lineCode)) {
                            $code = trim((string) $ReportItem->lineCode);
                        } else {
                            $code = 'XXX';
                        }
                        if (isset($ReportItem->analyticCode)) {
                            $analyticCode = trim((string) $ReportItem->analyticCode);
                        } else {
                            $analyticCode = 'XXX';
                        }

                        if (isset($ReportItem->targetFundsStartYear)) {
                            $targetFundsStartYear = (double) $ReportItem->targetFundsStartYear;
                        } else {
                            $targetFundsStartYear = 0;
                        }
                        if (isset($ReportItem->targetFundsEndYear)) {
                            $targetFundsEndYear = (double) $ReportItem->targetFundsEndYear;
                        } else {
                            $targetFundsEndYear = 0;
                        }

                        if (isset($ReportItem->stateTaskFundsStartYear)) {
                            $stateTaskFundsStartYear = (double) $ReportItem->stateTaskFundsStartYear;
                        } else {
                            $stateTaskFundsStartYear = 0;
                        }
                        if (isset($ReportItem->stateTaskFundsEndYear)) {
                            $stateTaskFundsEndYear = (double) $ReportItem->stateTaskFundsEndYear;
                        } else {
                            $stateTaskFundsEndYear = 0;
                        }
                        if (isset($ReportItem->revenueFundsStartYear)) {
                            $revenueFundsStartYear = (double) $ReportItem->revenueFundsStartYear;
                        } else {
                            $revenueFundsStartYear = 0;
                        }
                        if (isset($ReportItem->revenueFundsEndYear)) {
                            $revenueFundsEndYear = (double) $ReportItem->revenueFundsEndYear;
                        } else {
                            $revenueFundsEndYear = 0;
                        }
                        if (isset($ReportItem->totalStartYear)) {
                            $totalStartYear = (double) $ReportItem->totalStartYear;
                        } else {
                            $totalStartYear = 0;
                        }
                        if (isset($ReportItem->totalEndYear)) {
                            $totalEndYear = (double) $ReportItem->totalEndYear;
                        } else {
                            $totalEndYear = 0;
                        }
                        $parent = $new_id;
                        $statement = $this->insertRowFast($new_id,$page, $name, $code, $analyticCode, $targetFundsStartYear, $targetFundsEndYear, $stateTaskFundsStartYear, $stateTaskFundsEndYear, $revenueFundsStartYear, $revenueFundsEndYear, $totalStartYear, $totalEndYear, 0);
                        array_push($statements,$statement);
                        $new_id++;

                        if (isset($ReportItem->reportSubItem)) {
                            foreach ($ReportItem->reportSubItem as $reportSubItem) {
                                if (isset($ReportItem->name)) {
                                    $name = trim((string) $reportSubItem->name);
                                } else {
                                    $name = '<Пустое наименование>';
                                }
                                if (isset($ReportItem->lineCode)) {
                                    $code = trim((string) $reportSubItem->lineCode);
                                } else {
                                    $code = 'XXX';
                                }
                                if (isset($ReportItem->analyticCode)) {
                                    $analyticCode = trim((string) $reportSubItem->analyticCode);
                                } else {
                                    $analyticCode = 'XXX';
                                }
                                if (isset($ReportItem->targetFundsStartYear)) {
                                    $targetFundsStartYear = (double) $reportSubItem->targetFundsStartYear;
                                } else {
                                    $targetFundsStartYear = 0;
                                }
                                if (isset($ReportItem->targetFundsEndYear)) {
                                    $targetFundsEndYear = (double) $reportSubItem->targetFundsEndYear;
                                } else {
                                    $targetFundsEndYear = 0;
                                }

                                if (isset($ReportItem->stateTaskFundsStartYear)) {
                                    $stateTaskFundsStartYear = (double) $reportSubItem->stateTaskFundsStartYear;
                                } else {
                                    $stateTaskFundsStartYear = 0;
                                }
                                if (isset($ReportItem->stateTaskFundsEndYear)) {
                                    $stateTaskFundsEndYear = (double) $reportSubItem->stateTaskFundsEndYear;
                                } else {
                                    $stateTaskFundsEndYear = 0;
                                }
                                if (isset($ReportItem->revenueFundsStartYear)) {
                                    $revenueFundsStartYear = (double) $reportSubItem->revenueFundsStartYear;
                                } else {
                                    $revenueFundsStartYear = 0;
                                }
                                if (isset($ReportItem->revenueFundsEndYear)) {
                                    $revenueFundsEndYear = (double) $reportSubItem->revenueFundsEndYear;
                                } else {
                                    $revenueFundsEndYear = 0;
                                }
                                if (isset($ReportItem->totalStartYear)) {
                                    $totalStartYear = (double) $reportSubItem->totalStartYear;
                                } else {
                                    $totalStartYear = 0;
                                }
                                if (isset($ReportItem->totalEndYear)) {
                                    $totalEndYear = (double) $reportSubItem->totalEndYear;
                                } else {
                                    $totalEndYear = 0;
                                }
                                $statement = $this->insertRowFast($new_id,$page, $name, $code, $analyticCode, $targetFundsStartYear, $targetFundsEndYear, $stateTaskFundsStartYear, $stateTaskFundsEndYear, $revenueFundsStartYear, $revenueFundsEndYear, $totalStartYear, $totalEndYear, $parent);
                                array_push($statements,$statement);
                                $new_id++;
                            }
                        }
                    }
                }

                if (sizeof($statements) > 0) {
                  $query = 'INSERT INTO bus_BalanceF0503730_records (id,Balance,page,lineCode,analyticCode,targetFundsStartYear,targetFundsEndYear,stateTaskFundsStartYear,stateTaskFundsEndYear,revenueFundsStartYear,revenueFundsEndYear,totalStartYear,totalEndYear,parent) VALUES '.PHP_EOL;
                  foreach ($statements as $key => $value) {
                    $query.= $value;
                    if ( $key <> sizeof($statements) - 1) $query.= ',';
                    $query.= PHP_EOL;
                  }
                  //echo $query.PHP_EOL;
                  //APP::echo_log($query.PHP_EOL,true);
                  $this->db->query($query);
                  APP::echo_log("Количество строк в отчете: " . sizeof($statements),true);
                  return TRUE;
                } else {
                  APP::echo_log("Файл пустой ".$this->filename,true);
                  return FALSE;
                }
            } else {
              //если отчет старый
              APP::echo_log("Устаревшие данные в ".$this->filename.". В базе есть отчет от ".$changeDate_in_db." в файле ".$this->changeDate,true);
              return FALSE;
            }


        } else {
            APP::echo_log("[ALARM] Для файла $this->filename нет информации о ГОДЕ или владельце.");
            return FALSE;
        }
    }

}

class BusBalanceF0503737 extends Bus {

    public $id = 0;
    public $filename = '';
    public $kpp;
    public $inn;
    public $GeneralInfo = 0;
    public $formationPeriod = 0;
    public $versionNumber;
    public $changeDate;
    public $fundingSources = []; //Источники
    public $income = [];  //Доходы
    public $expense = []; //Расходы
    public $returnExpense = []; //Возвраты
    public $reportData = []; // ВидФинОбеспеч->incomes и тд
    public $db;

    function __construct($db, $inn = '', $kpp = '') {
        $this->db = $db;
        $this->inn = $inn;
        $this->kpp = $kpp;
        if ($inn != '') {
            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('s', $this->inn);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            $this->GeneralInfo = $id;
        }
    }

    public function FillFromXML($xml_file) {
        $this->filename = $xml_file;
        $bus = simplexml_load_file($xml_file);
        $ns2 = $bus->getNamespaces(true);
        $body = $bus->children($ns2['ns2'])->body;
        $position = $body->position->children($ns2['']);

        //print_r($position);


        $this->changeDate = APP::getDateTimeFromBus($position->changeDate);
        $this->versionNumber = (string) $position->versionNumber;
        $initiator = $position->initiator;
        $this->inn = (string) $initiator->inn;
        $this->kpp = (string) $initiator->kpp;
        $this->regNum = (string) $initiator->regNum;
        $GeneralInfo = new BusGeneralInfo($this->db, $this->inn,$this->regNum,$this->kpp,$xml_file);
        $this->GeneralInfo = $GeneralInfo->id;
        $this->formationPeriod = (int) $position->formationPeriod;

        if (isset($position->generalData->typeFinancialSupport)) {
            $this->reportData[(int) $position->generalData->typeFinancialSupport]['income'] = $position->income;
            $this->reportData[(int) $position->generalData->typeFinancialSupport]['expense'] = $position->expense;
            $this->reportData[(int) $position->generalData->typeFinancialSupport]['fundingSources'] = $position->fundingSources;
            $this->reportData[(int) $position->generalData->typeFinancialSupport]['returnExpense'] = $position->returnExpense;
        } else {
            foreach ($position->generalData->financialSupportData as $financialSupportData) {
                $this->reportData[(int) $financialSupportData->typeFinancialSupport]['income'] = $financialSupportData->income;
                $this->reportData[(int) $financialSupportData->typeFinancialSupport]['expense'] = $financialSupportData->expense;
                $this->reportData[(int) $financialSupportData->typeFinancialSupport]['fundingSources'] = $financialSupportData->fundingSources;
                $this->reportData[(int) $financialSupportData->typeFinancialSupport]['returnExpense'] = $financialSupportData->returnExpense;
            }
        }

        //print_r($this);
        unset($GeneralInfo);
    }

    private function insertRow($typeFinancialSupport, $page, $name, $code, $analyticCode, $approvedPlanAssignments, $execPersonalAuthorities, $execBankAccounts, $execCashAgency, $execNonCashOperations, $execTotal, $unexecPlanAssignments, $parent) {
        $array = [];
        $array['code'] = $code;
        $array['name'] = trim($name);
        $lineCode = new BusNsi($this->db, 'Balance_codes', $array);
        $lineCode_id = $lineCode->Save(true);


        $sql = 'INSERT INTO bus_BalanceF0503737_records (Balance,typeFinancialSupport,page,lineCode,analyticCode,'
                . 'approvedPlanAssignments,execPersonalAuthorities,execBankAccounts,execCashAgency,'
                . 'execNonCashOperations,execTotal,unexecPlanAssignments,parent) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('iiiisdddddddi', $this->id, $typeFinancialSupport, $page, $lineCode_id, $analyticCode, $approvedPlanAssignments, $execPersonalAuthorities, $execBankAccounts, $execCashAgency, $execNonCashOperations, $execTotal, $unexecPlanAssignments, $parent);
        $stmt->execute();
        $id = mysqli_insert_id($this->db);
        return $id;
    }

    public function Save() {
          //print_r($this);
        if ($this->GeneralInfo > 0 and $this->formationPeriod > 0) {

            //echo date("d.m.y").' '.date("H:i:s").'   поиск существующего по GeneralInfo и formationPeriod'.PHP_EOL.PHP_EOL;

            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_BalanceF0503737 WHERE GeneralInfo = ? and formationPeriod = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('si', $this->GeneralInfo, $this->formationPeriod);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            if ($stmt->fetch()) {
                $old_time = $changeDate;
                $this->id = $id;
            } else {
                $this->id = 0;
                $old_time = '1900-01-01 00:00:01';
            }
            //echo date("d.m.y").' '.date("H:i:s").'   поиск завершил'.PHP_EOL.PHP_EOL;
            $old_data_in_file = FALSE;

            if (isset($old_time)) {
                $old_timestemp = strtotime($old_time);
                $new_timestemp = strtotime($this->changeDate);
                if ($new_timestemp < $old_timestemp) {
                    $old_data_in_file = TRUE;
                }
            }
            //if ($old_data_in_file)
              //                {echo date("d.m.y").' '.date("H:i:s").'   определили, что в файле СТАРЫЕ данные'.PHP_EOL.PHP_EOL;}
              //            else {echo date("d.m.y").' '.date("H:i:s").'   определили, что в файле НОВЫЕ данные'.PHP_EOL.PHP_EOL;}
            if ((!$old_data_in_file) and $this->GeneralInfo > 0) {
                /* отчистим предыдущую информацию */
                /* В базе сделал каскадное удаление
                $stmt = $this->db->prepare('DELETE FROM bus_BalanceF0503737_records WHERE Balance = ?');
                $stmt->bind_param('i', $this->id);
                $stmt->execute();*/
                //echo date("d.m.y").' '.date("H:i:s").'   чистим устаревшие данные'.PHP_EOL.PHP_EOL;
                $stmt = $this->db->prepare('DELETE FROM bus_BalanceF0503737 WHERE id = ?');
                $stmt->bind_param('i', $this->id);
                $stmt->execute();
                //echo date("d.m.y").' '.date("H:i:s").'   закончили чистку'.PHP_EOL.PHP_EOL;


                //echo date("d.m.y").' '.date("H:i:s").'   Начали вставку'.PHP_EOL.PHP_EOL;
                $sql = 'INSERT INTO bus_BalanceF0503737 (GeneralInfo,changeDate,formationPeriod,versionNumber) VALUES (?, ?, ?, ?)';
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('isis', $this->GeneralInfo, $this->changeDate, $this->formationPeriod, $this->versionNumber);
                $stmt->execute();
                $this->id = mysqli_insert_id($this->db);

                foreach ($this->reportData as $type => $data) {
                    for ($page = 1; $page <= 4; $page++) {
                        $skip = false;
                        $parent = 0;
                        if ($page == 1) {
                            if (isset($data['income'])) {
                                $arr = $data['income']->reportItem;
                            } else {
                                $skip = true;
                            }
                        } elseif ($page == 2) {
                            if (isset($data['expense'])) {
                                $arr = $data['expense']->reportItem;
                            } else {
                                $skip = true;
                            }
                        } elseif ($page == 3) {
                            if (isset($data['fundingSources'])) {
                                $arr = $data['fundingSources']->reportItem;
                            } else {
                                $skip = true;
                            }
                        } elseif ($page == 4) {
                            if (isset($data['returnExpense'])) {
                                $arr = $data['returnExpense']->reportItem;
                            } else {
                                $skip = true;
                            }
                        }
                        if (count($arr) == 0)
                            $skip = true;
                        if ($skip)
                            continue;


                        foreach ($arr as $ReportItem) {
                            //print_r($ReportItem);
                            if (isset($ReportItem->name)) {
                                $name = trim((string) $ReportItem->name);
                            } else {
                                $name = '<Пустое наименование>';
                            }
                            if (isset($ReportItem->lineCode)) {
                                $code = trim((string) $ReportItem->lineCode);
                            } else {
                                $code = 'XXX';
                            }
                            if (isset($ReportItem->analyticCode)) {
                                $analyticCode = trim((string) $ReportItem->analyticCode);
                            } else {
                                $analyticCode = 'XXX';
                            }

                            if (isset($ReportItem->approvedPlanAssignments)) {
                                $approvedPlanAssignments = (double) $ReportItem->approvedPlanAssignments;
                            } else {
                                $approvedPlanAssignments = 0;
                            }
                            if (isset($ReportItem->execPersonalAuthorities)) {
                                $execPersonalAuthorities = (double) $ReportItem->execPersonalAuthorities;
                            } else {
                                $execPersonalAuthorities = 0;
                            }

                            if (isset($ReportItem->execBankAccounts)) {
                                $execBankAccounts = (double) $ReportItem->execBankAccounts;
                            } else {
                                $execBankAccounts = 0;
                            }
                            if (isset($ReportItem->execCashAgency)) {
                                $execCashAgency = (double) $ReportItem->execCashAgency;
                            } else {
                                $execCashAgency = 0;
                            }
                            if (isset($ReportItem->execNonCashOperations)) {
                                $execNonCashOperations = (double) $ReportItem->execNonCashOperations;
                            } else {
                                $execNonCashOperations = 0;
                            }
                            if (isset($ReportItem->execTotal)) {
                                $execTotal = (double) $ReportItem->execTotal;
                            } else {
                                $execTotal = 0;
                            }
                            if (isset($ReportItem->unexecPlanAssignments)) {
                                $unexecPlanAssignments = (double) $ReportItem->unexecPlanAssignments;
                            } else {
                                $unexecPlanAssignments = 0;
                            }

                            $parent = $this->insertRow($type, $page, $name, $code, $analyticCode, $approvedPlanAssignments, $execPersonalAuthorities, $execBankAccounts, $execCashAgency, $execNonCashOperations, $execTotal, $unexecPlanAssignments, 0);

                            if (isset($ReportItem->reportSubItem)) {
                                //print_r($ReportItem->reportSubItem);
                                foreach ($ReportItem->reportSubItem as $reportSubItem) {
                                    if (isset($ReportItem->name)) {
                                        $name = trim((string) $reportSubItem->name);
                                    } else {
                                        $name = '<Пустое наименование>';
                                    }
                                    if (isset($ReportItem->lineCode)) {
                                        $code = trim((string) $reportSubItem->lineCode);
                                    } else {
                                        $code = 'XXX';
                                    }
                                    if (isset($ReportItem->analyticCode)) {
                                        $analyticCode = trim((string) $reportSubItem->analyticCode);
                                    } else {
                                        $analyticCode = 'XXX';
                                    }
                                    if (isset($ReportItem->approvedPlanAssignments)) {
                                        $approvedPlanAssignments = (double) $reportSubItem->approvedPlanAssignments;
                                    } else {
                                        $approvedPlanAssignments = 0;
                                    }
                                    if (isset($ReportItem->execPersonalAuthorities)) {
                                        $execPersonalAuthorities = (double) $reportSubItem->execPersonalAuthorities;
                                    } else {
                                        $execPersonalAuthorities = 0;
                                    }

                                    if (isset($ReportItem->execBankAccounts)) {
                                        $execBankAccounts = (double) $reportSubItem->execBankAccounts;
                                    } else {
                                        $execBankAccounts = 0;
                                    }
                                    if (isset($ReportItem->execCashAgency)) {
                                        $execCashAgency = (double) $reportSubItem->execCashAgency;
                                    } else {
                                        $execCashAgency = 0;
                                    }
                                    if (isset($ReportItem->execNonCashOperations)) {
                                        $execNonCashOperations = (double) $reportSubItem->execNonCashOperations;
                                    } else {
                                        $execNonCashOperations = 0;
                                    }
                                    if (isset($ReportItem->execTotal)) {
                                        $execTotal = (double) $reportSubItem->execTotal;
                                    } else {
                                        $execTotal = 0;
                                    }
                                    if (isset($ReportItem->unexecPlanAssignments)) {
                                        $unexecPlanAssignments = (double) $reportSubItem->unexecPlanAssignments;
                                    } else {
                                        $unexecPlanAssignments = 0;
                                    }

                                    $parent = $this->insertRow($type, $page, $name, $code, $analyticCode, $approvedPlanAssignments, $execPersonalAuthorities, $execBankAccounts, $execCashAgency, $execNonCashOperations, $execTotal, $unexecPlanAssignments, $parent);
                                }
                            }
                        }
                    }
                }
            }
            //echo date("d.m.y").' '.date("H:i:s").'   Закончили вставку'.PHP_EOL.PHP_EOL;
            return TRUE;
        } else {
            //print_r($this);
            APP::echo_log("[ALARM] Для файла $this->filename нет информации о ГОДЕ или владельце.");
            return FALSE;
        }
    }

    private function insertRowFast($id,$typeFinancialSupport, $page, $name, $code, $analyticCode, $approvedPlanAssignments, $execPersonalAuthorities, $execBankAccounts, $execCashAgency, $execNonCashOperations, $execTotal, $unexecPlanAssignments, $parent) {
        $array = [];
        $array['code'] = $code;
        $array['name'] = trim($name);
        $lineCode = new BusNsi($this->db, 'Balance_codes', $array);
        $lineCode_id = $lineCode->Save(true);
        $statement = '('.$id.','.$this->id.','.$typeFinancialSupport.','.$page.','.$lineCode_id.',"'.$this->db->real_escape_string($analyticCode).'",'.$approvedPlanAssignments.','.$execPersonalAuthorities.','.$execBankAccounts.','.$execCashAgency.','.$execNonCashOperations.','.$execTotal.','.$unexecPlanAssignments.','.$parent.')';
        return $statement;
    }

    public function SaveFast() {
        if ($this->GeneralInfo == 0 or $this->formationPeriod == 0) {
            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_BalanceF0503737 WHERE GeneralInfo = ? and formationPeriod = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('si', $this->GeneralInfo, $this->formationPeriod);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            if ($stmt->fetch()) {
                $changeDate_in_db = $changeDate;
                $this->id = $id;
                APP::echo_log("В базе уже есть отчет от ".$changeDate_in_db." в файле данные от ".$this->changeDate,true);
            } else {
                $this->id = 0;
                $changeDate_in_db = '1900-01-01 00:00:01';
                APP::echo_log("В базе нет отчета.",true);
            }
            $stmt->close();
            //$old_data_in_file = App::Date1MoreThenDate2Bus($this->changeDate,$changeDate_in_db);

            if (App::Date1MoreThenDate2Bus($this->changeDate,$changeDate_in_db)) {
                $stmt = $this->db->prepare('DELETE FROM bus_BalanceF0503737 WHERE id = ?');
                $stmt->bind_param('i', $this->id);
                $stmt->execute();
                $stmt->close();
                $sql = 'INSERT INTO bus_BalanceF0503737 (GeneralInfo,changeDate,formationPeriod,versionNumber) VALUES (?, ?, ?, ?)';
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('isis', $this->GeneralInfo, $this->changeDate, $this->formationPeriod, $this->versionNumber);
                $stmt->execute();
                $this->id = mysqli_insert_id($this->db);
                $stmt->close();
                $stmt = $this->db->prepare('SELECT MAX(id) AS ID FROM bus_BalanceF0503737_records');
                $stmt->execute();
                $stmt->bind_result($id);
                $stmt->store_result();
                $new_id = 1;
                if ($stmt->fetch()) {
                    $new_id = $id + 1;
                }
                $stmt->close();
                $statements = [];
                foreach ($this->reportData as $type => $data) {
                    for ($page = 1; $page <= 4; $page++) {
                        $skip = false;
                        $parent = 0;
                        if ($page == 1) {
                            if (isset($data['income'])) {
                                $arr = $data['income']->reportItem;
                            } else {
                                $skip = true;
                            }
                        } elseif ($page == 2) {
                            if (isset($data['expense'])) {
                                $arr = $data['expense']->reportItem;
                            } else {
                                $skip = true;
                            }
                        } elseif ($page == 3) {
                            if (isset($data['fundingSources'])) {
                                $arr = $data['fundingSources']->reportItem;
                            } else {
                                $skip = true;
                            }
                        } elseif ($page == 4) {
                            if (isset($data['returnExpense'])) {
                                $arr = $data['returnExpense']->reportItem;
                            } else {
                                $skip = true;
                            }
                        }
                        if (count($arr) == 0)
                            $skip = true;
                        if ($skip) continue;


                        foreach ($arr as $ReportItem) {
                            //print_r($ReportItem);
                            if (isset($ReportItem->name)) {
                                $name = trim((string) $ReportItem->name);
                            } else {
                                $name = '<Пустое наименование>';
                            }
                            if (isset($ReportItem->lineCode)) {
                                $code = trim((string) $ReportItem->lineCode);
                            } else {
                                $code = 'XXX';
                            }
                            if (isset($ReportItem->analyticCode)) {
                                $analyticCode = trim((string) $ReportItem->analyticCode);
                            } else {
                                $analyticCode = 'XXX';
                            }

                            if (isset($ReportItem->approvedPlanAssignments)) {
                                $approvedPlanAssignments = (double) $ReportItem->approvedPlanAssignments;
                            } else {
                                $approvedPlanAssignments = 0;
                            }
                            if (isset($ReportItem->execPersonalAuthorities)) {
                                $execPersonalAuthorities = (double) $ReportItem->execPersonalAuthorities;
                            } else {
                                $execPersonalAuthorities = 0;
                            }

                            if (isset($ReportItem->execBankAccounts)) {
                                $execBankAccounts = (double) $ReportItem->execBankAccounts;
                            } else {
                                $execBankAccounts = 0;
                            }
                            if (isset($ReportItem->execCashAgency)) {
                                $execCashAgency = (double) $ReportItem->execCashAgency;
                            } else {
                                $execCashAgency = 0;
                            }
                            if (isset($ReportItem->execNonCashOperations)) {
                                $execNonCashOperations = (double) $ReportItem->execNonCashOperations;
                            } else {
                                $execNonCashOperations = 0;
                            }
                            if (isset($ReportItem->execTotal)) {
                                $execTotal = (double) $ReportItem->execTotal;
                            } else {
                                $execTotal = 0;
                            }
                            if (isset($ReportItem->unexecPlanAssignments)) {
                                $unexecPlanAssignments = (double) $ReportItem->unexecPlanAssignments;
                            } else {
                                $unexecPlanAssignments = 0;
                            }
                            $parent = $new_id;
                            $statement = $this->insertRowFast($new_id,$type, $page, $name, $code, $analyticCode, $approvedPlanAssignments, $execPersonalAuthorities, $execBankAccounts, $execCashAgency, $execNonCashOperations, $execTotal, $unexecPlanAssignments, 0);
                            array_push($statements,$statement);
                            $new_id++;

                            if (isset($ReportItem->reportSubItem)) {
                                //print_r($ReportItem->reportSubItem);
                                foreach ($ReportItem->reportSubItem as $reportSubItem) {
                                    if (isset($ReportItem->name)) {
                                        $name = trim((string) $reportSubItem->name);
                                    } else {
                                        $name = '<Пустое наименование>';
                                    }
                                    if (isset($ReportItem->lineCode)) {
                                        $code = trim((string) $reportSubItem->lineCode);
                                    } else {
                                        $code = 'XXX';
                                    }
                                    if (isset($ReportItem->analyticCode)) {
                                        $analyticCode = trim((string) $reportSubItem->analyticCode);
                                    } else {
                                        $analyticCode = 'XXX';
                                    }
                                    if (isset($ReportItem->approvedPlanAssignments)) {
                                        $approvedPlanAssignments = (double) $reportSubItem->approvedPlanAssignments;
                                    } else {
                                        $approvedPlanAssignments = 0;
                                    }
                                    if (isset($ReportItem->execPersonalAuthorities)) {
                                        $execPersonalAuthorities = (double) $reportSubItem->execPersonalAuthorities;
                                    } else {
                                        $execPersonalAuthorities = 0;
                                    }

                                    if (isset($ReportItem->execBankAccounts)) {
                                        $execBankAccounts = (double) $reportSubItem->execBankAccounts;
                                    } else {
                                        $execBankAccounts = 0;
                                    }
                                    if (isset($ReportItem->execCashAgency)) {
                                        $execCashAgency = (double) $reportSubItem->execCashAgency;
                                    } else {
                                        $execCashAgency = 0;
                                    }
                                    if (isset($ReportItem->execNonCashOperations)) {
                                        $execNonCashOperations = (double) $reportSubItem->execNonCashOperations;
                                    } else {
                                        $execNonCashOperations = 0;
                                    }
                                    if (isset($ReportItem->execTotal)) {
                                        $execTotal = (double) $reportSubItem->execTotal;
                                    } else {
                                        $execTotal = 0;
                                    }
                                    if (isset($ReportItem->unexecPlanAssignments)) {
                                        $unexecPlanAssignments = (double) $reportSubItem->unexecPlanAssignments;
                                    } else {
                                        $unexecPlanAssignments = 0;
                                    }

                                    $statement = $this->insertRowFast($new_id,$type, $page, $name, $code, $analyticCode, $approvedPlanAssignments, $execPersonalAuthorities, $execBankAccounts, $execCashAgency, $execNonCashOperations, $execTotal, $unexecPlanAssignments, $parent);
                                    array_push($statements,$statement);
                                    $new_id++;
                                }
                            }
                        }
                    }
                }

              if (sizeof($statements) > 0) {
                $query = 'INSERT INTO bus_BalanceF0503737_records (id,Balance,typeFinancialSupport,page,lineCode,analyticCode,approvedPlanAssignments,execPersonalAuthorities,execBankAccounts,execCashAgency,execNonCashOperations,execTotal,unexecPlanAssignments,parent) VALUES '.PHP_EOL;
                foreach ($statements as $key => $value) {
                  $query.= $value;
                  if ( $key <> sizeof($statements) - 1) $query.= ',';
                  $query.= PHP_EOL;
                }
              //  echo $query.PHP_EOL;
                //APP::echo_log($query.PHP_EOL,true);
                $this->db->query($query);
                APP::echo_log("Количество строк в отчете: " . sizeof($statements),true);
                return TRUE;
              } else {
                APP::echo_log("Файл пустой ".$this->filename,true);
                return FALSE;
              }


            }
            else {
              APP::echo_log("Устаревшие данные в ".$this->filename.". В базе есть отчет от ".$changeDate_in_db." в файле ".$this->changeDate,true);
              return FALSE;
            }

        } else {
            //print_r($this);
            APP::echo_log("[ALARM] Для файла $this->filename нет информации о ГОДЕ или владельце.");
            return FALSE;
        }
    }
}


class BusStateTask extends Bus {

    public $id = 0;
    public $filename = '';
    public $kpp;
    public $inn;
    public $regNum;
    public $GeneralInfo = 0;

    public $versionNumber;
    public $changeDate;

    public $reportYear = 0;
    public $financialYear = 0;
    public $nextFinancialYear = 0;
    public $planFirstYear = 0;
    public $planLastYear = 0;
    public $reportName = "";
    public $reportDate = null;
    public $reportGUID = "";

    public $services = []; //Услуги и работы


    public $db;

    function __construct($db, $inn = '', $kpp = '') {
        $this->db = $db;
        $this->inn = $inn;
        $this->kpp = $kpp;
        if ($inn != '') {
            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('s', $this->inn);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            $this->GeneralInfo = $id;
        }
    }

    public function FillFromXML($xml_file) {
        //echo PHP_EOL."                                                            точка 1";
        $this->filename = $xml_file;
        $bus = simplexml_load_file($xml_file);
        $ns2 = $bus->getNamespaces(true);
        $body = $bus->children($ns2['ns2'])->body;
        $position = $body->position->children($ns2['']);

        //print_r($position);

        $this->changeDate = APP::getDateTimeFromBus($position->changeDate);
        $this->versionNumber = (string) $position->versionNumber;

        $initiator = $position->initiator;
        $this->inn = (string) $initiator->inn;
        $this->kpp = (string) $initiator->kpp;
        $this->regNum = (string) $initiator->regNum;
        $GeneralInfo = new BusGeneralInfo($this->db, $this->inn,$this->regNum,$this->kpp,$xml_file);
        $this->GeneralInfo = $GeneralInfo->id;
        if (isset($position->reportYear)) {
            $this->reportYear = (int) $position->reportYear;
        }

        if (isset($position->financialYear)) {
            $this->financialYear = (int) $position->financialYear;
        }

        if (isset($position->nextFinancialYear)) {
            $this->nextFinancialYear = (int) $position->nextFinancialYear;
        }

        if (isset($position->planFirstYear)) {
            $this->planFirstYear = (int) $position->planFirstYear;
        }
        if (isset($position->planLastYear)) {
            $this->planLastYear = (int) $position->planLastYear;
        }

        $this->reportDate = date('1900-01-01');
        $this->reportGUID = "";
        $this->reportName = "";
        if (isset($position->reports)) {
            foreach ($position->reports as $report) {
                    $reportGUID = (string) $report->reportGUID;
                    $reportDate = APP::getDateTimeFromBus($report->date);
                    if ($reportDate > $this->reportDate) {
                        $this->reportDate = $reportDate;
                        $this->reportGUID = $reportGUID;
                        $this->reportName = (string) $report->periodInfo;
                    }
            }
        }

        $this->services = [];
        $count_serv = 0;
            foreach ($position->service as $service) {
                $count_serv++;
                $this->services[$count_serv]['name'] = trim((string)$service->name);
                if (trim((string)$service->type) == 'S') {
                    $this->services[$count_serv]['type'] = 1;
                } else {
                    $this->services[$count_serv]['type'] = 2;
                }
                $this->services[$count_serv]['code'] = trim((string)$service->uniqueNumber);
                if ($this->services[$count_serv]['code'] == "") $this->services[$count_serv]['code'] = trim((string)$service->code);
                $this->services[$count_serv]['ordinalNumber'] = (int)$service->ordinalNumber;
                $i = 0;
                foreach ($service->category as $cat) {
                    $i++;
                    $this->services[$count_serv]['category'][$i] = trim((string)$cat->name);
                }


                $count_qi = 0;
                for ($i = 1; $i <=2 ; $i++) {
                    if ($i == 1)  { $Curent_Indexes = $service->qualityIndex;}
                    else { $Curent_Indexes = $service->volumeIndex;}
                    foreach ($Curent_Indexes as $qI) {
                        $count_qi++;
                        $this->services[$count_serv]['indexes'][$count_qi]['code'] = $i;
                        $this->services[$count_serv]['indexes'][$count_qi]['regNum'] = trim((string)$qI->index->regNum);
                        $this->services[$count_serv]['indexes'][$count_qi]['name'] = trim((string)$qI->index->name);
                        $this->services[$count_serv]['indexes'][$count_qi]['unit_code'] = trim((string)$qI->index->unit->code);
                        $this->services[$count_serv]['indexes'][$count_qi]['unit_name'] = trim((string)$qI->index->unit->symbol);
                        $this->services[$count_serv]['indexes'][$count_qi]['deviation'] = trim((string)$qI->deviation);
                        $this->services[$count_serv]['indexes'][$count_qi]['nextYear'] = trim((string)$qI->valueYear->nextYear);
                        $this->services[$count_serv]['indexes'][$count_qi]['planFirstYear'] = trim((string)$qI->valueYear->planFirstYear);
                        $this->services[$count_serv]['indexes'][$count_qi]['planLastYear'] = trim((string)$qI->valueYear->planLastYear);
                        $this->services[$count_serv]['indexes'][$count_qi]['reportYear'] = trim((string)$qI->valueYear->reportYear);
                        $this->services[$count_serv]['indexes'][$count_qi]['currentYear'] = '';
                        if (isset($qI->valueYear->financialYear)) {
                            $this->services[$count_serv]['indexes'][$count_qi]['currentYear'] = trim((string)$qI->valueYear->financialYear);
                        }
                        if (isset($qI->valueYear->currentYear)) {
                            $this->services[$count_serv]['indexes'][$count_qi]['currentYear'] = trim((string)$qI->valueYear->currentYear);
                        }
                        $this->services[$count_serv]['indexes'][$count_qi]['actualValue'] = trim((string)$qI->valueActual->actualValue);
                        $this->services[$count_serv]['indexes'][$count_qi]['rejectReason'] = trim((string)$qI->valueActual->rejectReason);
                        $this->services[$count_serv]['indexes'][$count_qi]['reject'] = trim((string)$qI->valueActual->reject);

                    }
                }


            }

        unset($GeneralInfo);
    }
    private function insertService($code, $name, $type, $ordinalNumber) {
//        echo PHP_EOL."                                                            точка 3";
        $service = new BusNsi($this->db, 'StateTask_services', ['type' => $type, 'code' => $code, 'name' => $name]);
        $service_id = $service->Save(true);
        $sql = 'INSERT INTO bus_StateTask_records (StateTask,service,ordinalNumber) VALUES (?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('iii', $this->id, $service_id, $ordinalNumber);
        $stmt->execute();
        $id = mysqli_insert_id($this->db);
//        echo PHP_EOL."                                                            точка 4";
        return $id;
    }

    private function insertCategory($name, $record) {
//        echo PHP_EOL."                                                            точка 5";
        $cat = new BusNsi($this->db, 'category', ['name' => $name]);
        $cat_id = $cat->Save(true);

        $sql = 'INSERT INTO bus_StateTask_categorys (record,category) VALUES (?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('ii', $record, $cat_id);
        $stmt->execute();
        $id = mysqli_insert_id($this->db);
//        echo PHP_EOL."                                                            точка 6";
        return $id;
    }


    private function insertIndex($record,$index,$index_code,$unit_code,$unit_name,$deviation,$reportYear,$currentYear,$nextYear,$planFirstYear,$planLastYear,$actualValue,$reject,$rejectReason) {
//        echo PHP_EOL."                                                            точка 7";
        $ind = new BusNsi($this->db, 'StateTask_indexes', ['name' => $index,'code' => $index_code]);
        $ind_id = $ind->Save(true);
        $uni = new BusNsi($this->db, 'okei', ['code' => $unit_code,'name' => $unit_name]);
        $uni_id = $uni->Save(true);

        $sql = 'INSERT INTO bus_StateTask_indexes_values (StateTask_record,StateTask_index,unit,deviation,reportYear,currentYear,nextYear,planFirstYear,planLastYear,actualValue,reject,rejectReason) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('iiidddddddds', $record, $ind_id, $uni_id,$deviation,$reportYear,$currentYear,$nextYear,$planFirstYear,$planLastYear,$actualValue,$reject,$rejectReason);
        $stmt->execute();
        $id = mysqli_insert_id($this->db);
//        echo PHP_EOL."                                                            точка 8";
        return $id;
    }


        public function Save() {
        //print_r($this);
//        echo PHP_EOL."                                                            точка 9";
        if ($this->GeneralInfo > 0 and $this->reportYear > 0 ) {

            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_StateTask WHERE GeneralInfo = ? and reportYear = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('si', $this->GeneralInfo, $this->reportYear);
            $stmt->execute();
            $stmt->bind_result($id, $changeDate);
            $stmt->store_result();
            if ($stmt->fetch()) {
                $old_time = $changeDate;
                $this->id = $id;
            } else {
                $this->id = 0;
                $old_time = '1900-01-01 00:00:01';
            }

            $old_data_in_file = FALSE;

            if (isset($old_time)) {
                $old_timestemp = strtotime($old_time);
                $new_timestemp = strtotime($this->changeDate);
                if ($new_timestemp < $old_timestemp) {
                    $old_data_in_file = TRUE;
                }
            }
            if ((!$old_data_in_file) and $this->GeneralInfo > 0) {
                /* отчистим предыдущую информацию */
//                echo PHP_EOL."                                                            точка 11";
                //$stmt = $this->db->prepare('DELETE FROM bus_StateTask_indexes_values WHERE StateTask_record in (SELECT id FROM bus_StateTask_records WHERE StateTask = ?)');
//                $stmt = $this->db->prepare('DELETE i.* FROM bus_StateTask_indexes_values i JOIN bus_StateTask_records r on i.StateTask_record = r.id WHERE r.StateTask = ?');
//                $stmt->bind_param('i', $this->id);
//                $stmt->execute();
//                echo PHP_EOL."                                                            точка 12";
                //$stmt = $this->db->prepare('DELETE FROM bus_StateTask_categorys WHERE record in (SELECT id FROM bus_StateTask_records WHERE StateTask = ?)');
//                $stmt = $this->db->prepare('DELETE c.* FROM bus_StateTask_categorys c JOIN bus_StateTask_records r ON c.record = r.id  WHERE r.StateTask = ?');
//                $stmt->bind_param('i', $this->id);
//                $stmt->execute();
//                echo PHP_EOL."                                                            точка 13";
//                $stmt = $this->db->prepare('DELETE FROM bus_StateTask_records WHERE StateTask = ?');
//                $stmt->bind_param('i', $this->id);
//                $stmt->execute();
//                echo PHP_EOL."                                                            точка 14";
                $stmt = $this->db->prepare('DELETE FROM bus_StateTask WHERE id = ?');
                $stmt->bind_param('i', $this->id);
                $stmt->execute();
//                echo PHP_EOL."                                                            точка 15";


                $sql = 'INSERT INTO bus_StateTask (GeneralInfo,changeDate,reportYear,financialYear,nextFinancialYear,planFirstYear,planLastYear,reportName,reportDate,versionNumber) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('isiiiiisss', $this->GeneralInfo, $this->changeDate, $this->reportYear, $this->financialYear, $this->nextFinancialYear, $this->planFirstYear, $this->planLastYear, $this->reportName, $this->reportDate, $this->versionNumber);
                $stmt->execute();
                $this->id = mysqli_insert_id($this->db);

                if (isset($this->services)) {
                    foreach ($this->services as $service) {
                        $service_record_id = $this->insertService($service['code'], $service['name'], $service['type'], $service['ordinalNumber']);
                        if (isset($service['indexes'])) {
                            foreach ($service['indexes'] as $Index) {
                                $ind_record_id = $this->insertIndex($service_record_id, $Index['name'], $Index['code'], $Index['unit_code'], $Index['unit_name'], $Index['deviation'], $Index['reportYear'], $Index['currentYear'], $Index['nextYear'], $Index['planFirstYear'], $Index['planLastYear'], $Index['actualValue'], $Index['reject'], $Index['rejectReason']);
                            }
                        }
                        if (isset($service['category'])) {
                            foreach ($service['category'] as $key => $Cat) {
                                $cat_record_id = $this->insertCategory($Cat, $service_record_id);
                            }
                        }
                    }
                }
//                echo PHP_EOL."                                                            точка 10";
                return TRUE;
        } else {
            //print_r($this);
            APP::echo_log("[ALARM] Для файла $this->filename нет информации о владельце. ".$this->inn);
            return FALSE;
        }
    }




}

        }
