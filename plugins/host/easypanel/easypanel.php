<?php
use think\Db;


function easypanel_ConfigOptions()
{
$data=[
["name"=>"data1",'title'=>'产品列表ID', 'type'=>'input',"prompt"=>"康乐后台设置的产品列表的ID","value"=>''],
];
return $data;
}


//控制面板
function easypanel_ClientArea($b,$a,$data){
if($b["ssl"]=="1"){
$ssl="https://";
}else{
$ssl="http://";
}
$url=$ssl.$b["host"].":".$b["port"]."/vhost/?c=session&a=login";
$text="
<script src='/static/assets/vendor/layer/layer.js'></script>
<form action='".$url."' method='post'" .">
<input type='hidden'name='username'value='".$data["user"]."'/>
<input type='hidden'name='passwd'value='".$data["password"]."'/>
  <button type='submit' class='btn btn-primary' style='padding:7px 18px;background:#1E9FFF;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;margin-right:8px;'>登录控制面板</button>
  <button onclick='resetpass(".$data["id"].")' type='button' class='btn btn-primary' style='padding:7px 18px;background:#ff6b6b;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:13px;'>重置密码</button>
</form>
<br/>
账号:<span style='color:#ff6b6b'>".$data["user"]."</span><br/>密码:<span style='color:#ff6b6b'>".$data["password"]."</span>
<script>

     function resetpass(id){
        layer.confirm('确定要重置密码吗？重置后原密码将不可使用！',{icon:3}, function (){
            resetApi(id)
        });
    }



    function resetApi(id){
        var load = layer.load('1',{time:false});

        $.ajax({
            type:'POST',
            url:'',
            data:{
                act:'reset',
                id:id
            },
            dataType:'json',
            success:function (data){
                layer.close(load);
                if(data.code == 1){
                    setTimeout(function (){
                        location.href = ''
                    },1000);
                    layer.alert(data.msg,{icon:1});
                }else{
                    layer.alert(data.msg,{icon:2});
                }
            }
        });
    }


</script>";
return $text;
}

//重置密码
function easypanel_ChangePassword($b,$data,$password){
if($b["ssl"]=="1"){
$ssl="https://";
}else{
$ssl="http://";
}
$rand =rand(1000, 9999);
$url=$ssl.$b["host"].":".$b["port"]."/api/?";
       $arr = [
                'c' => "whm",
                'a' => 'change_password',
                'r' => $rand,
                's' => md5("change_password".$b["security"].$rand),
                'name' => $data["user"],
                'passwd' => $password,
                'json' =>"1" ,               
             ];
$url1=$url.http_build_query($arr);
$data1=@json_decode(file_get_contents($url1),true);
if($data1["result"]=="200"){
	$array["code"]="1";
	$array["msg"]="重置密码成功";	
}else{
	$array["code"]="-1";
	$array["msg"]=json_encode($data1);	
}
return $array;
}

//开通
function easypanel_CreateAccount($data3,$data2,$data,$times,$orderId=null){
$rand =rand(1000, 9999);
if($data3["ssl"]=="1"){
$ssl="https://";
}else{
$ssl="http://";
}
$url=$ssl.$data3["host"].":".$data3["port"]."/api/?";
       $arr = [
                'c' => "whm",
                'a' => 'add_vh',
                'r' => $rand,
               'init' => "1",
                's' => md5("add_vh".$data3["security"].$rand),
                'name' => $data2["user"],
                'passwd' => $data2["password"],
                'product_id' => $data["data1"],
                'status' => "0",
                'json' =>"1" ,               
             ];
$url1=$url.http_build_query($arr);
$data4=@json_decode(file_get_contents($url1),true);
if($data4["result"]=="200"){
	// 二次确认密码：调用 change_password 确保密码正确写入
	$rand2 = rand(1000, 9999);
	$cpArr = [
		'c' => "whm",
		'a' => 'change_password',
		'r' => $rand2,
		's' => md5("change_password".$data3["security"].$rand2),
		'name' => $data2["user"],
		'passwd' => $data2["password"],
		'json' => "1",
	];
	$cpUrl = $url.http_build_query($cpArr);
	@json_decode(file_get_contents($cpUrl), true);
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
}else{
	$array["code"]="-1";
	$array["msg"]=json_encode($data4);	
}
return $array;
}

//解除暂停
function easypanel_UnsuspendAccount($b,$data){
$rand =rand(1000, 9999);
if($b["ssl"]=="1"){
$ssl="https://";
}else{
$ssl="http://";
}
$url=$ssl.$b["host"].":".$b["port"]."/api/?";
       $arr = [
                'c' => "whm",
                'a' => 'update_vh',
                'r' => $rand,
                's' => md5("update_vh".$b["security"].$rand),
                'name' => $data["user"],
                'status' => "0",//0为正常 1为关闭
                'json' =>"1" ,               
             ];
$url1=$url.http_build_query($arr);
$data1=@json_decode(file_get_contents($url1),true);
if($data1["result"]=="200"){
	$array["code"]="1";
	$array["msg"]="开启成功";	
}else{
	$array["code"]="-1";
	$array["msg"]=json_encode($data1);	
}
return $array;
}

//暂停
function easypanel_SuspendAccount($data3,$order){
$rand =rand(1000, 9999);
if($data3["ssl"]=="1"){
$ssl="https://";
}else{
$ssl="http://";
}
$url=$ssl.$data3["host"].":".$data3["port"]."/api/?";
       $arr = [
                'c' => "whm",
                'a' => 'update_vh',
                'r' => $rand,
               'init' => "1",
                's' => md5("update_vh".$data3["security"].$rand),
                'name' => $order["user"],
                'status' => "1",
                'json' =>"1" ,               
             ];
$url1=$url.http_build_query($arr);
$data4=@json_decode(file_get_contents($url1),true);
if($data4["result"]=="200"){
	$array["code"]="1";
	$array["msg"]="暂停成功";	
}else{
	$array["code"]="-1";
	$array["msg"]=json_encode($data4);	
}
return $array;
}

//终止
function easypanel_TerminateAccount($data3,$order){
$rand =rand(1000, 9999);
if($data3["ssl"]=="1"){
$ssl="https://";
}else{
$ssl="http://";
}
$url=$ssl.$data3["host"].":".$data3["port"]."/api/?";
       $arr = [
                'c' => "whm",
                'a' => 'del_vh',
                'r' => $rand,
               'init' => "1",
                's' => md5("del_vh".$data3["security"].$rand),
                'name' => $order["user"],
                'json' =>"1" ,               
             ];
$url1=$url.http_build_query($arr);
$data4=@json_decode(file_get_contents($url1),true);
if($data4["result"]=="200"){
	$array["code"]="1";
	$array["msg"]="终止成功";	
}else{
	$array["code"]="-1";
	$array["msg"]=json_encode($data4);	
}
return $array;
}

//续费
function easypanel_renew($b,$data,$a,$times,$time){
	$array["code"]="1";
	$array["msg"]="续费成功";	
return $array;
}

//升级
function easypanel_upgrade($server,$order,$cart,$newcart){
$rand =rand(1000, 9999);
if($server["ssl"]=="1"){
$ssl="https://";
}else{
$ssl="http://";
}
$url=$ssl.$server["host"].":".$server["port"]."/api/?";
       $arr = [
                'c' => "whm",
                'a' => 'add_vh',
                'r' => $rand,
                'edit' => "1",
                's' => md5("add_vh".$server["security"].$rand),
                'name' => $order["user"],
                'product_id' =>$newcart["data1"],
                'json' =>"1" ,               
             ];
$url1=$url.http_build_query($arr);
$data4=@json_decode(file_get_contents($url1),true);
if($data4["result"]=="200"){
	$array["code"]="1";
	$array["msg"]="成功";	
}else{
	$array["code"]="-1";
	$array["msg"]=json_encode($data4);	
}
return $array;
}