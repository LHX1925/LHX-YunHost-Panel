<?php
namespace app\index\controller;
use think\Controller;
use think\Db;

class Sq extends Controller {

public function sq(){
$domain=input("domain");
if($domain==""){
$array["code"]="-1";
$array["msg"]="请求参数不全,请加入QQ群905412821联系群主!";
}else{
$user_IP = $_SERVER["REMOTE_ADDR"];
if($user_IP==""){
$array["code"]="-1";
$array["msg"]="no!请加入QQ群905412821联系群主!";
}else{
$data=Db::name('sq')->where("domain", 'like', '%'.$domain.'%')->find();
if($data){
if($data["ip"]==$user_IP){
$array["code"]="1";
$array["msg"]="已授权!";
}else{
$array["code"]="-2";
$array["msg"]="该域名未授权！<br/>当前域名:".$domain."<br/>IP:".$user_IP."<br/>授权请加入QQ群905412821联系群主!";
}
}else{
$array["code"]="-2";
$array["msg"]="该域名未授权！<br/>当前域名:".$domain."<br/>IP:".$user_IP."<br/>授权请加入QQ群905412821联系群主!";

}
}
}
return json_encode($array);
}


}