<?php
namespace app\index\controller;
use think\Controller;
use think\Db;
use think\Request;
use PHPMailer\PHPMailer\PHPMailer;


class User extends Controller {
	public function _initialize() {
		$this->web=web_config();
		// 如果数据库中仍配置为旧版 layui 主题，强制使用已重构的 default 主题
		if($this->web["template"]=="layui"){
			$this->web["template"]="default";
		}
if($this->web["wh"]=="1"){
exit($this->web["whxx"]);
}
		if(!session("userid")) {
			$this->redirect('/login');
		}else{
			$userstate="1";
}
		$this->user=Db::name('user')->where('id',session("userid"))->find();
if(!$this->user || $this->user["state"]=="0"){
session("userid",null);
$this->redirect('/login');
}
// 检查封禁状态
$isBanned = ($this->user['ban_time'] > time());
$banReason = $this->user['ban_reason'] ?? '';
$banEndTime = $this->user['ban_time'] ?? 0;
$file=file_exists(PATH."/app/index/view/".$this->web["template"]."/set.php");
if($file){
if($this->web["templateset"]){
$tempset=json_decode($this->web["templateset"],true);
$templateset=array(""=>"");
for($i=0;$i<count($tempset);$i++){
$templateset=array_merge($templateset,array($tempset[$i]["name"]=>$tempset[$i]["value"]));
}
}else{
$templateset=array(""=>"");
}
}else{
$templateset=array(""=>"");
}
		$webLogo = $this->web['logo'] ?? '';
		if ($webLogo && strpos($webLogo, 'http') !== 0 && strpos($webLogo, '/') !== 0 && strpos($webLogo, '://') === false) {
		    $webLogo = (isHTTPS() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/' . ltrim($webLogo, '/');
		}
		$this->web['logo'] = $webLogo;
		// 会员信息
		$membershipLevel = intval($this->user['membership_level'] ?? 0);
		$membershipInfo = null;
		if ($membershipLevel > 0) {
			try {
				$membershipInfo = \think\Db::name('membership_levels')->where('level', $membershipLevel)->where('status', 1)->find();
			} catch (\Exception $e) {}
		}
		$this->assign([
		            'webname'  => $this->web['name'],
		            'description'  => $this->web['description'],
		            'keywords'  => $this->web['keywords'],
		            'favicon'  => $this->web['favicon'],
		            'web'      => $this->web,
		"user"=>$this->user,
		"userstate"=>$userstate,
"templateset"=>$templateset,
"isBanned"=>$isBanned,
"membershipLevel"=>$membershipLevel,
"membershipInfo"=>$membershipInfo,
"banReason"=>$banReason,
'banEndTime'=>$banEndTime,
'forceQqGroup'=>$isBanned ? false : ($this->web['force_qq_group'] == '1' && !empty($this->web['force_qq_group_key']) && empty($this->user['force_qq_group_verified'])),
'forceQqGroupReason'=>$this->web['force_qq_group_reason'] ?? '',
'forceQqGroupNumber'=>$this->web['force_qq_group_number'] ?? '',
'forceQqGroupLink'=>$this->web['force_qq_group_link'] ?? '',
		        ]);
	}

	// 确保用户表包含最后登录相关字段
	private function ensureUserColumns() {
		ensure_user_columns();
	}

	// 确保购物车表存在
	private function ensureCartTable() {
		$prefix = \think\Db::getConfig('prefix');
		$prefix = $prefix ?: '';
		$sql = "CREATE TABLE IF NOT EXISTS `{$prefix}shopping_cart` (
		  `id` int(11) NOT NULL AUTO_INCREMENT,
		  `userid` int(11) NOT NULL,
		  `cartid` int(11) NOT NULL,
		  `user` varchar(300) DEFAULT NULL,
		  `password` varchar(320) DEFAULT NULL,
		  `time` int(11) NOT NULL DEFAULT '1',
		  `money` varchar(100) NOT NULL DEFAULT '0',
		  `cycle` varchar(100) NOT NULL,
		  `created_at` int(11) NOT NULL,
		  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未支付 1已支付 2已过期',
		  PRIMARY KEY (`id`),
		  KEY `idx_userid_status` (`userid`,`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
		Db::execute($sql);
	}

	// 加入购物车
	public function cartAdd() {
		if(Request::instance()->isPost()) {
			try {
				$this->ensureCartTable();
				$array = ["code"=>"-1", "msg"=>""];
				$cartid = input("cartid");
				$user = input("user");
				$password = input("password");
				$time = input("time");

				if($cartid=="" || $user=="" || $password=="" || $time==""){
				$array["msg"]="必填参数不可为空!";
				return json($array);
			}
			if(!is_numeric($time) || floor($time)!=$time || $time<1){
				$array["msg"]="购买时长只能填写正整数!";
				return json($array);
			}
			// 账号密码格式验证
			if(strlen($user) < 3 || strlen($user) > 50){
				$array["msg"]="面板账号长度需在3-50个字符之间!";
				return json($array);
			}
			if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_\-]*$/', $user)){
				$array["msg"]="面板账号只能以字母开头，包含字母、数字、下划线和连字符!";
				return json($array);
			}
			if(strlen($password) < 6 || strlen($password) > 50){
				$array["msg"]="面板密码长度需在6-50个字符之间!";
				return json($array);
			}
			if(preg_match('/[\x{4e00}-\x{9fa5}]/u', $password)){
				$array["msg"]="面板密码不能包含中文字符!";
				return json($array);
			}

				$cart = Db::name('cart')->where('id', $cartid)->find();
				if(!$cart){
					$array["msg"]="产品不存在!";
					return json($array);
				}
				if($cart["buy"]=="1"){
					$array["msg"]="该产品已设置禁止购买!";
					return json($array);
				}
				if($cart["inventory"] < 1){
					$array["msg"]="该产品已售完!";
					return json($array);
				}
				if($cart["limits"]=="1"){
					$exists = Db::name('order')->where(["cartid"=>$cartid,"userid"=>session("userid")])->find();
					if($exists){
						$array["msg"]="该产品只允许订购一次!";
						return json($array);
					}
				}
				if($cart["money"]=="0" && $time!="1"){
					$array["msg"]="免费产品的购买时间只能填写1!";
					return json($array);
				}
				if($cart["cycle"]=="unrestricted" && $time!="1"){
					$array["msg"]="一次性产品的购买时间只能填写1!";
					return json($array);
				}

				// 清空该用户该产品的旧未支付记录
				Db::name('shopping_cart')->where([
					"userid"=>session("userid"),
					"cartid"=>$cartid,
					"status"=>"0"
				])->delete();

				$discount = function_exists('get_membership_discount') ? get_membership_discount(session('userid'), 'buy') : 1.00;
				$money = ($cart["firstmo"]=="1") ? "0" : round(($cart["money"] * $time) * $discount, 2);
				$insertId = Db::name('shopping_cart')->insertGetId([
					"userid"=>session("userid"),
					"cartid"=>$cartid,
					"user"=>$user,
					"password"=>$password,
					"time"=>$time,
					"money"=>$money,
					"cycle"=>$cart["cycle"],
					"created_at"=>time(),
					"status"=>"0",
				]);

				$array["code"]="1";
				$array["msg"]="已加入购物车";
				$array["id"]=$insertId;
				return json($array);
			} catch (\Exception $e) {
				return json(["code"=>"-1","msg"=>"加入购物车异常：".$e->getMessage()]);
			}
		}
		return json(["code"=>"-1","msg"=>"非法请求"]);
	}

	// 支付回调后自动结算购物车
	public function cartSettle() {
		$this->clearExpiredCart();
		// 处理已到期的待开通订单（cron 未配置时兜底）
		process_pending_host_orders();
		$ids = session("cart_order_ids");
		if(!$ids){
			$this->redirect('/user/order');
		}
		session("cart_order_ids", null);
		session("cart_order_total", null);

		// 重新获取用户最新余额（在线支付后余额已被插件更新，_initialize中的user数据已过时）
		$this->user = Db::name('user')->where('id', session("userid"))->find();

		$items = Db::name('shopping_cart')
			->where([
				"userid"=>session("userid"),
				"status"=>"0",
				"id"=>["in", $ids]
			])
			->select();

		if(empty($items)){
			$this->redirect('/user/order');
		}

		$cartIds = array_unique(array_filter(array_column($items, 'cartid')));
		$cartMap = [];
		if(!empty($cartIds)){
			foreach(Db::name('cart')->where('id', 'in', $cartIds)->select() as $c){
				$cartMap[$c['id']] = $c;
			}
		}

		$total = 0;
		foreach($items as $item){
			$total += floatval($item['money']);
		}
		$total = round($total, 2);

		if($this->user['money'] < $total){
			return $this->fetch('/'.$this->web["template"].'/user/cart_settle',[
				"total"=>$total,
				"balance"=>$this->user['money'],
				"success"=>false,
				"msg"=>"账户余额不足，本次充值后余额:".$this->user['money']."元，需支付:".$total."元，差额:".round($total-$this->user['money'],2)."元",
			]);
		}

		$failedMsg = [];
		$pendingCount = 0;
		foreach($items as $item){
			$cart = isset($cartMap[$item['cartid']]) ? $cartMap[$item['cartid']] : null;
			if(!$cart){ $failedMsg[] = "产品#{$item['cartid']}不存在"; continue; }
			$server = Db::name('server')->where('id', $cart['serverid'])->find();
			if(!$server || $server['serverplugins']==""){ $failedMsg[] = "产品#{$item['cartid']}未配置服务器"; continue; }

			$times = 0;
			if($cart['cycle']=="month") $times = 2592000 * $item['time'];
			elseif($cart['cycle']=="season") $times = 7879680 * $item['time'];
			elseif($cart['cycle']=="year") $times = 31536000 * $item['time'];
			elseif($cart['cycle']=="day") $times = 86400 * $item['time'];
			elseif($cart['cycle']=="unrestricted") $times = 3153600000 * $item['time'];

			$autoCreate = isset($this->web['host_auto_create']) ? $this->web['host_auto_create'] : '0';
			$autoDelay = isset($this->web['host_auto_create_delay']) ? intval($this->web['host_auto_create_delay']) : 0;

			// 手动开通模式：插入待开通订单，由后台手动开通
			if($autoCreate == '0'){
				$now = time();
				$orderId = Db::name('order')->insertGetId([
					"user"=>$item['user'],
					"password"=>$item['password'],
					"userid"=>session("userid"),
					"cartid"=>$cart['id'],
					"atime"=>$now,
					"ztime"=>$now+$times,
					"state"=>"0",
					"auto_create_at"=>"0",
					"ordernumber"=>generate_order_number(0),
					"data1"=>"",
					"data2"=>"",
					"data3"=>"",
					"data4"=>"",
					"data5"=>"",
					"data6"=>"",
					"data7"=>"",
					"data8"=>"",
					"data9"=>"",
					"data10"=>"",
				]);
				Db::name('order')->where('id', $orderId)->update(['ordernumber'=>generate_order_number($orderId)]);
				Db::name('cart')->where('id', $cart['id'])->update(['inventory'=>max(0, intval($cart['inventory'])-1)]);
				Db::name('shopping_cart')->where('id', $item['id'])->update(['status'=>"1"]);
				Db::name('transaction')->insertGetId([
					"userid"=>session("userid"),
					"content"=>"购物车(在线支付)购买产品,ID:".$orderId."(套餐ID:".$cart['id']."),时长:".$item['time'].",消费:".$item['money']."(待手动开通)",
					"time"=>time(),
				]);
				$pendingCount++;
				continue;
			}

			// 自动开通延迟模式：先插入待开通订单，到达时间后自动开通
			if($autoCreate == '1' && $autoDelay > 0){
				$now = time();
				$orderId = Db::name('order')->insertGetId([
					"user"=>$item['user'],
					"password"=>$item['password'],
					"userid"=>session("userid"),
					"cartid"=>$cart['id'],
					"atime"=>$now,
					"ztime"=>$now+$times,
					"state"=>"0",
					"auto_create_at"=>$now+$autoDelay*60,
					"ordernumber"=>generate_order_number(0),
					"data1"=>"",
					"data2"=>"",
					"data3"=>"",
					"data4"=>"",
					"data5"=>"",
					"data6"=>"",
					"data7"=>"",
					"data8"=>"",
					"data9"=>"",
					"data10"=>"",
				]);
				Db::name('order')->where('id', $orderId)->update(['ordernumber'=>generate_order_number($orderId)]);
				Db::name('cart')->where('id', $cart['id'])->update(['inventory'=>max(0, intval($cart['inventory'])-1)]);
				Db::name('shopping_cart')->where('id', $item['id'])->update(['status'=>"1"]);
				Db::name('transaction')->insertGetId([
					"userid"=>session("userid"),
					"content"=>"购物车(在线支付)购买产品,ID:".$orderId."(套餐ID:".$cart['id']."),时长:".$item['time'].",消费:".$item['money']."(延迟".$autoDelay."分钟自动开通)",
					"time"=>time(),
				]);
				$pendingCount++;
				continue;
			}

			// 自动开通模式（延迟为0）：付款后立即开通
			$pluginFile = PATH."plugins/host/".$server['serverplugins']."/".$server['serverplugins'].".php";
			if(!file_exists($pluginFile)){ $failedMsg[] = "产品#{$item['cartid']}插件文件不存在"; continue; }
			include_once $pluginFile;

			$function = $server['serverplugins']."_CreateAccount";
			if(!function_exists($function)){ $failedMsg[] = "产品#{$item['cartid']}未实现开通接口"; continue; }
			$result = @$function($server, ["user"=>$item['user'],"password"=>$item['password'],"time"=>$item['time']], $cart, $times);
			if(!is_array($result) || !isset($result['code']) || $result['code']!="1"){
				$failedMsg[] = "产品#{$item['cartid']}开通失败：".($result['msg'] ?? '未知错误');
				continue;
			}
			Db::name('cart')->where('id', $cart['id'])->update(['inventory'=>max(0, intval($cart['inventory'])-1)]);
			Db::name('shopping_cart')->where('id', $item['id'])->update(['status'=>"1"]);
			Db::name('transaction')->insertGetId([
				"userid"=>session("userid"),
				"content"=>"购物车(在线支付)购买产品,ID:".$result['id']."(套餐ID:".$cart['id']."),时长:".$item['time'].",消费:".$item['money'],
				"time"=>time(),
			]);
		}

		$money1 = round($this->user['money'] - $total, 2);
		Db::name('user')->where('id', session("userid"))->update([
			'money' => $money1,
			'total_recharge' => round(floatval($this->user['total_recharge'] ?? 0) + $total, 2)
		]);
		if (function_exists('update_user_membership')) {
			update_user_membership(session('userid'));
		}

		if($pendingCount > 0 && empty($failedMsg)){
			$successMsg = "结算成功，产品将于 ".$autoDelay." 分钟后自动开通";
		}elseif($pendingCount > 0){
			$successMsg = "部分产品待自动开通，部分产品开通失败：".implode("；", $failedMsg);
		}else{
			$successMsg = empty($failedMsg) ? "结算成功，产品已开通" : "部分产品开通失败：".implode("；", $failedMsg);
		}

		return $this->fetch('/'.$this->web["template"].'/user/cart_settle',[
			"total"=>$total,
			"success"=>true,
			"msg"=>$successMsg,
		]);
	}

	// 清理过期购物车
	private function clearExpiredCart() {
		$this->ensureCartTable();
		Db::name('shopping_cart')
			->where('status', '0')
			->where('created_at', '<', time()-900)
			->update(["status"=>"2"]);
	}

	// 购物车列表页
	public function cart() {
		$this->clearExpiredCart();
		$items = Db::name('shopping_cart')
			->where([
				"userid"=>session("userid"),
				"status"=>"0"
			])
			->order('id desc')
			->select();

		$cartIds = array_unique(array_filter(array_column($items, 'cartid')));
		$cartMap = [];
		if(!empty($cartIds)){
			foreach(Db::name('cart')->where('id', 'in', $cartIds)->select() as $c){
				$cartMap[$c['id']] = $c;
			}
		}

		$total = 0;
		foreach($items as &$item){
			$c = isset($cartMap[$item['cartid']]) ? $cartMap[$item['cartid']] : [];
			$item['cart_name'] = isset($c['name']) ? $c['name'] : '产品#'.$item['cartid'];
			$item['cart_content'] = isset($c['content']) ? $c['content'] : '';
			$item['expire_at'] = $item['created_at'] + 900;
			$total += floatval($item['money']);
		}
		unset($item);

		return $this->fetch('/'.$this->web["template"].'/user/cart',[
			"items"=>$items,
			"total"=>round($total,2),
			"pays"=>Db::name("pays")->where("state","1")->select(),
		]);
	}

	// 删除购物车项
	public function cartDel() {
		if(Request::instance()->isPost()){
			$id = input("id");
			if($id==""){
				return json(["code"=>"-1","msg"=>"参数错误"]);
			}
			Db::name('shopping_cart')->where([
				"id"=>$id,
				"userid"=>session("userid")
			])->delete();
			return json(["code"=>"1","msg"=>"已删除"]);
		}
		return json(["code"=>"-1","msg"=>"非法请求"]);
	}

	// 购物车结算
	public function cartCheckout() {
		if(Request::instance()->isPost()){
			try {
				$this->clearExpiredCart();
				// 处理已到期的待开通订单（cron 未配置时兜底）
				process_pending_host_orders();
				$check = check_realname_limit('buy');
			if($check['code'] != 1){
				return json(["code"=>(string)$check['code'], "msg"=>$check['msg']]);
			}
			$array = ["code"=>"-1", "msg"=>""];
			$ids = input("ids");
				$paytype = input("paytype", "balance");

				if(!$ids){
					$array["msg"]="请选择要结算的商品";
					return json($array);
				}
				$idArr = is_array($ids) ? $ids : explode(',', $ids);
				$idArr = array_filter(array_map('intval', $idArr));
				if(empty($idArr)){
					$array["msg"]="请选择要结算的商品";
					return json($array);
				}

				$items = Db::name('shopping_cart')
					->where('userid', session("userid"))
					->where('status', '0')
					->where('id', 'in', $idArr)
					->select();

				if(empty($items)){
					$array["msg"]="购物车商品已过期或不存在";
					return json($array);
				}

				$cartIds = array_unique(array_filter(array_column($items, 'cartid')));
				$cartMap = [];
				if(!empty($cartIds)){
					foreach(Db::name('cart')->where('id', 'in', $cartIds)->select() as $c){
						$cartMap[$c['id']] = $c;
					}
				}

				$total = 0;
				foreach($items as $item){
					$total += floatval($item['money']);
				}
				$total = round($total, 2);

				if($paytype == "balance"){
					if($this->user['money'] < $total){
						$array["msg"]="账户余额不足，需充值：".$total."元";
						return json($array);
					}

					$successIds = [];
					$failedMsg = [];
					$pendingCount = 0;
					$autoCreate = isset($this->web['host_auto_create']) ? $this->web['host_auto_create'] : '0';
					$autoDelay = isset($this->web['host_auto_create_delay']) ? intval($this->web['host_auto_create_delay']) : 0;
					foreach($items as $item){
						$cart = isset($cartMap[$item['cartid']]) ? $cartMap[$item['cartid']] : null;
						if(!$cart){
							$failedMsg[] = "产品#{$item['cartid']}不存在";
							continue;
						}
						$server = Db::name('server')->where('id', $cart['serverid'])->find();
						if(!$server || $server['serverplugins']==""){
							$failedMsg[] = "产品#{$item['cartid']}未配置服务器";
							continue;
						}

						$times = 0;
						if($cart['cycle']=="month") $times = 2592000 * $item['time'];
						elseif($cart['cycle']=="season") $times = 7879680 * $item['time'];
						elseif($cart['cycle']=="year") $times = 31536000 * $item['time'];
						elseif($cart['cycle']=="day") $times = 86400 * $item['time'];
						elseif($cart['cycle']=="unrestricted") $times = 3153600000 * $item['time'];

						// 手动开通模式：插入待开通订单，由后台手动开通
						if($autoCreate == '0'){
							$now = time();
							$orderId = Db::name('order')->insertGetId([
								"user"=>$item['user'],
								"password"=>$item['password'],
								"userid"=>session("userid"),
								"cartid"=>$cart['id'],
								"atime"=>$now,
								"ztime"=>$now+$times,
								"state"=>"0",
								"auto_create_at"=>"0",
								"ordernumber"=>generate_order_number(0),
								"data1"=>"",
								"data2"=>"",
								"data3"=>"",
								"data4"=>"",
								"data5"=>"",
								"data6"=>"",
								"data7"=>"",
								"data8"=>"",
								"data9"=>"",
								"data10"=>"",
							]);
							Db::name('order')->where('id', $orderId)->update(['ordernumber'=>generate_order_number($orderId)]);
							Db::name('cart')->where('id', $cart['id'])->update(['inventory'=>max(0, intval($cart['inventory'])-1)]);
							Db::name('shopping_cart')->where('id', $item['id'])->update(['status'=>"1"]);
							Db::name('transaction')->insertGetId([
								"userid"=>session("userid"),
								"content"=>"购物车(余额支付)购买产品,ID:".$orderId."(套餐ID:".$cart['id']."),时长:".$item['time']."周期:".$item['cycle'].",消费:".$item['money']."(待手动开通)",
								"time"=>time(),
							]);
							$pendingCount++;
							$successIds[] = $item['id'];
							continue;
						}

						// 自动开通延迟模式：先插入待开通订单，到达时间后自动开通
						if($autoCreate == '1' && $autoDelay > 0){
							$now = time();
							$orderId = Db::name('order')->insertGetId([
								"user"=>$item['user'],
								"password"=>$item['password'],
								"userid"=>session("userid"),
								"cartid"=>$cart['id'],
								"atime"=>$now,
								"ztime"=>$now+$times,
								"state"=>"0",
								"auto_create_at"=>$now+$autoDelay*60,
								"ordernumber"=>generate_order_number(0),
								"data1"=>"",
								"data2"=>"",
								"data3"=>"",
								"data4"=>"",
								"data5"=>"",
								"data6"=>"",
								"data7"=>"",
								"data8"=>"",
								"data9"=>"",
								"data10"=>"",
							]);
							Db::name('order')->where('id', $orderId)->update(['ordernumber'=>generate_order_number($orderId)]);
							Db::name('cart')->where('id', $cart['id'])->update(['inventory'=>max(0, intval($cart['inventory'])-1)]);
							Db::name('shopping_cart')->where('id', $item['id'])->update(['status'=>"1"]);
							Db::name('transaction')->insertGetId([
								"userid"=>session("userid"),
								"content"=>"购物车(余额支付)购买产品,ID:".$orderId."(套餐ID:".$cart['id']."),时长:".$item['time']."周期:".$item['cycle'].",消费:".$item['money']."(延迟".$autoDelay."分钟自动开通)",
								"time"=>time(),
							]);
							$pendingCount++;
							$successIds[] = $item['id'];
							continue;
						}

						// 自动开通模式（延迟为0）：付款后立即开通
						$pluginFile = PATH."plugins/host/".$server['serverplugins']."/".$server['serverplugins'].".php";
						if(!file_exists($pluginFile)){
							$failedMsg[] = "产品#{$item['cartid']}插件文件不存在";
							continue;
						}
						include_once $pluginFile;

						$data2 = [
							"user"=>$item['user'],
							"password"=>$item['password'],
							"time"=>$item['time'],
						];
						$function = $server['serverplugins']."_CreateAccount";
						if(!function_exists($function)){
							$failedMsg[] = "产品#{$item['cartid']}未实现开通接口";
							continue;
						}
						$result = @$function($server, $data2, $cart, $times);
						if(!is_array($result) || !isset($result['code']) || $result['code']!="1"){
							$failedMsg[] = "产品#{$item['cartid']}开通失败：".($result['msg'] ?? '未知错误');
							continue;
						}

						// 扣库存
						Db::name('cart')->where('id', $cart['id'])->update(['inventory'=>max(0, intval($cart['inventory'])-1)]);
						// 标记购物车已支付
						Db::name('shopping_cart')->where('id', $item['id'])->update(['status'=>"1"]);
						// 记录交易日志
						Db::name('transaction')->insertGetId([
							"userid"=>session("userid"),
							"content"=>"购物车购买产品,ID:".$result['id']."(套餐ID:".$cart['id']."),时长:".$item['time']."周期:".$item['cycle'].",消费:".$item['money'],
							"time"=>time(),
						]);
						// 推广佣金
						if(!empty($this->user["upperid"]) && floatval($item['money'])>0 && isset($this->web["affdiscount"])){
							$upper=round(floatval($item['money'])*floatval($this->web["affdiscount"]),2);
							$upperuser=Db::name('user')->where('id',$this->user["upperid"])->find();
							if($upperuser){
								Db::name('user')->where('id',$this->user["upperid"])->update([
									'affmoney' =>round($upperuser["affmoney"]+$upper,2),
								]);
								Db::name('affsymoney')->insertGetId([
									"information"=>"下级ID:".session("userid")."通过购物车购买产品",
									"money"=>$upper,
									"userid"=>$this->user["upperid"],
									"time"=>time(),
								]);
							}
						}
						$successIds[] = $item['id'];
					}

					if(empty($successIds)){
						$array["msg"]="结算失败：".implode("；", $failedMsg);
						return json($array);
					}

					// 扣款
					$money1 = round($this->user['money'] - $total, 2);
					Db::name('user')->where('id', session("userid"))->update([
						'money' => $money1,
						'total_recharge' => round(floatval($this->user['total_recharge'] ?? 0) + $total, 2)
					]);
					if (function_exists('update_user_membership')) {
						update_user_membership(session('userid'));
					}

					// 发送邮件通知
					if(isset($this->web["email"]) && $this->web["email"]=="1"){
						$userInfo = Db::name('user')->where("id",session("userid"))->find();
						if($userInfo && !empty($userInfo["mail"])){
							try {
								$this->email($userInfo["mail"],"购买产品通知","你账号:".$userInfo["user"]."在时间:".date("Y-m-d H:i:s")."在本站购物车购买产品成功,共".$total."元,请登录产品管理查看!<br/><br/>");
							} catch (\Exception $mailEx) {
							}
						}
					}

					$array["code"]="1";
					if($pendingCount > 0 && empty($failedMsg)){
						$array["msg"]="结算成功，产品将于 ".$autoDelay." 分钟后自动开通";
					}elseif($pendingCount > 0){
						$array["msg"]="部分产品待自动开通，部分产品开通失败：".implode("；", $failedMsg);
					}else{
						$array["msg"]="结算成功".(!empty($failedMsg) ? "，部分失败：".implode("；", $failedMsg) : "，产品已开通");
					}
					$array["success_ids"]=$successIds;
					return json($array);
				}else{
					// 支付插件：创建支付订单，保存购物车ID到session，跳转到支付页面
					$payid = input("payid");
					if(!$payid){
						$array["msg"]="请选择支付方式";
						return json($array);
					}
					$payInfo = Db::name('pays')->where(['id'=>$payid,"state"=>"1"])->find();
					if(!$payInfo){
						$array["msg"]="支付方式不存在或已关闭";
						return json($array);
					}

					session("cart_order_ids", $idArr);
					session("cart_order_total", $total);

					// 跳转到直接收款页
					$array["code"]="1";
					$array["msg"]="正在跳转支付...";
					$array["redirect"] = Request::instance()->root().'/user/cartPay/'.$payid;
					return json($array);
				}
			} catch (\Exception $e) {
				return json(["code"=>"-1","msg"=>"结算异常：".$e->getMessage()]);
			}
		}
		return json(["code"=>"-1","msg"=>"非法请求"]);
	}

	// 直接拉起支付插件收款（购物车在线支付）
	public function cartPay() {
		$payid = input("payid");
		if(!$payid){
			exit("<title>出错啦!</title>请选择支付方式!");
		}
		$check = check_realname_limit('buy');
		if($check['code'] != 1){
			// 统一跳回购物车，由购物车页面弹出实名认证提示
			$msg = urlencode($check['msg']);
			$this->redirect('/user/cart?realname_required=1&msg=' . $msg);
		}
		$cartIds = session("cart_order_ids");
		$cartTotal = session("cart_order_total");
		if(empty($cartIds) || !is_numeric($cartTotal)){
			exit("<title>出错啦!</title>购物车订单已过期，请重新结算!");
		}
		$data1 = Db::name('pays')->where([
			'id'=>$payid,
			"state"=>"1",
		])->find();
		if(!$data1){
			exit("<title>出错啦!</title>支付方式不存在或已关闭!");
		}
		// 插件通过 input() 读取金额和支付方式，这里手动注入到请求参数
		Request::instance()->get(['money'=>$cartTotal, 'payid'=>$payid]);
		Request::instance()->post(['money'=>$cartTotal, 'payid'=>$payid]);
		// 标记订单来源，便于支付回调后区分
		Request::instance()->post(['cart_pay'=>'1']);
		@include PATH."plugins/pay/".$data1["plugins"]."/go.php";
	}

	// 推广联盟
	public function aff() {
if(!$this->user["aff"]){
if(Request::instance()->isPost()) {
while(true){
$affsj=random("6");
$data=Db::name('user')->where('aff',$affsj)->find();
if(!$data){
$user=Db::name('user')->where('id',session("userid"))->update([
"aff"=>$affsj,
]);
break;
}
}
	$array["code"]="1";
	$array["msg"]="开启推广成功!";
return json($array);
}
return $this->fetch('/'.$this->web["template"].'/user/aff',[
]);
}else{
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="txye"){
if($this->user["affmoney"]< $this->web["affwithdrawal"]){
$array["code"]="-1";
$array["msg"]="最小提现金额为:".$this->web["affwithdrawal"];
}else{
$data=Db::name('user')->where('id',session("userid"))->update([
"money"=>$this->user["money"]+$this->user["affmoney"],
"affmoney"=>"0",
]);
if($data){
$data1=Db::name('afftxjl')->insertGetId([
"information"=>"提现到账户余额",
"money"=>$this->user["affmoney"],
"userid"=>session("userid"),
"state"=>"1",
"time"=>time(),
]);
$array["code"]="1";
$array["msg"]="已成功提现到账户余额!";
}else{
$array["code"]="-1";
$array["msg"]="提现到余额失败!";
}
}

return json($array);
}

if($act=="txzfb"){
$zfbxm=input("zfbxm");
$zfbzh=input("zfbzh");
if($zfbxm=="" || $zfbzh==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if($this->user["affmoney"]< $this->web["affwithdrawal"]){
$array["code"]="-1";
$array["msg"]="最小提现金额为:".$this->web["affwithdrawal"];
}else{
$data=Db::name('user')->where('id',session("userid"))->update([
"affmoney"=>"0",
]);
if($data){
$data1=Db::name('afftxjl')->insertGetId([
"information"=>"提现到支付宝账户,姓名:<span style='color:#ff6b6b'>".$zfbxm."</span>账号:<span style='color:#ff6b6b'>".$zfbzh."</span>",
"money"=>$this->user["affmoney"],
"userid"=>session("userid"),
"state"=>"0",
"time"=>time(),
]);
if($this->web["email"]=="1"){
$admin=Db::name('admin')->where("id","1")->find();
if($admin["mail"]){
$mailbox=$this->email($admin["mail"],"推广余额提现通知","账号:".$this->user["user"]."在时间:".date("Y-m-d H:i:s")."申请支付宝提现!<br/>提现记录ID为:".$data1."<br/><br/>");
}
}
$array["code"]="1";
$array["msg"]="提现申请已提交!";
}else{
$array["code"]="-1";
$array["msg"]="提现到余额失败!";
}
}
}
return json($array);
}


}


$affsymoney=Db::name("affsymoney")->where("userid",session("userid"))->order('id desc')->paginate(10);

$afftxjl=Db::name("afftxjl")->where("userid",session("userid"))->order('id desc')->paginate(10);
$affuser=Db::name("user")->where("upperid",session("userid"))->order('id desc')->paginate(10);
//exit(dump($affuser));

// 推广中心汇总数据
$totalEarnings = Db::name("affsymoney")->where("userid",session("userid"))->sum("money") ?: 0;
$totalWithdrawn = Db::name("afftxjl")->where("userid",session("userid"))->where("state","1")->sum("money") ?: 0;
$pendingWithdraw = Db::name("afftxjl")->where("userid",session("userid"))->where("state","0")->sum("money") ?: 0;
$referralCount = Db::name("user")->where("upperid",session("userid"))->count();

$web=$this->web;
return $this->fetch('/'.$this->web["template"].'/user/affs',[
"affurl"=>(isHTTPS() ? 'https://' : 'http://').$_SERVER['HTTP_HOST']."/aff/".$this->user["aff"],
"affdiscount"=>floatval($web["affdiscount"])*100,
"affwithdrawal"=>floatval($web["affwithdrawal"]),
"affsymoney"=>$affsymoney,
"afftxjl"=>$afftxjl,
"affus"=>$affuser,
"totalEarnings"=>round($totalEarnings,2),
"totalWithdrawn"=>round($totalWithdrawn,2),
"pendingWithdraw"=>round($pendingWithdraw,2),
"referralCount"=>$referralCount,
]);



}
}


public function transfer(){
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="validate"){
$captcha=input("captcha");
if($captcha==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!\app\index\controller\Captcha::check()){
	$array["code"]="-1";
	$array["msg"]="验证码错误!";
}else{
$random=random(6,'0123456789');
session("ghid",$this->user["id"]);
session("ghyzm",$random);
if($this->web["email"]=="1"){
if($this->user["mail"]){
if (!rate_limit('transfer_email_' . $this->user['id'], 3, 60)) { $array['code']='-1'; $array['msg']='发送频率过快，请稍后再试'; return json($array); }
$codeBody = "<p>您好，</p><p>您正在申请产品过户，本次验证码为：</p><p style='text-align:center;margin:28px 0;'><span style='display:inline-block;background:#eff6ff;color:#2563eb;font-size:28px;font-weight:700;padding:14px 32px;border-radius:10px;letter-spacing:4px;border:1px solid #bfdbfe;'>{$random}</span></p><p style='color:#64748b;font-size:13px;'>验证码 10 分钟内有效，请勿将验证码告知他人。如非本人操作，请忽略此邮件。</p>";
$mailbox=$this->email($this->user["mail"],"产品过户通知",$codeBody);
$array["code"]="1";
$array["msg"]="发送验证码成功!";
}else{
	$array["code"]="-1";
	$array["msg"]="没有绑定邮箱!";
}
}else{
$array["code"]="-1";
$array["msg"]="本站未开启邮箱提醒!";
}
}
}
return json($array);
}

if($act=="transfer"){
$code=input("code");
$orderid=input("orderid");
$newuserid=input("newuserid");
if($code=="" || $orderid=="" || $newuserid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if(!session("ghid") || !session("ghyzm")){
$array["code"]="-1";
$array["msg"]="请你重新获取验证码!";
}else{
if(session("ghid")!=$this->user["id"]){
$array["code"]="-1";
$array["msg"]="账号不匹配,请你重新获取验证码!";
}else{
if($code==session("ghyzm")){
if($newuserid==session("userid")){
$array["code"]="-1";
$array["msg"]="过户的用户ID不能为自己!";
}else{
$data=Db::name("order")->where([
"id"=>$orderid,
"userid"=>session("userid"),
])->find();
if($data){
$data1=Db::name("user")->where("id",$newuserid)->find();
if($data1){
$data2=Db::name("order")->where("id",$data["id"])->update([
"userid"=>$newuserid,
]);
if($data2){
session("ghid",null);
session("ghyzm",null);
if($this->web["email"]=="1"){
if($data1["mail"]){
$mailbox=$this->email($data1["mail"],"产品接收通知","账号:".$data1["user"]."在时间:".date("Y-m-d H:i:s")."接收产品成功!<br/>产品ID:".$orderid."<br/>它的账户ID:".session("userid")."<br/><br/>");
}
if($this->user["mail"]){
$mailbox1=$this->email($this->user["mail"],"过户成功通知","账号:".$this->user["user"]."在时间:".date("Y-m-d H:i:s")."过户产品成功!<br/>过户产品ID:".$orderid."<br/>过户的账号ID:".$newuserid."<br/><br/>");
}
}
$data4=Db::name('transferrecord')->insertGetId([
"userid"=>session("userid"),
"record"=>"产品ID:".$orderid.",过户给用户ID:".$newuserid,
"time"=>time(),
]);

$data5=Db::name('transferrecord')->insertGetId([
"userid"=>$newuserid,
"record"=>"接受产品ID:".$orderid.",过户者ID:".session("userid"),
"time"=>time(),
]);
$array["code"]="1";
$array["msg"]="过户成功!";
}else{
$array["code"]="-1";
$array["msg"]="过户失败!";
}
}else{
$array["code"]="-1";
$array["msg"]="你要过户的用户ID不存在!";
}
}else{
$array["code"]="-1";
$array["msg"]="产品不存在!";
}
}
}else{
$array["code"]="-1";
$array["msg"]="邮箱验证码错误!";
}
}
}
}
return json($array);
}
}

$order=Db::name("order")->where("userid",session("userid"))->order('id desc')->select();
// 优化：批量查询 cart 表，避免 N+1 查询
$cartIds = array_unique(array_filter(array_column($order, 'cartid')));
$cartMap = [];
if (!empty($cartIds)) {
    $cartMap = Db::name('cart')->where('id', 'in', $cartIds)->column('name', 'id');
}
foreach ($order as &$orderItem) {
    $cid = $orderItem['cartid'];
    $orderItem['cartid'] = isset($cartMap[$cid]) ? $cartMap[$cid] : ('产品#' . $cid);
}
unset($orderItem);
return $this->fetch('/'.$this->web["template"].'/user/transfer',[
"order"=>$order,
]);
}

public function mail() {
if($this->user["mail"]){
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="validate"){
$captcha=input("captcha");
if($captcha==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!\app\index\controller\Captcha::check()){
	$array["code"]="-1";
	$array["msg"]="验证码错误!";
}else{
$random=random(6,'0123456789');
session("xgmail",$this->user["mail"]);
session("xgmailyzm",$random);
if($this->web["email"]=="1"){
if($this->user["mail"]){
if (!rate_limit('mail_modify_' . $this->user['id'], 3, 60)) { $array['code']='-1'; $array['msg']='发送频率过快，请稍后再试'; return json($array); }
$codeBody = "<p>您好，</p><p>您正在申请修改邮箱，本次验证码为：</p><p style='text-align:center;margin:28px 0;'><span style='display:inline-block;background:#eff6ff;color:#2563eb;font-size:28px;font-weight:700;padding:14px 32px;border-radius:10px;letter-spacing:4px;border:1px solid #bfdbfe;'>{$random}</span></p><p style='color:#64748b;font-size:13px;'>验证码 10 分钟内有效，请勿将验证码告知他人。如非本人操作，请忽略此邮件。</p>";
$mailbox=$this->email($this->user["mail"],"修改邮箱通知",$codeBody);
$array["code"]="1";
$array["msg"]="发送验证码成功!";
}else{
	$array["code"]="-1";
	$array["msg"]="没有绑定邮箱!";
}
}else{
$array["code"]="-1";
$array["msg"]="本站未开启邮箱提醒!";
}
}
}
return json($array);
}

if($act=="modify"){
$code=input("code");
$newmail=input("newmail");
if($code=="" || $newmail==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if(!session("xgmail") || !session("xgmailyzm")){
$array["code"]="-1";
$array["msg"]="请你重新获取验证码!";
}else{
if(is_valid_email($newmail)){
if(session("xgmail")!=$this->user["mail"]){
$array["code"]="-1";
$array["msg"]="邮箱不匹配,请你重新获取验证码!";
}else{
if(session("xgmailyzm")==$code){
if($this->user["mail"]==$newmail){
$array["code"]="-1";
$array["msg"]="新邮箱与旧邮箱一样!";
}else{
$newuser=Db::name('user')->where('mail',$newmail)->find();
if($newuser){
$array["code"]="-1";
$array["msg"]="新的邮箱已被其他账号绑定!";
}else{
$data=Db::name('user')->where('id',session("userid"))->update([
"mail"=>$newmail,
]);
session("xgmail",null);
session("xgmailyzm",null);
if($data){
$array["code"]="1";
$array["msg"]="修改成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}
}
}

} else {
$array["code"]="-1";
$array["msg"]="邮箱验证码错误!";
}
}
} else {
$array["code"]="-1";
$array["msg"]="邮箱格式错误!";
}
}
}
return json($array);
}




}
	return $this->fetch('/'.$this->web["template"].'/user/mail',[
]);
}else{











if(Request::instance()->isPost()) {
$act=input("act");
if($act=="validate"){
$captcha=input("captcha");
$mail=input("mail");
if($captcha=="" || $mail==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(is_valid_email($mail)){
if(!session("bdmail") || !session("bdmailyzm")){
$array["code"]="-1";
$array["msg"]="请你重新获取验证码!";
}else{
if(!\app\index\controller\Captcha::check()){
	$array["code"]="-1";
	$array["msg"]="验证码错误!";
}else{
$random=random(6,'0123456789');
session("bdmail",$mail);
session("bdmailyzm",$random);
if($this->web["email"]=="1"){
if (!rate_limit('mail_bind_' . $this->user['id'], 3, 60)) { $array['code']='-1'; $array['msg']='发送频率过快，请稍后再试'; return json($array); }
$codeBody = "<p>您好，</p><p>您正在绑定邮箱，本次验证码为：</p><p style='text-align:center;margin:28px 0;'><span style='display:inline-block;background:#eff6ff;color:#2563eb;font-size:28px;font-weight:700;padding:14px 32px;border-radius:10px;letter-spacing:4px;border:1px solid #bfdbfe;'>{$random}</span></p><p style='color:#64748b;font-size:13px;'>验证码 10 分钟内有效，请勿将验证码告知他人。如非本人操作，请忽略此邮件。</p>";
$mailbox=$this->email($mail,"请求绑定邮箱通知",$codeBody);
$array["code"]="1";
$array["msg"]="发送验证码成功!";
}else{
$array["code"]="-1";
$array["msg"]="本站未开启邮箱提醒!";
}
}
}
}else{
$array["code"]="-1";
$array["msg"]="邮箱格式错误!";
}
}
return json($array);
}

if($act=="modify"){
$code=input("code");
$mail=input("mail");
if($code=="" || $mail==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if(is_valid_email($mail)){
if(!session("bdmail") || !session("bdmailyzm")){
$array["code"]="-1";
$array["msg"]="请你重新获取验证码!";
}else{
if(session("bdmail")!=$mail){
$array["code"]="-1";
$array["msg"]="邮箱不匹配,请你重新获取验证码!";
}else{
if(session("bdmailyzm")==$code){
$newuser=Db::name('user')->where('mail',$mail)->find();
if($newuser){
$array["code"]="-1";
$array["msg"]="邮箱已被其他账号绑定!";
}else{
$data=Db::name('user')->where('id',session("userid"))->update([
"mail"=>$mail,
]);
if($data){
session("bdmail",null);
session("bdmailyzm",null);
$array["code"]="1";
$array["msg"]="绑定成功!";
}else{
$array["code"]="-1";
$array["msg"]="绑定失败!";
}
}

}else{
$array["code"]="-1";
$array["msg"]="邮箱验证码错误!";
}
}
}
}else{
$array["code"]="-1";
$array["msg"]="邮箱格式错误!";
}
}
return json($array);
}




}










	return $this->fetch('/'.$this->web["template"].'/user/bdmail',[
]);
}
}



	public function index() {
$this->ensureUserColumns();
ensure_points_products_table();
ensure_membership_levels_table();
ensure_points_log_table();
// 处理待开通订单（cron 未配置时兜底）
process_pending_host_orders();
// 兜底：若用户信息中登录相关字段为空，则重新记录（兼容字段刚新增的老用户）
// 使用 get_client_ip() 兼容 CDN / 反向代理，避免获取到 CDN 节点 IP
$loginIp = function_exists('get_client_ip') ? get_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
$storedIp = isset($this->user['last_login_ip']) ? $this->user['last_login_ip'] : '';
$storedRegion = isset($this->user['last_login_region']) ? $this->user['last_login_region'] : '';
// IP为空、地区为空，或IP发生变化时，重新记录真实IP和地区（兼容CDN）
if (empty($storedIp) || empty($storedRegion) || $storedIp !== $loginIp) {
    try {
        Db::name('user')->where('id', session("userid"))->update([
            "last_login_time" => time(),
            "last_login_ip" => $loginIp,
            "last_login_region" => get_ip_region($loginIp)
        ]);
        $this->user = Db::name('user')->where('id', session("userid"))->find();
    } catch (\Exception $e) {}
}
$data=Db::name('order')->where("userid",session("userid"))->order('id desc')->select();
// 优化：批量查询 cart 表，避免 N+1 查询
$cartIds = array_unique(array_filter(array_column($data, 'cartid')));
$cartMap = [];
if (!empty($cartIds)) {
    $cartMap = Db::name('cart')->where('id', 'in', $cartIds)->column('name', 'id');
}
foreach ($data as &$dataItem) {
    $cid = $dataItem['cartid'];
    $dataItem['cartid'] = isset($cartMap[$cid]) ? $cartMap[$cid] : ('产品#' . $cid);
}
unset($dataItem);
$data1=Db::name('announcements')->where('status',1)->order('id desc')->limit(5)->select();
$countorder=Db::name('order')->where("userid",session("userid"))->count();
$counthost=Db::name('order')->where(["userid"=>session("userid"),"state"=>["<>","3"]])->count();
$countticket=Db::name('ticket')->where("userid",session("userid"))->count();
$countrenew=Db::name('order')->where(["userid"=>session("userid"),"state"=>["<>","3"],"ztime"=>["<",time()]])->count();
$lastLoginTime=$this->user['last_login_time']?date('Y-m-d H:i:s',$this->user['last_login_time']):'-';
$lastLoginIp=$this->user['last_login_ip']?:'-';
$lastLoginRegion=$this->user['last_login_region']?:'-';
$active='index';
// 违规公示：查询该用户的违规记录（status=1 公示中）
$myViolations = [];
$publicViolations = [];
try {
    $myViolations = Db::name('violation')
        ->where('user_id', session('userid'))
        ->where('status', 1)
        ->order('create_time desc')
        ->select();
    // 所有公示中的违规记录（用于公告栏，前端CSS限制最多显示2条高度，其余滚动查看）
    $publicViolations = Db::name('violation')
        ->where('status', 1)
        ->order('create_time desc')
        ->select();
    $publicViolationsTotal = count($publicViolations);
} catch (\Exception $e) {}
		// 积分和会员数据
		$todayStart = strtotime(date('Y-m-d'));
		$canCheckin = ($this->user['last_checkin_time'] ?? 0) < $todayStart;
		$userPoints = intval($this->user['points'] ?? 0);
		$membershipLevel = intval($this->user['membership_level'] ?? 0);
		$membershipInfo = null;
		if ($membershipLevel > 0) {
			try {
				$membershipInfo = Db::name('membership_levels')->where('level', $membershipLevel)->where('status', 1)->find();
			} catch (\Exception $e) {}
		}
		// 最近签到记录
		$recentCheckins = [];
		try {
			$recentCheckins = Db::name('points_log')->where('userid', session('userid'))->where('type', 'checkin')->order('id desc')->limit(5)->select();
		} catch (\Exception $e) {}
		// 累计充值
		$totalRecharge = floatval($this->user['total_recharge'] ?? 0);
		// 下一个会员等级
		$nextLevel = null;
		if ($membershipLevel < 6) {
			try {
				$nextLevel = Db::name('membership_levels')->where('level', $membershipLevel + 1)->where('status', 1)->find();
			} catch (\Exception $e) {}
		}
		return $this->fetch('/'.$this->web["template"].'/user/index',[
"user"=>$this->user,
"order"=>$data,
"announcement"=>$data1,
"countorder"=>$countorder,
"counthost"=>$counthost,
"countticket"=>$countticket,
"countrenew"=>$countrenew,
"lastLoginTime"=>$lastLoginTime,
"lastLoginIp"=>$lastLoginIp,
"lastLoginRegion"=>$lastLoginRegion,
"active"=>$active,
"myViolations"=>$myViolations,
"publicViolations"=>$publicViolations,
"publicViolationsTotal"=>$publicViolationsTotal,
"userPoints"=>$userPoints,
"canCheckin"=>$canCheckin,
"membershipLevel"=>$membershipLevel,
"membershipInfo"=>$membershipInfo,
"totalRecharge"=>$totalRecharge,
"nextLevel"=>$nextLevel,
"recentCheckins"=>$recentCheckins,

]);
	}


public function information() {
if(Request::instance()->isPost()) {
$name=input("name");
$qq=input("qq");
$address=input("address");
if($name=="" || $qq==""){
$array["code"]="-1";
$array["msg"]="必填参数不能为空!";
}else{
$data=Db::name('user')->where('id',$this->user["id"])->update([
"name" =>$name,
"qq"=>$qq,
"address"=>$address,
]);
if($data){
$array["code"]="1";
$array["msg"]="修改资料成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改资料失败!";
}
}
return json($array);
}
return $this->fetch('/'.$this->web["template"].'/user/information');
}

	public function password() {

		if(Request::instance()->isPost()) {
			$act=input("act");
			if($act=="jmmxg"){
			$password=input("userPwd");
			$newpassword=input("newUserPwd");
            $newuserrepwd=input("newUserRepwd");
if($password=="" || $newpassword=="" || $newuserrepwd==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if($newpassword!=$newuserrepwd){
	$array["code"]="-1";
	$array["msg"]="两次输入的新密码不一样!";
}else{
if($password==$newpassword){
	$array["code"]="-1";
    $array["msg"]="原始密码不能和新密码一样!";
}else{
			if(password_verify($password,$this->user["password"])) {
				$data=Db::name('user')->where('id',$this->user["id"])->update([
'password' =>password_hash($newpassword,PASSWORD_DEFAULT),
]);
				if($data) {
					$array["code"]="1";
					$array["msg"]="旧密码修改密码成功!";
if($this->web["email"]=="1"){
if($this->user["mail"]){
if (!rate_limit('password_modify_' . $this->user['id'], 3, 60)) { $array['code']='-1'; $array['msg']='发送频率过快，请稍后再试'; return json($array); }
$mailbox=$this->email($this->user["mail"],"旧密码修改密码通知","你账号:".$this->user["user"]."在时间:".date("Y-m-d H:i:s")."在本站旧密码修改密码成功!<br/><br/>");
}
}
				} else {
					$array["code"]="-1";
					$array["msg"]="修改密码失败";
				}
			} else {
				$array["code"]="-1";
				$array["msg"]="原始密码错误!";
			}
}
}
}

			return json($array);
			}
			
			if($act=="validate"){
$captcha=input("captcha");
if($captcha==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!\app\index\controller\Captcha::check()){
	$array["code"]="-1";
	$array["msg"]="验证码错误!";
}else{
$random=random(6,'0123456789');
session("mmzh",$this->user["id"]);
session("mmyzm",$random);
if($this->web["email"]=="1"){
if($this->user["mail"]){
if (!rate_limit('password_validate_' . $this->user['id'], 3, 60)) { $array['code']='-1'; $array['msg']='发送频率过快，请稍后再试'; return json($array); }
$codeBody = "<p>您好，</p><p>您正在申请修改密码，本次验证码为：</p><p style='text-align:center;margin:28px 0;'><span style='display:inline-block;background:#eff6ff;color:#2563eb;font-size:28px;font-weight:700;padding:14px 32px;border-radius:10px;letter-spacing:4px;border:1px solid #bfdbfe;'>{$random}</span></p><p style='color:#64748b;font-size:13px;'>验证码 10 分钟内有效，请勿将验证码告知他人。如非本人操作，请忽略此邮件。</p>";
$mailbox=$this->email($this->user["mail"],"修改密码通知",$codeBody);
$array["code"]="1";
$array["msg"]="发送验证码成功!";
}else{
	$array["code"]="-1";
	$array["msg"]="没有绑定邮箱!";
}
}else{
$array["code"]="-1";
$array["msg"]="本站未开启邮箱提醒!";
}
}
}
return json($array);
}

if($act=="yxmmxg"){
$code=input("code");
$newpass=input("newpass");
if($code=="" || $newpass==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if(!session("mmzh") || !session("mmyzm")){
$array["code"]="-1";
$array["msg"]="请你重新获取验证码!";
}else{
if(session("mmzh")!=$this->user["id"]){
$array["code"]="-1";
$array["msg"]="账号不匹配,请你重新获取验证码!";
}else{
if(session("mmyzm")==$code){
$data=Db::name('user')->where('id',session("userid"))->update([
"password"=>password_hash($newpass,PASSWORD_DEFAULT),
]);
if($data){
session("mmzh",null);
session("mmyzm",null);
if($this->web["email"]=="1"){
if($this->user["mail"]){
$mailbox=$this->email($this->user["mail"],"邮箱修改密码通知","你账号:".$this->user["user"]."在时间:".date("Y-m-d H:i:s")."在本站邮箱修改密码成功!<br/><br/>");
}
}
$array["code"]="1";
$array["msg"]="修改成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}

}else{
$array["code"]="-1";
$array["msg"]="邮箱验证码错误!";
}
}
}
}
return json($array);
}




		}
		
		
		
		return $this->fetch('/'.$this->web["template"].'/user/password');
	}

	public function logout() {
		session("userid",null);
		$this->redirect('/login');
	}

	// 实名认证
	public function realname() {
		ensure_user_columns();
		$this->user = Db::name('user')->where('id', session("userid"))->find();
		$realnameMode = isset($this->web['realname_mode']) ? $this->web['realname_mode'] : '0';

		if(Request::instance()->isPost()) {
			$act = input('act');
			$array = ['code' => '-1', 'msg' => ''];

			if($act == 'submit') {
				$name = trim(input('realname', ''));
				$idcard = trim(input('idcard', ''));
				$mobile = trim(input('mobile', ''));
				if(empty($name) || empty($idcard)) {
					$array['msg'] = '姓名和身份证号不能为空';
					return json($array);
				}
				// 基本格式校验
				if(!preg_match('/^[\x{4e00}-\x{9fa5}·]{2,20}$/u', $name)) {
					$array['msg'] = '姓名格式错误';
					return json($array);
				}
				if(!preg_match('/^\d{17}[\dXx]$/', $idcard)) {
					$array['msg'] = '身份证号格式错误';
					return json($array);
				}
				// 手机三要素需要手机号
				$apiType = isset($this->web['realname_api_type']) ? $this->web['realname_api_type'] : '1';
				if ($apiType == '3' && empty($mobile)) {
					$array['msg'] = '手机号不能为空';
					return json($array);
				}
				if ($apiType == '3' && !preg_match('/^1[3-9]\d{9}$/', $mobile)) {
					$array['msg'] = '手机号格式错误';
					return json($array);
				}

				// 已通过认证的不能重复认证
				if ($this->user['realname_status'] == 1) {
					$array['msg'] = '您已完成实名认证，无需重复认证';
					return json($array);
				}
				// 待审核中的不能重复提交
				if ($this->user['realname_status'] == 3) {
					$array['msg'] = '您的实名认证申请正在审核中，请耐心等待';
					return json($array);
				}

				$userId = session("userid");
				$attempts = intval(isset($this->user['realname_attempts']) ? $this->user['realname_attempts'] : 0);
				$firstFree = (isset($this->web['realname_first_free']) ? $this->web['realname_first_free'] : '1') == '1';
				$chargeAmount = floatval(isset($this->web['realname_charge_amount']) ? $this->web['realname_charge_amount'] : 0);

				// 判断是否需要收费
				$needCharge = !($firstFree && $attempts == 0);

				if ($needCharge && $chargeAmount > 0) {
					// 检查余额是否足够
					if (floatval($this->user['money']) < $chargeAmount) {
						$array['msg'] = '实名认证需要' . $chargeAmount . '元，您的余额不足，请先充值';
						return json($array);
					}
				}

				if($realnameMode == '1') {
					try {
						// 先扣费/扣次数（无论结果如何，调用API即计费）
						if ($needCharge && $chargeAmount > 0) {
							Db::name('user')->where('id', $userId)->setDec('money', $chargeAmount);
							Db::name('transaction')->insert([
								'userid' => $userId,
								'content' => '实名认证费用，扣除' . $chargeAmount . '元',
								'time' => time(),
							]);
						}

						Db::name('user')->where('id', $userId)->setInc('realname_attempts');
						$attempts++;

						$result = realname_api_verify($name, $idcard, $mobile);

						if (!is_array($result) || !isset($result['code'])) {
							$array['msg'] = '实名认证返回数据异常';
							return json($array);
						}

						$status = ($result['code'] == 1) ? 1 : 2;

						ensure_realname_record_table();
						Db::name('realname_record')->insert([
							'user_id' => $userId,
							'realname' => $name,
							'idcard' => $idcard,
							'status' => $status,
							'apply_time' => time(),
							'review_time' => ($status == 1) ? time() : 0,
						]);

						if($result['code'] == 1) {
							Db::name('user')->where('id', $userId)->update([
								'realname' => $name,
								'idcard' => $idcard,
								'realname_status' => 1,
							]);
							$this->user['realname'] = $name;
							$this->user['idcard'] = $idcard;
							$this->user['realname_status'] = 1;
							// API认证成功，发送邮件通知用户
							if($this->web["email"]=="1" && !empty($this->user["mail"])){
								try {
									self::email($this->user["mail"], "实名认证通过通知", '<p>您好 '.htmlspecialchars($name).'，</p><p>恭喜！您的实名认证已审核通过。</p><p style="color:#64748b;font-size:13px;">认证姓名：'.htmlspecialchars($name).'<br>认证时间：'.date("Y-m-d H:i:s").'</p>');
								} catch (\Exception $mailEx) {}
							}
						}

						if ($result['code'] != 1) {
							$costInfo = $needCharge && $chargeAmount > 0
								? '（已扣除' . $chargeAmount . '元）'
								: '（已消耗认证机会）';
							$remainingFree = max(0, ($firstFree ? 1 : 0) - $attempts);
							if ($firstFree && $remainingFree > 0) {
								$result['msg'] .= $costInfo . '，还剩余 ' . $remainingFree . ' 次免费认证机会';
							} else {
								$result['msg'] .= $costInfo . '，已累计认证 ' . $attempts . ' 次';
							}
						}

						return json($result);
					} catch (\Throwable $e) {
						$logDir = defined('LOG_PATH') ? LOG_PATH : (PATH . '/runtime/log/');
						if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
						@file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " User.php realname exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
						$array['msg'] = '实名认证处理异常：' . $e->getMessage();
						return json($array);
					}
				}

				if($realnameMode == '2') {
					// 人工审核模式：不扣费，提交换审核
					Db::name('user')->where('id', $userId)->update([
						'realname' => $name,
						'idcard' => $idcard,
						'realname_status' => 3,
					]);
					$this->user['realname'] = $name;
					$this->user['idcard'] = $idcard;
					$this->user['realname_status'] = 3;
					ensure_realname_record_table();
					Db::name('realname_record')->insert([
						'user_id' => $userId,
						'realname' => $name,
						'idcard' => $idcard,
						'status' => 3,
						'apply_time' => time(),
					]);
					// 发送邮件通知管理员有新的实名认证待审核
					if($this->web["email"]=="1" && !empty($this->web['emailname'])){
						$userInfo = $this->user;
						$adminEmail = $this->web['emailname'];
						$auditToken = md5($userId . $name . 'email_audit_salt_2024');
						$siteUrl = request()->domain() . request()->root();
						$approveUrl = $siteUrl . '/admin/emailAudit?token=' . $auditToken . '&action=approve&id=' . $userId;
						$rejectUrl = $siteUrl . '/admin/emailAudit?token=' . $auditToken . '&action=reject&id=' . $userId;
						try {
							self::email($adminEmail, "新的实名认证待审核", '<div style="max-width:600px;margin:0 auto;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',\'PingFang SC\',sans-serif;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.06);"><div style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);padding:28px 32px;text-align:center;"><h2 style="color:#fff;margin:0;font-size:20px;">新的实名认证待审核</h2></div><div style="padding:32px;"><p style="color:#334155;font-size:15px;line-height:1.6;">您好管理员，</p><p style="color:#334155;font-size:15px;line-height:1.6;">用户 <b>'.htmlspecialchars($userInfo['name'] ?? $userInfo['user']).'</b>（ID:'.$userId.'）提交了实名认证申请，请尽快审核。</p><table style="width:100%;border-collapse:collapse;margin:20px 0;background:#f8fafc;border-radius:8px;overflow:hidden;"><tr><td style="padding:12px 16px;color:#64748b;font-size:13px;border-bottom:1px solid #e2e8f0;">认证姓名</td><td style="padding:12px 16px;color:#0f172a;font-size:14px;font-weight:500;border-bottom:1px solid #e2e8f0;">'.htmlspecialchars($name).'</td></tr><tr><td style="padding:12px 16px;color:#64748b;font-size:13px;">提交时间</td><td style="padding:12px 16px;color:#0f172a;font-size:14px;font-weight:500;">'.date("Y-m-d H:i:s").'</td></tr></table><div style="margin:24px 0;text-align:center;"><a href="'.$approveUrl.'" style="display:inline-block;padding:12px 32px;background:linear-gradient(135deg,#059669,#047857);color:#fff;text-decoration:none;border-radius:8px;font-size:15px;font-weight:500;margin:0 6px;">✓ 通过审核</a><a href="'.$rejectUrl.'" style="display:inline-block;padding:12px 32px;background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff;text-decoration:none;border-radius:8px;font-size:15px;font-weight:500;margin:0 6px;">✕ 驳回审核</a></div><p style="color:#94a3b8;font-size:12px;text-align:center;margin-top:16px;">或 <a href="'.$siteUrl.'/admin/realnameReview" style="color:#3b82f6;">前往后台审核页面</a> 查看详情</p></div></div>');
						} catch (\Exception $mailEx) {}
					}
					$array['code'] = '1';
					$array['msg'] = '已提交实名认证申请，请等待管理员审核';
					return json($array);
				}

				if($realnameMode == '3') {
					$array['msg'] = '阿里云认证暂未实现，请使用其他认证方式';
					return json($array);
				}

				$array['msg'] = '实名认证未开启';
				return json($array);
			}

			return json($array);
		}

		return $this->fetch('/'.$this->web["template"].'/user/realname', [
			'realnameMode' => $realnameMode,
			'apiType' => isset($this->web['realname_api_type']) ? $this->web['realname_api_type'] : '1',
			'user' => $this->user,
		]);
	}


	public function pay() {
	if($this->user['ban_time'] > time()){
		exit("<title>账号已封禁</title>你的账号已被封禁至 ".date("Y-m-d H:i:s",$this->user['ban_time'])."，封禁期间无法充值。<br>原因：".($this->user['ban_reason'] ?: '无'));
	}
	$check = check_realname_limit('pay');
	if($check['code'] != 1){
		$data=Db::name("pays")->where("state","1")->select();
		for($i=0;$i<count($data);$i++)
		{
			unset($data[$i]["plugins"]);
			unset($data[$i]["data"]);
		}
		return $this->fetch('/'.$this->web["template"].'/user/pay',[
			"pay"=>$data,
			"paycron"=>$this->web["paycron"],
			"cartAmount"=>session("cart_order_total") ?: null,
			"realnameRequired"=>true,
			"realnameMsg"=>$check['msg'],
			"realnameUrl"=>'/user/realname',
		]);
	}
	if(Request::instance()->isPost()) {
if(is_numeric(input("money"))){
if(!input("money")){
exit("<title>出错啦!</title>金额不可为空或为0!");
}else{
if(input("money")<"0.01"){
exit("<title>出错啦!</title>金额不可少于0.01!");
}else{
if(getLen(input("money"))>2){
exit("<title>出错啦!</title>金额的小数点后不能超过两位!");
}else{
$data1=Db::name('pays')->where([
'id'=>input("payid"),
"state"=>"1",
])->find();
if($data1){
@include PATH."plugins/pay/".$data1["plugins"]."/go.php";
}else{
exit("<title>出错啦!</title>支付方式不存在!");
}
}}
}
}else{
exit("<title>出错啦!</title>金额必须是数学!");
}
}
$data=Db::name("pays")->where("state","1")->select();
for($i=0;$i<count($data);$i++)  
   {
unset($data[$i]["plugins"]);
unset($data[$i]["data"]);
}
		return $this->fetch('/'.$this->web["template"].'/user/pay',[
"pay"=>$data,
"paycron"=>$this->web["paycron"],
"cartAmount"=>session("cart_order_total") ?: null,
]);
}

	public function return($id) {
$data1=Db::name('pays')->where('id',$id)->find();
if(!$data1){
exit("<title>出错啦!</title>没有此支付通道!");
}
@include PATH."plugins/pay/".$data1["plugins"]."/return.php";

// 检查是否有待结算的购物车订单
if(session("cart_order_ids")){
	$this->redirect('/user/cartSettle');
	return;
}

return $this->fetch('/'.$this->web["template"].'/user/return',[
"msg"=>$msg,
]);
}

	public function order($id=null) {
if($id){

$data=Db::name('order')->where([
'id'=>$id,
"userid"=>session("userid"),
])->find();
if($data){
$a=Db::name('cart')->where('id',$data["cartid"])->find();
$b=Db::name('server')->where('id',$a["serverid"])->find();
if(!$b) $b = [];
if(Request::instance()->isPost()) {
$act=input("act");

$hasPlugin = ($b && !empty($b["serverplugins"]));
if($hasPlugin){
	@include PATH."plugins/host/".$b["serverplugins"]."/ClientArea.php";
}

if($act=="renew"){
$check = check_realname_limit('renew');
if($check['code'] != 1){
	$array["code"]=(string)$check['code'];
	$array["msg"]=$check['msg'];
	return json($array);
}
$time=input("time");
if($time==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
if(!is_numeric($time)){
$array["code"]="-1";
$array["msg"]="参数有误,只能填写数字!";
}else{
if(floor($time)!=$time){
	$array["code"]="-1";
	$array["msg"]="只能填写整数!";
}else{
if($time<1){
	$array["code"]="-1";
	$array["msg"]="续费时间不能小于1!";
}else{
if($a["renew"]=="1"){
$array["code"]="-1";
$array["msg"]="该产品已设置禁止续费!";
}else{
$db=Db::name('user')->where('id',session("userid"))->find();
$discount = function_exists('get_membership_discount') ? get_membership_discount(session('userid'), 'renew') : 1.00;
$money = round($a["money"] * $time * $discount, 2);
if($a["cycle"]=="unrestricted"){
$array["code"]="-1";
$array["msg"]="一次性付款产品,禁止续费!";
}else{
if($db["money"]>=$money){
$times=$data["ztime"]-time();
if($a["money"]=="0" &&  $times > 432000){
$array["code"]="-1";
$array["msg"]="免费产品只能在到期前的5天内续费!";
}else{
if($a["money"]=="0" &&  $time!="1"){
$array["code"]="-1";
$array["msg"]="免费产品续费时间只能填写1";
}else{
if($data["state"]=="3"){
//判断产品为终止状态
$array["code"]="-1";
$array["msg"]="该产品已终止,禁止续费!";
}else{
include_once PATH."plugins/host/".$b["serverplugins"]."/".$b["serverplugins"].".php";
			if($data["state"]=="2"){
			//判断产品为暂停状态 加一个修改主机状态
			if($hasPlugin){
				$function2=$b["serverplugins"]."_"."UnsuspendAccount";
				if(function_exists($function2)){
					$data1=@$function2($b,$data,$a);
				}
			}
			$data2=Db::name('order')->where([
			'id'=>$id,
			"userid"=>session("userid"),
			])->update([
			"state"=>"1",
			]);
			}
			if($a["cycle"]=="month"){
			$times="2592000"*$time;
			}
			if($a["cycle"]=="season"){
			$times="7879680"*$time;
			}
			if($a["cycle"]=="year"){
			$times="31536000"*$time;
			}
			if($a["cycle"]=="day"){
			$times="86400"*$time;
			}
			if($a["cycle"]=="unrestricted"){
			$times="315360000"*$time;
			}
			if($hasPlugin){
				$function1=$b["serverplugins"]."_"."renew";
				if(function_exists($function1)){
					$data11=$function1($b,$data,$a,$times,$time);
				}else{
					$data11=["code"=>"1","msg"=>"续费成功"];
				}
			}else{
				$data11=["code"=>"1","msg"=>"续费成功"];
			}
			if($data11["code"]=="1"){
$money1=round($db["money"]-$money,2);
Db::name('user')->where('id',session("userid"))->update([
	'money' => $money1,
	'total_recharge' => round(floatval($db['total_recharge'] ?? 0) + $money, 2)
]);
if (function_exists('update_user_membership')) {
	update_user_membership(session('userid'));
}
$data1=Db::name('order')->where([
'id'=>$id,
"userid"=>session("userid"),
])->update([
"ztime"=>$data["ztime"]+$times,
]);
//消费记录
$data66=Db::name('transaction')->insertGetId([
"userid"=>session("userid"),
"content"=>"续费产品,ID:".$id.",时长:".$time.",消费:".$money,
"time"=>time(),
]);

//aff收益
if($db["upperid"]){
if($money!="0"){
$upper=round($money*floatval($this->web["affdiscount"]),2);
$upperuser=Db::name('user')->where('id',$db["upperid"])->find();
$uppermoney=Db::name('user')->where('id',$db["upperid"])->update([
'affmoney' =>round($upperuser["affmoney"]+$upper,2),
]);
$data5=Db::name('affsymoney')->insertGetId([
"information"=>"下级ID:".session("userid")."续费产品",
"money"=>$upper,
"userid"=>$db["upperid"],
"time"=>time(),
]);
}
}

	$array["code"]="1";
	$array["msg"]="续费成功";	
}else{
	$array["code"]="-1";
	$array["msg"]="续费失败!".$data11["msg"];	

}

}

}

}

} else {
	$array["code"]="-1";
	$array["msg"]="余额不足";	
}
}
}
}
}
}
}
return json($array);
}

if($act=="upgrade"){
$db=Db::name('user')->where('id',session("userid"))->find();
$newcartid=input("newcartid");
if($newcartid){
if($data["state"]=="3"){
	$array["code"]="-1";
	$array["msg"]="产品是终止状态,禁止变更!";	
}else{
include_once PATH."plugins/host/".$b["serverplugins"]."/".$b["serverplugins"].".php";
			$function1=$b["serverplugins"]."_"."upgrade";
			if(!$hasPlugin){
				$array["code"]="-1";
				$array["msg"]="当前产品未配置服务器插件，无法升降级!";
				return json($array);
			}
			if($a["upgrade"]!="1" || judge(json_decode($a["upgrades"],true),$newcartid)!="1" || !function_exists($function1)){
$array["code"]="-1";
$array["msg"]="变更三要素检测不通过,禁止变更!";
}else{
if($data["cartid"]==$newcartid){
	$array["code"]="-1";
	$array["msg"]="产品一样,无需变更!";	
}else{
$db12=Db::name('cart')->where('id',$newcartid)->find();
if($a["serverid"]!=$db12["serverid"]){
	$array["code"]="-1";
	$array["msg"]="不可升级!";	
}else{
if($db12["inventory"] < 1){
$array["code"]="-1";
$array["msg"]="变更失败,变更的产品库存不足!";
}else{
if($db12){
if($a["cycle"]=="month"){
$timess="2592000";
}
if($a["cycle"]=="season"){
$timess="7879680";
}
if($a["cycle"]=="year"){
$timess="31536000";
}
if($a["cycle"]=="day"){
$timess="86400";
}
if($a["cycle"]=="unrestricted"){
$timess="315360000";
}
if($db12["cycle"]=="month"){
$times="2592000";
}
if($db12["cycle"]=="season"){
$times="7879680";
}
if($db12["cycle"]=="year"){
$times="31536000";
}
if($db12["cycle"]=="day"){
$times="86400";
}
if($db12["cycle"]=="unrestricted"){
$times="315360000";
}
$money12=(($db12["money"]/($times/86400))*(($data["ztime"]-time())/86400));
$money12=$money12-(($a["money"]/($timess/86400))*(($data["ztime"]- time())/86400));
$money12=round($money12,2);
if($money12<0){
$money12="0";
}
if($this->user["money"]<$money12){
	$array["code"]="-1";
	$array["msg"]="余额不足,需要:".$money12."元";	
}else{
include_once PATH."plugins/host/".$b["serverplugins"]."/".$b["serverplugins"].".php";
$function=$b["serverplugins"]."_"."upgrade";
if(function_exists($function)){
$data1=@$function($b,$data,$a,$db12);
}
if($data1["code"]=="1"){
$db13=Db::name('user')->where('id',session("userid"))->update([
'money' =>round($this->user["money"]-$money12,2),
]);
$db14=Db::name('order')->where('id',$data["id"])->update([
'cartid' =>$db12["id"],
]);
$db15=Db::name('cart')->where('id',$a["id"])->update([
'inventory' =>$a["inventory"]+1,
]);
$db16=Db::name('cart')->where('id',$db12["id"])->update([
'inventory' =>$db12["inventory"]-1,
]);

//消费记录
if($money12!="0"){
$data66=Db::name('transaction')->insertGetId([
"userid"=>session("userid"),
"content"=>"升级产品,ID:".$id.",消费:".$money12,
"time"=>time(),
]);
}

//aff收益
if($db["upperid"]){
if($money12!="0"){
$upper=round($money12*floatval($this->web["affdiscount"]),2);
$upperuser=Db::name('user')->where('id',$db["upperid"])->find();
$uppermoney=Db::name('user')->where('id',$db["upperid"])->update([
'affmoney' =>round($upperuser["affmoney"]+$upper,2),
]);
$data5=Db::name('affsymoney')->insertGetId([
"information"=>"下级ID:".session("userid")."升级产品",
"money"=>$upper,
"userid"=>$db["upperid"],
"time"=>time(),
]);
}
}
	$array["code"]="1";
	$array["msg"]="操作成功!";	
}else{
	$array["code"]="-1";
	$array["msg"]=$data1["msg"];	
}
}
}else{
	$array["code"]="-1";
	$array["msg"]="产品不存在!";	
}
}
}
}
}
}
}else{
	$array["code"]="-1";
	$array["msg"]="请选择产品!";	
}
			return json($array);
			}

			if($act=="reset"){
				$array=["code"=>"-1","msg"=>"重置失败"];
				if(!$hasPlugin){
					// 无插件：生成本地密码
					$newpass = random(10);
					Db::name('order')->where(['id'=>$id,"userid"=>session("userid")])->update(["password"=>$newpass]);
					$array["code"]="1";
					$array["msg"]="密码已重置";
					return json($array);
				}
				include_once PATH."plugins/host/".$b["serverplugins"]."/".$b["serverplugins"].".php";
				$function=$b["serverplugins"]."_"."ChangePassword";
				if(!function_exists($function)){
					$newpass = random(10);
					Db::name('order')->where(['id'=>$id,"userid"=>session("userid")])->update(["password"=>$newpass]);
					$array["code"]="1";
					$array["msg"]="密码已重置";
					return json($array);
				}
				$newpass = random(10);
				$result = @$function($b,$data,$newpass);
				if(is_array($result) && isset($result['code']) && $result['code']=="1"){
					Db::name('order')->where(['id'=>$id,"userid"=>session("userid")])->update(["password"=>$newpass]);
					$array["code"]="1";
					$array["msg"]="密码已重置";
				}else{
					$array["msg"]="重置失败: ".($result['msg'] ?? '接口异常');
				}
				return json($array);
			}

			if($act=="delete"){
				$array=["code"=>"-1","msg"=>"删除失败"];
				if($data["state"]=="1" || $data["state"]=="2"){
					if($hasPlugin){
						include_once PATH."plugins/host/".$b["serverplugins"]."/".$b["serverplugins"].".php";
						$function=$b["serverplugins"]."_"."TerminateAccount";
						if(function_exists($function)){
							@$function($b,$data,$a);
						}
					}
				}
				Db::name('order')->where(['id'=>$id,"userid"=>session("userid")])->delete();
				Db::name('cart')->where('id',$a['id'])->update(["inventory"=>intval($a['inventory'])+1]);
				$array["code"]="1";
				$array["msg"]="删除成功";
				return json($array);
			}

		}
			$hasPlugin = ($b && !empty($b["serverplugins"]));
			if($hasPlugin){
				@include_once PATH."plugins/host/".$b["serverplugins"]."/".$b["serverplugins"].".php";
				$function=$b["serverplugins"]."_"."ClientArea";
				$function1=$b["serverplugins"]."_"."upgrade";
			}else{
				$function="";
				$function1="";
			}

			if($a["upgrade"]=="1" && $a["upgrades"] && function_exists($function1)){
			$upgrade="1";
			}else{
			$upgrade="0";
			}
if($a["upgrades"] && $a["upgrades"]!="null"){
$a["upgrades"]=json_decode($a["upgrades"],true);
for($i=0;$i<count($a["upgrades"]);$i++)  
   {
$db11=Db::name('cart')->where('id',$a["upgrades"][$i])->find();

if($a["cycle"]=="month"){
$timess="2592000";
}
if($a["cycle"]=="season"){
$timess="7879680";
}
if($a["cycle"]=="year"){
$timess="31536000";
}
if($a["cycle"]=="day"){
$timess="86400";
}
if($a["cycle"]=="unrestricted"){
$timess="315360000";
}

if($db11["cycle"]=="month"){
$times="2592000";
}
if($db11["cycle"]=="season"){
$times="7879680";
}
if($db11["cycle"]=="year"){
$times="31536000";
}
if($db11["cycle"]=="day"){
$times="86400";
}
if($db11["cycle"]=="unrestricted"){
$times="315360000";
}
$money11=(($db11["money"]/($times/86400))*(($data["ztime"]-time())/86400));
$money11=$money11-(($a["money"]/($timess/86400))*(($data["ztime"]- time())/86400));
$money11=round($money11,2);
if($money11<0){
$money11="0";
}

$upgrades[$i]["id"]=$db11["id"];
$upgrades[$i]["information"]="ID:".$db11["id"]."=>".$db11["name"]."=>所需金额:".$money11."元";

}
}else{
$upgrades="";
}
//var_dump($upgrade);
if(function_exists($function)){
$ClientArea=@$function($b,$a,$data);
}else{
$ClientArea="";
}
		return $this->fetch('/'.$this->web["template"].'/user/panel',[
"server"=>$b,
"data"=>$data,
"cart"=>$a,
"ClientArea"=>$ClientArea,
"upgrade"=>$upgrade,
"upgrades"=>$upgrades,
]);

}else{
		$this->redirect('/user/order/');
}
}else{
		$data=Db::name('order')->where("userid",session("userid"))->order('id desc')->select();
	// 优化：批量查询 cart 表，避免 N+1 查询
	$cartIds = array_unique(array_filter(array_column($data, 'cartid')));
	$cartMap = [];
	if (!empty($cartIds)) {
	    $cartMap = Db::name('cart')->where('id', 'in', $cartIds)->column('name', 'id');
	}
	foreach ($data as &$dataItem) {
	    $cid = $dataItem['cartid'];
	    $dataItem['cartid'] = isset($cartMap[$cid]) ? $cartMap[$cid] : ('产品#' . $cid);
	}
	unset($dataItem);
/**
		foreach ($data as &$item) {
		$b=Db::name('cart')->where('id',$item["cartid"])->find();
		$item["name"]=$b["name"];		 
		}
		**/
	
		$totalCount = count($data);
	$runningCount = 0;
	$expiredCount = 0;
	$now = time();
	foreach ($data as $item) {
		if ($item['state'] == "1") {
			$runningCount++;
		}
		if ($item['state'] == "3" || (isset($item['ztime']) && $item['ztime'] < $now)) {
			$expiredCount++;
		}
	}
	return $this->fetch('/'.$this->web["template"].'/user/order',[
"order"=>$data,
"totalCount"=>$totalCount,
"runningCount"=>$runningCount,
"expiredCount"=>$expiredCount,
]);
}

}

	public function payrecord() {
$data=Db::name('pay')->where("userid",session("userid"))->order('id desc')->paginate(10);
return $this->fetch('/'.$this->web["template"].'/user/payrecord',[
"payrecord"=>$data,
"active"=>"payrecord",
]);
}


public function submitticket(){
	if($this->user['ban_time'] > time()){
		$array["code"]="-1";
		$array["msg"]="你的账号已被封禁至 ".date("Y-m-d H:i:s",$this->user['ban_time'])."，无法提交工单。原因：".($this->user['ban_reason'] ?: '无');
		return json($array);
	}
	$realnameCheck = check_realname_limit('ticket');
if(Request::instance()->isPost()) {
	if($realnameCheck['code'] != 1){
		$array["code"]=(string)$realnameCheck['code'];
		$array["msg"]=$realnameCheck['msg'];
		return json($array);
	}
$title=htmlspecialchars(trim(input("title")));
$content=htmlspecialchars(trim(input("content")));
if($title=="" || $content==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$array1=array(
array(
"personnel"=>"2",
"content"=>$content,
"time"=>time(),
),
);
	$data=Db::name('ticket')->insertGetId([
				"title"=>$title,
				"content"=>json_encode($array1),
				"userid"=>session("userid"),
				"time"=>time(),
				"state"=>"1",
				]);
if($data){
if($this->web["email"]=="1"){
if($this->user["mail"]){
$mailbox=$this->email($this->user["mail"],"提交工单通知","你账号:".$this->user["user"]."在时间:".date("Y-m-d H:i:s")."在本站提交工单成功!<br/>工单id:".$data."<br/>请耐心等待管理员回复!<br/><br/>");
}
$admin=Db::name('admin')->where("id","1")->find();
if($admin["mail"]){
$mailbox=$this->email($admin["mail"],"客户提交工单通知","客户账号:".$this->user["user"]."在时间:".date("Y-m-d H:i:s")."在本站提交工单<br/>工单id:".$data."<br/>请及时处理!<br/><br/>");
}
}
$array["code"]="1";
$array["msg"]="提交工单成功!";
$array["id"]=$data;
}else{
$array["code"]="-1";
$array["msg"]="提交工单失败!";
}
}
return json($array);
}
return $this->fetch('/'.$this->web["template"].'/user/submitticket',[
"realnameCheck"=>$realnameCheck,
]);
}

public function supportticket($id=null){
if($id){


$data=Db::name('ticket')->where([
"id"=>$id,
"userid"=>session("userid"),
])->find();
if($data){
$data["content"]=json_decode($data["content"],true);
$admin=Db::name('admin')->where("id","1")->find();
unset($admin["password"]);
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="reply"){
	if($this->user['ban_time'] > time()){
		$array["code"]="-1";
		$array["msg"]="你的账号已被封禁，无法回复工单。";
		return json($array);
	}
$content=htmlspecialchars(trim(input("content")));
if($content==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$array1=array(
array(
"personnel"=>"2",
"content"=>$content,
"time"=>time(),
),
);
$array2=array_merge($data["content"],$array1);
$data1=Db::name('ticket')->where([
"id"=>$id,
"userid"=>session("userid"),
])->update([
'content' =>json_encode($array2),
'state'=>'2',
]);
if($data1){
if($this->web["email"]=="1"){
if($admin["mail"]){
$mailbox=$this->email($admin["mail"],"客户回复工单通知","客户账号:".$this->user["user"]."在时间:".date("Y-m-d H:i:s")."已回复工单<br/>工单ID:".$id."<br/>标题:".$data["title"]."<br/>回复内容:<br/>".nl2br(htmlspecialchars($content))."<br/><br/>请登录后台查看完整对话:".request()->domain()."/admin/ticket/".$id);
}
}
$array["code"]="1";
$array["msg"]="回复工单成功!";
}else{
$array["code"]="-1";
$array["msg"]="回复工单失败!";
}
}
return json($array);
}

if($act=="end"){
$data1=Db::name('ticket')->where([
"id"=>$id,
"userid"=>session("userid"),
])->update([
'state'=>'4',
]);
if($data["state"]=="4"){
$array["code"]="-1";
$array["msg"]="工单已关闭!";
}else{
if($data1){
$array["code"]="1";
$array["msg"]="关闭工单成功!";
}else{
$array["code"]="-1";
$array["msg"]="关闭工单失败!";
}
}
return json($array);
}
}

return $this->fetch('/'.$this->web["template"].'/user/supportticket',[
"ticket"=>$data,
"admin"=>$admin,
]);


}else{
	$this->redirect('/user/supportticket');
}




}else{
$data=Db::name('ticket')->where("userid",session("userid"))->order('id desc')->paginate(10);
return $this->fetch('/'.$this->web["template"].'/user/supporttickets',[
"ticket"=>$data,
]);
}


}

public function transferrecord(){
$data=Db::name('transferrecord')->where("userid",session("userid"))->order('id desc')->paginate(10);
return $this->fetch('/'.$this->web["template"].'/user/transferrecord',[
"data"=>$data,
]);
}


public function transaction(){
$data=Db::name('transaction')->where("userid",session("userid"))->order('id desc')->paginate(10);
return $this->fetch('/'.$this->web["template"].'/user/transaction',[
"data"=>$data,
]);
}




// 卡密兑换
public function cdkey() {
	if(Request::instance()->isPost()) {
		$array = ['code' => '-1', 'msg' => ''];
		$cdkey = trim(input('cdkey', ''));
		if(empty($cdkey)) { $array['msg'] = '请输入卡密'; return json($array); }
		ensure_cdkey_table();
		ensure_cdkey_usage_log_table();
		$key = Db::name('cdkey')->where('cdkey', $cdkey)->find();
		if(!$key) { $array['msg'] = '卡密不存在'; return json($array); }
		if($key['status'] == 2) { $array['msg'] = '该卡密已被停用'; return json($array); }

		$userId = session('userid');
		$now = time();

		// 使用限制检查
		$restrict_type = isset($key['restrict_type']) ? $key['restrict_type'] : 'all';
		$restrict_users = isset($key['restrict_users']) ? trim($key['restrict_users']) : '';
		if($restrict_type == 'single'){
			if(trim($restrict_users) != strval($userId)){
				$array['msg'] = '此卡密不适用于您的账户'; return json($array);
			}
		}elseif($restrict_type == 'multi'){
			$allowedIds = array_map('trim', explode(',', $restrict_users));
			if(!in_array(strval($userId), $allowedIds)){
				$array['msg'] = '此卡密不适用于您的账户'; return json($array);
			}
		}elseif($restrict_type == 'once_per_user'){
			$alreadyUsed = Db::name('cdkey_usage_log')->where('cdkey', $cdkey)->where('userid', $userId)->find();
			if($alreadyUsed){
				$array['msg'] = '您已经兑换过此卡密，每人限兑换一次'; return json($array);
			}
		}

		// 可重复使用检查
		$repeatable = isset($key['repeatable']) ? intval($key['repeatable']) : 0;
		if(!$repeatable){
			if($key['status'] == 1) { $array['msg'] = '该卡密已被使用'; return json($array); }
		}

		if($key['type'] == 'balance') {
			// 余额充值
			$money = floatval($key['money']);
			$userInfo = Db::name('user')->where('id', $userId)->find();
			Db::name('user')->where('id', $userId)->update([
				'money' => round(floatval($userInfo['money'] ?? 0) + $money, 2),
				'total_recharge' => round(floatval($userInfo['total_recharge'] ?? 0) + $money, 2)
			]);
			if (function_exists('update_user_membership')) {
				update_user_membership($userId);
			}
			if(!$repeatable && $restrict_type != 'once_per_user'){
				Db::name('cdkey')->where('id', $key['id'])->update([
					'status' => 1, 'used_at' => $now, 'used_userid' => $userId
				]);
			}
			if($restrict_type == 'once_per_user'){
				Db::name('cdkey_usage_log')->insert([
					'cdkey' => $cdkey,
					'userid' => $userId,
					'used_at' => $now
				]);
			}
			Db::name('transaction')->insert([
				'userid' => $userId,
				'content' => '卡密充值余额，卡密ID:' . $key['id'] . '，金额:' . $money . '元',
				'time' => $now,
			]);
			$array['code'] = '1';
			$array['msg'] = '兑换成功，余额增加' . $money . '元';
			return json($array);
		}

		if($key['type'] == 'host') {
			// 卡密购买主机
			$cart = Db::name('cart')->where('id', $key['cartid'])->find();
			if(!$cart) { $array['msg'] = '关联产品不存在'; return json($array); }
			if($cart['buy'] == '1') { $array['msg'] = '该产品已设置禁止购买'; return json($array); }
			if($cart['inventory'] < 1) { $array['msg'] = '该产品已售完'; return json($array); }

			$server = Db::name('server')->where('id', $cart['serverid'])->find();
			if(!$server || empty($server['serverplugins'])) { $array['msg'] = '产品未配置服务器'; return json($array); }

			// 生成主机账号密码
			$hostUser = 'u' . date('ymd') . random(4);
			$hostPass = random(10);
			$times = 2592000; // 默认1个月
			if($cart['cycle'] == 'season') $times = 7879680;
			if($cart['cycle'] == 'year') $times = 31536000;
			if($cart['cycle'] == 'day') $times = 86400;
			if($cart['cycle'] == 'unrestricted') $times = 3153600000;

			// 调用开通
			$pluginFile = PATH . 'plugins/host/' . $server['serverplugins'] . '/' . $server['serverplugins'] . '.php';
			if(!file_exists($pluginFile)) { $array['msg'] = '插件文件不存在'; return json($array); }
			include_once $pluginFile;
			$function = $server['serverplugins'] . '_CreateAccount';
			if(!function_exists($function)) { $array['msg'] = '开通接口未实现'; return json($array); }
			$result = @$function($server, ['user' => $hostUser, 'password' => $hostPass, 'time' => 1], $cart, $times);

			if(is_array($result) && isset($result['code']) && $result['code'] == '1') {
				if(!$repeatable && $restrict_type != 'once_per_user'){
					Db::name('cdkey')->where('id', $key['id'])->update([
						'status' => 1, 'used_at' => $now, 'used_userid' => $userId
					]);
				}
				if($restrict_type == 'once_per_user'){
					Db::name('cdkey_usage_log')->insert([
						'cdkey' => $cdkey,
						'userid' => $userId,
						'used_at' => $now
					]);
				}
				Db::name('cart')->where('id', $cart['id'])->update(['inventory' => max(0, intval($cart['inventory']) - 1)]);
				$orderId = isset($result['id']) ? $result['id'] : 0;
				Db::name('transaction')->insert([
					'userid' => $userId,
					'content' => '卡密开通产品，卡密ID:' . $key['id'] . '，产品:' . $cart['name'] . '，订单ID:' . $orderId,
					'time' => $now,
				]);
				$array['code'] = '1';
				$array['msg'] = '产品开通成功！账号：' . $hostUser;
				return json($array);
			} else {
				// 开通失败回滚 - 卡密不退
				if(!$repeatable && $restrict_type != 'once_per_user'){
					Db::name('cdkey')->where('id', $key['id'])->update([
						'status' => 1, 'used_at' => $now, 'used_userid' => $userId
					]);
				}
				$err = is_array($result) && isset($result['msg']) ? $result['msg'] : '开通失败';
				Db::name('transaction')->insert([
					'userid' => $userId,
					'content' => '卡密开通失败，卡密ID:' . $key['id'] . '，错误:' . $err . '，请联系管理员',
					'time' => $now,
				]);
				$array['msg'] = '开通失败：' . $err . '，请联系管理员处理';
				return json($array);
			}
		}

		$array['msg'] = '未知卡密类型';
		return json($array);
	}
	return $this->fetch('/'.$this->web["template"].'/user/cdkey', [
		'active' => 'cdkey',
		'web' => $this->web,
		'user' => $this->user,
	]);
}

// 积分签到
public function checkin() {
	$array = ['code' => '-1', 'msg' => ''];
	if (!Request::instance()->isPost()) {
		$array['msg'] = '非法请求';
		return json($array);
	}
	$todayStart = strtotime(date('Y-m-d'));
	$lastCheckin = intval($this->user['last_checkin_time'] ?? 0);
	if ($lastCheckin >= $todayStart) {
		$array['msg'] = '今日已签到，请明天再来';
		return json($array);
	}
	// 随机1~20积分
	$points = rand(1, 20);
	try {
		\think\Db::name('user')->where('id', session('userid'))->update([
			'points' => intval($this->user['points'] ?? 0) + $points,
			'last_checkin_time' => time(),
		]);
		\think\Db::name('points_log')->insert([
			'userid' => session('userid'),
			'type' => 'checkin',
			'points' => $points,
			'content' => '每日签到获得' . $points . '积分',
			'created_at' => time(),
		]);
		$array['code'] = '1';
		$array['msg'] = '签到成功！获得' . $points . '积分';
		$array['points'] = $points;
		$array['total'] = intval($this->user['points'] ?? 0) + $points;
	} catch (\Exception $e) {
		$array['msg'] = '签到失败：' . $e->getMessage();
	}
	return json($array);
}

// 积分商城首页
public function pointsShop() {
	ensure_points_products_table();
	ensure_points_log_table();
	$this->ensureUserColumns();
	$products = \think\Db::name('points_products')
		->where('status', 1)
		->order('sort asc, id asc')
		->select();
	$userPoints = intval($this->user['points'] ?? 0);
	// 兑换记录
	$exchangeLogs = [];
	try {
		$exchangeLogs = \think\Db::name('points_log')
			->where('userid', session('userid'))
			->where('type', 'exchange')
			->order('id desc')
			->limit(10)
			->select();
	} catch (\Exception $e) {}
	return $this->fetch('/'.$this->web["template"].'/user/points_shop', [
		'products' => $products,
		'userPoints' => $userPoints,
		'exchangeLogs' => $exchangeLogs,
		'active' => 'points_shop',
		'web' => $this->web,
		'user' => $this->user,
	]);
}

// 积分兑换
public function pointsExchange() {
	$array = ['code' => '-1', 'msg' => ''];
	if (!Request::instance()->isPost()) {
		$array['msg'] = '非法请求';
		return json($array);
	}
	ensure_points_products_table();
	ensure_points_log_table();
	$productId = intval(input('product_id'));
	$product = \think\Db::name('points_products')->where('id', $productId)->where('status', 1)->find();
	if (!$product) {
		$array['msg'] = '产品不存在或已下架';
		return json($array);
	}
	$userPoints = intval($this->user['points'] ?? 0);
	if ($userPoints < $product['points']) {
		$array['msg'] = '积分不足，需要' . $product['points'] . '积分，当前' . $userPoints . '积分';
		return json($array);
	}
	// 检查库存
	if ($product['stock'] == 0) {
		$array['msg'] = '该产品已兑完';
		return json($array);
	}
	try {
		$userId = session('userid');
		$now = time();
		if ($product['type'] == 'balance') {
			// 积分兑换余额
			$value = floatval($product['value']);
			\think\Db::name('user')->where('id', $userId)->update([
				'points' => $userPoints - $product['points'],
				'money' => round(floatval($this->user['money']) + $value, 2),
			]);
			\think\Db::name('transaction')->insert([
				'userid' => $userId,
				'content' => '积分兑换余额：' . $product['name'] . '，消耗' . $product['points'] . '积分，获得' . $value . '元',
				'time' => $now,
			]);
		} elseif ($product['type'] == 'host') {
			// 积分兑换主机
			$cartid = intval($product['value']);
			$cart = \think\Db::name('cart')->where('id', $cartid)->find();
			if (!$cart) {
				$array['msg'] = '关联产品不存在';
				return json($array);
			}
			if ($cart['inventory'] < 1) {
				$array['msg'] = '该产品已售完';
				return json($array);
			}
			$server = \think\Db::name('server')->where('id', $cart['serverid'])->find();
			if (!$server || empty($server['serverplugins'])) {
				$array['msg'] = '产品未配置服务器';
				return json($array);
			}
			$hostUser = 'u' . date('ymd') . random(4);
			$hostPass = random(10);
			$times = 2592000;
			if ($cart['cycle'] == 'season') $times = 7879680;
			elseif ($cart['cycle'] == 'year') $times = 31536000;
			elseif ($cart['cycle'] == 'day') $times = 86400;
			elseif ($cart['cycle'] == 'unrestricted') $times = 3153600000;
			$pluginFile = PATH . 'plugins/host/' . $server['serverplugins'] . '/' . $server['serverplugins'] . '.php';
			if (!file_exists($pluginFile)) {
				$array['msg'] = '插件文件不存在';
				return json($array);
			}
			include_once $pluginFile;
			$function = $server['serverplugins'] . '_CreateAccount';
			if (!function_exists($function)) {
				$array['msg'] = '开通接口未实现';
				return json($array);
			}
			$result = @$function($server, ['user' => $hostUser, 'password' => $hostPass, 'time' => 1], $cart, $times);
			if (is_array($result) && isset($result['code']) && $result['code'] == '1') {
				\think\Db::name('cart')->where('id', $cart['id'])->update(['inventory' => max(0, intval($cart['inventory']) - 1)]);
				$orderId = isset($result['id']) ? $result['id'] : 0;
				\think\Db::name('transaction')->insert([
					'userid' => $userId,
					'content' => '积分兑换主机：' . $product['name'] . '，消耗' . $product['points'] . '积分，订单ID:' . $orderId,
					'time' => $now,
				]);
				$array['code'] = '1';
				$array['msg'] = '兑换成功！主机已开通，账号：' . $hostUser;
			} else {
				$err = is_array($result) && isset($result['msg']) ? $result['msg'] : '开通失败';
				$array['msg'] = '开通失败：' . $err . '，积分已退还';
				return json($array);
			}
		} elseif ($product['type'] == 'renew') {
			// 积分兑换续费天数
			$renewDays = intval($product['value']);
			$array['msg'] = '请在主机管理页面选择要续费的主机，使用积分续费功能';
			return json($array);
		} elseif ($product['type'] == 'unban') {
			// 积分兑换免除法卡（解除封禁）
			if ($this->user['ban_time'] <= time()) {
				$array['msg'] = '你的账号当前未被封禁，无需使用免除法卡';
				return json($array);
			}
			\think\Db::name('user')->where('id', $userId)->update([
				'ban_time' => 0,
				'ban_reason' => '',
			]);
			\think\Db::name('transaction')->insert([
				'userid' => $userId,
				'content' => '积分兑换免除法卡：' . $product['name'] . '，消耗' . $product['points'] . '积分，账号已解除封禁',
				'time' => $now,
			]);
			$array['code'] = '1';
			$array['msg'] = '兑换成功！你的账号已解除封禁，恢复正常使用';
		}
		// 扣积分和记录
		\think\Db::name('user')->where('id', $userId)->update([
			'points' => $userPoints - $product['points'],
		]);
		\think\Db::name('points_log')->insert([
			'userid' => $userId,
			'type' => 'exchange',
			'points' => -$product['points'],
			'content' => '兑换：' . $product['name'] . '，消耗' . $product['points'] . '积分',
			'created_at' => $now,
		]);
		// 扣库存
		if ($product['stock'] > 0) {
			\think\Db::name('points_products')->where('id', $product['id'])->setDec('stock');
		}
		$this->user = \think\Db::name('user')->where('id', $userId)->find();
		$array['total'] = intval($this->user['points'] ?? 0);
		if ($array['code'] != '1') $array['code'] = '1';
		if (empty($array['msg'])) $array['msg'] = '兑换成功';
	} catch (\Exception $e) {
		$array['msg'] = '兑换失败：' . $e->getMessage();
	}
	return json($array);
}

// 积分续费主机
public function pointsRenew() {
	$array = ['code' => '-1', 'msg' => ''];
	if (!Request::instance()->isPost()) {
		$array['msg'] = '非法请求';
		return json($array);
	}
	ensure_points_products_table();
	$orderId = intval(input('order_id'));
	$productId = intval(input('product_id'));
	$order = \think\Db::name('order')->where('id', $orderId)->where('userid', session('userid'))->find();
	if (!$order) {
		$array['msg'] = '主机不存在';
		return json($array);
	}
	$renewProduct = \think\Db::name('points_products')->where('id', $productId)->where('status', 1)->where('type', 'renew')->find();
	if (!$renewProduct) {
		$array['msg'] = '续费产品不存在';
		return json($array);
	}
	$userPoints = intval($this->user['points'] ?? 0);
	if ($userPoints < $renewProduct['points']) {
		$array['msg'] = '积分不足';
		return json($array);
	}
	$renewDays = intval($renewProduct['value']);
	$cart = \think\Db::name('cart')->where('id', $order['cartid'])->find();
	$server = \think\Db::name('server')->where('id', $cart['serverid'] ?? 0)->find();
	$times = $renewDays * 86400;
	$hasPlugin = ($server && !empty($server['serverplugins']));
	try {
		if ($hasPlugin) {
			include_once PATH . "plugins/host/" . $server["serverplugins"] . "/" . $server["serverplugins"] . ".php";
			$function = $server["serverplugins"] . "_renew";
			if (function_exists($function)) {
				$result = $function($server, $order, $cart, $times, $renewDays);
				if (!is_array($result) || $result['code'] != '1') {
					$array['msg'] = '续费失败：' . ($result['msg'] ?? '未知错误');
					return json($array);
				}
			}
		}
		\think\Db::name('order')->where('id', $orderId)->update([
			'ztime' => $order['ztime'] + $times,
		]);
		\think\Db::name('user')->where('id', session('userid'))->update([
			'points' => $userPoints - $renewProduct['points'],
		]);
		\think\Db::name('points_log')->insert([
			'userid' => session('userid'),
			'type' => 'exchange',
			'points' => -$renewProduct['points'],
			'content' => '积分续费主机 #' . $orderId . '，续费' . $renewDays . '天，消耗' . $renewProduct['points'] . '积分',
			'created_at' => time(),
		]);
		$this->user = \think\Db::name('user')->where('id', session('userid'))->find();
		$array['code'] = '1';
		$array['msg'] = '续费成功！主机已延长' . $renewDays . '天';
		$array['total'] = intval($this->user['points'] ?? 0);
	} catch (\Exception $e) {
		$array['msg'] = '续费失败：' . $e->getMessage();
	}
	return json($array);
}

// ========== 公告通知 ==========
public function announcements() {
	ensure_announcements_table();
	$userId = session('userid');
	$list = Db::name('announcements')
		->where('status', 1)
		->order('created_at desc')
		->select();
	$annIds = array_column($list, 'id');
	$readMap = [];
	if(!empty($annIds)){
		$reads = Db::name('announcement_reads')
			->where('user_id', $userId)
			->where('announcement_id', 'in', $annIds)
			->column('read_at', 'announcement_id');
		if($reads){
			foreach($reads as $aid => $rat){
				$readMap[$aid] = true;
			}
		}
	}
	$unreadCount = 0;
	$forcePopup = null;
	$result = [];
	foreach($list as $item){
		$isRead = isset($readMap[$item['id']]);
		if(!$isRead) $unreadCount++;
		$result[] = [
			'id' => $item['id'],
			'title' => $item['title'],
			'content' => $item['content'],
			'notice_type' => $item['notice_type'],
			'created_at' => $item['created_at'],
			'is_read' => $isRead,
		];
		if(!$forcePopup && $item['notice_type'] == 'force' && !$isRead){
			$forcePopup = [
				'id' => $item['id'],
				'title' => $item['title'],
				'content' => $item['content'],
			];
		}
	}
	return json([
		'code' => 1,
		'data' => [
			'list' => $result,
			'unread_count' => $unreadCount,
			'force_popup' => $forcePopup,
		]
	]);
}

public function announcementRead() {
	$array = ['code' => '-1', 'msg' => ''];
	if(!Request::instance()->isPost()){
		$array['msg'] = '非法请求';
		return json($array);
	}
	$annId = intval(input('announcement_id', 0));
	if(!$annId){
		$array['msg'] = '参数错误';
		return json($array);
	}
	$userId = session('userid');
	ensure_announcements_table();
	try {
		Db::name('announcement_reads')->insert([
			'announcement_id' => $annId,
			'user_id' => $userId,
			'read_at' => time(),
		]);
	} catch (\Exception $e) {
		// 重复忽略
	}
	$array['code'] = '1';
	return json($array);
}

// 发送邮箱（异步版本 - 先返回响应再发送邮件）
public static function email($email,$name,$body)
{
if (!rate_limit('email_send_' . $email, 3, 60)) { return ['code'=>'-1','msg'=>'发送频率过快，请稍后再试']; }
$body = sanitize_email_body($body);
$web=web_config();
// 使用register_shutdown_function延迟发送，避免阻塞响应
$webData = [
	'emailchar' => $web['emailchar'] ?? 'UTF-8',
	'emailauth' => $web['emailauth'] ?? true,
	'emailsecure' => $web['emailsecure'] ?? '',
	'emailport' => $web['emailport'] ?? 25,
	'emailhost' => $web['emailhost'] ?? '',
	'emailname' => $web['emailname'] ?? '',
	'emailpass' => $web['emailpass'] ?? '',
	'webname' => $web['name'] ?? '',
];
register_shutdown_function(function() use ($email, $name, $body, $webData) {
	try {
		$mail = new PHPMailer();
		$mail->IsSMTP();
		$mail->CharSet = $webData['emailchar'];
		$mail->SMTPAuth = $webData['emailauth'];
		$mail->Timeout = 10;
		if($webData['emailsecure']){
			$mail->SMTPSecure = $webData['emailsecure'];
		}
		$mail->Port = $webData['emailport'];
		$mail->Host = $webData['emailhost'];
		$mail->Username = $webData['emailname'];
		$mail->Password = $webData['emailpass'];
		$mail->From = $webData['emailname'];
		$mail->FromName = $webData['webname'];
		$mail->AddAddress($email);
		$mail->Subject = $name;
		$mail->Body = build_email_html($name, $body);
		$mail->WordWrap = 80;
		$mail->isHTML(true);
		$mail->Send();
	} catch (\Exception $e) {
		$logDir = defined('LOG_PATH') ? LOG_PATH : (PATH . '/runtime/log/');
		if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
		@file_put_contents($logDir . 'email_error.log', date('Y-m-d H:i:s') . " To:{$email} Subject:{$name} Error:" . $e->getMessage() . "\n", FILE_APPEND);
	}
});
$array["code"]="1";
$array["msg"]="邮箱发送成功";
return json($array);
}

// ========== 主机转让功能 ==========

// 转让市场
public function transferMarket() {
	ensure_host_transfer_table();
	ensure_host_transfer_message_table();
	$myUserId = intval(session('userid'));
	$data = Db::name('host_transfer')
		->alias('t')
		->join('order o', 't.order_id = o.id')
		->join('user u', 't.userid = u.id')
		->join('cart c', 'o.cartid = c.id')
		->where('t.status', '0')
		->where(function($query) use ($myUserId) {
			$query->where('t.userid', $myUserId)
			      ->whereOr('t.target_userid', 0)
			      ->whereOr('t.target_userid', $myUserId);
		})
		->field('t.*, o.user as host_user, o.atime, o.ztime, c.name as product_name, c.money as product_money, c.serverid, c.id as cart_id, u.user as seller_name, u.qq as seller_qq, u.id as seller_id')
		->order('t.id desc')
		->paginate(10);
	return $this->fetch('/'.$this->web["template"].'/user/transfer_market', [
		"transfers" => $data,
		"myUserId" => $myUserId,
	]);
}

// 发起转让（获取自己的主机列表）
public function transferHost() {
	ensure_host_transfer_table();
	if (Request::instance()->isPost()) {
		$act = input("act");
		
		// 发送邮箱验证码
		if ($act == "sendcode") {
			$userInfo = Db::name('user')->where('id', session('userid'))->find();
			if (empty($userInfo['mail'])) {
				return json(['code' => '-1', 'msg' => '请先绑定邮箱后再操作']);
			}
			$code = random(6, '0123456789');
			session('transfer_email_code', $code);
			session('transfer_email_time', time());
			$codeBody = "<p>您好，</p><p>您正在 {$this->web['name']} 发起主机转让，本次验证码为：</p><p style='text-align:center;margin:28px 0;'><span style='display:inline-block;background:#eff6ff;color:#2563eb;font-size:28px;font-weight:700;padding:14px 32px;border-radius:10px;letter-spacing:4px;border:1px solid #bfdbfe;'>{$code}</span></p><p style='color:#64748b;font-size:13px;'>验证码 10 分钟内有效。如非本人操作，请忽略此邮件。</p>";
			if ($this->web["email"] == "1") {
				$this->email($userInfo['mail'], "主机转让验证码", $codeBody);
			}
			return json(['code' => '1', 'msg' => '验证码已发送至邮箱']);
		}
		
		// 提交转让
		if ($act == "submit") {
			$orderId = intval(input("order_id"));
			$price = floatval(input("price"));
			$targetUserid = intval(input("target_userid"));
			$contactInfo = trim(input("contact_info"));
			$emailCode = input("email_code");
			
			// 验证邮箱验证码
			$savedCode = session('transfer_email_code');
			$savedTime = session('transfer_email_time');
			if (empty($savedCode) || empty($emailCode) || $savedCode != $emailCode) {
				return json(['code' => '-1', 'msg' => '邮箱验证码错误']);
			}
			if (time() - $savedTime > 600) {
				return json(['code' => '-1', 'msg' => '验证码已过期，请重新获取']);
			}
			
			if ($orderId <= 0) {
				return json(['code' => '-1', 'msg' => '请选择要转让的主机']);
			}
			if ($price < 0) {
				return json(['code' => '-1', 'msg' => '转让价格不能为负数']);
			}
			
			// 验证订单归属
			$order = Db::name('order')->where([
				'id' => $orderId,
				'userid' => session('userid'),
			])->find();
			if (!$order) {
				return json(['code' => '-1', 'msg' => '订单不存在或不属于您']);
			}
			if ($order['state'] != '1') {
				return json(['code' => '-1', 'msg' => '只有运行中的主机才能转让']);
			}
			
			// 获取原购买价格
			$cart = Db::name('cart')->where('id', $order['cartid'])->find();
			$originalPrice = $cart ? floatval($cart['money']) : 0;
			
			// 转让价格不能超过原购买价格
			if ($originalPrice > 0 && $price > $originalPrice) {
				return json(['code' => '-1', 'msg' => '转让价格不能超过原购买价格（¥' . $originalPrice . '）']);
			}
			if ($originalPrice == 0 && $price > 0) {
				return json(['code' => '-1', 'msg' => '免费主机转让价格必须为0']);
			}
			
			// 指定目标用户时验证
			if ($targetUserid > 0) {
				$targetUser = Db::name('user')->where('id', $targetUserid)->find();
				if (!$targetUser) {
					return json(['code' => '-1', 'msg' => '指定的目标用户不存在']);
				}
				if ($targetUserid == session('userid')) {
					return json(['code' => '-1', 'msg' => '不能转让给自己']);
				}
			}
			
			$now = time();
			Db::name('host_transfer')->insert([
				'order_id' => $orderId,
				'userid' => session('userid'),
				'target_userid' => $targetUserid,
				'price' => $price,
				'original_price' => $originalPrice,
				'status' => 0,
				'email_verified' => 1,
				'contact_info' => $contactInfo,
				'created_at' => $now,
				'updated_at' => $now,
			]);
			
			// 清除验证码
			session('transfer_email_code', null);
			session('transfer_email_time', null);
			
			return json(['code' => '1', 'msg' => '主机已成功发布到转让市场']);
		}
	}
	
	// GET: 获取可转让的主机列表
	$orders = Db::name('order')
		->alias('o')
		->join('cart c', 'o.cartid = c.id')
		->where('o.userid', session('userid'))
		->where('o.state', '1')
		->field('o.id, o.user, o.atime, o.ztime, c.name as product_name, c.money')
		->select();
	
	return $this->fetch('/'.$this->web["template"].'/user/transfer_host', [
		"orders" => $orders,
	]);
}

// 购买转让主机
public function transferBuy() {
	ensure_host_transfer_table();
	if (!Request::instance()->isPost()) {
		return json(['code' => '-1', 'msg' => '非法请求']);
	}
	
	$transferId = intval(input('transfer_id'));
	$transfer = Db::name('host_transfer')->where('id', $transferId)->where('status', '0')->find();
	if (!$transfer) {
		return json(['code' => '-1', 'msg' => '该转让已失效或不存在']);
	}
	if ($transfer['userid'] == session('userid')) {
		return json(['code' => '-1', 'msg' => '不能购买自己的转让主机']);
	}
	if ($transfer['target_userid'] > 0 && $transfer['target_userid'] != session('userid')) {
		return json(['code' => '-1', 'msg' => '该主机仅限指定用户购买']);
	}
	
	$order = Db::name('order')->where('id', $transfer['order_id'])->find();
	if (!$order || $order['state'] != '1') {
		return json(['code' => '-1', 'msg' => '原主机已不可用']);
	}
	
	$buyer = Db::name('user')->where('id', session('userid'))->find();
	$price = floatval($transfer['price']);
	
	if ($price > 0 && $buyer['money'] < $price) {
		return json(['code' => '-1', 'msg' => '账户余额不足，需要 ¥' . $price . '，当前余额 ¥' . $buyer['money']]);
	}
	
	$now = time();
	
	// 扣款并转账给卖家
	if ($price > 0) {
		Db::name('user')->where('id', session('userid'))->setDec('money', $price);
		Db::name('user')->where('id', $transfer['userid'])->setInc('money', $price);
		
		// 买家交易记录
		Db::name('transaction')->insert([
			'userid' => session('userid'),
			'content' => '购买转让主机 #' . $order['id'] . '，支付 ¥' . $price,
			'time' => $now,
		]);
		// 卖家交易记录
		Db::name('transaction')->insert([
			'userid' => $transfer['userid'],
			'content' => '转让主机 #' . $order['id'] . ' 售出，收入 ¥' . $price,
			'time' => $now,
		]);
	}
	
	// 更新订单归属
	Db::name('order')->where('id', $transfer['order_id'])->update([
		'userid' => session('userid'),
		'user' => $buyer['user'],
	]);
	
	// 更新转让状态
	Db::name('host_transfer')->where('id', $transferId)->update([
		'status' => 1,
		'buyer_userid' => session('userid'),
		'updated_at' => $now,
	]);
	
	return json(['code' => '1', 'msg' => '主机转让成功！']);
}

// 联系卖家
public function transferContact() {
	ensure_host_transfer_table();
	$transferId = intval(input('transfer_id'));
	$transfer = Db::name('host_transfer')
		->alias('t')
		->join('user u', 't.userid = u.id')
		->where('t.id', $transferId)
		->field('t.*, u.user as seller_name, u.qq as seller_qq, u.mail as seller_mail')
		->find();
	if (!$transfer) {
		return json(['code' => '-1', 'msg' => '转让信息不存在']);
	}
	return json([
		'code' => '1',
		'msg' => 'ok',
		'seller_name' => $transfer['seller_name'],
		'seller_qq' => $transfer['seller_qq'] ?: '',
		'contact_info' => $transfer['contact_info'] ?: '卖家未填写联系方式',
	]);
}

// 获取转让主机配置详情
public function transferDetail() {
	ensure_host_transfer_table();
	$transferId = intval(input('transfer_id'));
	if ($transferId <= 0) {
		return json(['code' => '-1', 'msg' => '参数错误']);
	}
	try {
		$transfer = Db::name('host_transfer')
			->alias('t')
			->join('order o', 't.order_id = o.id')
			->join('cart c', 'o.cartid = c.id')
			->join('user u', 't.userid = u.id')
			->join('server s', 'c.serverid = s.id', 'LEFT')
			->where('t.id', $transferId)
			->where('t.status', '0')
			->field('t.*, o.user as host_user, o.atime, o.ztime, o.state, c.name as product_name, c.money as product_money, c.serverid, c.content as product_desc, u.user as seller_name, u.qq as seller_qq, s.name as server_name')
			->find();
		if (!$transfer) {
			return json(['code' => '-1', 'msg' => '转让信息不存在或已失效']);
		}
		return json([
			'code' => '1',
			'msg' => 'ok',
			'data' => $transfer,
		]);
	} catch (\Exception $e) {
		return json(['code' => '-1', 'msg' => '查询失败：' . $e->getMessage()]);
	}
}

// 取消转让
public function transferCancel() {
	ensure_host_transfer_table();
	if (!Request::instance()->isPost()) {
		return json(['code' => '-1', 'msg' => '非法请求']);
	}
	$transferId = intval(input('transfer_id'));
	$transfer = Db::name('host_transfer')->where([
		'id' => $transferId,
		'userid' => session('userid'),
		'status' => '0',
	])->find();
	if (!$transfer) {
		return json(['code' => '-1', 'msg' => '转让记录不存在或已处理']);
	}
	Db::name('host_transfer')->where('id', $transferId)->update([
		'status' => 3,
		'updated_at' => time(),
	]);
	return json(['code' => '1', 'msg' => '已取消转让']);
}

// 发送转让邮箱验证码（独立接口）
public function transferSendCode() {
	$userInfo = Db::name('user')->where('id', session('userid'))->find();
	if (empty($userInfo['mail'])) {
		return json(['code' => '-1', 'msg' => '请先绑定邮箱后再操作']);
	}
	// 60秒发送频率限制
	$lastSend = session('transfer_email_time');
	if ($lastSend && time() - $lastSend < 60) {
		return json(['code' => '-1', 'msg' => '发送频率过快，请' . (60 - (time() - $lastSend)) . '秒后再试']);
	}
	$code = random(6, '0123456789');
	session('transfer_email_code', $code);
	session('transfer_email_time', time());
	$codeBody = "<p>您好，</p><p>您正在 {$this->web['name']} 发起主机转让，本次验证码为：</p><p style='text-align:center;margin:28px 0;'><span style='display:inline-block;background:#eff6ff;color:#2563eb;font-size:28px;font-weight:700;padding:14px 32px;border-radius:10px;letter-spacing:4px;border:1px solid #bfdbfe;'>{$code}</span></p><p style='color:#64748b;font-size:13px;'>验证码 10 分钟内有效。如非本人操作，请忽略此邮件。</p>";
	if ($this->web["email"] == "1") {
		$this->email($userInfo['mail'], "主机转让验证码", $codeBody);
	}
	return json(['code' => '1', 'msg' => '验证码已发送至邮箱']);
}

// 发送对话消息
public function transferSendMsg() {
	ensure_host_transfer_message_table();
	ensure_host_transfer_table();
	if (!Request::instance()->isPost()) {
		return json(['code' => '-1', 'msg' => '非法请求']);
	}
	$transferId = intval(input('transfer_id'));
	$content = trim(input('content'));
	if ($transferId <= 0 || empty($content)) {
		return json(['code' => '-1', 'msg' => '参数错误']);
	}
	if (mb_strlen($content) > 500) {
		return json(['code' => '-1', 'msg' => '消息内容不能超过500字']);
	}
	
	$transfer = Db::name('host_transfer')->where('id', $transferId)->where('status', '0')->find();
	if (!$transfer) {
		return json(['code' => '-1', 'msg' => '该转让已失效']);
	}
	
	$myUserId = intval(session('userid'));
	$sellerId = intval($transfer['userid']);
	
	// 只有买卖双方可以发消息
	if ($myUserId != $sellerId) {
		// 买家：必须对卖家发
		$receiverId = $sellerId;
	} else {
		// 卖家：发给最后一条消息的发送方（买家）；如果还没人问过，不能主动发
		$lastMsg = Db::name('host_transfer_message')
			->where('transfer_id', $transferId)
			->where('sender_id', '<>', $sellerId)
			->order('id desc')
			->find();
		if (!$lastMsg) {
			return json(['code' => '-1', 'msg' => '暂无买家咨询，无法主动发送']);
		}
		$receiverId = intval($lastMsg['sender_id']);
	}
	
	if ($receiverId == $myUserId) {
		return json(['code' => '-1', 'msg' => '不能给自己发消息']);
	}
	
	$now = time();
	Db::name('host_transfer_message')->insert([
		'transfer_id' => $transferId,
		'sender_id' => $myUserId,
		'receiver_id' => $receiverId,
		'content' => $content,
		'is_read' => 0,
		'created_at' => $now,
	]);
	
	return json([
		'code' => '1',
		'msg' => '发送成功',
		'data' => [
			'id' => Db::name('host_transfer_message')->getLastInsID(),
			'content' => $content,
			'sender_id' => $senderId,
			'created_at' => $now,
		]
	]);
}

// 获取对话消息列表
public function transferGetMsgs() {
	ensure_host_transfer_message_table();
	$transferId = intval(input('transfer_id'));
	$lastId = intval(input('last_id'));
	if ($transferId <= 0) {
		return json(['code' => '-1', 'msg' => '参数错误']);
	}
	
	$myUserId = intval(session('userid'));
	
	$query = Db::name('host_transfer_message')
		->alias('m')
		->join('user u', 'm.sender_id = u.id')
		->where('m.transfer_id', $transferId)
		->where(function($q) use ($myUserId) {
			$q->where('m.sender_id', $myUserId)
			  ->whereOr('m.receiver_id', $myUserId);
		});
	
	if ($lastId > 0) {
		$query->where('m.id', '>', $lastId);
	}
	
	$msgs = $query->field('m.*, u.user as sender_name, u.qq as sender_qq')
		->order('m.id asc')
		->limit(50)
		->select();
	
	// 标记对方发来的消息为已读
	$unreadIds = [];
	foreach ($msgs as $msg) {
		if ($msg['receiver_id'] == $myUserId && $msg['is_read'] == 0) {
			$unreadIds[] = $msg['id'];
		}
	}
	if (!empty($unreadIds)) {
		Db::name('host_transfer_message')->where('id', 'in', $unreadIds)->update(['is_read' => 1]);
	}
	
	foreach ($msgs as &$msg) {
		$msg['is_mine'] = ($msg['sender_id'] == $myUserId);
		$msg['time_str'] = date('H:i', $msg['created_at']);
	}
	unset($msg);
	
	return json(['code' => '1', 'data' => $msgs]);
}

// 获取未读消息数量
public function transferUnreadCount() {
	ensure_host_transfer_message_table();
	$myUserId = intval(session('userid'));
	$count = Db::name('host_transfer_message')
		->where('receiver_id', $myUserId)
		->where('is_read', 0)
		->count();
	return json(['code' => '1', 'count' => $count]);
}

// 强制QQ群卡密验证
public function qqGroupVerify() {
	$array = ['code' => '-1', 'msg' => ''];
	if (!Request::instance()->isPost()) {
		$array['msg'] = '非法请求';
		return json($array);
	}
	$key = trim(input('key', ''));
	if (empty($key)) {
		$array['msg'] = '请输入卡密';
		return json($array);
	}
	$configKey = isset($this->web['force_qq_group_key']) ? trim($this->web['force_qq_group_key']) : '';
	if (empty($configKey)) {
		$array['msg'] = '系统未配置卡密，请联系管理员';
		return json($array);
	}
	if ($key === $configKey) {
		Db::name('user')->where('id', session('userid'))->update(['force_qq_group_verified' => 1]);
		$array['code'] = '1';
		$array['msg'] = '验证成功，永久有效';
	} else {
		$array['msg'] = '卡密错误，请检查后重试';
	}
	return json($array);
}

}