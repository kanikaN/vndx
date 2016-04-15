<?php
/**
 * @Author Kanika Navla (kanikanavla@gmail.com)
 * 
 * Connect to Mysql 
 */

error_reporting(E_ERROR | E_PARSE);

class Connect{
    
    private $con ;
    public $error = '';
     function __construct() {
        //mysql -uapi-avery -pavery123 --port=3306
        //50.59.80.110, api-avery.vnddev.com
        $user = 'root';
        $password = 'root';
        //$port = 3306;
        $host = 'localhost';
        $db = 'api_avery';
        try{
            if(!$this->con = new mysqli($host,$user,$password,$db)){
                $this->error = "could not connect to Mysql";    
                return False;
            }  
        }catch(Exception $e){
            throw $e->getMessage();
        }
        return true;
    }
    
    /**
     * run query function
     */
    public function rq($query){
        //$query = mysqli_escape_string($this->con, $query);
        if($result = $this->con->query($query)){
            if(mysqli_num_fields($result)>0){
                while($row = mysqli_fetch_assoc($result)){
                    $return[]=$row;
                }
                mysqli_close($this->con);
                return $return;
            }else{
                $this->error = 0;
                $aff = mysqli_affected_rows($this->con);
                mysqli_close($this->con);
                return $aff;
            }
        }else{
            mysqli_close($this->con);
            return FALSE;
        }
    }
    
    /**
     * fetch agents from db
     * @return boolean
     */
    public function getAgents(){
        $query = "select * from tbl_agents";
        $result = $this->rq($query);
        return $result;
    }
    
    /**
     * add emails to DB
     * @param type $values
     */
    public function addEmails($values){
        if(is_array($values)){
            $keys = '`'.implode('`,`', array_keys ($values[0])).'`';
            foreach($values as $value){
                $value = array_map('mysql_escape_string', array_values($value));
                $tempArr[] = '(\''.implode('\', \'', $value).'\')'; 
            }
            $values = implode(',', $tempArr);
        }else{
            $keys = 'message_id, agent_id,contact_id, from_email, from_name, '
                . 'date_sent,date_created,read_status,subject';
        }
        $query = "insert into tbl_email_inbound($keys) VALUES $values "
                    ."ON DUPLICATE KEY UPDATE read_status=VALUES(read_status)";
        return $this->rq($query);
    }
    
    
    public function getOutboundWithAgents(){
        $query = "select toe.*, ta.fname agent_fname, ta.lname agent_lname, ta.exchange_password, ta.exchange_email,"
                . "tc.fname,tc.lname,tc.primary_email,tce.email from tbl_email_outbound toe, "
                . "tbl_contacts tc,tbl_contact_emails tce, tbl_agents ta where ta.status='ACTIVE' "
                . "and toe.status='PENDING' and toe.agent_id = ta.id and tc.id= toe.contact_id "
                . "and tce.id = toe.contact_email_id";
        echo 56;$ret = $this->rq($query);
        print_r($ret);
        return $ret;
    } 
    
   
}