<?php

class BusGeneralInfo extends App{
    const ftp_directory = '/GeneralInfo/';
    const local_directory = 'GeneralInfo\\';
    
    public $id = 0;
    public $fullName;    
    public $inn;
    public $kpp;
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
    public $changeDate;
    public $db;
    
    function __construct($db,$inn = '',$kpp = '') {
        $this->db = $db;
       /* if ($inn != '') {
            $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? AND kpp = ? ORDER BY id LIMIT 1');
            $stmt->bind_param('ss', $this->inn,$this->kpp);
            $stmt->execute();
            $stmt->bind_result($id,$changeDate);
            $stmt->store_result();
        }*/
        
    }
    
    public function FillFromXML($xml_file){      
        
        $bus = simplexml_load_file($xml_file);
        
        $ns2 = $bus->getNamespaces(true);
        
	$body = $bus->children($ns2['ns2'])->body;
        

	//$ns2 = $body->getNamespaces(true);			
			
	$position = $body->position->children($ns2['']);
        $str = substr((string)$position->changeDate, 0,10).' '.substr((string)$position->changeDate, 11,8);
        //echo $str.PHP_EOL;
        //$timestemp = strtotime($str);
        //echo date('Y-m-d',$timestemp).PHP_EOL;        
        //$this->changeDate = $timestemp;//$position->changeDate;
        $this->changeDate = $str;
        
        $this->versionNumber = (string)$position->versionNumber;
	$initiator = $position->initiator;
        
        

        $this->fullName = (string)$initiator->fullName;
        $this->inn = (string)$initiator->inn;
        $this->kpp = (string)$initiator->kpp;
	
        $main = $position->main;
        $this->shortName = (string)$main->shortName;
        $this->ogrn = (string)$main->ogrn;
        $this->orgType =  (string)$main->orgType;
        
        
	$classifier = $main->classifier->children('');
	
        $this->okfs[(string)$classifier->okfs->code] = (string)$classifier->okfs->name;
        $this->okopf[(string)$classifier->okopf->code] = (string)$classifier->okopf->name;
        $this->okpo[(string)$classifier->okpo->code] = (string)$classifier->okpo->name;
        $this->oktmo[(string)$classifier->oktmo->code] = (string)$classifier->oktmo->name;
        
        //print_r($classifier);
    
        
        foreach ($classifier as $param => $value) {
            if ($param == 'okved') {
                if ((string)$value->type == 'C') {
                    $this->okved[(string)$value->code] = (string)$value->name;
                }                
            }
	}
        foreach ($classifier as $param => $value) {
            if ($param == 'okved') {
                if ((string)$value->type != 'C') {
                    $this->okved[(string)$value->code] = (string)$value->name;
                }                
            }
	}        
        
        $additional = $position->additional->children('');
        $this->institutionType[(string)$additional->institutionType->code] = (string)$additional->institutionType->name; 
        $this->ppo = (string)$additional->ppo->name;
        $this->phone = (string)$additional->phone;
        $this->www = (string)$additional->www;
        $this->eMail = (string)$additional->eMail;
        //print_r($this);
    }
    
    public function Save() {
        $stmt = $this->db->prepare('SELECT id,changeDate FROM bus_GeneralInfo WHERE inn = ? AND kpp = ? ORDER BY id LIMIT 1');        
        $stmt->bind_param('ss', $this->inn,$this->kpp);
        $stmt->execute();
        $stmt->bind_result($id,$changeDate);        
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
            if ($new_timestemp < $old_timestemp) $old_data_in_file = TRUE;
        }
        if ($this->id != 0) {
            echo "old_time=$old_time".PHP_EOL;
            echo "newtime=$this->changeDate".PHP_EOL;
            echo "id=$this->id".PHP_EOL;
        }
        
        if (!$old_data_in_file or $this->id == 0) {            

            
            $oktmo = new BusNsi($this->db,'oktmo',key($this->oktmo),$this->oktmo[key($this->oktmo)]);
            $oktmo_id = $oktmo->Save();

            $institutionType = new BusNsi($this->db,'institutionType',key($this->institutionType),$this->institutionType[key($this->institutionType)]);
            $institutionType_id = $institutionType->Save();
            

            $okfs = new BusNsi($this->db,'okfs',key($this->okfs),$this->okfs[key($this->okfs)]);
            $okfs_id = $okfs->Save();

            $okopf = new BusNsi($this->db,'okopf',key($this->okopf),$this->okopf[key($this->okopf)]);
            $okopf_id = $okopf->Save();

            if ($this->id == 0) {
                echo 'INSERT'.PHP_EOL;                
                $sql = 'INSERT INTO bus_GeneralInfo (fullName,inn,kpp,shortName,ogrn,orgType,okfs,okopf,oktmo,institutionType,ppo,www,eMail,versionNumber,changeDate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $stmt = $this->db->prepare($sql); 
                $stmt->bind_param('ssssssiiiisssss', 
                        $this->fullName, $this->inn, $this->kpp, $this->shortName, $this->ogrn, $this->orgType,
                        $okfs_id,$okopf_id,$oktmo_id,$institutionType_id,
                        $this->ppo, $this->www, $this->eMail, $this->versionNumber, $this->changeDate);
                $stmt->execute();
                $this->id = mysqli_insert_id($this->db);
                echo "id=$this->id".PHP_EOL;
                $this->SaveOkveds();
                
            } else {
                echo 'UPDATE'.PHP_EOL;
                $stmt = $this->db->prepare('UPDATE bus_GeneralInfo SET fullName = ?,inn = ?,kpp = ?,shortName = ?,ogrn = ?,orgType = ?,okfs = ?,okopf = ?,oktmo = ?,institutionType = ?,ppo = ?,www = ?,eMail = ?,versionNumber = ?,changeDate = ? WHERE id = ?');
                $stmt->bind_param('ssssssiiiisssssi',
                        $this->fullName, $this->inn, $this->kpp, $this->shortName, $this->ogrn, $this->orgType,
                        $okfs_id,$okopf_id,$oktmo_id,$institutionType_id,
                        $this->ppo, $this->www, $this->eMail, $this->versionNumber, $this->changeDate,$this->id);
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
            $stmt->bind_param('i',$this->id);
            $stmt->execute();
            $i = 0;
            foreach ($this->okved as $code => $name) {
                $i++;
                if ($i == 1) { $main = 1;} else { $main = 0;}
                $okved = new BusNsi($this->db,'okved',$code,$name);
                $okved_id = $okved->Save();
                
                $sql = "INSERT INTO bus_GeneralInfo_okved (GeneralInfo,Okved,main) VALUES (?,?,?)";
                $stmt = $this->db->prepare($sql);
                $stmt->bind_param('iii',$this->id,$okved_id,$main);
                $stmt->execute();                                
            }
        }
    }
}
