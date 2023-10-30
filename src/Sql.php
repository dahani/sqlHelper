<?php
namespace Dahani\Sql;
use \PDO;
ini_set('html_errors', false);
class Result{
    private $res=[];
    public function __construct($d=[]) {
        $this->res = $d;
    }
    public function  isError():bool{
        return !$this->res['test']??true;
    }
    public function  getResult():array{
        return $this->res['data']??'';
    }
    public function  getResultGroupedBy($key):array{
        return $this->group_by(key:$key);
    }
    public function  getCount():int{
        return $this->res['count']??0;
    }
    public function  getPdo(){
        return $this->res['res']??'';
    }
    public function  getError():string{
        return $this->res['errors']??'no error';
    }
    public function  getLastID():int{
        return $this->res['last']??0;
    }
    function group_by($key) {$result = array();
        if(count($this->res['data'])==0)
        throw new \Exception("Empty array no Data Result", 1);
        if(!isset($this->res['data'][0][$key]))
        throw new \Exception("key not Found", 1);
        
        foreach($this->res['data'] as $val) {if(array_key_exists($key, $val)){$result[$val[$key]][] = $val;}else{$result[""][] = $val;}}
        return $result;
    }

}
class Sql{
	private $con=null;

    public function __construct(public string $env) {
       $this->parse_env_file_contents_to_array($env);
        $this->connect();
    }
   

    function GetTableCount(string $table,bool $customQuery=false,$QueryParams=[]){ 
       $sql="SELECT COUNT(*)as cnt FROM `".$table."` WHERE  1";
        try {
            $res=$this->con->prepare(!$customQuery?$sql:$table);
            $res->execute($QueryParams);$count=$res->fetch(PDO::FETCH_COLUMN);
            return New Result(['count'=>($count)?$count:0,"test"=>true]);
        }catch(\PDOException $e) {
            $er=__FUNCTION__." :".($e->getMessage())."<br>".$table;
            return new Result(["errors"=>$er,"test"=>false]);
        }
    }
    function SQL_DELETE($table,$id,$val){
        $sql="DELETE FROM `{$table}` WHERE   {$id}='{$val}'";
        $res=$this->con->query($sql);
     if($res){return new Result(["test"=>true]);}else{return new Result(["res"=>$res,"test"=>false,"errors"=>$this->con->errorInfo()[2]]);}
    }

    function SQL_ADD($table,$fields,$ignore=false,$dup=""){$sql_dup=" ";
       $sql="INSERT ".($ignore?" IGNORE ":"")." INTO ".$table.' (';
        $i=0;$nefileds=array();foreach($fields as $k=>$v){$en[$k]=$v==""?null:$v;};$fields=$en;
        foreach($fields as $key=>$val){$sql.=($i==count($fields)-1)?$key:$key.' ,';$i++;}
        $sql.=" ) VALUES ( ";$i=0;
        foreach($fields as $key=>$val){
           if(@preg_match('/^date_/i', $key)){ if(!@preg_match('/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/',$fields[$key])){$fields[$key]=@substr($val,0,10);if($fields[$key]==""){$fields[$key]=null;}}}
           $fields[$key]=@strlen($fields[$key])==0?null:$fields[$key];
            $nefileds[$key]=$fields[$key];
            $sql.=($i==count($fields)-1)?":".$key:":".$key.' ,';
            if($key!='id'){
            $sql_dup.=$key.'=:'.$key.(($i==count($fields)-1)?" ":' ,');
            }
            $i++;}$sql.=" )  ";
            if($dup!=""){$sql.=" ON DUPLICATE KEY UPDATE ".$sql_dup;
            }
        //echo $sql;exit;
        try {
            $res=$this->con->prepare($sql);$res=$result=$res->execute($nefileds);
            return new Result(["res"=>$res,"test"=>true,"last"=>$this->con->lastinsertId()]);
        }
        catch(\PDOException $e) {
            return new Result(["test"=>false,"errors"=>"SQL_ADD:".$e->errorInfo[2]."<br>".$sql]);
        }
    }
    function SQL_UPDATE($table,$fields){
        $sql="UPDATE `{$table}` SET  ";$i=0;$nefileds=array();
        foreach($fields as $key=>$val){
            if(@preg_match('/^date_/i', $key)){if(!@preg_match('/(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2}):(\d{2})/',$fields[$key])){$fields[$key]=@substr($val,0,10);}}
            $fields[$key]=@strlen($fields[$key])==0?null:$fields[$key];
            $nefileds[$key]=$fields[$key];
            $sql.=($i==count($fields)-1)?$key."=:".$key:$key."=:".$key." ,";$i++;}
        $sql.=" WHERE id='{$fields['id']}'";
        try {
            $res=$this->con->prepare($sql);$result=$res->execute($nefileds);
            return new Result(["res"=>$res,"test"=>$result?true:false]);
        }
        catch(\PDOException $e) {
            return new Result(["test"=>false,'errors'=>"SQL_UPDATE:".$e->errorInfo[2]."<br>".$sql]);}	
    }
    function SQL_SELECT($table,$key=1,$val=1,$limit="",$fields="*",$op="="){
        $sql="SELECT {$fields} FROM {$table} WHERE  {$key}{$op}:val ".$limit;
        try { 
            $res=$this->con->prepare($sql);
            $re=$res->execute(array(":val"=>$val));$data= $res->fetchAll(PDO::FETCH_ASSOC);
            return new Result(["test"=>true,"data"=>$data,"res"=>$res]);
        }catch(\PDOException $e) {
            return new Result(["test"=>false,"errors"=>"SQL_SELECT:".$e->errorInfo[2]."<br>".$sql]);
        }
    }
    function SQL_QUERY($query,$params=array()){
        try {$rex=[];
            $res=$this->con->prepare($query);$res->execute($params);
            if (@preg_match("/select|show/i", $query)) {$data= $res->fetchAll(PDO::FETCH_ASSOC);$rex=(["test"=>true,"data"=>$data,"res"=>$res]);
            }else{$rex= array("test"=>true,"res"=>$res);}
            return new Result($rex);
        }
        catch(\PDOException $e) {
            return new Result(["test"=>false,"errors"=>"SQL_QUERY:".$e->getMessage()."<br>".$query]);}
    }
    function connect(){
        try {
            $CONFIG=parse_url($_ENV['DATABASE_URL']);$CONFIG['path']=ltrim($CONFIG['path'],'/');
            $this->con= new \PDO("mysql:host={$CONFIG['host']};dbname={$CONFIG['path']}",$CONFIG['user'],@$CONFIG['pass'].'',array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8 ,time_zone = "'.$_ENV['TIME_ZONE'].'";',PDO::ATTR_EMULATE_PREPARES=>false,PDO::MYSQL_ATTR_DIRECT_QUERY=>true,PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
        }catch (\PDOException $e) {$this->echoJson(array("errors"=>'Connexion Acces: ' . $e->getMessage(),"test"=>false));exit;}
        }
    private function echoJson($arr){
        echo json_encode($arr);exit;
    }
    function parse_env_file_contents_to_array($file) {
        $cnt=file_get_contents($file);
        $lines = explode("\n", $cnt);
        foreach ($lines as $line) {
            if ($line === '' || substr($line, 0, 1) === '#') {
                continue;
            }
            $equals_pos = strpos($line, '=');
            if ($equals_pos !== false) {
                $key = substr($line, 0, $equals_pos);
                $value = substr($line, $equals_pos + 1);
                if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
                    $value = substr($value, 1, -1);
                }
                elseif (substr($value, 0, 1) === "'" && substr($value, -1) === "'") {
                    $value = substr($value, 1, -1);
                }
                $_ENV[$key]=$value;
            }
        }
    }
}