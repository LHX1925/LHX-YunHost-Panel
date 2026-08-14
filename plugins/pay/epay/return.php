<?php
use think\Db;
use pay\epay;
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
$epay = new epay($epay_config);
$verify_result = $epay->verifyReturn();
if($verify_result) {//验证成功
	//商户订单号
	$out_trade_no = $_GET['out_trade_no'];
	//支付宝交易号
	$trade_no = $_GET['trade_no'];
	//交易状态
	$trade_status = $_GET['trade_status'];
	//支付方式
	$type = $_GET['type'];
	if($_GET['trade_status'] == 'TRADE_SUCCESS') {
$db=Db::name('pay')->where([
"ordernumber"=>$out_trade_no,
])->find();
if($db){
	// 验证用户身份：session存在则校验，session丢失则信任订单号（异步通知已处理）
	if(session("userid") && $db["userid"] != session("userid")){
		$msg="订单不属于当前用户";
	}elseif($db["state"]=="1"){
	$msg="充值成功!";
}else{
	// 使用订单记录中的 userid 进行余额更新，不依赖 session
	$payUser = Db::name('user')->where('id', $db["userid"])->find();
	if($payUser){
		Db::name('user')->where('id', $db["userid"])->update([
			"money"=>round($payUser["money"]+$db["money"],2),
			"total_recharge"=>round(floatval($payUser["total_recharge"] ?? 0) + $db["money"], 2),
		]);
		if (function_exists('update_user_membership')) {
			update_user_membership($db["userid"]);
		}
	}
	Db::name('pay')->where([
		"ordernumber"=>$out_trade_no,
		"state"=>"2",
	])->update([
		"state"=>"1",
		"time"=>time(),
	]);
	$msg="充值成功";
}
}else{
	$msg="未找到该订单";
}
	}else{
$msg="trade_status=".$_GET['trade_status'];
}
}else {
	//验证失败
	$msg="验证失败";
}
	