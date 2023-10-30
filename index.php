<?php

require 'vendor/autoload.php';

use Dahani\Sql\Sql as DhSql;

$sql=new DhSql(env:".env.local");
$add=["name"=>"name 1 updated","id"=>2];


//$res=$sql->SQL_SELECT(table:"walls",fields:"id,alt",limit:" ORDER BY rand() LIMIT 0,30");
$res=$sql->SQL_QUERY(query:"SELECT * FROM walls order by rand() LIMIT 0,300");
if($res->isError()){
    echo $res->getError();
}else{
    echo json_encode($res->getResultGroupedBy("tag"));
}