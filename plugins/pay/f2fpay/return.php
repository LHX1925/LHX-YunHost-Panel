<?php
use think\Db;
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
$out_trade_no=input("post.no");
$db=Db::name('pay')->where([
"ordernumber"=>$out_trade_no,
])->find();
if($db){
	// 验证用户身份
	if(session("userid") && $db["userid"] != session("userid")){
		$msg=["code"=>"-1","msg"=>"订单不属于当前用户"];
	}elseif($db["state"]=="1"){
	$msg=["code"=>"1","msg"=>"充值成功!"];
}else{
	$msg=["code"=>"-1","msg"=>"未支付!"];}
}else{
	$msg=["code"=>"-1","msg"=>"未找到订单!"];
}
exit(json_encode($msg));