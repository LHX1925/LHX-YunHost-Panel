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
// 异步通知使用 verifyNotify，同时兼容 GET/POST 传参
$verify_result = $epay->verifyNotify();
if($verify_result) {//验证成功
	// 优先从 $_GET 读取参数（该易支付接口回调使用 GET）
	$out_trade_no = isset($_GET['out_trade_no']) ? $_GET['out_trade_no'] : '';
	$trade_no = isset($_GET['trade_no']) ? $_GET['trade_no'] : '';
	$trade_status = isset($_GET['trade_status']) ? $_GET['trade_status'] : '';
	$type = isset($_GET['type']) ? $_GET['type'] : '';
	if($trade_status == 'TRADE_SUCCESS') {
$db=Db::name('pay')->where([
"ordernumber"=>$out_trade_no,
])->find();
if($db){
if($db["state"]=="1"){
	$msg="该订单已充值成功,禁止重复提交!";
}else{
	// 使用条件更新防止并发重复充值（原子操作）
	$affected = Db::name('pay')->where([
		"ordernumber"=>$out_trade_no,
		"state"=>"2",  // 仅更新未支付订单
	])->update([
		"state"=>"1",
		"time"=>time(),
	]);
	if ($affected > 0) {
		$user=Db::name('user')->where('id',$db["userid"])->find();
		// 增加余额
		Db::name('user')->where('id',$db["userid"])->update([
			"money"=>round($user["money"]+$db["money"],2),
			"total_recharge"=>round(floatval($user["total_recharge"] ?? 0) + $db["money"], 2),
		]);
		if (function_exists('update_user_membership')) {
			update_user_membership($db["userid"]);
		}
		$msg="充值成功";
	} else {
		$msg="该订单已处理,禁止重复提交!";
	}
}
}else{
	$msg="未找到该订单";
}
	}else{
$msg="trade_status=".$trade_status;
}
} else {
	//验证失败
	$msg="验证失败";
}
	exit($msg);