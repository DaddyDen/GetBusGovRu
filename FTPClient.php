<?php

Class FTPClient {

    private $connectionId;                                                          //id FTP соединения
    private $messageArray = array();                                                //массив статусов работы с FTP

//запись/показ всех статусов и действий с FTP

    public function logMessage($message = false) {
        if ($message != false)
            $this->messageArray[] = $message;
        else
            return $this->messageArray;
    }

//оединение с FTP
    public function connect($server, $ftpUser, $ftpPassword, $isPassive = true) {
        $this->connectionId = @ftp_connect($server);                                    //получаем id FTP соединения
        $loginResult = @ftp_login($this->connectionId, $ftpUser, $ftpPassword);         //имя пользователя и пароль FTP
//проверка соединения
        if ((!$this->connectionId) || (!$loginResult)) {
            $this->logMessage('Ошибка подключения по FTP!');
            return false;
        } else {                                                                          //соединение успешно
            ftp_pasv($this->connectionId, $isPassive);                                      //устанавливает пассивный режим (по умолчанию стоит on)
            $this->logMessage('Соединение к ' . $server . ', для пользователя ' . $ftpUser);
            return true;
        }
    }

//создать директорию
    public function makeDir($directory) {
        if (@ftp_mkdir($this->connectionId, $directory)) {
            $this->logMessage('Директория ' . $directory . '" успешно создана');
        } else {
            $this->logMessage('Ошибка создания директории "' . $directory . '"');
        }
    }

//удалить директорию
    public function delDir($directory) {
        if (@ftp_rmdir($this->connectionId, $directory)) {
            $this->logMessage('Директория ' . $directory . '" удалена');
        } else {
            $this->logMessage('Не удалось удалить директорию "' . $directory . '"');
        }
    }

    /*
      //Загрузка файла на удаленный сервер с текущего сервера (при совпадении имен - перезаписывает)
      - $fileTo - в какую директорию и с каким именем сохранить файл на удаленный сервер
      - $fileFrom - из какой директории текущего сервера предоставить файл на загрузку
     */

    public function uploadFile($fileTo, $fileFrom) {
        $asciiArray = array('txt', 'csv', 'php', 'html', 'htm', 'xml', 'doc', 'docx', 'css', 'js');   //метод передачи. Обычно, для текстовых - FTP_ASCII, а картинок - FTP_BINARY
        $extension = end(explode('.', $fileFrom));
        if (in_array($extension, $asciiArray)) {
            $mode = FTP_ASCII;
        } else {
            $mode = FTP_BINARY;
        }
        $upload = @ftp_put($this->connectionId, $fileTo, $fileFrom, $mode);
        if (!$upload) {
            $this->logMessage('Не удалось загрузить файл!');
        } else {
            $this->logMessage('Загружен "' . $fileFrom . '" как "' . $fileTo . '"');
        }
    }

    /*
      //Скачка файла из удаленного сервера в текущий сервер
      - $fileTo - в какую директорию и с каким именем скачать файл в текущем сервере из удаленного
      - $fileFrom - из какой директории удаленного сервера предоставить файл на скачку
     */

    public function downloadFile($fileTo, $fileFrom) {
        $asciiArray = array('txt', 'csv', 'php', 'html', 'htm', 'xml', 'doc', 'docx', 'css', 'js');   //метод передачи. Обычно, для текстовых - FTP_ASCII, а картинок - FTP_BINARY
        $extension = end(explode('.', $fileFrom));
        if (in_array($extension, $asciiArray)) {
            $mode = FTP_ASCII;
        } else {
            $mode = FTP_BINARY;
        }
        $upload = @ftp_get($this->connectionId, $fileTo, $fileFrom, $mode);
        if (!$upload) {
            $this->logMessage('Не удалось скачать файл!');
        } else {
            $this->logMessage('Скачан "' . $fileFrom . '" как "' . $fileTo . '"');
        }
    }

//удалить файл на сервере
    public function delFile($directory) {
        if (@ftp_delete($this->connectionId, $directory)) {
            $this->logMessage('Файл ' . $directory . '" удален');
        } else {
            $this->logMessage('Не удалось удалить файл "' . $directory . '"');
        }
    }

//получить список файлов и папок директории
    public function listFile($directory) {
        $now_dir = ftp_nlist($this->connectionId, $directory);
        for ($as_logs = 0; $as_logs <= count($now_dir); $as_logs++) {
            echo $now_dir[$as_logs] . '<br>';
        }
    }

}
