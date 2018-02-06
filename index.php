<?php

# @Author : Mouhchadi Bakali Tahiri
# @Date : 2018-02-06
# @Description : The program should run once per minute using cron. When run, it should discover any CSV
# files in the “uploaded” directory, parse the rows in the file, insert their contents into a MySQL
# database, and then move each CSV file to the “processed” directory.

# Some stuff could be refactored , like generate a config file to read all configuation
 
class Process
{
    public  $new_registros = array();
    public  $fields_name = array();
    public  $num_campos;
    public  $num_registros;
    private $conn;
    private $file;
    
    public function __construct()
    {
        //this method could be reafctored and moved outside. using singleton pattern
        $servername = "localhost";
        $username = "root";
        $password = "root";
        
        try {
            $this->conn = new PDO("mysql:host=$servername;dbname=infinitydb", $username, $password);
            // set the PDO error mode to exception
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            //echo "Connected successfully";
        } catch (PDOException $e) {
            echo "Connection failed: " . $e->getMessage();
        }
    }// end method construct
    
    
    // Open file from uploaded directory and process
    function openFile()
    {
        
        $directorio = opendir("uploaded"); //current route
        while ($archivo = readdir($directorio))  
        {
            if (!is_dir($archivo)) {
                
                $this->file = $archivo;
                $registros = array();
                $contador = 0;
                
                if (($fichero = fopen("uploaded/".$this->file, "r")) !== false) {
                    
                    print "processing file : ".$this->file;
                    print "<br>";

                    //calling this function to set delimiters
                    $this->replaceDelimiters("uploaded/".$this->file);                    
                    
                    $nombres_campos = fgetcsv($fichero, 0);
                    
                    $this->fields_name = $nombres_campos;
                    $this->num_campos = count($nombres_campos);
                    
                    while (($datos = fgetcsv($fichero)) !== false) {
                        $registros[$contador] = $datos;
                        $contador++;
                    }
                    fclose($fichero);
                    
                    $this->num_registros = count($registros);
                    
                    // create a new associative array using fields_name as key
                    $contador = 0;
                    foreach ($registros as $key => $values) {
                        foreach ($values as $key2 => $values2) {
                            if ($values2 != null) {
                                $this->new_registros[$contador][$nombres_campos[$key2]] = $values2;
                            }
                        }//end internal foreach
                        $contador++;
                    }//end external foreach
                }//end if statment

                $this->validateType();
                $this->updateDb();

            }//end if statment
        }// end while        
        
    }//end method
    
    private function validateType()
    {

        # $fila means row
        # $columna means column
        
        for ($fila = 0; $fila < $this->num_registros - 1; $fila++) {
            
            for ($columna = 0; $columna < $this->num_campos; $columna++) {

                // this could be refactored using switch statement o moved to functions
                
                if ($this->fields_name[$columna] == 'eventDatetime' && $this->validateDate($this->new_registros[$fila][$this->fields_name[$columna]])) {
                        $date = strtotime($this->new_registros[$fila][$this->fields_name[$columna]]);
                        $this->new_registros[$fila][$this->fields_name[$columna]] = date('Y-m-d H:i:s', $date);         
                }//end if statment
                
                if ($this->fields_name[$columna] == 'eventAction' && strlen($this->new_registros[$fila][$this->fields_name[$columna]]) > 20) {
                    $this->new_registros[$fila][$this->fields_name[$columna]] = "invalid";
                }//end if statment
                
                if ($this->fields_name[$columna] == 'eventAction' && !isset($this->new_registros[$fila][$this->fields_name[$columna]])) {
                    $this->new_registros[$fila]['eventAction'] = "invalid";
                }//end if statment
                
                if ($this->fields_name[$columna] == 'callRef') {
                    $this->new_registros[$fila][$this->fields_name[$columna]] = (int) $this->new_registros[$fila][$this->fields_name[$columna]];
                }//end if statment             
                
                if ($this->fields_name[$columna] == 'eventValue' && !isset($this->new_registros[$fila][$this->fields_name[$columna]])) {
                    $this->new_registros[$fila]['eventValue'] = 0.0;
                    $this->new_registros[$fila][$this->fields_name[$columna]];
                }//end if statment
                
                if ($this->fields_name[$columna] == 'eventCurrencyCode' && !isset($this->new_registros[$fila][$this->fields_name[$columna]])) {
                    $this->new_registros[$fila]['eventCurrencyCode'] = "GBP";
                    $this->new_registros[$fila][$this->fields_name[$columna]];
                } elseif ($this->fields_name[$columna] == 'eventCurrencyCode' && isset($this->new_registros[$fila][$this->fields_name[$columna]])) {
                    $this->new_registros[$fila][$this->fields_name[$columna]];
                }//end if-else statment
            } //end internal for
            
        }//end external for
        
    }//end method
    
    
    private function updateDb()
    {
        //fetch fields name database using PDO class
        $q = $this->conn->prepare("DESCRIBE clients");
        $q->execute();
        $table_fields = $q->fetchAll(PDO::FETCH_COLUMN); 

        //this could be refactored using method bind to avoid injection
        foreach ($this->new_registros as $k) {
            $cadena_campos = implode(",",  $table_fields);
            $data_string = "\"" . $k["eventDatetime"] . "\"" . "," . "\"" . $k["eventAction"] . "\"" . "," . $k["callRef"] . "," . $k["eventValue"] . "," . "\"" . $k["eventCurrencyCode"] . "\"";
            $stmt = $this->conn->prepare("INSERT INTO clients ($cadena_campos)
            VALUES ($data_string)");
            $stmt->execute();
            $data_string = '';
        }
        // }// end external foreach
    }//end method
    
    
    //helpers function to validate date    
    private function validTime($time, $format = 'H:i:s')
    {
        $d = DateTime::createFromFormat("Y-m-d $format", "2017-12-01 $time");
        return $d && $d->format($format) == $time;
    }//end method


    private function validateDate($date, $format = 'Y-m-d H:i:s')
    {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) == $date;
    }

    //helper to force delimitir en file    
    private function replaceDelimiters($file)
    {
        // Delimiters to be replaced: pipe, comma, semicolon, caret, tabs
        $delimiters = array('|', ';', '^', "\t");
        $delimiter = ',';        
        $str = file_get_contents($file);
        $str = str_replace($delimiters, $delimiter, $str);
        file_put_contents($file, $str);
    }      
    
}//end class


$infinity = new Process();
$infinity->openFile();