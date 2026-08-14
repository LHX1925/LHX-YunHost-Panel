<?php
use think\Db;
function default_ConfigOptions()
{
$data=[
["name"=>"data1",'title'=>'提示信息', 'type'=>'textarea',"prompt"=>"订单未审核时提示的信息","value"=>''],

];
return $data;
}

function default_OrderConfigOptions()
{
$data=[
["name"=>"data1",'title'=>'内容', 'type'=>'textarea',"prompt"=>"订单审核成功后的内容","value"=>''],
];

return $data;
}
//控制面板
function default_ClientArea($b,$a,$data){
if($data["data1"]){
$msg=$data["data1"];
}else{
$msg=$a["data1"];
}
$text="
账号:<span style='color:#ff6b6b'>".$data["user"]."</span><br/>密码:<span style='color:#ff6b6b'>".$data["password"]."</span><br/><br/>".$msg;
return $text;
}



//开通
function default_CreateAccount($data3,$data2,$data,$times,$orderId=null){
	$now=time();
	$orderData=[
		"user"=>$data2["user"],
		"password"=>$data2["password"],
		"cartid"=>$data["id"],
		"atime"=>$now,
		"ztime"=>$now+$times,
		"state"=>"1",
	];
	if($orderId!==null){
		$exist=Db::name('order')->where('id',$orderId)->find();
		if($exist){
			$orderData["userid"]=$exist["userid"];
		}
		Db::name('order')->where('id',$orderId)->update($orderData);
		$data6=$orderId;
	}else{
		$orderData["userid"]=session("userid");
		$orderData["data1"]="";
		$orderData["data2"]="";
		$orderData["data3"]="";
		$orderData["data4"]="";
		$orderData["data5"]="";
		$orderData["data6"]="";
		$orderData["data7"]="";
		$orderData["data8"]="";
		$orderData["data9"]="";
		$orderData["data10"]="";
		$data6=Db::name('order')->insertGetId($orderData);
	}
	$array["code"]="1";
	$array["msg"]="创建成功";
	$array["id"]=$data6;
return $array;
}

//解除暂停
function default_UnsuspendAccount($b,$data){
	$array["code"]="1";
	$array["msg"]="开启成功";	
return $array;
}

//暂停
function default_SuspendAccount($data3,$order){
	$array["code"]="1";
	$array["msg"]="暂停成功";	
return $array;
}

//终止
function default_TerminateAccount($data3,$order){
	$array["code"]="1";
	$array["msg"]="终止成功";	
return $array;
}

//续费
function default_renew($b,$data,$a,$times,$time){
	$array["code"]="1";
	$array["msg"]="续费成功";	
return $array;
}
