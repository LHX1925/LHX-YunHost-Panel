<?php
use think\Db;
use think\Request;
use pay\epay;
$user=Db::name('user')->where('id',session("userid"))->find();
$file=file_exists(PATH."/plugins/pay/".$data1["plugins"]."/set.php");
if($file){
if($data1["data"]){
$ppay=json_decode($data1["data"],true);
}else{
exit("<title>出错啦!</title>没有参数文件!");
}
}else{
exit("<title>出错啦!</title>没有参数文件!");
}
//商户url
$epay_config['apiurl'] = $ppay["0"]["value"];

//商户ID
$epay_config['pid'] = $ppay["1"]["value"];

//商户密钥
$epay_config['key'] = $ppay["2"]["value"];
if($epay_config['apiurl']=="" || $epay_config['pid']=="" || $epay_config['key']==""){
exit("<title>出错啦!</title>此站点未配置支付接口!");
}else{
$notify_url = Request::instance()->domain() . Request::instance()->root() ."/index/notify/".$data1["id"]."/";
//需http://格式的完整路径，不能加?id=123这类自定义参数

//页面跳转同步通知页面路径
$return_url = Request::instance()->domain() . Request::instance()->root() ."/user/return/".$data1["id"]."/";
//需http://格式的完整路径，不能加?id=123这类自定义参数

//商户订单号
$out_trade_no = date('YmdHis').rand(000,999);
//商户网站订单系统中唯一订单号，必填

if($ppay["3"]["value"]=="支付宝"){
$type="alipay";
}
if($ppay["3"]["value"]=="QQ"){
$type="qqpay";
}
if($ppay["3"]["value"]=="微信"){
$type="wxpay";
}
//构造要请求的参数数组，无需改动
$parameter = array(
	"pid" => $epay_config['pid'],
	"type" => $type,
	"notify_url" => $notify_url,
	"return_url" => $return_url,
	"out_trade_no" => $out_trade_no,
	"name" => "账号ID:".$user["id"].",余额充值",
	"money"	=>input("money"),
);
//exit(input("payid"));
$db=Db::name('pay')->insertGetId([
"name"=>"余额充值",
"ordernumber"=>$out_trade_no,
"pay"=>input("payid"),
"money"=>input("money"),
"userid"=>$user["id"],
"time"=>time(),
"state"=>"2",
]);
//建立请求
$epay = new epay($epay_config);
$html_text = $epay->pagePay($parameter);
echo $html_text;
}