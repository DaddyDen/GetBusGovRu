<?php

 
class BusNsi extends App {
    
    public $id = 0;
    public $code;
    public $name;
    public $nsi;
    public $db;
    
    function __construct($db,$nsi,$code,$name) {
        $this->code = $code;
        $this->name = $name;
        $this->nsi = $nsi;
        $this->db = $db;
           
        $sql = 'SELECT id FROM bus_'.$this->nsi.' WHERE code = ? ORDER BY id LIMIT 1';        
        
        
        $stmt = $this->db->prepare($sql);        
        $stmt->bind_param('s', $this->code);
        
            
                
        $stmt->execute();
        $stmt->bind_result($id);
        $stmt->store_result();
        
        if ($stmt->fetch()) $this->id = $id;
    
        $stmt->close();
    }
    
    function Save() {
        $this->name = trim($this->name);
        if ($this->id == 0) {
            $sql = 'INSERT INTO bus_'.$this->nsi.' (code,name) VALUES (?, ?)';
            $stmt = $this->db->prepare($sql); 
            $stmt->bind_param('ss', $this->code, $this->name);
            $stmt->execute();
            $this->id = mysqli_insert_id($this->db);
        } else {
            $stmt = $this->db->prepare('UPDATE bus_'.$this->nsi.' SET code = ?,name = ? WHERE id = ?'); 
            $stmt->bind_param('ssi', $this->code, $this->name,$this->id);
            $stmt->execute();            
        }
        $stmt->close();
        return $this->id;
    }
}
