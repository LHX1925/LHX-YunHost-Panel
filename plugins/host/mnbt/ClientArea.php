<?php
use think\Db;

if($act=="reset"){
if($data["state"]=="3"){
//判断产品为终止状态
$array["code"]="-2";
$array["msg"]="该产品已终止,禁止重置密码！";
}else{

include_once PATH."plugins/host/".$b["serverplugins"]."/".$b["serverplugins"].".php";
$password=random();
$function=$b["serverplugins"]."_"."ChangePassword";
$data1=$function($b,$data,$password);
if($data1["code"]=="1"){
$data=Db::name('order')->where([
'id'=>$id,
"userid"=>session("userid"),
])->update([
'password' => $password,
]);
	$array["code"]="1";
	$array["msg"]="重置密码成功！";	
}else{
	$array["code"]="-1";
	$array["msg"]="重置密码失败".$data1["msg"];
}
}
exit(json_encode($array));
}