<?php


      
        
$data =file_get_contents(       "crm_data/".(int)$_POST['aids'].'.json'
, true);


if (preg_match('/\/gad/', $_SERVER["REQUEST_URI"])) {
	echo "gad";

}elseif(preg_match('/\/gsd/', $_SERVER["REQUEST_URI"])){
	echo "gsd";
}elseif(preg_match('/\/billable/', $_SERVER["REQUEST_URI"])
){
	echo $data;
}else{}
?>


