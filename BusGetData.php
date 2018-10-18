<?php

//namespace App;

class BusGetData extends App{

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

    public function GetFilesFromFTP ($remote_directory,$local_directory,$array_of_regions )  {

        $files_for_download =[];
        $conn_id = ftp_connect($this->ftp_server);
        if(!$conn_id) {
            echo("Не смог подключиться к $this->ftp_server");
            exit;
            }
        else {
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
                 ftp_chdir( $conn_id, $remote_directory);
                 App::echo_log("Считываю список регионов в каталоге: $remote_directory");
                 App::echo_log("Готовлю список файлов для закачки");
                 $array_of_regions = ftp_nlist($conn_id, '');
             }
             if (sizeof($array_of_regions) <> 0) {
                 $this->array_of_regions = $array_of_regions;
                 $size_of_all_files = 0;
                 $size_of_downloads = 0;
                 if (!file_exists($local_directory)) {
                   mkdir($local_directory,700,true);                   
                 }
                 foreach ($array_of_regions as $region) {
                     //echo $remote_directory.$region;
                     ftp_chdir( $conn_id, $remote_directory.$region);
                     App::echo_log("Работаю с каталогом региона: ".$region);
                     $files = ftp_nlist($conn_id, '*all*');                     
                     foreach ($files as $file){
                         
                         $destination_file = $local_directory.$region.'_'.$file;                         
                         $size = ftp_size($conn_id, $file);
                         
                         if (file_exists($destination_file) == true and $size == filesize($destination_file)) {
                             App::echo_log("Файл уже скачан ".$file);
                         } else {
                            $size_of_all_files = $size_of_all_files + $size;
                            array_push($files_for_download, ['file'=>$remote_directory.$region.'/'.$file,'size'=>$size,'localfile'=>$destination_file]);                            
                         }
                         array_push($this->zip_files,$destination_file);
                     }
                     ftp_chdir( $conn_id, $remote_directory);
                     }
                     App::echo_log("Регионов просмотрено: ".(sizeof($array_of_regions)));
                     App::echo_log("Всего файлов: ".(sizeof($files_for_download))."    Размер: ".(round($size_of_all_files/1024/1024,1))." Мб.");

                     $f = ftp_nlist($conn_id, '');
                     $n = 0;
                     foreach ($files_for_download as $file){                         
                         if (ftp_get($conn_id, $file['localfile'], $file['file'], FTP_BINARY)) {
                             $n++;
                             $size_of_downloads = $size_of_downloads + $file['size'];
                             App::echo_log("$n из ".(sizeof($files_for_download)).". Скачал файл ".$file['file'].' Размер '.(round($file['size']/1024/1024,1))." Мб. ( ".(round($size_of_downloads/$size_of_all_files * 100)."% )") );
                         } else {
                          App::echo_log("Ошибка при скачивании файла ".$file['file']." в ".$file['localfile']." произошла проблема");
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
    public function UnZipFiles($array_of_files = [],$local_directory='',$template = 'xml'){
      $extracted_files =[];
        if (sizeof($array_of_files) == 0) {
            $array_of_files = $this->zip_files;
        }
        if (sizeof($array_of_files) == 0) {
            App::echo_log('UnZip. Передан пустой список файлов.');
            return [];
        }
        if ($local_directory == ''){
          $local_directory = App::temp_directory;
        }
        $n = 0;
        $cout_extracted_files = 0;
        foreach ($array_of_files as $file) {
          $n++;
          App::echo_log('UnZip. Файл '.$n.' из '.sizeof($array_of_files).'. Готовим список для распакаовки.');
          if (file_exists($file)) {
            $zip = new ZipArchive;
            $files_to_extract = [];

            if ($zip->open($file) === TRUE){
              for ( $i = 0; $i < $zip->numFiles; $i++ ){
                $filename = $zip->getNameIndex($i);
                if (preg_match('#\.('.$template.')$#i', $filename)) {
                    $zip->extractTo($local_directory,$filename);
                    //App::echo_log('       Извлечён '.$filename);

                    $destination_file = $local_directory.$n.'_'.$i.'_'.$filename;
                    rename($local_directory.$filename,  $destination_file);
                    $cout_extracted_files ++;
                    array_push($extracted_files,$destination_file);
                    array_push($this->temp_files,$destination_file);
                }

              }
              $zip->close();

            }

          } else {
            App::echo_log('UnZip. Файла ['.$file.'] не существует');
          }
        }
      //array_push($this->temp_files,$extracted_files);
      //print_r($this->temp_files);
      App::echo_log('UnZip. Файлов распаковано: '.$cout_extracted_files);
      return $extracted_files;
    }

    public function DelTempFiles(){
      foreach ($this->temp_files as $file) {
        try {        
            if (file_exists($file)) {
                   unlink($file);
                 }                      
        }
        catch(Exception $e) {
            App::echo_log($e->getMessage());
        	}
      }
    App::echo_log('DelTemp. Удалили временные файлы  ');
    }
}