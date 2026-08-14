<?php
namespace app\admin\controller;
use think\Controller;
use think\Db;
use think\Request;
use PHPMailer\PHPMailer\PHPMailer;

class Index extends Controller
{
    protected $hasFullAccess = false;

public function _initialize() {
		if(!session("adminid")) {
			$this->redirect(url('admin/login/index'));
		}
		$this->user=Db::name('admin')->where('id',session("adminid"))->find();
		$this->web=web_config();
		// 如果数据库中仍配置为旧版 layui 后台主题，强制使用已重构的 default 主题
		if($this->web["admintemplate"]=="layui"){
			$this->web["admintemplate"]="default";
		}
$file=file_exists(PATH."/app/index/view/".$this->web["template"]."/set.php");
if($file){
$templateset="1";
}else{
$templateset="0";
}
		// 检测 admin_role 表是否存在，不存在则自动创建
		$adminRoleTableExists = false;
		try {
			Db::name('admin_role')->count();
			$adminRoleTableExists = true;
		} catch (\Exception $e) {
			$adminRoleTableExists = false;
		}

		// 如果 admin_role 表不存在，自动创建并初始化三个默认角色
		if (!$adminRoleTableExists) {
			try {
				$tableName = Db::name('admin_role')->getTable();
				Db::execute("CREATE TABLE IF NOT EXISTS `{$tableName}` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`name` varchar(50) NOT NULL COMMENT '角色名称',
					`permissions` text COMMENT '权限JSON',
					`description` varchar(255) DEFAULT '' COMMENT '角色描述',
					`created_at` int(11) DEFAULT 0,
					PRIMARY KEY (`id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8");
				Db::name('admin_role')->insertAll([
					[
						'id'          => 1,
						'name'        => '站长',
						'permissions' => json_encode(['all']),
						'description' => '网站最高管理，拥有所有权限',
						'created_at'  => time(),
					],
					[
						'id'          => 2,
						'name'        => '超级管理员',
						'permissions' => json_encode(['user', 'product', 'classification', 'server', 'order', 'ticket', 'announcement', 'pay', 'aff', 'set', 'admin_manager', 'sq', 'transaction', 'transferrecord', 'op_log']),
					'description' => '除支付配置外所有功能',
					'created_at'  => time(),
				],
				[
					'id'          => 3,
					'name'        => '普通管理员',
					'permissions' => json_encode(['user']),
					'description' => '仅能使用概览和用户管理',
						'created_at'  => time(),
					],
				]);
			} catch (\Exception $e) {
				// 建表失败，回退到直接放行所有权限
			}
		}

		// 确保三个默认角色始终存在（兼容旧版升级，只插入不存在的角色）
		try {
			$existingRoles = Db::name('admin_role')->column('id');
			$defaultRoles = [
				['id' => 1, 'name' => '站长', 'permissions' => json_encode(['all']), 'description' => '网站最高管理，拥有所有权限', 'created_at' => time()],
				['id' => 2, 'name' => '超级管理员', 'permissions' => json_encode(['user', 'product', 'classification', 'server', 'order', 'ticket', 'announcement', 'pay', 'aff', 'set', 'admin_manager', 'sq', 'transaction', 'transferrecord', 'op_log']), 'description' => '除支付配置外所有功能', 'created_at' => time()],
				['id' => 3, 'name' => '普通管理员', 'permissions' => json_encode(['user']), 'description' => '仅能使用概览和用户管理', 'created_at' => time()],
			];
			foreach ($defaultRoles as $role) {
				if (!in_array($role['id'], $existingRoles)) {
					Db::name('admin_role')->insert($role);
				}
			}
			// 修复旧版：将 ID=1 的 "超级管理员" 重命名为 "站长"
			$oldRole = Db::name('admin_role')->where('id', 1)->find();
			if ($oldRole && $oldRole['name'] == '超级管理员') {
				Db::name('admin_role')->where('id', 1)->update(['name' => '站长', 'description' => '网站最高管理，拥有所有权限']);
			}
		} catch (\Exception $e) {
			// 角色修复失败，不影响后续流程
		}

		// 确保管理员表字段和管理员角色表完整（兼容旧版升级）
		ensure_admin_columns();
		ensure_admin_role_table();
		ensure_admin_op_log_table();
		ensure_web_bg_column();
		ensure_order_ordernumber_column();

		// 重新读取管理员信息（字段补全后）
		$this->user = Db::name('admin')->where('id', session("adminid"))->find();

		// 安全兜底：第一个管理员（ID=1）始终拥有全部权限，防止误锁后台
		if ($this->user['id'] == 1 && (!$this->user['is_super'] || $this->user['role_id'] != 1)) {
			try {
				Db::name('admin')->where('id', $this->user['id'])->update([
					'is_super' => 1,
					'role_id'  => 1,
				]);
				$this->user['is_super'] = 1;
				$this->user['role_id']  = 1;
			} catch (\Exception $e) {
				// 更新失败不影响后续流程
			}
		}

		// 自动添加 qq_group 字段（如果不存在），避免 ALTER 报错
		try {
			$webTableName = Db::name('web')->getTable();
			$cols = Db::query("SHOW COLUMNS FROM `{$webTableName}` LIKE 'qq_group'");
			if (empty($cols)) {
				Db::execute("ALTER TABLE `{$webTableName}` ADD COLUMN `qq_group` varchar(255) NOT NULL DEFAULT '' COMMENT 'QQ官方群号或链接' AFTER `telecom_license`");
			}
		} catch (\Exception $e) {
			// 字段添加失败，不影响后续流程
		}

		// 自动添加弹窗公告相关字段（如果不存在）
		try {
			$webTableName = Db::name('web')->getTable();

			$cols = Db::query("SHOW COLUMNS FROM `{$webTableName}` LIKE 'popup_notice'");
			if (empty($cols)) {
				Db::execute("ALTER TABLE `{$webTableName}` ADD COLUMN `popup_notice` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用弹窗公告' AFTER `global_datacenters`");
			}

			$cols = Db::query("SHOW COLUMNS FROM `{$webTableName}` LIKE 'popup_title'");
			if (empty($cols)) {
				Db::execute("ALTER TABLE `{$webTableName}` ADD COLUMN `popup_title` varchar(255) NOT NULL DEFAULT '' COMMENT '弹窗公告标题' AFTER `popup_notice`");
			}

			$cols = Db::query("SHOW COLUMNS FROM `{$webTableName}` LIKE 'popup_content'");
			if (empty($cols)) {
				Db::execute("ALTER TABLE `{$webTableName}` ADD COLUMN `popup_content` text COMMENT '弹窗公告 HTML 内容' AFTER `popup_title`");
			}
		} catch (\Exception $e) {
			// 字段添加失败，不影响后续流程
		}

		// 自动添加 disposable_email_block 字段（防临时邮箱注册）
		try {
			$webTableName = Db::name('web')->getTable();
			$cols = Db::query("SHOW COLUMNS FROM `{$webTableName}` LIKE 'disposable_email_block'");
			if (empty($cols)) {
				Db::execute("ALTER TABLE `{$webTableName}` ADD COLUMN `disposable_email_block` tinyint(1) NOT NULL DEFAULT '0' COMMENT '防临时邮箱注册' AFTER `yxdl`");
			}
		} catch (\Exception $e) {
			// 字段添加失败，不影响后续流程
		}

		// 自动将 web 表转换为 utf8mb4，以支持 Emoji 等 4 字节 UTF-8 字符
		try {
			$webTableName = Db::name('web')->getTable();
			$tableInfo = Db::query("SHOW TABLE STATUS LIKE '{$webTableName}'");
			if (!empty($tableInfo) && isset($tableInfo[0]['Collation']) && strpos($tableInfo[0]['Collation'], 'utf8mb4') === false) {
				Db::execute("ALTER TABLE `{$webTableName}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
			}
		} catch (\Exception $e) {
			// 字符集转换失败，不影响后续流程
		}

		// 超级管理员或超级管理员角色直接放行
		$this->hasFullAccess = ($this->user['is_super'] == 1 || $this->user['role_id'] == 1);

		// Load role permissions (for sidebar display)
		$adminPermissions = $this->hasFullAccess ? ['all'] : [];
		if (!$this->hasFullAccess && $this->user['role_id']) {
			try {
				$role = Db::name('admin_role')->where('id', $this->user['role_id'])->find();
				if ($role) {
					$adminPermissions = json_decode($role['permissions'], true) ?: [];
				}
			} catch (\Exception $e) {
				$adminPermissions = [];
			}
		}
		// 展开 'all' 为所有独立权限，确保模板中 in_array 检查生效
		if ($this->hasFullAccess && in_array('all', $adminPermissions)) {
			$adminPermissions = ['all', 'user', 'product', 'classification', 'server', 'order', 'ticket', 'announcement', 'pay', 'pays', 'aff', 'set', 'admin_manager', 'sq', 'transaction', 'transferrecord', 'op_log'];
		}
		$this->assign([
		            'webname'  => $this->web['name'],
		"user"=>$this->user,
        "templateset"=>$templateset,
        "adminPermissions"=>$adminPermissions,
        "csrf_token"=>csrf_token(),
		        ]);
	}

	protected function checkPermission($permission) {
		// 超级管理员或超级管理员角色 grant 全部权限
		if ($this->hasFullAccess) return true;

		try {
			$role = Db::name('admin_role')->where('id', $this->user['role_id'])->find();
			if (!$role) return false;
			$permissions = json_decode($role['permissions'], true);
			if (!is_array($permissions)) return false;
			return in_array('all', $permissions) || in_array($permission, $permissions);
		} catch (\Exception $e) {
			// 查询异常时默认拒绝，确保权限限制生效；一号管理员仍可通过 hasFullAccess 访问
			return false;
		}
	}

	/**
	 * 更新实名认证记录
	 * 优先更新该用户最新的待审核记录；如不存在则插入一条新记录，保留历史审核轨迹
	 */
	protected function updateRealnameRecord($userId, $status, $reviewerId = 0, $reviewerName = '') {
		try {
			$record = Db::name('realname_record')
				->where('user_id', $userId)
				->where('status', '3')
				->order('id desc')
				->find();
			if ($record) {
				Db::name('realname_record')->where('id', $record['id'])->update([
					'status' => $status,
					'review_time' => time(),
					'reviewer_id' => $reviewerId,
					'reviewer_name' => $reviewerName,
				]);
			} else {
				$user = Db::name('user')->where('id', $userId)->find();
				if ($user) {
					Db::name('realname_record')->insert([
						'user_id' => $userId,
						'realname' => $user['realname'] ?? '',
						'idcard' => $user['idcard'] ?? '',
						'status' => $status,
						'apply_time' => $user['last_login_time'] ?? time(),
						'review_time' => time(),
						'reviewer_id' => $reviewerId,
						'reviewer_name' => $reviewerName,
					]);
				}
			}
		} catch (\Exception $e) {
			// 记录更新失败不影响主流程
		}
	}


public function sq($id=null){
if($id){
$data1=Db::name('sq')->where("id",$id)->find();
if($data1){
if(Request::instance()->isPost()) {
$info=input("post.");
if($info["domain"]==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$data4=Db::name('sq')->where("id",$id)->update($info);
if($data4){
$array["code"]="1";
$array["msg"]="修改成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}
}
return json($array);
}
return $this->fetch('/'.$this->web["admintemplate"]."/sqs",[
"sq"=>$data1,
]);
}else{
$this->redirect('/admin/sq');
}
}else{
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="add"){
$info=input("post.");
if($info["domain"]==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
unset($info["act"]);
$info["time"]=time();
$data1=Db::name('sq')->insertGetId($info);
if($data1){
$array["code"]="1";
$array["msg"]="添加成功!";
}else{
$array["code"]="-1";
$array["msg"]="添加失败!";
}
}
return json($array);
}
if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$cid=array_filter($cid);
// 优化：批量删除，避免 N+1 查询
$a="0"; $b="0";
if(!empty($cid)){
	$deleted=Db::name("sq")->where("id","in",$cid)->delete();
	$a=(string)$deleted;
	$b=(string)(count($cid)-$deleted);
}
$c="";
if($b>0){
$c="<br/>失败原因:已经删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}

if(input("act")=="qbdelete"){
// 优化：单条 SQL 查询所有 id，单条 DELETE 批量删除
$ids=Db::name("sq")->column('id');
$total=count($ids);
$a="0"; $b="0";
if($total>0){
	$deleted=Db::name("sq")->where("id","in",$ids)->delete();
	$a=(string)$deleted;
	$b=(string)($total-$deleted);
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过此条记录了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}
}
$search=input("search");
if($search){
$data=Db::name('sq')->whereor("id", 'like', '%'.$search.'%')->whereor("domain", 'like', '%'.$search.'%')->whereor("ip", 'like', '%'.$search.'%')->whereor("qq", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('sq')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/sq",[
"sq"=>$data,
]);
}
}
    public function index()
    {
        $now = time();
        $todayStart = strtotime(date('Y-m-d 00:00:00'));
        $monthStart = strtotime(date('Y-m-01 00:00:00'));

        // 用户数量
        $usercount = Db::name('user')->count();
        // 今日新增用户
        $usercountToday = Db::name('user')->where('time', '>=', $todayStart)->count();

        // 工单统计
        $ticketcount1 = Db::name('ticket')->where("state","1")->count();
        $ticketcount2 = Db::name('ticket')->where("state","2")->count();

        // 订单统计（time 字段为 varchar 时间戳）
        $orderToday = Db::name('order')->where('atime', '>=', $todayStart)->where('atime', '<=', $now)->count();
        $orderMonth = Db::name('order')->where('atime', '>=', $monthStart)->where('atime', '<=', $now)->count();
        $hostActive = Db::name('order')->where('state', '1')->count();
        $hostCreating = Db::name('order')->where('state', '0')->count();
        $hostPending = Db::name('shopping_cart')->where('status', '0')->count();
        $hostExpired = Db::name('order')->where('state', '2')->count();

        // 收入统计（pay.time 为 varchar 时间戳，money 为 varchar）
        $paymoney = Db::name('pay')->where("state", "1")->sum('money');
        $paymoney1 = Db::name('pay')->where("state", "1")
            ->where('time', '>=', $todayStart)->where('time', '<=', $now)->sum('money');
        $paymoneyMonth = Db::name('pay')->where("state", "1")
            ->where('time', '>=', $monthStart)->where('time', '<=', $now)->sum('money');
        $paymoney = $paymoney ?: 0;
        $paymoney1 = $paymoney1 ?: 0;
        $paymoneyMonth = $paymoneyMonth ?: 0;

        // 最近7天订单趋势
        $orderTrendLabels = [];
        $orderTrendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $dayStart = strtotime(date('Y-m-d 00:00:00', strtotime("-{$i} days")));
            $dayEnd = strtotime(date('Y-m-d 23:59:59', strtotime("-{$i} days")));
            $orderTrendLabels[] = date('m-d', $dayStart);
            $orderTrendData[] = Db::name('order')
                ->where('atime', '>=', $dayStart)
                ->where('atime', '<=', $dayEnd)
                ->count();
        }

        // 确保用户表包含 last_login_region 等字段
        ensure_user_columns();
        // 根据已有 IP 补全用户地区，使地域分布图能统计到数据
        refresh_user_regions(50);
        // 确保购物车表存在
        ensure_shopping_cart_table();
        // 处理待开通订单（无 cron 时作为兜底）
        process_pending_host_orders();

        // 最近10条订单
        $userTable = Db::name('user')->getTable();
        $cartTable = Db::name('cart')->getTable();
        $recentOrders = Db::name('order')
            ->alias('o')
            ->field('o.id,o.user,o.userid,o.cartid,o.atime,o.state,u.user as username,c.name as cartname')
            ->join($userTable . ' u', 'o.userid = u.id', 'LEFT')
            ->join($cartTable . ' c', 'o.cartid = c.id', 'LEFT')
            ->order('o.id desc')
            ->limit(10)
            ->select();
        if (empty($recentOrders)) {
            $recentOrders = [];
        }

        $invalidRegions = ['', '本地', '未知'];

        // 用户地区分布（用于中国地图）
        $userRegionRows = Db::name('user')
            ->field('last_login_region as region, count(*) as total')
            ->where('last_login_region', 'not in', $invalidRegions)
            ->group('last_login_region')
            ->select();
        $userRegionData = [];
        foreach ($userRegionRows as $row) {
            $region = normalize_province_name($row['region']);
            if (!isset($userRegionData[$region])) $userRegionData[$region] = 0;
            $userRegionData[$region] += intval($row['total']);
        }

        // 各地区购买量（订单数）
        $orderRegionRows = Db::name('order')
            ->alias('o')
            ->join($userTable . ' u', 'o.userid = u.id', 'LEFT')
            ->field('u.last_login_region as region, count(*) as total')
            ->where('u.last_login_region', 'not in', $invalidRegions)
            ->group('u.last_login_region')
            ->select();
        $orderRegionData = [];
        foreach ($orderRegionRows as $row) {
            $region = normalize_province_name($row['region']);
            if (!isset($orderRegionData[$region])) $orderRegionData[$region] = 0;
            $orderRegionData[$region] += intval($row['total']);
        }

        // 各地区充值金额
        $payRegionRows = Db::name('pay')
            ->alias('p')
            ->join($userTable . ' u', 'p.userid = u.id', 'LEFT')
            ->field('u.last_login_region as region, sum(p.money) as total')
            ->where('p.state', '1')
            ->where('u.last_login_region', 'not in', $invalidRegions)
            ->group('u.last_login_region')
            ->select();
        $payRegionData = [];
        foreach ($payRegionRows as $row) {
            $region = normalize_province_name($row['region']);
            if (!isset($payRegionData[$region])) $payRegionData[$region] = 0;
            $payRegionData[$region] += round(floatval($row['total']), 2);
        }

        return $this->fetch('/'.$this->web["admintemplate"]."/index",[
            "usercount"=>$usercount,
            "usercountToday"=>$usercountToday,
            "ticketcount"=>$ticketcount1+$ticketcount2,
            "orderToday"=>$orderToday,
            "orderMonth"=>$orderMonth,
            "hostActive"=>$hostActive,
            "hostCreating"=>$hostCreating,
            "hostPending"=>$hostPending,
            "hostExpired"=>$hostExpired,
            "paymoney"=>$paymoney,
            "paymoney1"=>$paymoney1,
            "paymoneyMonth"=>$paymoneyMonth,
            "orderTrendLabels"=>$orderTrendLabels,
            "orderTrendData"=>$orderTrendData,
            "recentOrders"=>$recentOrders,
            "userRegionData"=>$userRegionData,
            "orderRegionData"=>$orderRegionData,
            "payRegionData"=>$payRegionData,
            "chinaMapUrl"=>\think\Request::instance()->root() . '/admin/chinamapjson',
            "ajaxMapUrl"=>\think\Request::instance()->root() . '/admin/ajaxmapdata',
            "chinaMapGeoJson"=>file_exists(PATH . 'public/static/map/china.json') ? json_decode(file_get_contents(PATH . 'public/static/map/china.json'), true) : [],
        ]);
    }

public function chinaMapJson() {
    $file = PATH . 'public/static/map/china.json';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        return json(json_decode($content, true), 200, ['Content-Type' => 'application/json']);
    }
    return json(['error' => 'Map file not found'], 404);
}

public function ajaxMapData() {
    $userTable = Db::name('user')->getTable();
    $invalidRegions = ['', '本地', '未知'];

    $userRegionRows = Db::name('user')
        ->field('last_login_region as region, count(*) as total')
        ->where('last_login_region', 'not in', $invalidRegions)
        ->group('last_login_region')
        ->select();
    $userRegionData = [];
    foreach ($userRegionRows as $row) {
        $region = normalize_province_name($row['region']);
        if (!isset($userRegionData[$region])) $userRegionData[$region] = 0;
        $userRegionData[$region] += intval($row['total']);
    }

    $orderRegionRows = Db::name('order')
        ->alias('o')
        ->join($userTable . ' u', 'o.userid = u.id', 'LEFT')
        ->field('u.last_login_region as region, count(*) as total')
        ->where('u.last_login_region', 'not in', $invalidRegions)
        ->group('u.last_login_region')
        ->select();
    $orderRegionData = [];
    foreach ($orderRegionRows as $row) {
        $region = normalize_province_name($row['region']);
        if (!isset($orderRegionData[$region])) $orderRegionData[$region] = 0;
        $orderRegionData[$region] += intval($row['total']);
    }

    $payRegionRows = Db::name('pay')
        ->alias('p')
        ->join($userTable . ' u', 'p.userid = u.id', 'LEFT')
        ->field('u.last_login_region as region, sum(p.money) as total')
        ->where('p.state', '1')
        ->where('u.last_login_region', 'not in', $invalidRegions)
        ->group('u.last_login_region')
        ->select();
    $payRegionData = [];
    foreach ($payRegionRows as $row) {
        $region = normalize_province_name($row['region']);
        if (!isset($payRegionData[$region])) $payRegionData[$region] = 0;
        $payRegionData[$region] += round(floatval($row['total']), 2);
    }

    return json([
        'code' => 1,
        'userRegionData' => $userRegionData,
        'orderRegionData' => $orderRegionData,
        'payRegionData' => $payRegionData,
    ]);
}

public function info(){
if(Request::instance()->isPost()) {
$name=input("name");
$mail=input("mail");
$qq=input("qq");
if($name=="" || $mail=="" || $qq==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$data=Db::name('admin')->where('id',$this->user["id"])->update([
"name" =>$name,
"mail"=>$mail,
"qq"=>$qq,
]);
if($data){
$array["code"]="1";
$array["msg"]="修改信息成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改信息失败!";
}
}
return json($array);
}
	
return $this->fetch('/'.$this->web["admintemplate"]."/info");
}



public function password(){
if(Request::instance()->isPost()) {
$oldpassword=input("oldpassword");
$password=input("password");
$newpassword=input("newpassword");
if($oldpassword=="" || $password=="" || $newpassword==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if(!password_verify($oldpassword,$this->user["password"])){
$array["code"]="-1";
$array["msg"]="旧密码错误!";
}else{
if($password!=$newpassword){
$array["code"]="-1";
$array["msg"]="两次输入的新密码不一致!";
}else{
$data=Db::name('admin')->where('id',$this->user["id"])->update([
"password" =>password_hash($password,PASSWORD_DEFAULT),
]);
if($data){
$array["code"]="1";
$array["msg"]="修改密码成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改密码失败!";
}
}
}
}
return json($array);
}
	
return $this->fetch('/'.$this->web["admintemplate"]."/password");
}

public function logout() {
		session("adminid",null);
		$this->redirect('/admin/login');
	}


public function set() {
if (!$this->checkPermission('set')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if(Request::instance()->isPost()) {
try {
$webinput=input("post.");
// 全球数据中心：仅支持 JSON 格式
// 绕过 ThinkPHP default_filter 的 htmlspecialchars 过滤
$webinput['global_datacenters'] = isset($_POST['global_datacenters']) ? trim($_POST['global_datacenters']) : '';

// 弹窗公告标题与内容支持 HTML，使用 $_POST 原始值避免被转义
$webinput['popup_title'] = isset($_POST['popup_title']) ? trim($_POST['popup_title']) : '';
$webinput['popup_content'] = isset($_POST['popup_content']) ? trim($_POST['popup_content']) : '';

// 实名限制开关：未勾选时不提交，需显式置 0
$realnameLimitKeys = ['realname_limit_pay', 'realname_limit_buy', 'realname_limit_ticket', 'realname_limit_renew', 'realname_first_free'];
foreach ($realnameLimitKeys as $rk) {
    if (!isset($webinput[$rk])) {
        $webinput[$rk] = '0';
    }
}

// 液态玻璃总开关（表单同时提交 hidden 0 和 checkbox 1，取最后提交的 checkbox 值）
$webinput['glass_enabled'] = isset($_POST['glass_enabled']) ? intval($_POST['glass_enabled']) : 0;
// 液态玻璃主题配色方案
$webinput['glass_theme'] = isset($_POST['glass_theme']) && in_array($_POST['glass_theme'], ['default', 'pink_blue']) ? $_POST['glass_theme'] : 'default';
// 液态玻璃透明度（30-100，默认72）
$glassOpacity = isset($_POST['glass_opacity']) ? intval($_POST['glass_opacity']) : 72;
$webinput['glass_opacity'] = max(30, min(100, $glassOpacity));
// 轮播背景图清理空行
if (isset($webinput['bg_images'])) {
    $lines = array_filter(array_map('trim', explode("\n", $webinput['bg_images'])));
    $webinput['bg_images'] = implode("\n", $lines);
}
// 背景类型与视频开关
$webinput['bg_type'] = isset($webinput['bg_type']) && in_array($webinput['bg_type'], ['image','video','gif']) ? $webinput['bg_type'] : 'image';
$webinput['bg_video_loop'] = isset($webinput['bg_video_loop']) ? intval($webinput['bg_video_loop']) : 1;
$webinput['bg_video_muted'] = isset($webinput['bg_video_muted']) ? intval($webinput['bg_video_muted']) : 1;

// 校验 JSON 格式
$dcTrimmed = trim($webinput['global_datacenters']);
if ($dcTrimmed !== '') {
    json_decode($dcTrimmed);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return json(['code' => -1, 'msg' => '全球数据中心 JSON 格式错误：' . json_last_error_msg()]);
    }
}
if($this->web["template"]!=$webinput["template"]){
$file1=file_exists(PATH."/app/index/view/".$webinput["template"]."/set.php");
if($file1){
$wj=include_once(PATH."/app/index/view/".$webinput["template"]."/set.php");
$webinput["templateset"]=json_encode($wj);
}else{
$webinput["templateset"]="";
}
}
if($webinput["zcyxyz"]=="1" && $webinput["email"]!="1"){
$array["code"]="-1";
$array["msg"]="修改失败,开启注册邮箱验证需要先开启邮箱通知!";
}else{
if($webinput["yxdl"]=="1" && $webinput["email"]!="1"){
$array["code"]="-1";
$array["msg"]="修改失败,开启邮箱登录需要先开启邮箱通知!";
}else{
unset($webinput['__token__']);
// 确保 Live2D AI 相关列存在
$live2dAiCols = [
    'live2d_ai_enabled' => "ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `live2d_ai_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'AI聊天开关'",
    'live2d_ai_api_url' => "ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `live2d_ai_api_url` varchar(500) NOT NULL DEFAULT '' COMMENT 'AI API地址'",
    'live2d_ai_api_key' => "ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `live2d_ai_api_key` varchar(500) NOT NULL DEFAULT '' COMMENT 'AI API密钥'",
    'live2d_ai_model' => "ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `live2d_ai_model` varchar(100) NOT NULL DEFAULT 'deepseek-v4-flash' COMMENT 'AI模型'",
    'live2d_ai_persona' => "ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `live2d_ai_persona` TEXT NULL COMMENT 'AI人设（自定义）'",
];
try {
    $existingCols = Db::query("SHOW COLUMNS FROM " . config('database.prefix') . "web");
    $existingNames = array_column($existingCols, 'Field');
    foreach ($live2dAiCols as $colName => $alterSql) {
        if (!in_array($colName, $existingNames)) {
            Db::execute($alterSql);
        }
    }
    // 刷新列列表，确保后续 update 不会因列不存在而失败
    $existingCols = Db::query("SHOW COLUMNS FROM " . config('database.prefix') . "web");
    $existingNames = array_column($existingCols, 'Field');
    foreach (array_keys($live2dAiCols) as $colName) {
        if (!in_array($colName, $existingNames)) {
            unset($webinput[$colName]);
        }
    }
} catch (\Exception $e) {
    // 列创建失败，从表单数据中移除这些字段，避免 update 报错
    foreach (array_keys($live2dAiCols) as $colName) {
        unset($webinput[$colName]);
    }
}
// 确保 glass_theme 列存在
try {
    $columns = Db::query("SHOW COLUMNS FROM " . config('database.prefix') . "web LIKE 'glass_theme'");
    if (empty($columns)) {
        Db::execute("ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `glass_theme` varchar(20) NOT NULL DEFAULT 'default' COMMENT '配色方案:default/pink_blue' AFTER `glass_opacity`");
    }
} catch (\Throwable $e) {}
// 确保聚合登录（QQ登录）相关字段存在，避免回调地址等配置被静默丢弃导致保存失败
$oauthCols = [
    'oauth_enabled'  => "ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `oauth_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用聚合登录'",
    'oauth_appid'    => "ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `oauth_appid` varchar(100) DEFAULT '' COMMENT 'API应用ID'",
    'oauth_appkey'   => "ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `oauth_appkey` varchar(100) DEFAULT '' COMMENT 'API应用密钥'",
    'oauth_callback' => "ALTER TABLE " . config('database.prefix') . "web ADD COLUMN `oauth_callback` varchar(255) DEFAULT '' COMMENT '回调URL'",
];
try {
    $existingCols = Db::query("SHOW COLUMNS FROM " . config('database.prefix') . "web");
    $existingNames = array_column($existingCols, 'Field');
    foreach ($oauthCols as $colName => $alterSql) {
        if (!in_array($colName, $existingNames)) {
            Db::execute($alterSql);
        }
    }
} catch (\Exception $e) {
    // 列创建失败，从表单数据中移除这些字段，避免 update 报错
    foreach (array_keys($oauthCols) as $colName) {
        unset($webinput[$colName]);
    }
}
// 确保看板娘相关字段存在（后台"启用看板娘"开关依赖 live2d_enabled）
ensure_web_bg_column();
// 通用字段过滤：移除 web 表中不存在的字段，防止 fields_strict 导致"字段不存在"报错、保存失败
try {
    $webColumns = Db::query("SHOW COLUMNS FROM " . config('database.prefix') . "web");
    $webColumnNames = array_column($webColumns, 'Field');
    foreach (array_keys($webinput) as $webFieldName) {
        if (!in_array($webFieldName, $webColumnNames)) {
            unset($webinput[$webFieldName]);
        }
    }
} catch (\Exception $e) {}
$data=Db::name('web')->where('id',"1")->update($webinput);
if($data!==false){
$array["code"]="1";
$array["msg"]="修改成功!";
admin_op_log('set_update', '修改网站设置');
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}
}
}

return json($array);
} catch (\Exception $e) {
    return json(['code' => -1, 'msg' => '保存失败：' . $e->getMessage()]);
}
}
	
return $this->fetch('/'.$this->web["admintemplate"]."/set",[
"web"=>$this->web,
"cronurl"=>(isHTTPS() ? 'https://' : 'http://').$_SERVER['HTTP_HOST']."/cron",
"template"=>my_dir(PATH."/app/index/view/"),
"admintemplate"=>my_dir(PATH."/app/admin/view/"),
]);
	}

	// 测试实名认证 API 配置（仅管理员）
	public function testRealnameApi()
	{
		if (!$this->checkPermission('set')) {
			return json(['code' => -1, 'msg' => '无权限']);
		}
		if (!Request::instance()->isPost()) {
			return json(['code' => -1, 'msg' => '非法请求']);
		}
		$apiType = input('api_type', '1');
		if ($apiType == '2') {
			$secretId = input('secret_id', '');
			$secretKey = input('secret_key', '');
			if (empty($secretId) || empty($secretKey)) {
				return json(['code' => -1, 'msg' => 'SecretId 和 SecretKey 不能为空']);
			}
			$result = cloudmarket_realname_verify('测试', '110101199001010000', $secretId, $secretKey);
			if ($result['code'] == 1) {
				return json(['code' => 1, 'msg' => '云市场 二要素 API 账号校验通过']);
			}
			if (stripos($result['msg'], '请求参数错误') !== false || stripos($result['msg'], 'idcard') !== false) {
				return json(['code' => 1, 'msg' => '云市场 二要素 API 账号校验通过（测试身份证不合法，属于预期结果）']);
			}
			return json(['code' => -1, 'msg' => '云市场 二要素 API 测试失败：' . $result['msg']]);
		}
		if ($apiType == '3') {
			$secretId = input('secret_id', '');
			$secretKey = input('secret_key', '');
			if (empty($secretId) || empty($secretKey)) {
				return json(['code' => -1, 'msg' => 'SecretId 和 SecretKey 不能为空']);
			}
			$result = phone3element_verify('测试', '110101199001010000', '13800138000', $secretId, $secretKey);
			if ($result['code'] == 1) {
				return json(['code' => 1, 'msg' => '云市场 手机三要素 API 账号校验通过']);
			}
			if (stripos($result['msg'], '不匹配') !== false || stripos($result['msg'], '无记录') !== false) {
				return json(['code' => 1, 'msg' => '云市场 手机三要素 API 账号校验通过（测试数据不匹配，属于预期结果）']);
			}
			if (stripos($result['msg'], 'error_code=10025') !== false) {
				return json(['code' => 1, 'msg' => '云市场 手机三要素 API 账号校验通过（测试手机号库无记录，属于预期结果）']);
			}
			return json(['code' => -1, 'msg' => '云市场 手机三要素 API 测试失败：' . $result['msg']]);
		}
		$appid = input('appid', '');
		$appkey = input('appkey', '');
		if (empty($appid) || empty($appkey)) {
			return json(['code' => -1, 'msg' => 'AppID 和 AppKey 不能为空']);
		}
		// 使用一组固定测试数据调用接口（不会通过，仅验证账号可用性）
		$result = realname_api_verify_with_key('测试', '110101199001010000', $appid, $appkey);
		if ($result['code'] == 1) {
			return json(['code' => 1, 'msg' => '花迹数据 API 账号校验通过']);
		}
		if (stripos($result['msg'], '4000') !== false || stripos($result['msg'], '账号或密钥错误') !== false) {
			return json(['code' => -1, 'msg' => '花迹数据 API 账号或密钥错误（错误码：4000），请检查 AppID / AppKey']);
		}
		if (stripos($result['msg'], '余额不足') !== false) {
			return json(['code' => -1, 'msg' => '花迹数据 API 账号余额不足，请充值后再试']);
		}
		// 其它错误（如参数错误）说明账号可用，只是测试身份证不合法
		if (stripos($result['msg'], 'idcard') !== false || stripos($result['msg'], '参数') !== false) {
			return json(['code' => 1, 'msg' => '花迹数据 API 账号校验通过（测试身份证不合法，属于预期结果）']);
		}
		return json(['code' => -1, 'msg' => '花迹数据 API 测试失败：' . $result['msg']]);
	}

	// 背景文件上传（支持图片/视频/GIF）
	public function bg_upload() {
		if (!$this->checkPermission('set')) {
			return json(['code' => -1, 'msg' => '无权限']);
		}
		try {
			$file = request()->file('file');
			if(!$file) {
				return json(['code' => -1, 'msg' => '请选择文件']);
			}

			// 从文件扩展名自动判断类型，不再依赖前端传参（更稳健）
			$ext = strtolower(pathinfo($file->getInfo('name'), PATHINFO_EXTENSION));
			$videoExts = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
			$gifExts = ['gif'];
			$imageExts = ['jpg', 'jpeg', 'png', 'webp', 'bmp'];

			if (in_array($ext, $videoExts)) {
				$bgType = 'video';
				$maxSize = 104857600; // 100MB
				$allowedExts = 'mp4,webm,mov,avi,mkv';
				$allowedMimes = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo', 'video/x-matroska'];
			} elseif (in_array($ext, $gifExts)) {
				$bgType = 'gif';
				$maxSize = 20971520; // 20MB
				$allowedExts = 'gif';
				$allowedMimes = ['image/gif'];
			} else {
				$bgType = 'image';
				$maxSize = 52428800; // 50MB
				$allowedExts = 'jpg,jpeg,png,webp,bmp';
				$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/bmp'];
			}

			$uploadDir = PATH . 'public/uploads/bg/';
			if(!is_dir($uploadDir)) {
				@mkdir($uploadDir, 0755, true);
			}
			if(!is_dir($uploadDir) || !is_writable($uploadDir)) {
				$uploadDir = PATH . 'public/uploads/';
			}

			$info = $file->validate([
				'size' => $maxSize,
				'ext'  => $allowedExts,
			])->move($uploadDir);
			if(!$info) {
				return json(['code' => -1, 'msg' => $file->getError() ?: '上传失败']);
			}
			// 二次校验 MIME
			$realPath = $info->getRealPath();
			if(function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mime = finfo_file($finfo, $realPath);
				finfo_close($finfo);
				if(!in_array($mime, $allowedMimes)) {
					@unlink($realPath);
					return json(['code' => -1, 'msg' => '文件类型不允许：' . $mime]);
				}
			}
			$url = '/uploads/bg/' . $info->getSaveName();
			Db::name('web')->where('id', '1')->update([
				'bg_image' => $url,
				'bg_type' => $bgType,
			]);
			return json(['code' => 1, 'msg' => '上传成功', 'url' => $url, 'type' => $bgType]);
		} catch (\Exception $e) {
			return json(['code' => -1, 'msg' => '上传异常：' . $e->getMessage()]);
		}
	}

	// 重置背景图
	public function bg_reset() {
		if (!$this->checkPermission('set')) {
			return json(['code' => -1, 'msg' => '无权限']);
		}
		Db::name('web')->where('id', '1')->update(['bg_image' => '', 'bg_images' => '', 'bg_type' => 'image']);
		return json(['code' => 1, 'msg' => '背景图已重置']);
	}

	// 轮播背景图多图上传（仅上传文件并返回URL，不直接写入数据库）
	public function bg_multi_upload() {
		if (!$this->checkPermission('set')) {
			return json(['code' => -1, 'msg' => '无权限']);
		}
		try {
			$file = request()->file('file');
			if(!$file) {
				return json(['code' => -1, 'msg' => '请选择文件']);
			}
			$uploadDir = PATH . 'public/uploads/bg/';
			if(!is_dir($uploadDir)) {
				@mkdir($uploadDir, 0755, true);
			}
			if(!is_dir($uploadDir) || !is_writable($uploadDir)) {
				$uploadDir = PATH . 'public/uploads/';
			}
			$info = $file->validate([
				'size' => 52428800,
				'ext'  => 'jpg,jpeg,png,webp,gif',
			])->move($uploadDir);
			if(!$info) {
				return json(['code' => -1, 'msg' => $file->getError() ?: '上传失败']);
			}
			$realPath = $info->getRealPath();
			if(function_exists('finfo_open')) {
				$finfo = finfo_open(FILEINFO_MIME_TYPE);
				$mime = finfo_file($finfo, $realPath);
				finfo_close($finfo);
				$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
				if(!in_array($mime, $allowedMimes)) {
					@unlink($realPath);
					return json(['code' => -1, 'msg' => '文件类型不允许：' . $mime]);
				}
			}
			$url = '/uploads/bg/' . $info->getSaveName();
			return json(['code' => 1, 'msg' => '上传成功', 'url' => $url]);
		} catch (\Exception $e) {
			return json(['code' => -1, 'msg' => '上传异常：' . $e->getMessage()]);
		}
	}

	// 实名审核
	public function realnameReview() {
		if (!$this->checkPermission('user')) {
			$this->error('您没有权限访问此页面', '/admin/index');
		}
		ensure_user_columns();
		ensure_realname_record_table();
		if(Request::instance()->isPost()) {
			$act = input('act');
			$id = input('id', 0);
			$array = ['code' => '-1', 'msg' => ''];
			$user = Db::name('user')->where('id', $id)->find();
			if(!$user) {
				$array['msg'] = '用户不存在';
				return json($array);
			}
			$reviewerId = session('adminid');
			$reviewerName = $this->user['user'] ?? '';
			if($act == 'approve') {
				Db::name('user')->where('id', $id)->update(['realname_status' => 1]);
				$this->updateRealnameRecord($id, 1, $reviewerId, $reviewerName);
				// 发送实名认证通过邮件通知
				if($this->web["email"]=="1" && !empty($user["mail"])){
					$realname = $user['realname'] ?: $user['name'];
					try {
						self::email($user["mail"], "实名认证通过通知", '<p>您好 '.htmlspecialchars($realname).'，</p><p>恭喜！您的实名认证已审核通过。</p><p style="color:#64748b;font-size:13px;">认证姓名：'.htmlspecialchars($realname).'<br>认证时间：'.date("Y-m-d H:i:s").'</p>');
					} catch (\Exception $mailEx) {}
				}
				$array['code'] = '1';
				$array['msg'] = '已通过实名认证';
				return json($array);
			}
			if($act == 'reject') {
				Db::name('user')->where('id', $id)->update(['realname_status' => 2]);
				$this->updateRealnameRecord($id, 2, $reviewerId, $reviewerName);
				$array['code'] = '1';
				$array['msg'] = '已驳回实名认证';
			return json($array);
		}

		return json($array);
	}
	$list = Db::name('user')
			->where('realname_status', '3')
			->order('id desc')
			->paginate(15);
		return $this->fetch('/'.$this->web["admintemplate"].'/realname_review', [
			'list' => $list,
		]);
	}

	public function user($id=null,$orderid=null) {
if (!$this->checkPermission('user')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if(!$id){
if(Request::instance()->isPost()) {

if(input("act")=="login"){
$userid=input("userid");
if($userid==""){
$this->redirect('/user/index');
}else{
session("userid",$userid);
$this->redirect('/user/index');
}
}

if(input("act")=="delete"){
if(input("userid")){
$userid=explode(",",input("userid"));
$a="0";
$b="0";
// 优化：一次性查询所有有订单的 userid, 避免 N+1 查询
$usersWithOrders = Db::name("order")->where("userid", "in", $userid)->column("userid");
$usersWithOrders = array_unique($usersWithOrders);
for($i=0;$i<count($userid);$i++){
if(in_array($userid[$i], $usersWithOrders)){
$b=$b+1;
}else{
$data1=Db::name("user")->where("id",$userid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除或者账户下还有未删除的产品!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}else{
$array["code"]="-1";
$array["msg"]="必填参数不可为空!!";
}
return json($array);
}


if(input("act")=="banUser"){
$userid=input("userid");
$ban_duration=input("ban_duration");
$ban_reason=input("ban_reason");
if($userid=="" || $ban_duration==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if(!is_numeric($ban_duration) || $ban_duration<1 || $ban_duration>87600){
$array["code"]="-1";
$array["msg"]="封禁时长必须为1-87600小时之间的数字!";
}else{
$user=Db::name('user')->where('id',$userid)->find();
if(!$user){
$array["code"]="-1";
$array["msg"]="用户不存在!";
}else{
$ban_time=time()+$ban_duration*3600;
$data=Db::name('user')->where('id',$userid)->update([
"ban_time"=>$ban_time,
"ban_reason"=>$ban_reason,
]);
// 自动停用该用户所有活跃主机
$activeOrders=Db::name('order')->where([
"userid"=>$userid,
"state"=>"1",
])->select();
if(!empty($activeOrders)){
$cartIds=array_unique(array_column($activeOrders,'cartid'));
$cartMap=Db::name('cart')->where('id','in',$cartIds)->column('serverid','id');
$serverIds=array_unique(array_values($cartMap));
$serverMap=Db::name('server')->where('id','in',$serverIds)->column('serverplugins','id');
foreach($activeOrders as $order){
$serverId=isset($cartMap[$order['cartid']])?$cartMap[$order['cartid']]:null;
if($serverId && isset($serverMap[$serverId])){
$pluginFile=PATH."plugins/host/".$serverMap[$serverId]."/".$serverMap[$serverId].".php";
if(file_exists($pluginFile)){
include_once $pluginFile;
$function=$serverMap[$serverId]."_"."SuspendAccount";
if(function_exists($function)){
$cart=Db::name('cart')->where('id',$order['cartid'])->find();
$server=Db::name('server')->where('id',$serverId)->find();
@$function($server,$order,$cart);
}
}
}
Db::name('order')->where('id',$order['id'])->update(["state"=>"2"]);
}
}
if($data!==false){
$array["code"]="1";
$array["msg"]="封禁成功!已自动停用该用户所有主机";
}else{
$array["code"]="-1";
$array["msg"]="封禁失败!";
}
}
}
}
return json($array);
}

if(input("act")=="unbanUser"){
$userid=input("userid");
if($userid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$data=Db::name('user')->where('id',$userid)->update([
"ban_time"=>"0",
"ban_reason"=>"",
]);
// 自动恢复该用户所有被暂停的主机
$suspendedOrders=Db::name('order')->where([
"userid"=>$userid,
"state"=>"2",
])->select();
$recovered=0;
if(!empty($suspendedOrders)){
$cartIds=array_unique(array_column($suspendedOrders,'cartid'));
$cartMap=Db::name('cart')->where('id','in',$cartIds)->column('serverid','id');
$serverIds=array_unique(array_values($cartMap));
$serverMap=Db::name('server')->where('id','in',$serverIds)->column('serverplugins','id');
foreach($suspendedOrders as $order){
$serverId=isset($cartMap[$order['cartid']])?$cartMap[$order['cartid']]:null;
if($serverId && isset($serverMap[$serverId])){
$pluginFile=PATH."plugins/host/".$serverMap[$serverId]."/".$serverMap[$serverId].".php";
if(file_exists($pluginFile)){
include_once $pluginFile;
$function=$serverMap[$serverId]."_"."UnsuspendAccount";
if(function_exists($function)){
$cart=Db::name('cart')->where('id',$order['cartid'])->find();
$server=Db::name('server')->where('id',$serverId)->find();
@$function($server,$order,$cart);
}
}
}
Db::name('order')->where('id',$order['id'])->update(["state"=>"1"]);
$recovered++;
}
}
if($data!==false){
$array["code"]="1";
$array["msg"]="解封成功！已恢复 ".$recovered." 台主机";
}else{
$array["code"]="-1";
$array["msg"]="解封失败!";
}
}
return json($array);
}

if(input("act")=="qbdelete"){
// 优化：批量查询所有用户 id 和有订单的 userid，单条 DELETE 批量删除
$ids=Db::name("user")->column('id');
$a="0"; $b="0";
if(!empty($ids)){
	$usersWithOrders=Db::name("order")->where("userid","in",$ids)->column("userid");
	$usersWithOrders=array_unique($usersWithOrders);
	$deleteIds=[];
	foreach($ids as $uid){
		if(!in_array($uid,$usersWithOrders)){
			$deleteIds[]=$uid;
		}
	}
	if(!empty($deleteIds)){
		$deleted=Db::name("user")->where("id","in",$deleteIds)->delete();
		$a=(string)$deleted;
		$b=(string)(count($ids)-$deleted);
	}else{
		$b=(string)count($ids);
	}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除或者账户下还有未删除的产品!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}

if(input("act")=="adduser"){
$name=input("name");
$user=input("user");
$qq=input("qq");
$mail=input("mail");
$password=input("password");
if($name=="" || $user=="" || $qq=="" || $password==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if($mail){
$data4=is_valid_email($mail);
if($data4){
	$data=Db::name('user')->where("user",$user)->find();
			if($data) {
				$array["code"]="-1";
				$array["msg"]="账号已存在!";
			} else {
$data2=Db::name('user')->where("mail",$mail)->find();
if($data2){
				$array["code"]="-1";
				$array["msg"]="邮箱已存在!";
}else{
	$data3=Db::name('user')->insertGetId([
				"name"=>$name,
				"user"=>$user,
				"password"=>password_hash($password,PASSWORD_DEFAULT),
				"mail"=>$mail,
				"time"=>time(),
                "qq"=>$qq,
                "address"=>"",
                "aff"=>"",
                "upperid"=>"",
				]);
if($data3){
$array["code"]="1";
$array["msg"]="添加成功!";
}else{
$array["code"]="-1";
$array["msg"]="添加失败!";
}
}
}
}else{
$array["code"]="-1";
$array["msg"]="邮箱格式错误!";
}
}else{






	$data=Db::name('user')->where("user",$user)->find();
			if($data) {
				$array["code"]="-1";
				$array["msg"]="账号已存在!";
			} else {
	$data3=Db::name('user')->insertGetId([
				"name"=>$name,
				"user"=>$user,
				"password"=>password_hash($password,PASSWORD_DEFAULT),
				"mail"=>$mail,
				"time"=>time(),
                "qq"=>$qq,
                "address"=>"",
                "aff"=>"",
                "upperid"=>"",
				]);
if($data3){
$array["code"]="1";
$array["msg"]="添加成功!";
}else{
$array["code"]="-1";
$array["msg"]="添加失败!";
}
}



}
}
return json($array);
}


}
$search = input("search", '');
$realnameFilter = input("realname_filter", '');  // 1=已实名 0=未实名
$vipFilter = input("vip_filter", '');            // 1-6 VIP等级
$oauthFilter = input("oauth_filter", '');        // 1=QQ快捷登录
$stateFilter = input("state_filter", '');        // 1=正常 0=冻结

$query = Db::name('user');

// 关键词搜索
if ($search) {
    $query->where(function($q) use ($search) {
        $q->where("id", 'like', '%'.$search.'%')
          ->whereOr("user", 'like', '%'.$search.'%')
          ->whereOr("name", 'like', '%'.$search.'%')
          ->whereOr("mail", 'like', '%'.$search.'%')
          ->whereOr("qq", 'like', '%'.$search.'%')
          ->whereOr("address", 'like', '%'.$search.'%')
          ->whereOr("aff", 'like', '%'.$search.'%');
    });
}

// 实名状态筛选
if ($realnameFilter !== '') {
    if ($realnameFilter == '1') {
        $query->where('realname_status', 1);  // 已实名
    } else {
        $query->where('realname_status', '<>', 1)->whereOr('realname_status', null);  // 未实名
    }
}

// VIP等级筛选
if ($vipFilter !== '') {
    $query->where('membership_level', intval($vipFilter));
}

// QQ快捷登录筛选
if ($oauthFilter == '1') {
    $query->where('oauth_qq', '<>', '');
}

// 状态筛选
if ($stateFilter !== '') {
    $query->where('state', $stateFilter);
}

$users = $query->order('id desc')->paginate(10, false, ['query' => request()->param()]);

$levels = Db::name('membership_levels')->where('status', 1)->order('level asc')->select();
$levelMap = [];
foreach ($levels as $lv) { $levelMap[$lv['level']] = $lv; }

return $this->fetch('/'.$this->web["admintemplate"]."/user", [
    "users"          => $users,
    "levelMap"       => $levelMap,
    "search"         => $search,
    "realnameFilter" => $realnameFilter,
    "vipFilter"      => $vipFilter,
    "oauthFilter"    => $oauthFilter,
    "stateFilter"    => $stateFilter,
]);
}else{
$data=Db::name("user")->where("id",$id)->find();
if($data){
if($orderid){
$data1=Db::name("order")->where([
"id"=>$orderid,
"userid"=>$id,
])->find();
if($data1){
//用户产品
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="edit"){
$post=input("post.");
$post["atime"]=strtotime($post["atime"]);
if($post["atime"]==""){
$post["atime"]="1";
}
$post["ztime"]=strtotime($post["ztime"]);
if($post["ztime"]==""){
$post["ztime"]="1";
}
unset($post["act"]);
$db=Db::name("order")->where([
"id"=>$orderid,
"userid"=>$id,
])->update($post);
if($db){
	$array["code"]="1";
	$array["msg"]="修改成功!";
}else{
	$array["code"]="-1";
	$array["msg"]="修改失败!";
}
return json($array);
}
//暂停
if($act=="stop"){
if($data1["state"]=="3"){
	$array["code"]="-1";
	$array["msg"]="产品已终止,禁止修改此状态!";
}else{
$da1=Db::name('cart')->where("id",$data1["cartid"])->find();
$da2=Db::name('server')->where("id",$da1["serverid"])->find();
include_once PATH."plugins/host/".$da2["serverplugins"]."/".$da2["serverplugins"].".php";
$function=$da2["serverplugins"]."_"."SuspendAccount";
if(function_exists($function)){
$da4=@$function($da2,$data1,$da1);
}
$da5=Db::name("order")->where("id",$data1["id"])->update([
"state"=>"2",
]);
	$array["code"]="1";
	$array["msg"]="暂停成功!";
}
return json($array);

}
//解除暂停
if($act=="stopoff"){
if($data1["state"]=="3"){
	$array["code"]="-1";
	$array["msg"]="产品已终止,禁止修改此状态!";
}else{
$da1=Db::name('cart')->where("id",$data1["cartid"])->find();
$da2=Db::name('server')->where("id",$da1["serverid"])->find();
include_once PATH."plugins/host/".$da2["serverplugins"]."/".$da2["serverplugins"].".php";
$function=$da2["serverplugins"]."_"."UnsuspendAccount";
if(function_exists($function)){
$da3=@$function($da2,$data1,$da1);
}
$da5=Db::name("order")->where("id",$data1["id"])->update([
"state"=>"1",
]);
	$array["code"]="1";
	$array["msg"]="解除暂停成功!";
}
return json($array);

}
//终止
if($act=="end"){
$da1=Db::name('cart')->where("id",$data1["cartid"])->find();
$da2=Db::name('server')->where("id",$da1["serverid"])->find();
include_once PATH."plugins/host/".$da2["serverplugins"]."/".$da2["serverplugins"].".php";
$function=$da2["serverplugins"]."_"."TerminateAccount";
if(function_exists($function)){
$da4=@$function($da2,$data1,$da1);
}
$da5=Db::name("order")->where("id",$data1["id"])->update([
"state"=>"3",
]);
$da6=Db::name("cart")->where("id",$data1["cartid"])->update([
"inventory"=>$da1["inventory"]+1,
]);
	$array["code"]="1";
	$array["msg"]="终止成功!";
return json($array);

}
//删除
if($act=="delete"){
$da5=Db::name("order")->where("id",$data1["id"])->delete();
	$array["code"]="1";
	$array["msg"]="删除成功!";
return json($array);
}


}



$userorder=Db::name("order")->where([
"id"=>$orderid,
"userid"=>$id,
])->find();


$da6=Db::name('cart')->where("id",$userorder["cartid"])->find();
$da5=Db::name('server')->where("id",$da6["serverid"])->find();
include_once PATH."plugins/host/".$da5["serverplugins"]."/".$da5["serverplugins"].".php";
$function=$da5["serverplugins"]."_"."OrderConfigOptions";
if(function_exists($function)){
$da4=@$function();
for($i=0;$i<count($da4);$i++){
foreach ($userorder as $key => $value){
if($da4[$i]["name"]==$key){
if($value!=""){
$da4[$i]["value"]=$value;
}
}
}
}
$data7=$da4;
}else{
$data7="";
}

return $this->fetch('/'.$this->web["admintemplate"]."/userorder",[
"userorder"=>$userorder,
"data7"=>$data7,
]);



}else{
$this->redirect('/admin/user/'.$id);
}
}else{
//用户信息
			if(Request::instance()->isPost()) {
			$act = input("act");
			// 禁用实名信息
			if($act == "clearRealname"){
				$userid = input("userid", 0);
				if(!$userid){
					$array["code"]="-1";
					$array["msg"]="参数错误";
					return json($array);
				}
				$user = Db::name('user')->where('id', $userid)->find();
				if(!$user){
					$array["code"]="-1";
					$array["msg"]="用户不存在";
					return json($array);
				}
				Db::name('user')->where('id', $userid)->update([
					'realname' => '',
					'idcard' => '',
					'realname_status' => 0,
				]);
				$reviewerId = session('adminid');
				$reviewerName = $this->user['user'] ?? '';
				ensure_realname_record_table();
				Db::name('realname_record')->insert([
					'user_id' => $userid,
					'realname' => $user['realname'] ?? '',
					'idcard' => $user['idcard'] ?? '',
					'status' => 0,
					'apply_time' => $user['last_login_time'] ?? time(),
					'review_time' => time(),
					'reviewer_id' => $reviewerId,
					'reviewer_name' => $reviewerName,
				]);
				$array["code"]="1";
				$array["msg"]="已禁用实名信息";
				return json($array);
			}

			$name=input("name");
			$user=input("user");
			$money=input("money");
			$qq=input("qq");
			$mail=input("mail");
			$address=input("address");
			$password=input("password");
			$aff=input("aff");
			$affmoney=input("affmoney");
			$upperid=input("upperid");
			$state=input("state");
			$realname=input("realname", '');
			$idcard=input("idcard", '');
			$realname_status=input("realname_status", 0);
			$membership_level=intval(input("membership_level", 0));
			$points=intval(input("points", 0));
			if($name=="" || $user=="" || $money=="" || $qq=="" || $state=="" || $affmoney==""){
				$array["code"]="-1";
				$array["msg"]="必填项不可为空!";
			}else{
			$update = [
				"name"=>$name,
				"user"=>$user,
				"money"=>$money,
				"qq"=>$qq,
				"mail"=>$mail,
				"address"=>$address,
				"aff"=>$aff,
				"affmoney"=>$affmoney,
				"upperid"=>$upperid,
				"state"=>$state,
				"realname"=>$realname,
				"idcard"=>$idcard,
				"realname_status"=>$realname_status,
				"membership_level"=>$membership_level,
				"points"=>$points,
			];
			if($password){
				$update["password"] = password_hash($password,PASSWORD_DEFAULT);
			}
			$data3=Db::name('user')->where('id',$data["id"])->update($update);
			// 如果余额增加，更新累计充值
			$oldMoney = floatval($data['money'] ?? 0);
			$newMoney = floatval($money);
			if ($newMoney > $oldMoney) {
				$diff = round($newMoney - $oldMoney, 2);
				Db::name('user')->where('id', $data['id'])->setInc('total_recharge', $diff);
				if (function_exists('update_user_membership')) {
					update_user_membership($data['id']);
				}
			}
			if($data3){
				$array["code"]="1";
				$array["msg"]="修改成功!";
			}else{
				$array["code"]="-1";
				$array["msg"]="修改失败!";
			}
			}
			return json($array);
			}

$data2=Db::name("order")->where([
"userid"=>$id,
])->order('id desc')->select();
// 优化：批量查询 cart 表，避免 N+1 查询
$cartIds=array_unique(array_filter(array_column($data2,'cartid')));
$cartMap=[];
if(!empty($cartIds)){
	$cartMap=Db::name('cart')->where('id','in',$cartIds)->column('name','id');
}
for($i=0;$i<count($data2);$i++){
	$cid=$data2[$i]['cartid'];
	$data2[$i]['cartid']=isset($cartMap[$cid])?$cartMap[$cid]:('产品#'.$cid);
}


	
$realnameRecords = Db::name('realname_record')
	->where('user_id', $id)
	->order('id desc')
	->select();
return $this->fetch('/'.$this->web["admintemplate"]."/userinfo",[
"userinfo"=>$data,
"userorder"=>$data2,
"userid"=>$id,
"realnameRecords"=>$realnameRecords,
]);

}
}else{
$this->redirect('/admin/user');
}


}
}



public function ticket($id=null){
if (!$this->checkPermission('ticket')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if($id){
$data=Db::name('ticket')->where("id",$id)->find();
if($data){
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="submit"){
$content=htmlspecialchars(trim(input("content")));
if(!csrf_verify(input('__token__'))) {
	$array["code"]="-1";
	$array["msg"]="安全验证失败，请刷新页面重试!";
	return json($array);
}
if($content==""){
	$array["code"]="-1";
	$array["msg"]="必填参数不可为空!";
}else{
$array1=array(
array(
"personnel"=>"1",
"content"=>$content,
"time"=>time(),
),
);
$array2=array_merge(json_decode($data["content"],true),$array1);
$data1=Db::name('ticket')->where([
"id"=>$id,
])->update([
'content' =>json_encode($array2),
'state'=>'3',
]);
if($data1){
			if($this->web["email"]=="1"){
			$user=Db::name('user')->where("id",$data["userid"])->find();
			if($user["mail"]){
			$mailbox=$this->email($user["mail"],"回复工单通知","站长在时间:".date("Y-m-d H:i:s")."已回复工单<br/>工单ID:".$id."<br/>标题:".$data["title"]."<br/>回复内容:<br/>".nl2br(htmlspecialchars($content))."<br/><br/>请登录查看完整对话:".request()->domain()."/user/supportticket/".$id);
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
if($data["state"]=="4"){
$array["code"]="-1";
$array["msg"]="工单已关闭!";
}else{
$data1=Db::name('ticket')->where([
"id"=>$id,
])->update([
'state'=>'4',
]);
if($data1){
if($this->web["email"]=="1"){
$user=Db::name('user')->where("id",$data["userid"])->find();
if($user["mail"]){
$mailbox=$this->email($user["mail"],"关闭工单通知","站长在时间:".date("Y-m-d H:i:s")."已关闭工单<br/>工单id:".$id."<br/><br/>");
}
}
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

$user=Db::name('user')->where('id',$data["userid"])->find();
$data["username"]=$user["name"];
$data["userqq"]=$user["qq"];
$data["content"]=json_decode($data["content"],true);
return $this->fetch('/'.$this->web["admintemplate"]."/tickets",[
"tickets"=>$data,
]);
}else{
$this->redirect('/admin/ticket');
}
}else{
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name("ticket")->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已经删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}


if($act=="off"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data2=Db::name('ticket')->where("id",$cid[$i])->find();
$data1=Db::name("ticket")->where("id",$cid[$i])->update([
"state"=>"4",
]);
if($this->web["email"]=="1"){
$data3=Db::name('user')->where("id",$data2["userid"])->find();
if($data3["mail"]){
$mailbox=$this->email($data3["mail"],"你的工单已被站长关闭","站长在时间:".date("Y-m-d H:i:s")."已关闭你的工单<br/>工单ID:".$data2["id"]."<br/><br/>");
}
}
if($data2["state"]!="4"){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已经关闭过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}

if(input("act")=="qbdelete"){
$data11=Db::name("ticket")->select();
$a="0";
$b="0";
for($i=0;$i<count($data11);$i++){
$data1=Db::name("ticket")->where("id",$data11[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过此条记录了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}
}
$search=input("search");
if($search){
$data=Db::name('ticket')->whereor("id", 'like', '%'.$search.'%')->whereor("userid", 'like', '%'.$search.'%')->whereor("title", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('ticket')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/ticket",[
"ticket"=>$data,
]);
}
}


public function classification($id=null){
if (!$this->checkPermission('classification')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if($id){
$data2=$data=Db::name('product')->where("id",$id)->find();
if($data2){
if(Request::instance()->isPost()) {
$name=input("name");
$introduce=input("introduce");
$hide=input("hide");
$sort=input("sort");
if($name==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$data3=Db::name('product')->where("id",$id)->update([
"name"=>$name,
"introduce"=>$introduce,
"hide"=>$hide,
"sort"=>$sort,
]);
if($data3){
$array["code"]="1";
$array["msg"]="修改成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}
}
return json($array);
}

return $this->fetch('/'.$this->web["admintemplate"]."/classifications",[
"product"=>$data2,
]);
}else{
$this->redirect('/admin/classification');
}

}else{
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="add"){
$name=input("name");
$introduce=input("introduce");
$hide=input("hide");
$sort=input("sort");
if($name==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$data1=Db::name('product')->insertGetId([
				"name"=>$name,
				"introduce"=>$introduce,
                "hide"=>$hide,
                "sort"=>$sort,
				]);
if($data1){
$array["code"]="1";
$array["msg"]="添加成功!";
}else{
$array["code"]="-1";
$array["msg"]="添加失败!";
}
}
return json($array);
}

if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name("product")->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已经删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}

if(input("act")=="qbdelete"){
$data11=Db::name("product")->select();
$a="0";
$b="0";
for($i=0;$i<count($data11);$i++){
$data1=Db::name("product")->where("id",$data11[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过此条记录了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}


}
$search=input("search");
if($search){
$data=Db::name('product')->whereor("id", 'like', '%'.$search.'%')->whereor("name", 'like', '%'.$search.'%')->whereor("introduce", 'like', '%'.$search.'%')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('product')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/classification",[
"product"=>$data,
]);
}
}


public function server($id=null){
if (!$this->checkPermission('server')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if($id){
$data2=$data=Db::name('server')->where("id",$id)->find();
if($data2){
if(Request::instance()->isPost()) {
$info=input("post.");
if($info["name"]==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$data3=Db::name('server')->where("id",$id)->update($info);
if($data3){
$array["code"]="1";
$array["msg"]="修改成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}
}
return json($array);
}

if(file_exists(PATH."plugins/host/".$data2["serverplugins"]."/".$data2["serverplugins"].".php")){
include_once PATH."plugins/host/".$data2["serverplugins"]."/".$data2["serverplugins"].".php";
$function=$data2["serverplugins"]."_"."AdminConfigOptions";
if(function_exists($function)){
$da4=@$function();

for($i=0;$i<count($da4);$i++){
foreach ($data2 as $key => $value){
if($da4[$i]["name"]==$key){
if($value!=""){
$da4[$i]["value"]=$value;
}
}
}
}
$data7=$da4;
}else{
$data7="";
}
}else{
$data7="";
}
return $this->fetch('/'.$this->web["admintemplate"]."/servers",[
"server"=>$data2,
"plugins"=>my_dir(PATH."/plugins/host"),
"data7"=>$data7,
]);
}else{
$this->redirect('/admin/server');
}

}else{
if(Request::instance()->isPost()) {
$act=input("act");

if($act=="add"){
$info=input("post.");
if($info["name"]==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
unset($info["act"]);
$data1=Db::name('server')->insertGetId($info);
if($data1){
$array["code"]="1";
$array["msg"]="添加成功!";
}else{
$array["code"]="-1";
$array["msg"]="添加失败!";
}
}
return json($array);
}

if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data4=Db::name('cart')->where("serverid",$cid[$i])->find();
if($data4){
$b=$b+1;
}else{
$data1=Db::name('server')->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了或者该服务器下还有未删除的产品!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}

if($act=="qbdelete"){
$data18=Db::name("server")->select();
$a="0";
$b="0";
for($i=0;$i<count($data18);$i++){
$data4=Db::name('cart')->where("serverid",$data18[$i]["id"])->find();
if($data4){
$b=$b+1;
}else{
$data1=Db::name('server')->where("id",$data18[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了或者该服务器下还有未删除的产品!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}
}
$search=input("search");
if($search){
$data=Db::name('server')->whereor("id", 'like', '%'.$search.'%')->whereor("name", 'like', '%'.$search.'%')->whereor("host", 'like', '%'.$search.'%')->whereor("ip", 'like', '%'.$search.'%')->whereor("security", 'like', '%'.$search.'%')->whereor("port", 'like', '%'.$search.'%')->whereor("user", 'like', '%'.$search.'%')->whereor("password", 'like', '%'.$search.'%')->whereor("serverplugins", 'like', '%'.$search.'%')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('server')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/server",[
"server"=>$data,
"plugins"=>my_dir(PATH."/plugins/host"),
]);
}
}

public function product($id=null){
if (!$this->checkPermission('product')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if($id){
$data1=Db::name('cart')->where("id",$id)->find();
if($data1){
if(Request::instance()->isPost()) {
$info=input("post.");
if(!is_numeric($info["inventory"]) || !is_numeric($info["money"])){
$array["code"]="-1";
$array["msg"]="库存或价格必须是数字!";
}else{
if(floor($info["inventory"])!=$info["inventory"]){
$array["code"]="-1";
$array["msg"]="库存必须是整数!";
}else{
$upgrades=@json_encode($info["upgrades"]);
if($upgrades=="null"){
$upgrades="";
}
$info["upgrades"]=$upgrades;
if($info["name"]==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if($info["serverid"]!=$data1["serverid"]){
$info["upgrade"]="0";
$info["upgrades"]="";
}
if($info["firstmo"]=="1" && $info["cycle"]=="unrestricted"){
$array["code"]="-1";
$array["msg"]="一次性产品不可设置首次购买免费!";
}else{
$data4=Db::name('cart')->where("id",$id)->update($info);
if($data4){
$array["code"]="1";
$array["msg"]="修改成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}
}
}
}
}
return json($array);
}
$data1["upgrades"]=json_decode($data1["upgrades"],true);
$data11=Db::name('cart')->where("serverid",$data1["serverid"])->select();


$data2=Db::name('product')->select();
$data3=Db::name('server')->select();


$da5=Db::name('server')->where("id",$data1["serverid"])->find();
// PHP 8 兼容：find() 可能返回 null，需先判断再访问数组下标
if(!empty($da5) && isset($da5["serverplugins"]) && file_exists(PATH."plugins/host/".$da5["serverplugins"]."/".$da5["serverplugins"].".php")){
include_once PATH."plugins/host/".$da5["serverplugins"]."/".$da5["serverplugins"].".php";
$function=$da5["serverplugins"]."_"."ConfigOptions";
if(function_exists($function)){
$da4=@$function();
for($i=0;$i<count($da4);$i++){
foreach ($data1 as $key => $value){
if($da4[$i]["name"]==$key){
if($value!=""){
$da4[$i]["value"]=$value;
}
}
}
}
$data7=$da4;
}else{
$data7="";
}
}else{
$data7="";
}
return $this->fetch('/'.$this->web["admintemplate"]."/products",[
"product"=>$data1,//产品信息
"upgrade"=>$data11,//全局升级产品数据
"data2"=>$data2,//全部分类数据
"data3"=>$data3,//全部服务器数据
"data7"=>$data7,
]);
}else{
$this->redirect('/admin/product');
}
}else{
if(Request::instance()->isPost()) {
$act=input("act");

if($act=="add"){
$info=input("post.");
if($info["name"]==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
if(!is_numeric($info["inventory"]) || !is_numeric($info["money"])){
$array["code"]="-1";
$array["msg"]="库存或价格必须是数字!";
}else{
unset($info["act"]);
$data1=Db::name('cart')->insertGetId($info);
if($data1){
$array["code"]="1";
$array["msg"]="添加成功!";
}else{
$array["code"]="-1";
$array["msg"]="添加失败!";
}
}
}
return json($array);
}

if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data4=Db::name('order')->where("cartid",$cid[$i])->find();
if($data4){
$b=$b+1;
}else{
$data1=Db::name('cart')->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了或者该产品下还有未删除的订单!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}


if($act=="qbdelete"){
$data12=Db::name("cart")->select();
$a="0";
$b="0";
for($i=0;$i<count($data12);$i++){
$data4=Db::name('order')->where("cartid",$data12[$i]["id"])->find();
if($data4){
$b=$b+1;
}else{
$data1=Db::name('cart')->where("id",$data12[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了或者该产品下还有未删除的订单!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}

}
$search=input("search");
if($search){
$data=Db::name('cart')->whereor("id", 'like', '%'.$search.'%')->whereor("name", 'like', '%'.$search.'%')->whereor("content", 'like', '%'.$search.'%')->whereor("money", 'like', '%'.$search.'%')->whereor("inventory", 'like', '%'.$search.'%')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('cart')->paginate(10);
}
$data2=Db::name('product')->select();
$data3=Db::name('server')->select();
return $this->fetch('/'.$this->web["admintemplate"]."/product",[
"product"=>$data,
"data2"=>$data2,
"data3"=>$data3,
]);
}
}


public function announcement($id=null){
if (!$this->checkPermission('announcement')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if($id){
$data1=Db::name('announcement')->where("id",$id)->find();
if($data1){
if(Request::instance()->isPost()) {
$info=input("post.");
if($info["name"]==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$info["time"]=strtotime($info["time"]);
if($info["time"]==""){
$info["time"]="1";
}
$data4=Db::name('announcement')->where("id",$id)->update($info);
if($data4){
$array["code"]="1";
$array["msg"]="修改成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}
}
return json($array);
}
return $this->fetch('/'.$this->web["admintemplate"]."/announcements",[
"announcement"=>$data1,
]);
}else{
$this->redirect('/admin/announcement');
}
}else{
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="add"){
$info=input("post.");
if($info["name"]==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
unset($info["act"]);
$info["time"]=time();
$data1=Db::name('announcement')->insertGetId($info);
if($data1){
$array["code"]="1";
$array["msg"]="添加成功!";
}else{
$array["code"]="-1";
$array["msg"]="添加失败!";
}
}
return json($array);
}
if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name("announcement")->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已经删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}

if(input("act")=="qbdelete"){
$data11=Db::name("announcement")->select();
$a="0";
$b="0";
for($i=0;$i<count($data11);$i++){
$data1=Db::name("announcement")->where("id",$data11[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过此条记录了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}
}
$search=input("search");
if($search){
$data=Db::name('announcement')->whereor("id", 'like', '%'.$search.'%')->whereor("name", 'like', '%'.$search.'%')->whereor("information", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('announcement')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/announcement",[
"announcement"=>$data,
]);
}
}


public function aff(){
if (!$this->checkPermission('aff')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="ok"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$data3=Db::name('afftxjl')->where("id",$cid)->find();
if($data3["state"]=="1"){
$array["code"]="-1";
$array["msg"]="已经处理过了!";
}else{
$data4=Db::name('afftxjl')->where("id",$cid)->update([
"state"=>"1",
]);
if($data4){
if($this->web["email"]=="1"){
$user=Db::name('user')->where("id",$data3["userid"])->find();
if($user["mail"]){
$mailbox=$this->email($user["mail"],"你的提现申请已处理","站长在时间:".date("Y-m-d H:i:s")."已处理你的提现申请<br/>提现记录ID:".$cid."<br/>请及时查看!<br/><br/>");
}
}
$array["code"]="1";
$array["msg"]="成功!";
}else{
$array["code"]="-1";
$array["msg"]="失败!";
}
}
}
return json($array);
}
if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name("afftxjl")->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已经删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}

if(input("act")=="qbdelete"){
$data11=Db::name("afftxjl")->select();
$a="0";
$b="0";
for($i=0;$i<count($data11);$i++){
$data1=Db::name("afftxjl")->where("id",$data11[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过此条记录了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}
}
$search=input("search");
if($search){
$data=Db::name('afftxjl')->whereor("id", 'like', '%'.$search.'%')->whereor("information", 'like', '%'.$search.'%')->whereor("money", 'like', '%'.$search.'%')->whereor("state", 'like', '%'.$search.'%')->whereor("userid", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('afftxjl')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/aff",[
"aff"=>$data,
]);
}

public function order($id=null){
if (!$this->checkPermission('order')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if($id){
$data1=Db::name('order')->where("id",$id)->find();
if($data1){
$da6=Db::name('cart')->where("id",$data1["cartid"])->find();
$da5=Db::name('server')->where("id",$da6["serverid"])->find();
include_once PATH."plugins/host/".$da5["serverplugins"]."/".$da5["serverplugins"].".php";
$function=$da5["serverplugins"]."_"."OrderConfigOptions";
if(function_exists($function)){
$da4=@$function();
for($i=0;$i<count($da4);$i++){
foreach ($data1 as $key => $value){
if($da4[$i]["name"]==$key){
if($value!=""){
$da4[$i]["value"]=$value;
}
}
}
}
$data7=$da4;
}else{
$data7="";
}

if(Request::instance()->isPost()) {
$act=input("act");


//暂停
if($act=="stop"){
if($data1["state"]=="3"){
	$array["code"]="-1";
	$array["msg"]="产品已终止,禁止修改此状态!";
}else{
// 优化：复用已加载的 $da6(cart) 和 $da5(server), 避免重复查询
$da1=$da6;
$da2=$da5;
include_once PATH."plugins/host/".$da2["serverplugins"]."/".$da2["serverplugins"].".php";
$function=$da2["serverplugins"]."_"."SuspendAccount";
if(function_exists($function)){
$da4=@$function($da2,$data1,$da1);
}
$da5=Db::name("order")->where("id",$data1["id"])->update([
"state"=>"2",
]);
	$array["code"]="1";
	$array["msg"]="暂停成功!";
}
return json($array);

}
//解除暂停
if($act=="stopoff"){
if($data1["state"]=="3"){
	$array["code"]="-1";
	$array["msg"]="产品已终止,禁止修改此状态!";
}else{
// 优化：复用已加载的 $da6(cart) 和 $da5(server), 避免重复查询
$da1=$da6;
$da2=$da5;
include_once PATH."plugins/host/".$da2["serverplugins"]."/".$da2["serverplugins"].".php";
$function=$da2["serverplugins"]."_"."UnsuspendAccount";
if(function_exists($function)){
$da3=@$function($da2,$data1,$da1);
}
$da5=Db::name("order")->where("id",$data1["id"])->update([
"state"=>"1",
]);
	$array["code"]="1";
	$array["msg"]="解除暂停成功!";
}
return json($array);

}
//终止
if($act=="end"){
// 优化：复用已加载的 $da6(cart) 和 $da5(server), 避免重复查询
$da1=$da6;
$da2=$da5;
include_once PATH."plugins/host/".$da2["serverplugins"]."/".$da2["serverplugins"].".php";
$function=$da2["serverplugins"]."_"."TerminateAccount";
if(function_exists($function)){
$da4=@$function($da2,$data1,$da1);
}
$da5=Db::name("order")->where("id",$data1["id"])->update([
"state"=>"3",
]);
$da6=Db::name("cart")->where("id",$data1["cartid"])->update([
"inventory"=>$da1["inventory"]+1,
]);
	$array["code"]="1";
	$array["msg"]="终止成功!";
return json($array);

}
//删除
if($act=="delete"){
$da5=Db::name("order")->where("id",$data1["id"])->delete();
	$array["code"]="1";
	$array["msg"]="删除成功!";
return json($array);
}

if($act=="edit"){
$post=input("post.");
$post["atime"]=strtotime($post["atime"]);
if($post["atime"]==""){
$post["atime"]="1";
}
$post["ztime"]=strtotime($post["ztime"]);
if($post["ztime"]==""){
$post["ztime"]="1";
}
unset($post["act"]);
$db=Db::name("order")->where([
"id"=>$id,
])->update($post);
if($db){
	$array["code"]="1";
	$array["msg"]="修改成功!";
}else{
	$array["code"]="-1";
	$array["msg"]="修改失败!";
}
return json($array);
}
// 手动开通待开通订单
if($act=="createhost"){
if($data1["state"]!="0"){
	$array["code"]="-1";
	$array["msg"]="该订单不是待开通状态!";
	return json($array);
}
$cart=$da6;
$server=$da5;
// 如果订单账号密码为空，自动生成随机凭据
$hostUser = $data1["user"];
$hostPass = $data1["password"];
if(empty($hostUser) || strlen($hostUser) < 3){
	$hostUser = 'user' . rand(10000, 99999);
}
if(empty($hostPass) || strlen($hostPass) < 6){
	$hostPass = substr(md5(uniqid(mt_rand(), true)), 0, 12);
}
// 将生成的凭据更新到订单
if($hostUser != $data1["user"] || $hostPass != $data1["password"]){
	Db::name('order')->where('id', $data1["id"])->update([
		"user" => $hostUser,
		"password" => $hostPass,
	]);
	$data1["user"] = $hostUser;
	$data1["password"] = $hostPass;
}
$times=intval($data1["ztime"])-intval($data1["atime"]);
if($times<0){ $times=0; }
$cycleTime=1;
if($cart["cycle"]=="month") $cycleTime=2592000;
elseif($cart["cycle"]=="season") $cycleTime=7879680;
elseif($cart["cycle"]=="year") $cycleTime=31536000;
elseif($cart["cycle"]=="day") $cycleTime=86400;
elseif($cart["cycle"]=="unrestricted") $cycleTime=3153600000;
$buyTime=($cycleTime>0) ? intval($times/$cycleTime) : 1;
if($buyTime<1){ $buyTime=1; }
$pluginFile=PATH."plugins/host/".$server["serverplugins"]."/".$server["serverplugins"].".php";
if(!file_exists($pluginFile)){
	$array["code"]="-1";
	$array["msg"]="插件文件不存在!";
	return json($array);
}
include_once $pluginFile;
$function=$server["serverplugins"]."_CreateAccount";
if(!function_exists($function)){
	$array["code"]="-1";
	$array["msg"]="未实现开通接口!";
	return json($array);
}
$result=@$function($server, ["user"=>$data1["user"],"password"=>$data1["password"],"time"=>$buyTime], $cart, $times, $data1["id"]);
if(is_array($result) && isset($result["code"]) && $result["code"]=="1"){
	$array["code"]="1";
	$array["msg"]="开通成功！账号:".$data1["user"]."，密码:".$data1["password"];
	$array["id"]=$data1["id"];
}else{
	$array["code"]="-1";
	$array["msg"]="开通失败：".($result["msg"] ?? '未知错误');
}
return json($array);
}
}

return $this->fetch('/'.$this->web["admintemplate"]."/orders",[
"order"=>$data1,
"data7"=>$data7,
]);
}else{
$this->redirect('/admin/order');
}
}else{
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name('order')->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}


if($act=="qbdelete"){
$data12=Db::name("order")->select();
$a="0";
$b="0";
for($i=0;$i<count($data12);$i++){
$data1=Db::name('order')->where("id",$data12[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}

}
$search=input("search");
if($search){
$data=Db::name('order')->whereor("id", 'like', '%'.$search.'%')->whereor("user", 'like', '%'.$search.'%')->whereor("password", 'like', '%'.$search.'%')->whereor("userid", 'like', '%'.$search.'%')->whereor("cartid", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('order')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/order",[
"order"=>$data,
]);
}
}


public function pay(){
if (!$this->checkPermission('pay')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name('pay')->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}


if($act=="qbdelete"){
$data12=Db::name("pay")->select();
$a="0";
$b="0";
for($i=0;$i<count($data12);$i++){
$data1=Db::name('pay')->where("id",$data12[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}
}
$search=input("search");
if($search){
$data=Db::name('pay')->whereor("id", 'like', '%'.$search.'%')->whereor("name", 'like', '%'.$search.'%')->whereor("ordernumber", 'like', '%'.$search.'%')->whereor("pay", 'like', '%'.$search.'%')->whereor("money", 'like', '%'.$search.'%')->whereor("userid", 'like', '%'.$search.'%')->whereor("state", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('pay')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/pay",[
"pay"=>$data,
]);
}

public function templateset(){
if (!$this->checkPermission('set')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if(Request::instance()->isPost()) {
$input=input("post.");
if($input){
$wj=include_once(PATH."/app/index/view/".$this->web["template"]."/set.php");
for($i=0;$i<count($wj);$i++){
foreach ($input as $key => $value){
if($wj[$i]["name"]==$key){
$wj[$i]["value"]=$value;
}
}
}
$data=Db::name('web')->where('id',"1")->update([
"templateset"=>json_encode($wj),
]);
if($data){
$array["code"]="1";
$array["msg"]="修改成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}
}else{
$array["code"]="-1";
$array["msg"]="没有数据!";
}
return json($array);
}
$tyyy=@json_decode($this->web['templateset'],true);
if($tyyy=="null"){
$tyyy="";
}
return $this->fetch('/'.$this->web["admintemplate"]."/templateset",[
"tempset"=>$tyyy,
]);
}

public function transferrecord(){
if (!$this->checkPermission('aff')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name('transferrecord')->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}


if($act=="qbdelete"){
$data12=Db::name("transferrecord")->select();
$a="0";
$b="0";
for($i=0;$i<count($data12);$i++){
$data1=Db::name('transferrecord')->where("id",$data12[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}
}
$search=input("search");
if($search){
$data=Db::name('transferrecord')->whereor("id", 'like', '%'.$search.'%')->whereor("userid", 'like', '%'.$search.'%')->whereor("record", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('transferrecord')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"].'/transferrecord',[
"data"=>$data,
]);
}

public function transferHostRecord(){
	ensure_host_transfer_table();
	if (!$this->checkPermission('aff')) {
		$this->error('您没有权限访问此页面', '/admin/index');
	}
	if(Request::instance()->isPost()) {
		$act=input("act");
		if($act=="reject"){
			$id=intval(input("id"));
			$reason=trim(input("reason"));
			if($id<=0){
				return json(['code'=>'-1','msg'=>'参数错误']);
			}
			if(empty($reason)){
				return json(['code'=>'-1','msg'=>'请填写驳回原因']);
			}
			$transfer=Db::name('host_transfer')->where('id',$id)->where('status','0')->find();
			if(!$transfer){
				return json(['code'=>'-1','msg'=>'该转让记录不存在或已处理']);
			}
			Db::name('host_transfer')->where('id',$id)->update([
				'status'=>2,
				'reject_reason'=>$reason,
				'updated_at'=>time(),
			]);
			return json(['code'=>'1','msg'=>'已成功驳回该转让']);
		}
	}
	// 修复：使用正确的表名前缀
	$userTable = Db::name('user')->getTable();
	$search=input("search");
	if($search){
		$data=Db::name('host_transfer')
			->alias('t')
			->join($userTable.' s','t.userid=s.id','LEFT')
			->join($userTable.' b','t.buyer_userid=b.id','LEFT')
			->join($userTable.' tg','t.target_userid=tg.id','LEFT')
			->where(function($q) use($search){
				$q->where('t.order_id','like','%'.$search.'%')
				  ->whereOr('s.user','like','%'.$search.'%')
				  ->whereOr('b.user','like','%'.$search.'%');
			})
			->field('t.*, s.user as seller_name, b.user as buyer_name, tg.user as target_name')
			->order('t.id desc')
			->paginate(10,false,['query'=>request()->param()]);
	}else{
		$data=Db::name('host_transfer')
			->alias('t')
			->join($userTable.' s','t.userid=s.id','LEFT')
			->join($userTable.' b','t.buyer_userid=b.id','LEFT')
			->join($userTable.' tg','t.target_userid=tg.id','LEFT')
			->field('t.*, s.user as seller_name, b.user as buyer_name, tg.user as target_name')
			->order('t.id desc')
			->paginate(10);
	}
	return $this->fetch('/'.$this->web["admintemplate"].'/transfer_host_record',[
		"data"=>$data,
		"search"=>$search,
	]);
}

public function transaction(){
if (!$this->checkPermission('aff')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name('transaction')->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}


if($act=="qbdelete"){
$data12=Db::name("transaction")->select();
$a="0";
$b="0";
for($i=0;$i<count($data12);$i++){
$data1=Db::name('transaction')->where("id",$data12[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}

}

$search=input("search");
if($search){
$data=Db::name('transaction')->whereor("id", 'like', '%'.$search.'%')->whereor("userid", 'like', '%'.$search.'%')->whereor("content", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('transaction')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"].'/transaction',[
"data"=>$data,
]);
}



public function pays($id=null){
if (!$this->checkPermission('pays')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if($id){
$data1=Db::name('pays')->where("id",$id)->find();
if($data1){
if(Request::instance()->isPost()) {
$input=input("post.");
if($input){
if(file_exists(PATH."/plugins/pay/".$data1["plugins"]."/set.php")){
$wj=include_once(PATH."/plugins/pay/".$data1["plugins"]."/set.php");
for($i=0;$i<count($wj);$i++){
foreach ($input as $key => $value){
if($wj[$i]["name"]==$key){
$wj[$i]["value"]=$value;
}
}
}
$sj=json_encode($wj);
}else{
$sj="";
}
$data=Db::name('pays')->where('id',$id)->update([
"name"=>$input["nname"],
"state"=>$input["nstate"],
"data"=>$sj,
]);
if($data){
$array["code"]="1";
$array["msg"]="修改成功!";
}else{
$array["code"]="-1";
$array["msg"]="修改失败!";
}
}else{
$array["code"]="-1";
$array["msg"]="没有数据!";
}
return json($array);
}
$tyyy=@json_decode($data1["data"],true);
if($tyyy=="null"){
$tyyy="";
}
return $this->fetch('/'.$this->web["admintemplate"]."/payss",[
"nname"=>$data1["name"],
"nstate"=>$data1["state"],
"payss"=>$tyyy,
]);
}else{
$this->redirect('/admin/pays');
}
}else{
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="add"){
$info=input("post.");
if($info["name"]=="" || !$info["plugins"]){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
unset($info["act"]);
if(file_exists(PATH."/plugins/pay/".$info["plugins"]."/set.php")){
$wj=include_once(PATH."/plugins/pay/".$info["plugins"]."/set.php");
$info["data"]=json_encode($wj);
}else{
$info["data"]="";
}
$data1=Db::name('pays')->insertGetId($info);
if($data1){
$array["code"]="1";
$array["msg"]="添加成功!";
}else{
$array["code"]="-1";
$array["msg"]="添加失败!";
}
}
return json($array);
}





if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name("pays")->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已经删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}

if(input("act")=="qbdelete"){
$data11=Db::name("pays")->select();
$a="0";
$b="0";
for($i=0;$i<count($data11);$i++){
$data1=Db::name("pays")->where("id",$data11[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过此条通道了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}
}
$search=input("search");
if($search){
$data=Db::name('pays')->whereor("id", 'like', '%'.$search.'%')->whereor("name", 'like', '%'.$search.'%')->whereor("plugins", 'like', '%'.$search.'%')->whereor("state", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('pays')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/pays",[
"pays"=>$data,
"payss"=>my_dir(PATH."/plugins/pay"), 
]);
}
}


public function affsy(){
if (!$this->checkPermission('aff')) {
    $this->error('您没有权限访问此页面', '/admin/index');
}
if(Request::instance()->isPost()) {
$act=input("act");
if($act=="delete"){
$cid=input("cid");
if($cid==""){
$array["code"]="-1";
$array["msg"]="必填参数不可为空!";
}else{
$cid=explode(",",input("cid"));
$a="0";
$b="0";
for($i=0;$i<count($cid);$i++){
$data1=Db::name("affsymoney")->where("id",$cid[$i])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已经删除过了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
}
return json($array);
}

if(input("act")=="qbdelete"){
$data11=Db::name("affsymoney")->select();
$a="0";
$b="0";
for($i=0;$i<count($data11);$i++){
$data1=Db::name("affsymoney")->where("id",$data11[$i]["id"])->delete();
if($data1){
$a=$a+1;
}else{
$b=$b+1;
}
}
$c="";
if($b>0){
$c="<br/>失败原因:已删除过此条记录了!";
}
$array["code"]="1";
$array["msg"]="成功:".$a.";失败:".$b.$c;
return json($array);
}

}
$search=input("search");
if($search){
$data=Db::name('affsymoney')->whereor("id", 'like', '%'.$search.'%')->whereor("information", 'like', '%'.$search.'%')->whereor("money", 'like', '%'.$search.'%')->whereor("userid", 'like', '%'.$search.'%')->order('id desc')->paginate(10,false,['query'=>request()->param()]);
}else{
$data=Db::name('affsymoney')->order('id desc')->paginate(10);
}
return $this->fetch('/'.$this->web["admintemplate"]."/affsy",[
"affsy"=>$data,
]);
}

//发送邮箱
		public static function email($email,$name,$body)
		{
if (!rate_limit('email_send_' . $email, 3, 60)) { return ['code'=>'-1','msg'=>'发送频率过快，请稍后再试']; }
$body = sanitize_email_body($body);
$web=web_config();
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

// 卡密管理
public function cdkey() {
	if (!$this->checkPermission('set')) {
		$this->error('您没有权限访问此页面', '/admin/index');
	}
	ensure_cdkey_table();
	ensure_cdkey_usage_log_table();
	if(Request::instance()->isPost()) {
		$act = input('act');
		$array = ['code' => '-1', 'msg' => ''];

		// 批量添加卡密
		if($act == 'add') {
			$type = input('type', 'balance');
			$count = intval(input('count', 1));
			$money = floatval(input('money', 0));
			$cartid = intval(input('cartid', 0));
			$remark = trim(input('remark', ''));
			$repeatable = intval(input('repeatable', 0));
			$restrict_type = input('restrict_type', 'all');
			$restrict_users = trim(input('restrict_users', ''));
			if($count < 1) $count = 1;
			if($count > 500) { $array['msg'] = '单次最多生成500个卡密'; return json($array); }
			if($type == 'balance' && $money <= 0) { $array['msg'] = '余额充值金额必须大于0'; return json($array); }
			if($type == 'host' && $cartid <= 0) { $array['msg'] = '请选择关联产品'; return json($array); }
			$prefix = 'CD' . strtoupper(substr(md5(time().mt_rand()), 0, 4));
			$insertData = [];
			$now = time();
			for($i = 0; $i < $count; $i++) {
				$key = $prefix . strtoupper(substr(md5(mt_rand().uniqid()), 0, 16));
				$insertData[] = [
					'cdkey' => $key,
					'type' => $type,
					'money' => $money,
					'cartid' => $cartid,
					'status' => 0,
					'created_at' => $now,
					'remark' => $remark,
					'repeatable' => $repeatable,
					'restrict_type' => $restrict_type,
					'restrict_users' => $restrict_users,
				];
			}
			Db::name('cdkey')->insertAll($insertData);
			$array['code'] = '1';
			$array['msg'] = "成功生成 {$count} 个卡密";
			return json($array);
		}

		// 追缴（停用）卡密
		if($act == 'disable') {
			$ids = input('ids', '');
			if(empty($ids)) { $array['msg'] = '请选择要追缴的卡密'; return json($array); }
			$idArr = explode(',', $ids);
			Db::name('cdkey')->where('id', 'in', $idArr)->where('status', '0')->update(['status' => 2]);
			$array['code'] = '1';
			$array['msg'] = '已追缴所选卡密';
			return json($array);
		}

		// 回收（重新启用）卡密
		if($act == 'enable') {
			$ids = input('ids', '');
			if(empty($ids)) { $array['msg'] = '请选择要回收的卡密'; return json($array); }
			$idArr = explode(',', $ids);
			Db::name('cdkey')->where('id', 'in', $idArr)->where('status', '2')->update(['status' => 0]);
			$array['code'] = '1';
			$array['msg'] = '已回收所选卡密，可重新使用';
			return json($array);
		}

		// 删除卡密
		if($act == 'delete') {
			$ids = input('ids', '');
			if(empty($ids)) { $array['msg'] = '请选择要删除的卡密'; return json($array); }
			$idArr = explode(',', $ids);
			Db::name('cdkey')->where('id', 'in', $idArr)->delete();
			$array['code'] = '1';
			$array['msg'] = '已删除所选卡密';
		return json($array);
	}

	// 导出卡密
	if($act == 'export') {
		$expType = input('type', 'all');
		$expStatus = input('status', '');
		$expKeyword = input('keyword', '');
		$expMap = [];
		if($expType != 'all' && $expType != '') $expMap['type'] = $expType;
		if($expStatus !== '' && $expStatus !== 'all') $expMap['status'] = intval($expStatus);
		if($expKeyword) $expMap['cdkey'] = ['like', "%{$expKeyword}%"];
		$expList = Db::name('cdkey')->where($expMap)->order('id desc')->limit(5000)->column('cdkey');
		if (empty($expList)) {
			$array['msg'] = '没有可导出的卡密';
			return json($array);
		}
		$array['code'] = '1';
		$array['cdkeys'] = $expList;
		return json($array);
	}

	return json($array);
}
$type = input('type', 'all');
	$status = input('status', '');
	$keyword = input('keyword', '');
	$map = [];
	if($type != 'all') $map['type'] = $type;
	if($status !== '' && $status !== 'all') $map['status'] = intval($status);
	if($keyword) $map['cdkey'] = ['like', "%{$keyword}%"];
	$list = Db::name('cdkey')->where($map)->order('id desc')->paginate(15);
	// 为 once_per_user 类型卡密附加使用次数统计
	$oncePerUserCdkeys = [];
	foreach ($list as $item) {
		if (isset($item['restrict_type']) && $item['restrict_type'] == 'once_per_user') {
			$oncePerUserCdkeys[] = $item['cdkey'];
		}
	}
	$usageCounts = [];
	if (!empty($oncePerUserCdkeys)) {
		$countResult = Db::name('cdkey_usage_log')->where('cdkey', 'in', $oncePerUserCdkeys)->group('cdkey')->column('count(*) as cnt', 'cdkey');
		if ($countResult) {
			foreach ($countResult as $ck => $cnt) {
				$usageCounts[$ck] = $cnt;
			}
		}
	}
	foreach ($list as &$item) {
		if (isset($item['restrict_type']) && $item['restrict_type'] == 'once_per_user') {
			$item['usage_count'] = isset($usageCounts[$item['cdkey']]) ? intval($usageCounts[$item['cdkey']]) : 0;
		}
	}
	unset($item);
	$cartList = Db::name('cart')->where('hide', '0')->field('id,name')->order('id desc')->select();
	return $this->fetch('/'.$this->web["admintemplate"].'/cdkey', [
		'list' => $list,
		'cartList' => $cartList,
		'type' => $type,
		'status' => $status,
		'keyword' => $keyword,
	]);
}

// ========== 管理员登录日志 ==========
public function loginLog() {
	if (!$this->checkPermission('set') && !$this->user['is_super']) {
		$this->error('您没有权限访问此页面', '/admin/index');
	}
	// 确保表存在
	try {
		$tableName = Db::name('admin_login_log')->getTable();
		Db::execute("CREATE TABLE IF NOT EXISTS `{$tableName}` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`admin_id` int(11) DEFAULT 0 COMMENT '管理员ID',
			`username` varchar(50) DEFAULT '' COMMENT '登录用户名',
			`ip` varchar(50) DEFAULT '' COMMENT '登录IP',
			`status` tinyint(1) DEFAULT 0 COMMENT '1=成功 0=失败',
			`msg` varchar(255) DEFAULT '' COMMENT '备注',
			`create_time` int(11) DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `idx_admin_id` (`admin_id`),
			KEY `idx_create_time` (`create_time`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	} catch (\Exception $e) {}
	$search = input('search', '');
	if($search) {
		$data = Db::name('admin_login_log')
			->where('username', 'like', '%'.$search.'%')
			->whereOr('ip', 'like', '%'.$search.'%')
			->order('id desc')->paginate(15, false, ['query' => request()->param()]);
	} else {
		$data = Db::name('admin_login_log')->order('id desc')->paginate(15);
	}
	return $this->fetch('/'.$this->web["admintemplate"].'/login_log', [
		'logs' => $data,
		'search' => $search,
	]);
}

// 操作日志
public function opLog() {
	if (!$this->checkPermission('op_log') && !$this->user['is_super']) {
		$this->error('您没有权限访问此页面', '/admin/index');
	}
	$action = input('action', '');
	$search = input('search', '');
	$query = Db::name('admin_op_log')->order('id desc');
	if ($action) {
		$query->where('action', $action);
	}
	if ($search) {
		$query->where('admin_name|target|ip', 'like', '%' . $search . '%');
	}
	$data = $query->paginate(15, false, ['query' => request()->param()]);
	return $this->fetch('/'.$this->web["admintemplate"].'/op_log', [
		'logs' => $data,
		'action' => $action,
		'search' => $search,
	]);
}

// ========== 违规用户通报系统 ==========
public function violation($id = null) {
	if (!$this->checkPermission('user')) {
		$this->error('您没有权限访问此页面', '/admin/index');
	}
	// 确保表存在
	try {
		$tableName = Db::name('violation')->getTable();
		Db::execute("CREATE TABLE IF NOT EXISTS `{$tableName}` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`user_id` int(11) DEFAULT 0 COMMENT '用户ID',
			`username` varchar(100) DEFAULT '' COMMENT '用户名',
			`title` varchar(255) DEFAULT '' COMMENT '违规标题',
			`content` text COMMENT '违规内容描述',
			`reason` text COMMENT '处罚原因',
			`punishment` varchar(255) DEFAULT '' COMMENT '处罚措施',
			`images` text COMMENT '证据图片',
			`status` tinyint(1) DEFAULT 1 COMMENT '1=公示 0=隐藏',
			`create_time` int(11) DEFAULT 0,
			`update_time` int(11) DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `idx_user_id` (`user_id`),
			KEY `idx_status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	} catch (\Exception $e) {}

	// 统一处理所有 POST 请求（upload_image 列表页和编辑页通用）
	if(Request::instance()->isPost()) {
		$act = input('act');
		$array = ['code' => '-1', 'msg' => ''];

		// CSRF 验证（图片上传使用 FormData，单独验证）
		if($act != 'upload_image') {
			$csrf = input('csrf_token', '');
			if(!csrf_verify($csrf)) {
				$array['msg'] = '安全验证失败，请刷新页面重试';
				return json($array);
			}
		}

		// 图片上传（列表页和编辑页通用）
		if($act == 'upload_image') {
			try {
				$file = request()->file('file');
				if(!$file) { $array['msg'] = '请选择文件'; return json($array); }
				// 确保上传目录存在
				$uploadDir = PATH . 'public/uploads/violation/';
				if(!is_dir($uploadDir)) {
					@mkdir($uploadDir, 0755, true);
				}
				// 如果子目录创建失败，回退到 uploads 根目录
				if(!is_dir($uploadDir) || !is_writable($uploadDir)) {
					$uploadDir = PATH . 'public/uploads/';
				}
				// 安全校验：扩展名 + MIME 类型双重验证
				$info = $file->validate([
					'size' => 5242880, // 5MB
					'ext'  => 'jpg,jpeg,png,webp',
				])->move($uploadDir);
				if(!$info) {
					$array['msg'] = $file->getError() ?: '文件上传失败';
					return json($array);
				}
				// 二次校验：真实 MIME 类型
				$realPath = $info->getRealPath();
				if(function_exists('finfo_open')) {
					$finfo = finfo_open(FILEINFO_MIME_TYPE);
					$mime = finfo_file($finfo, $realPath);
					finfo_close($finfo);
					$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
					if(!in_array($mime, $allowedMimes)) {
						@unlink($realPath);
						$array['msg'] = '文件类型不允许（仅支持 JPG/PNG/WebP）';
						return json($array);
					}
				}
				// 构建 URL：如果回退到了 uploads 根目录，URL 前缀相应调整
				$saveName = $info->getSaveName();
				$url = (strpos($uploadDir, 'violation') !== false) 
					? '/uploads/violation/' . $saveName 
					: '/uploads/' . $saveName;
				$array['code'] = '1';
				$array['url'] = $url;
				$array['msg'] = '上传成功';
				return json($array);
			} catch (\Exception $e) {
				$array['msg'] = '上传异常：' . $e->getMessage();
				return json($array);
			}
		}

		// 编辑页 POST
		if($id && $act == 'edit') {
			$data = Db::name('violation')->where('id', $id)->find();
			if(!$data) { $array['msg'] = '记录不存在'; return json($array); }
			$title = input('title', '');
			$content = input('content', '');
			$reason = input('reason', '');
			$punishment = input('punishment', '');
			$status = input('status', 1);
			$images = input('images', '');
			if(empty($title)) { $array['msg'] = '标题不能为空'; return json($array); }
			Db::name('violation')->where('id', $id)->update([
				'title' => xss_clean($title),
				'content' => $content,
				'reason' => $reason,
				'punishment' => xss_clean($punishment),
				'images' => $images,
				'status' => $status,
				'update_time' => time(),
			]);
			security_log('violation_edit', "Admin edited violation #{$id} title: {$title}");
			$array['code'] = '1';
			$array['msg'] = '修改成功';
			return json($array);
		}

		// 列表页 POST
		if(!$id) {
			if($act == 'add') {
				$userId = input('user_id', 0);
				$username = input('username', '');
				$title = input('title', '');
				$content = input('content', '');
				$reason = input('reason', '');
				$punishment = input('punishment', '');
				$images = input('images', '');
				if(empty($title)) { $array['msg'] = '标题不能为空'; return json($array); }
				Db::name('violation')->insert([
					'user_id' => $userId,
					'username' => xss_clean($username),
					'title' => xss_clean($title),
					'content' => $content,
					'reason' => $reason,
					'punishment' => xss_clean($punishment),
					'images' => $images,
					'status' => 1,
					'create_time' => time(),
					'update_time' => time(),
				]);
				security_log('violation_add', "Admin added violation for user {$username} title: {$title}");
				$array['code'] = '1';
				$array['msg'] = '添加成功';
				return json($array);
			}
			if($act == 'delete') {
				$ids = input('ids', '');
				if(empty($ids)) { $array['msg'] = '请选择记录'; return json($array); }
				$idArr = explode(',', $ids);
				Db::name('violation')->where('id', 'in', $idArr)->delete();
				security_log('violation_delete', "Admin deleted violations: {$ids}");
				$array['code'] = '1';
				$array['msg'] = '删除成功';
				return json($array);
			}
			if($act == 'toggle') {
				$vid = input('id', 0);
				$v = Db::name('violation')->where('id', $vid)->find();
				if(!$v) { $array['msg'] = '记录不存在'; return json($array); }
				$newStatus = $v['status'] == 1 ? 0 : 1;
				Db::name('violation')->where('id', $vid)->update(['status' => $newStatus, 'update_time' => time()]);
				$array['code'] = '1';
				$array['msg'] = $newStatus ? '已公示' : '已隐藏';
				return json($array);
			}
		}

		return json($array);
	}

	// GET 请求：编辑页
	if($id) {
		$data = Db::name('violation')->where('id', $id)->find();
		if(!$data) {
			$this->error('记录不存在', '/admin/violation');
		}
		return $this->fetch('/'.$this->web["admintemplate"].'/violation_edit', [
			'v' => $data,
			'csrf_token' => csrf_token(),
		]);
	}

	// GET 请求：列表页
	$search = input('search', '');
	if($search) {
		$data = Db::name('violation')
			->where('title', 'like', '%'.$search.'%')
			->whereOr('username', 'like', '%'.$search.'%')
			->order('id desc')->paginate(15, false, ['query' => request()->param()]);
	} else {
		$data = Db::name('violation')->order('id desc')->paginate(15);
	}
	return $this->fetch('/'.$this->web["admintemplate"].'/violation', [
		'list' => $data,
		'search' => $search,
		'csrf_token' => csrf_token(),
	]);
}

// ========== 邮件审核实名认证 ==========
public function emailAudit() {
	$token = input('token', '');
	$action = input('action', ''); // approve / reject
	$id = input('id', 0);
	if(empty($token) || empty($action) || !$id) {
		return '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>审核失败</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8fafc;}.box{text-align:center;background:#fff;padding:48px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.08);}h2{color:#dc2626;}</style></head><body><div class="box"><h2>审核链接无效</h2><p>参数不完整</p></div></body></html>';
	}
	// 验证 token（用 user_id + realname_status + 密钥 的简单签名）
	$user = Db::name('user')->where('id', $id)->where('realname_status', '3')->find();
	if(!$user) {
		return '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>审核失败</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8fafc;}.box{text-align:center;background:#fff;padding:48px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.08);}h2{color:#dc2626;}</style></head><body><div class="box"><h2>审核失败</h2><p>该用户不存在或已审核</p></div></body></html>';
	}
	$expectedToken = md5($id . $user['realname'] . 'email_audit_salt_2024');
	if($token !== $expectedToken) {
		return '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>审核失败</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8fafc;}.box{text-align:center;background:#fff;padding:48px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.08);}h2{color:#dc2626;}</style></head><body><div class="box"><h2>审核失败</h2><p>Token验证失败</p></div></body></html>';
	}
	if($action == 'approve') {
		Db::name('user')->where('id', $id)->update(['realname_status' => 1]);
		$this->updateRealnameRecord($id, 1, 0, '邮件审核');
		// 通知用户
		if($this->web["email"]=="1" && !empty($user["mail"])){
			$realname = $user['realname'] ?: $user['name'];
			try { self::email($user["mail"], "实名认证通过通知", '<p>您好 '.htmlspecialchars($realname).'，</p><p>恭喜！您的实名认证已审核通过。</p>'); } catch (\Exception $e) {}
		}
		return '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>审核成功</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8fafc;}.box{text-align:center;background:#fff;padding:48px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.08);}.icon{font-size:48px;color:#059669;margin-bottom:16px;}h2{color:#0f172a;}</style></head><body><div class="box"><div class="icon">✓</div><h2>已通过实名认证</h2><p>用户 '.htmlspecialchars($user['realname'] ?: $user['name']).' 的实名认证已审核通过</p></div></body></html>';
	} elseif($action == 'reject') {
		Db::name('user')->where('id', $id)->update(['realname_status' => 2]);
		$this->updateRealnameRecord($id, 2, 0, '邮件审核');
		return '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>审核成功</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f8fafc;}.box{text-align:center;background:#fff;padding:48px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.08);}.icon{font-size:48px;color:#f59e0b;margin-bottom:16px;}h2{color:#0f172a;}</style></head><body><div class="box"><div class="icon">✕</div><h2>已驳回实名认证</h2><p>用户 '.htmlspecialchars($user['realname'] ?: $user['name']).' 的实名认证已驳回</p></div></body></html>';
	}
	return '<html><head><meta charset="utf-8"><title>错误</title></head><body><h2>未知操作</h2></body></html>';
}

// ========== 公告管理（新） ==========
public function announcements($id = null) {
	if (!$this->checkPermission('announcement')) {
		$this->error('您没有权限访问此页面', '/admin/index');
	}
	ensure_announcements_table();

	$request = Request::instance();

	// 统一处理所有 POST 请求（增删改查操作）
	// 使用 request()->post() 直接读取 $_POST，避免 input() 走 param() 合并路由参数的问题
	if ($request->isPost()) {
		$act  = $request->post('act');
		$array = ['code' => '-1', 'msg' => ''];

		if ($act == 'add') {
			$title       = trim($request->post('title', ''));
			$content     = $request->post('content', '');
			$notice_type = $request->post('notice_type', 'silent');
			$status      = intval($request->post('status', 1));
			$send_email  = intval($request->post('send_email', 0));
			if (empty($title)) { $array['msg'] = '标题不能为空'; return json($array); }
			Db::name('announcements')->insert([
				'title'       => $title,
				'content'     => $content,
				'notice_type' => $notice_type,
				'status'      => $status,
				'send_email'  => $send_email,
				'created_at'  => time(),
				'updated_at'  => time(),
			]);
			$array['code'] = '1';
			$array['msg']  = '发布成功';
			return json($array);
		}

		if ($act == 'edit') {
			$editId = intval($request->post('id', 0));
			if (!$editId) { $array['msg'] = '参数错误'; return json($array); }
			$title       = trim($request->post('title', ''));
			$content     = $request->post('content', '');
			$notice_type = $request->post('notice_type', 'silent');
			$status      = intval($request->post('status', 1));
			$send_email  = intval($request->post('send_email', 0));
			if (empty($title)) { $array['msg'] = '标题不能为空'; return json($array); }
			Db::name('announcements')->where('id', $editId)->update([
				'title'       => $title,
				'content'     => $content,
				'notice_type' => $notice_type,
				'status'      => $status,
				'send_email'  => $send_email,
				'updated_at'  => time(),
			]);
			$array['code'] = '1';
			$array['msg']  = '修改成功';
			return json($array);
		}

		if ($act == 'delete') {
			$delId = intval($request->post('id', 0));
			$ids   = $request->post('ids', '');
			if (empty($ids) && $delId) $ids = (string)$delId;
			if (empty($ids)) { $array['msg'] = '请选择公告'; return json($array); }
			$idArr = explode(',', $ids);
			Db::name('announcements')->where('id', 'in', $idArr)->delete();
			$array['code'] = '1';
			$array['msg']  = '删除成功';
			return json($array);
		}

		if ($act == 'toggle') {
			$toggleId = intval($request->post('id', 0));
			if (!$toggleId) { $array['msg'] = '参数错误'; return json($array); }
			$row = Db::name('announcements')->where('id', $toggleId)->find();
			if (!$row) { $array['msg'] = '公告不存在'; return json($array); }
			$newStatus = $row['status'] == 1 ? 0 : 1;
			Db::name('announcements')->where('id', $toggleId)->update(['status' => $newStatus, 'updated_at' => time()]);
			$array['code'] = '1';
			$array['msg']  = $newStatus ? '已显示' : '已隐藏';
			return json($array);
		}

		$array['msg'] = '未知操作';
		return json($array);
	}

	// GET 请求带 id 参数：返回 JSON 供编辑弹窗加载
	// 使用 request()->get('id') 直接读取 $_GET，不依赖 input()/isAjax()
	$ajaxId = intval($id ?: $request->get('id'));
	if ($ajaxId && $request->isGet()) {
		$data = Db::name('announcements')->where('id', $ajaxId)->find();
		if (!$data) {
			return json(['code' => '-1', 'msg' => '公告不存在']);
		}
		return json(['code' => '1', 'data' => $data]);
	}

	// 列表页
	$search = $request->get('search', '');
	if ($search) {
		$list = Db::name('announcements')
			->where('title', 'like', '%'.$search.'%')
			->order('id desc')
			->paginate(10, false, ['query' => request()->param()]);
	} else {
		$list = Db::name('announcements')->order('id desc')->paginate(10);
	}
	return $this->fetch('/'.$this->web["admintemplate"].'/announcements', [
		'list'   => $list,
		'search' => $search,
		'web'    => $this->web,
	]);
}

// ========== 积分商城管理 ==========
public function pointsProducts($id = null) {
	if (!$this->checkPermission('user')) {
		$this->error('您没有权限访问此页面', '/admin/index');
	}
	ensure_points_products_table();
	if ($id) {
		// 编辑/查看单个产品
		$product = Db::name('points_products')->where('id', $id)->find();
		if (!$product) {
			$this->error('产品不存在');
		}
		if (Request::instance()->isPost()) {
			$csrf = input('csrf_token', '');
			if (!csrf_verify($csrf)) {
				return json(['code' => '-1', 'msg' => '安全验证失败']);
			}
			$data = [
				'name' => input('name', ''),
				'type' => input('type', 'balance'),
				'points' => intval(input('points', 0)),
				'value' => floatval(input('value', 0)),
				'stock' => intval(input('stock', -1)),
				'description' => input('description', ''),
				'status' => intval(input('status', 1)),
				'sort' => intval(input('sort', 0)),
			];
			Db::name('points_products')->where('id', $id)->update($data);
			return json(['code' => '1', 'msg' => '保存成功']);
		}
		$this->assign('product', $product);
		return $this->fetch('/'.$this->web["admintemplate"].'/points_product_edit');
	}
	if (Request::instance()->isPost()) {
		$csrf = input('csrf_token', '');
		if (!csrf_verify($csrf)) {
			return json(['code' => '-1', 'msg' => '安全验证失败']);
		}
		$act = input('act');
		if ($act == 'add') {
			$name = trim(input('name', ''));
			$type = input('type', 'balance');
			$points = intval(input('points', 0));
			$value = input('value', '');
			if ($name === '') {
				return json(['code' => '-1', 'msg' => '请输入产品名称']);
			}
			if ($points <= 0) {
				return json(['code' => '-1', 'msg' => '所需积分必须大于0']);
			}
			if ($value === '' || $value === null) {
				if ($type !== 'unban') {
					return json(['code' => '-1', 'msg' => '请填写产品价值']);
				}
				$value = '0';
			}
			$data = [
				'name' => $name,
				'type' => $type,
				'points' => $points,
				'value' => floatval($value),
				'stock' => intval(input('stock', -1)),
				'description' => input('description', ''),
				'status' => intval(input('status', 1)),
				'sort' => intval(input('sort', 0)),
				'created_at' => time(),
			];
			try {
				Db::name('points_products')->insert($data);
				return json(['code' => '1', 'msg' => '添加成功']);
			} catch (\Exception $e) {
				return json(['code' => '-1', 'msg' => '添加失败：' . $e->getMessage()]);
			}
		}
		if ($act == 'delete') {
			$delId = intval(input('del_id'));
			try {
				Db::name('points_products')->where('id', $delId)->delete();
				return json(['code' => '1', 'msg' => '删除成功']);
			} catch (\Exception $e) {
				return json(['code' => '-1', 'msg' => '删除失败：' . $e->getMessage()]);
			}
		}
	}
	$products = Db::name('points_products')->order('sort asc, id asc')->select();
	$cartList = Db::name('cart')->field('id,name')->where('buy', '<>', '1')->order('id asc')->select();
	$this->assign('products', $products);
	$this->assign('cartList', $cartList);
	return $this->fetch('/'.$this->web["admintemplate"].'/points_products');
}

// ========== 会员等级管理 ==========
public function membershipLevels($id = null) {
	if (!$this->checkPermission('user')) {
		$this->error('您没有权限访问此页面', '/admin/index');
	}
	ensure_membership_levels_table();
	if ($id) {
		$level = Db::name('membership_levels')->where('id', $id)->find();
		if (!$level) {
			$this->error('会员等级不存在');
		}
		if (Request::instance()->isPost()) {
			$csrf = input('csrf_token', '');
			if (!csrf_verify($csrf)) {
				return json(['code' => '-1', 'msg' => '安全验证失败']);
			}
			$data = [
				'name' => input('name', ''),
				'min_recharge' => floatval(input('min_recharge', 0)),
				'discount' => floatval(input('discount', 1.00)),
				'renew_discount' => floatval(input('renew_discount', 1.00)),
				'status' => intval(input('status', 1)),
			];
			Db::name('membership_levels')->where('id', $id)->update($data);
			return json(['code' => '1', 'msg' => '保存成功']);
		}
		$this->assign('level', $level);
		return $this->fetch('/'.$this->web["admintemplate"].'/membership_level_edit');
	}
	$levels = Db::name('membership_levels')->order('level asc')->select();
	$this->assign('levels', $levels);
	return $this->fetch('/'.$this->web["admintemplate"].'/membership_levels');
}

// ========== 访客访问统计 ==========
public function visitorStats() {
	if (!$this->checkPermission('set') && !$this->user['is_super']) {
		$this->error('您没有权限访问此页面', '/admin/index');
	}
	// 确保表存在
	try {
		$tableName = Db::name('visitor_log')->getTable();
		Db::execute("CREATE TABLE IF NOT EXISTS `{$tableName}` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`ip` varchar(50) DEFAULT '' COMMENT '访问IP',
			`url` varchar(500) DEFAULT '' COMMENT '访问URL',
			`referer` varchar(500) DEFAULT '' COMMENT '来源页面',
			`user_agent` varchar(500) DEFAULT '' COMMENT '浏览器UA',
			`visit_time` int(11) DEFAULT 0 COMMENT '访问时间戳',
			`date` varchar(10) DEFAULT '' COMMENT '日期',
			`hour` int(2) DEFAULT 0 COMMENT '小时0-23',
			PRIMARY KEY (`id`),
			KEY `idx_date` (`date`),
			KEY `idx_hour` (`date`,`hour`),
			KEY `idx_visit_time` (`visit_time`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8");
	} catch (\Exception $e) {}

	// POST 重置：清理30天前的记录
	if (Request::instance()->isPost()) {
		$act = input('act', '');
		if ($act == 'reset') {
			$cutoff = time() - 30 * 86400;
			Db::name('visitor_log')->where('visit_time', '<', $cutoff)->delete();
			return json(['code' => '1', 'msg' => '已清理30天前的访问记录']);
		}
		return json(['code' => '-1', 'msg' => '无效操作']);
	}

	$today = date('Y-m-d');

	// 今日统计
	$todayTotal = Db::name('visitor_log')->where('date', $today)->count();
	$todayUniqueIp = Db::name('visitor_log')->where('date', $today)->group('ip')->count();
	$todayUniqueIps = Db::name('visitor_log')->where('date', $today)->field('ip')->group('ip')->column('ip');
	$todayUniqueIpCount = count($todayUniqueIps);

	// 本周统计（周一至周日）
	$weekStart = date('Y-m-d', strtotime('monday this week'));
	$weekEnd = date('Y-m-d', strtotime('sunday this week'));
	$weekTotal = Db::name('visitor_log')->where('date', '>=', $weekStart)->where('date', '<=', $weekEnd)->count();
	$weekUniqueIp = Db::name('visitor_log')->where('date', '>=', $weekStart)->where('date', '<=', $weekEnd)->group('ip')->count();
	$weekUniqueIps = Db::name('visitor_log')->where('date', '>=', $weekStart)->where('date', '<=', $weekEnd)->field('ip')->group('ip')->column('ip');
	$weekUniqueIpCount = count($weekUniqueIps);

	// 近7天每日访问量
	$days7 = [];
	$days7Pv = [];
	$days7Uv = [];
	for ($i = 6; $i >= 0; $i--) {
		$d = date('Y-m-d', strtotime("-{$i} days"));
		$days7[] = date('m/d', strtotime("-{$i} days"));
		$pv = Db::name('visitor_log')->where('date', $d)->count();
		$uv = Db::name('visitor_log')->where('date', $d)->group('ip')->count();
		$days7Pv[] = (int)$pv;
		$days7Uv[] = (int)$uv;
	}

	// 今日24小时分布
	$hours24 = [];
	$hours24Count = [];
	for ($h = 0; $h < 24; $h++) {
		$hours24[] = sprintf('%02d:00', $h);
		$hours24Count[] = (int)Db::name('visitor_log')->where('date', $today)->where('hour', $h)->count();
	}

	// 最近访问记录（最近50条）
	$recentVisits = Db::name('visitor_log')->order('id desc')->limit(50)->select();

	// Top 10 页面
	$topPages = Db::name('visitor_log')
		->field('url, COUNT(*) as cnt')
		->group('url')
		->order('cnt desc')
		->limit(10)
		->select();

	return $this->fetch('/'.$this->web["admintemplate"].'/visitor_stats', [
		'todayTotal'       => $todayTotal,
		'todayUniqueIp'    => $todayUniqueIpCount,
		'weekTotal'        => $weekTotal,
		'weekUniqueIp'     => $weekUniqueIpCount,
		'days7'            => $days7,
		'days7Pv'          => $days7Pv,
		'days7Uv'          => $days7Uv,
		'hours24'          => $hours24,
		'hours24Count'     => $hours24Count,
		'recentVisits'     => $recentVisits,
		'topPages'         => $topPages,
	]);
}

}
