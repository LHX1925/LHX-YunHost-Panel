<?php
include_once __DIR__ . '/common_security.php';

// 发送安全HTTP头（必须在任何输出之前调用）
send_security_headers();

// 记录访客日志（使用原生超全局变量，此时框架 session() 等 helper 尚未加载）
// 使用 shutdown 函数延迟到框架初始化完成后执行，确保 \think\Db 可用
$visitorIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$visitorUri = $_SERVER['REQUEST_URI'] ?? '';
$visitorUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
$visitorUserId = isset($_SESSION['userid']) ? intval($_SESSION['userid']) : 0;
$visitorMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$visitorReferer = $_SERVER['HTTP_REFERER'] ?? '';
register_shutdown_function(function() use ($visitorIp, $visitorUri, $visitorUa, $visitorUserId, $visitorMethod, $visitorReferer) {
    if (function_exists('record_visitor')) {
        try {
            record_visitor($visitorIp, $visitorUri, $visitorUa, $visitorUserId, $visitorMethod, $visitorReferer);
        } catch (\Throwable $e) {}
    }
});

// 扫描器检测：拦截常见探测路径（/phpmyadmin、/.env、/.git/ 等）
check_scanner_activity();

// 已封禁IP检查：封禁列表中的IP直接403
// 注意：此时框架 helper.php 尚未加载，不可使用 request() 辅助函数
$clientIp = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '127.0.0.1');
if (is_ip_banned($clientIp)) {
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    die('<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>403 Forbidden</title><style>body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#fef2f2;color:#dc2626;margin:0}.box{text-align:center;padding:40px}.code{font-size:100px;font-weight:900;color:#fecaca}.msg{font-size:16px;margin-top:12px}</style></head><body><div class="box"><div class="code">403</div><div class="msg">您已被限制访问此站点</div></div></body></html>');
}

// 全局输入过滤：拦截路径遍历等攻击载荷
global_input_filter();

function random($length = 8,$chars = null){
  if(empty($chars)){
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
  }
  $count = strlen($chars) - 1;
  $code = '';
  while( strlen($code) < $length){
    $code .= substr($chars,rand(0,$count),1);
  }
  return $code;
}

function userrandom(){
$rand="a".rand(100000,999999);
return $rand;
}

// 邮箱格式验证（支持所有合法TLD，包括 .com .cn .net .org 等）
function is_valid_email($email) {
    if (empty($email)) return false;
    // 先使用PHP内置过滤器，兼容性好
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) return true;
    // 备用正则：兼容PHP旧版本对部分TLD验证失败的问题
    return preg_match('/^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/', $email) === 1;
}

// 国内邮箱白名单验证 — 仅允许国内主流邮箱服务商（杜绝临时邮箱/海外邮箱）
function is_allowed_email($email) {
    if (empty($email)) return false;
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    if (!$domain) return false;
    $allowedDomains = [
        // 腾讯系
        'qq.com',
        // 网易系
        '163.com', '126.com', 'yeah.net',
        // 新浪系
        'sina.com', 'sina.cn',
        // 搜狐系
        'sohu.com',
        // 阿里系
        'aliyun.com',
        // 腾讯企业
        'foxmail.com',
        // 运营商
        '139.com', '189.cn', 'wo.cn',
    ];
    return in_array($domain, $allowedDomains);
}

//获取目录下的子目录
function my_dir($dir) {
    $files = array();
    if(@$handle = opendir($dir)) { //注意这里要加一个@，不然会有warning错误提示：）
        while(($file = readdir($handle)) !== false) {
            if($file != ".." && $file != ".") { //排除根目录；
               $files[] = $file; 
            }
        }
        closedir($handle);
        return $files;
    }
}

function generateRand($m, $n)
{
    if ($m > $n) {
        $numMax = $m;
        $numMin = $n;
    } else {
        $numMax = $n;
        $numMin = $m;
    }
    /**
     * 生成$numMin和$numMax之间的随机浮点数，保留2位小数
     */
    $rand = $numMin + mt_rand() / mt_getrandmax() * ($numMax - $numMin);
    return floatval(number_format($rand,2));
}

//判断是否是HTTPS
function isHTTPS()
{
    if (defined('HTTPS') && HTTPS) return true;
    if (!isset($_SERVER)) return FALSE;
    if (!isset($_SERVER['HTTPS'])) return FALSE;
    if ($_SERVER['HTTPS'] === 1) {  //Apache
        return TRUE;
    } elseif ($_SERVER['HTTPS'] === 'on') { //IIS
        return TRUE;
    } elseif ($_SERVER['SERVER_PORT'] == 443) { //其他
        return TRUE;
    }
    return FALSE;
}


function judge($a,$b){
    if(in_array($b,$a)){
     return "1";
    }else{
return "2";
}
}

function getLen($num)
{
         $arr = explode('.',$num);
     $str=array_pop($arr);
if($str==$num){
$len="0";
}else{
     $len=strlen($str);
}
         return $len;
}

/**
 * 获取网站配置 (单次请求内静态缓存, 避免控制器 _initialize 与 email 函数重复查询)
 * @param string|null $key 配置字段名, 不传则返回全部配置数组
 * @return mixed|null
 */
function web_config($key = null){
    static $web = null;
    static $migrated = false;
    if($web === null){
        $web = \think\Db::name('web')->where('id', 1)->find();
    }
    // 自动迁移新字段（仅单次请求执行一次）
    if(!$migrated){
        $migrated = true;
        try {
            $webTable = \think\Db::name('web')->getTable();
            $orderTable = \think\Db::name('order')->getTable();
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'host_auto_create'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `host_auto_create` varchar(10) DEFAULT '0' COMMENT '0=manual 1=auto'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'host_auto_create_delay'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `host_auto_create_delay` varchar(50) DEFAULT '0' COMMENT 'minutes'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$orderTable}` LIKE 'auto_create_at'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$orderTable}` ADD COLUMN `auto_create_at` varchar(50) DEFAULT '0' COMMENT '自动开通时间戳'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_mode'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_mode` varchar(10) DEFAULT '0' COMMENT '实名模式:0关闭 1API 2人工 3阿里云'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_api_uname'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_api_uname` varchar(100) DEFAULT '' COMMENT '实名API账号'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_api_password'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_api_password` varchar(100) DEFAULT '' COMMENT '实名API密码'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_limit_pay'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_limit_pay` varchar(10) DEFAULT '0' COMMENT '实名限制充值'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_limit_buy'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_limit_buy` varchar(10) DEFAULT '0' COMMENT '实名限制购买主机'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_limit_ticket'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_limit_ticket` varchar(10) DEFAULT '0' COMMENT '实名限制提交工单'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_limit_renew'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_limit_renew` varchar(10) DEFAULT '0' COMMENT '实名限制续费主机'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_api_appid'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_api_appid` varchar(100) DEFAULT '' COMMENT '花迹数据API AppID'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_api_appkey'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_api_appkey` varchar(100) DEFAULT '' COMMENT '花迹数据API AppKey'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_first_free'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_first_free` varchar(10) DEFAULT '1' COMMENT '首次实名认证免费:0关闭 1开启'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_charge_amount'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_charge_amount` varchar(20) DEFAULT '0' COMMENT '实名认证每次收费金额(元)'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_api_type'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_api_type` varchar(10) DEFAULT '1' COMMENT '实名API类型:1花迹数据 2云市场'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_secret_id'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_secret_id` varchar(100) DEFAULT '' COMMENT '云市场API SecretId'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'realname_secret_key'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `realname_secret_key` varchar(100) DEFAULT '' COMMENT '云市场API SecretKey'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'disposable_email_block'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `disposable_email_block` tinyint(1) NOT NULL DEFAULT '0' COMMENT '防临时邮箱注册'");
            }
            // 液态玻璃总开关
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'glass_enabled'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `glass_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用液态玻璃主题:0关闭 1开启'");
            }
            // 强制QQ群加群相关字段
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'force_qq_group'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `force_qq_group` tinyint(1) NOT NULL DEFAULT '0' COMMENT '强制加入QQ群:0关闭 1开启'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'force_qq_group_key'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `force_qq_group_key` varchar(100) NOT NULL DEFAULT '' COMMENT '强制加群卡密'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'force_qq_group_reason'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `force_qq_group_reason` text COMMENT '强制加群原因说明'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'force_qq_group_number'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `force_qq_group_number` varchar(50) NOT NULL DEFAULT '' COMMENT 'QQ群号码'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'force_qq_group_link'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `force_qq_group_link` varchar(500) NOT NULL DEFAULT '' COMMENT 'QQ群加群链接'");
            }
            // 确保背景图相关字段存在
            ensure_web_bg_column();
            // 确保主机转让表存在
            ensure_host_transfer_table();
            // 聚合登录配置字段
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'oauth_enabled'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `oauth_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用聚合登录'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'oauth_appid'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `oauth_appid` varchar(100) DEFAULT '' COMMENT 'API应用ID'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'oauth_appkey'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `oauth_appkey` varchar(100) DEFAULT '' COMMENT 'API应用密钥'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'oauth_callback'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `oauth_callback` varchar(255) DEFAULT '' COMMENT '回调URL'");
            }
            // Live2D 看板娘配置字段
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_model'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_model` varchar(50) DEFAULT 'shufulei' COMMENT 'Live2D模型选择'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_scale'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_scale` varchar(10) DEFAULT '0.015' COMMENT 'Live2D缩放比例'");
            }
            // 仅当模型值为空或不在已知模型列表时才回退到本地舒芙蕾（保留用户已选的 senko 等远程模型）
            try {
                $curLive2dModel = \think\Db::name('web')->where('id', 1)->value('live2d_model');
                $knownModels = ['shufulei', 'youxiaomiao', 'shizuku', 'shizuku_pajama', 'pio', 'senko', 'hk416', 'cat_black'];
                if (empty($curLive2dModel) || !in_array($curLive2dModel, $knownModels)) {
                    \think\Db::name('web')->where('id', 1)->update(['live2d_model' => 'shufulei', 'live2d_scale' => '0.015']);
                }
            } catch (\Exception $e) {
                // 忽略数据迁移异常
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_pos_x'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_pos_x` varchar(10) DEFAULT '70' COMMENT 'Live2D水平位置'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_pos_y'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_pos_y` varchar(10) DEFAULT '70' COMMENT 'Live2D垂直位置'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_primary_color'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_primary_color` varchar(20) DEFAULT '#38B0DE' COMMENT 'Live2D主题色'");
            }
            // Live2D AI 聊天相关字段
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_ai_enabled'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_ai_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'AI聊天开关'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_ai_api_url'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_ai_api_url` varchar(500) NOT NULL DEFAULT '' COMMENT 'AI API地址'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_ai_api_key'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_ai_api_key` varchar(500) NOT NULL DEFAULT '' COMMENT 'AI API密钥'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_ai_model'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_ai_model` varchar(100) NOT NULL DEFAULT 'deepseek-v4-flash' COMMENT 'AI模型'");
            }
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$webTable}` LIKE 'live2d_ai_persona'");
            if(empty($cols)){
                \think\Db::execute("ALTER TABLE `{$webTable}` ADD COLUMN `live2d_ai_persona` TEXT NULL COMMENT 'AI人设（自定义）'");
            }

            // 重新读取配置以包含新增字段
            $web = \think\Db::name('web')->where('id', 1)->find();
        } catch (\Exception $e) {
            // 迁移失败不影响后续流程
        }
    }
    if($key === null){
        return $web;
    }
    return isset($web[$key]) ? $web[$key] : null;
}

/**
 * Build enterprise HTML email template
 */
function build_email_html($title, $content, $webname = '') {
    $web = web_config();
    if (!$webname) {
        $webname = $web['name'] ?? 'MNBT-SALE';
    }
    $year = date('Y');

    // Build enterprise logo URL
    $logoHtml = '';
    if (!empty($web['logo'])) {
        $logo = $web['logo'];
        if (strpos($logo, 'http') !== 0) {
            $logo = ltrim($logo, '/');
            try {
                $domain = request()->domain();
                $logo = rtrim($domain, '/') . '/' . $logo;
            } catch (\Exception $e) {
                $logo = '/' . $logo;
            }
        }
        $logoHtml = '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars($webname) . '" style="max-height:42px;display:block;margin:0 auto 12px;">';
    }

    $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f7fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,'PingFang SC','Microsoft YaHei',sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f7fb;padding:48px 16px;">
<tr>
<td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 32px rgba(30,58,95,0.08);border:1px solid #e8eef5;">
<!-- Header -->
<tr>
<td style="background:#ffffff;padding:28px 40px 12px;text-align:center;border-bottom:1px solid #eef2f7;">
{$logoHtml}
<h1 style="color:#1e293b;font-size:20px;font-weight:700;margin:0;letter-spacing:0.02em;">{$webname}</h1>
<p style="color:#64748b;font-size:12px;margin:6px 0 0;letter-spacing:0.04em;">SECURE CLOUD SERVICE</p>
</td>
</tr>
<!-- Title -->
<tr>
<td style="padding:36px 48px 8px;">
<h2 style="color:#0f172a;font-size:18px;font-weight:600;margin:0;">{$title}</h2>
</td>
</tr>
<!-- Content -->
<tr>
<td style="padding:12px 48px 36px;color:#475569;font-size:14px;line-height:1.85;">
{$content}
</td>
</tr>
<!-- Divider -->
<tr>
<td style="padding:0 48px;">
<div style="border-top:1px solid #e2e8f0;"></div>
</td>
</tr>
<!-- Footer -->
<tr>
<td style="padding:24px 48px 32px;text-align:center;">
<p style="color:#94a3b8;font-size:12px;margin:0 0 4px;line-height:1.6;">此邮件由系统自动发送，请勿直接回复</p>
<p style="color:#94a3b8;font-size:12px;margin:0;">&copy; {$year} {$webname} All Rights Reserved.</p>
</td>
</tr>
</table>
</td>
</tr>
</table>
</body>
</html>
HTML;
    return $html;
}

/**
 * 确保订单表包含订单号字段
 */
function ensure_order_ordernumber_column() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}order";
        $columns = \think\Db::query("SHOW COLUMNS FROM `{$table}`");
        $columnNames = array_column($columns, 'Field');
        if (!in_array('ordernumber', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `ordernumber` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号' AFTER `id`");
        }
        // 为已有订单补充订单号
        $emptyCount = \think\Db::name('order')->where('ordernumber', '')->count();
        if ($emptyCount > 0) {
            $orders = \think\Db::name('order')->where('ordernumber', '')->select();
            foreach ($orders as $order) {
                $ordernumber = 'YH' . date('Ymd', intval($order['atime'] ?: time())) . str_pad($order['id'], 6, '0', STR_PAD_LEFT);
                \think\Db::name('order')->where('id', $order['id'])->update(['ordernumber' => $ordernumber]);
            }
        }
    } catch (\Exception $e) {
        // 忽略
    }
}

/**
 * 生成订单号
 */
function generate_order_number($orderId) {
    return 'YH' . date('Ymd') . str_pad($orderId, 6, '0', STR_PAD_LEFT);
}

/**
 * 确保用户表包含最后登录相关字段（登录时间、IP、地区）
 * 供前台用户中心和后台总览共用
 */
function ensure_user_columns() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}user";
        $columns = \think\Db::query("SHOW COLUMNS FROM `{$table}`");
        $columnNames = array_column($columns, 'Field');
        if (!in_array('last_login_time', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `last_login_time` int(11) NOT NULL DEFAULT 0 COMMENT '最后登录时间戳'");
        }
        if (!in_array('last_login_ip', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `last_login_ip` varchar(255) NOT NULL DEFAULT '' COMMENT '最后登录IP'");
        }
        if (!in_array('last_login_region', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `last_login_region` varchar(50) NOT NULL DEFAULT '' COMMENT '最后登录地区（省份）'");
        }
        if (!in_array('realname', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `realname` varchar(100) NOT NULL DEFAULT '' COMMENT '真实姓名'");
        }
        if (!in_array('idcard', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `idcard` varchar(20) NOT NULL DEFAULT '' COMMENT '身份证号'");
        }
        if (!in_array('realname_status', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `realname_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '实名状态:0未认证 1已认证 2已驳回 3待审核'");
        }
        if (!in_array('realname_attempts', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `realname_attempts` int(11) NOT NULL DEFAULT 0 COMMENT '实名认证已尝试次数'");
        }
        if (!in_array('points', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `points` int(11) NOT NULL DEFAULT 0 COMMENT '用户积分'");
        }
        if (!in_array('last_checkin_time', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `last_checkin_time` int(11) NOT NULL DEFAULT 0 COMMENT '最后签到时间戳'");
        }
        if (!in_array('total_recharge', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `total_recharge` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '累计充值消费金额'");
        }
        if (!in_array('membership_level', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `membership_level` int(11) NOT NULL DEFAULT 0 COMMENT '会员等级:0普通用户 1-6对应VIP等级'");
        }
        if (!in_array('oauth_qq', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `oauth_qq` varchar(100) NOT NULL DEFAULT '' COMMENT 'QQ聚合登录social_uid'");
        }
        if (!in_array('oauth_wechat', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `oauth_wechat` varchar(100) NOT NULL DEFAULT '' COMMENT '微信聚合登录social_uid'");
        }
        if (!in_array('force_qq_group_verified', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `force_qq_group_verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '强制QQ群卡密已验证:0未验证 1已验证'");
        }
    } catch (\Exception $e) {
        // 忽略字段已存在等异常
    }
}

/**
 * 确保积分商城产品表存在
 */
function ensure_points_products_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}points_products";
        $tables = \think\Db::query("SHOW TABLES LIKE '{$table}'");
        if (empty($tables)) {
            \think\Db::execute("CREATE TABLE `{$table}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL COMMENT '产品名称',
                `type` varchar(50) NOT NULL DEFAULT 'balance' COMMENT '类型:balance余额 host主机 renew续费',
                `points` int(11) NOT NULL DEFAULT 0 COMMENT '所需积分',
                `value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '兑换价值(余额金额/主机cartid/续费天数)',
                `stock` int(11) NOT NULL DEFAULT -1 COMMENT '库存 -1无限',
                `description` text COMMENT '产品描述',
                `image` varchar(500) DEFAULT '' COMMENT '产品图片',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0下架 1上架',
                `sort` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
                `created_at` int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (\Exception $e) {}
}

/**
 * 确保会员等级表存在
 */
function ensure_membership_levels_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}membership_levels";
        $tables = \think\Db::query("SHOW TABLES LIKE '{$table}'");
        if (empty($tables)) {
            \think\Db::execute("CREATE TABLE `{$table}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `level` int(11) NOT NULL DEFAULT 1 COMMENT '等级 1-6',
                `name` varchar(50) NOT NULL COMMENT '等级名称',
                `icon` varchar(500) DEFAULT '' COMMENT '等级图标',
                `min_recharge` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '升级所需累计充值',
                `discount` decimal(3,2) NOT NULL DEFAULT 1.00 COMMENT '折扣比例 0.95=95折',
                `renew_discount` decimal(3,2) NOT NULL DEFAULT 1.00 COMMENT '续费折扣比例',
                `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态:0禁用 1启用',
                `created_at` int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            // 插入默认会员等级
            $defaultLevels = [
                ['level' => 1, 'name' => '青铜VIP', 'icon' => '创享青铜vip1.svg', 'min_recharge' => 0, 'discount' => 0.98, 'renew_discount' => 0.98, 'status' => 1, 'created_at' => time()],
                ['level' => 2, 'name' => '白银VIP', 'icon' => '创享白银vip2.svg', 'min_recharge' => 100, 'discount' => 0.95, 'renew_discount' => 0.95, 'status' => 1, 'created_at' => time()],
                ['level' => 3, 'name' => '黄金VIP', 'icon' => '创享黄金vip3.svg', 'min_recharge' => 500, 'discount' => 0.90, 'renew_discount' => 0.90, 'status' => 1, 'created_at' => time()],
                ['level' => 4, 'name' => '紫金VIP', 'icon' => '创享紫金vip4.svg', 'min_recharge' => 1000, 'discount' => 0.85, 'renew_discount' => 0.85, 'status' => 1, 'created_at' => time()],
                ['level' => 5, 'name' => '钻石VIP', 'icon' => '创享钻石vip5.svg', 'min_recharge' => 2000, 'discount' => 0.80, 'renew_discount' => 0.80, 'status' => 1, 'created_at' => time()],
                ['level' => 6, 'name' => '至尊VIP', 'icon' => '创享至尊vip6.svg', 'min_recharge' => 5000, 'discount' => 0.75, 'renew_discount' => 0.75, 'status' => 1, 'created_at' => time()],
            ];
            foreach ($defaultLevels as $lvl) {
                \think\Db::name('membership_levels')->insert($lvl);
            }
        }
    } catch (\Exception $e) {}
}

/**
 * 确保积分兑换记录表存在
 */
function ensure_points_log_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}points_log";
        $tables = \think\Db::query("SHOW TABLES LIKE '{$table}'");
        if (empty($tables)) {
            \think\Db::execute("CREATE TABLE `{$table}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `userid` int(11) NOT NULL,
                `type` varchar(50) NOT NULL DEFAULT 'checkin' COMMENT '类型:checkin签到 exchange兑换',
                `points` int(11) NOT NULL DEFAULT 0 COMMENT '积分变动(正数增加负数减少)',
                `content` varchar(500) DEFAULT '' COMMENT '描述',
                `created_at` int(11) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_userid` (`userid`),
                KEY `idx_type` (`type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (\Exception $e) {}
}

/**
 * 更新用户会员等级（根据累计充值金额）
 */
function update_user_membership($userid) {
    try {
        $user = \think\Db::name('user')->where('id', $userid)->find();
        if (!$user) return 0;
        $totalRecharge = floatval($user['total_recharge'] ?? 0);
        $levels = \think\Db::name('membership_levels')
            ->where('status', 1)
            ->where('min_recharge', '<=', $totalRecharge)
            ->order('level desc')
            ->select();
        $newLevel = 0;
        if (!empty($levels)) {
            $newLevel = intval($levels[0]['level']);
        }
        if (intval($user['membership_level'] ?? 0) != $newLevel) {
            \think\Db::name('user')->where('id', $userid)->update(['membership_level' => $newLevel]);
        }
        return $newLevel;
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * 获取会员折扣
 */
function get_membership_discount($userid, $type = 'buy') {
    try {
        $user = \think\Db::name('user')->where('id', $userid)->find();
        if (!$user || intval($user['membership_level'] ?? 0) == 0) return 1.00;
        $level = \think\Db::name('membership_levels')
            ->where('level', intval($user['membership_level']))
            ->where('status', 1)
            ->find();
        if (!$level) return 1.00;
        if ($type == 'renew') {
            return floatval($level['renew_discount'] ?? 1.00);
        }
        return floatval($level['discount'] ?? 1.00);
    } catch (\Exception $e) {
        return 1.00;
    }
}

/**
 * 确保邮箱验证记录表存在（用于跨控制器验证状态共享）
 */
function ensure_email_verify_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}email_verify";
        $tables = \think\Db::query("SHOW TABLES LIKE '{$table}'");
        if (empty($tables)) {
            \think\Db::execute("CREATE TABLE `{$table}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `mail` varchar(255) NOT NULL DEFAULT '' COMMENT '邮箱地址',
                `token` varchar(255) NOT NULL DEFAULT '' COMMENT '验证令牌',
                `code` varchar(10) NOT NULL DEFAULT '' COMMENT '备用验证码',
                `verified` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已验证:0未验证 1已验证',
                `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间戳',
                `expire_time` int(11) NOT NULL DEFAULT 0 COMMENT '过期时间戳',
                PRIMARY KEY (`id`),
                KEY `idx_mail` (`mail`),
                KEY `idx_token` (`token`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='邮箱验证记录'");
        } else {
            $cols = \think\Db::query("SHOW COLUMNS FROM `{$table}` LIKE 'code'");
            if (empty($cols)) {
                \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `code` varchar(10) NOT NULL DEFAULT '' COMMENT '备用验证码' AFTER `token`");
            }
        }
    } catch (\Throwable $e) {
        // 创建失败不影响后续流程
    }
}

/**
 * 确保管理员表包含权限相关字段
 */
function ensure_admin_columns() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}admin";
        $columns = \think\Db::query("SHOW COLUMNS FROM `{$table}`");
        $columnNames = array_column($columns, 'Field');
        if (!in_array('role_id', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `role_id` int(11) DEFAULT 0 COMMENT '角色ID'");
        }
        if (!in_array('is_super', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `is_super` tinyint(1) DEFAULT 0 COMMENT '是否超级管理员'");
        }
        if (!in_array('status', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `status` tinyint(1) DEFAULT 1 COMMENT '状态'");
        }
        if (!in_array('created_at', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `created_at` int(11) DEFAULT 0 COMMENT '创建时间'");
        }
    } catch (\Exception $e) {
        // 忽略字段已存在等异常
    }
}

/**
 * 确保管理员角色表存在，并初始化超级管理员角色
 */
function ensure_admin_role_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}admin_role` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `name` varchar(50) NOT NULL COMMENT '角色名称',
          `permissions` text COMMENT '权限JSON',
          `description` varchar(255) DEFAULT '' COMMENT '角色描述',
          `created_at` int(11) DEFAULT 0,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        \think\Db::execute($sql);

        // 确保三个默认角色存在，对齐新三角色体系
        $defaultRoles = [
            ['id' => 1, 'name' => '站长',          'permissions' => json_encode(['all']), 'description' => '网站最高管理，拥有所有权限'],
            ['id' => 2, 'name' => '超级管理员',    'permissions' => json_encode(['user', 'product', 'classification', 'server', 'order', 'ticket', 'announcement', 'pay', 'aff', 'set', 'admin_manager', 'sq', 'transaction', 'transferrecord', 'op_log']), 'description' => '除支付配置外所有功能'],
            ['id' => 3, 'name' => '普通管理员',    'permissions' => json_encode(['user']), 'description' => '仅能使用概览和用户管理'],
        ];
        $existingIds = \think\Db::name('admin_role')->column('id');
        foreach ($defaultRoles as $role) {
            if (!in_array($role['id'], $existingIds)) {
                \think\Db::name('admin_role')->insert(array_merge($role, ['created_at' => time()]));
            }
        }

        // 兼容旧版升级：id=1 旧名为 "超级管理员" → 重命名为 "站长"
        $oldRole1 = \think\Db::name('admin_role')->where('id', 1)->find();
        if ($oldRole1 && $oldRole1['name'] == '超级管理员') {
            \think\Db::name('admin_role')->where('id', 1)->update([
                'name'        => '站长',
                'permissions' => json_encode(['all']),
                'description' => '网站最高管理，拥有所有权限',
            ]);
        }

        // 兼容旧版升级：id=2 旧名为 "普通管理员" → 修正为 "超级管理员" 并更新权限
        $oldRole2 = \think\Db::name('admin_role')->where('id', 2)->find();
        if ($oldRole2 && $oldRole2['name'] == '普通管理员') {
            \think\Db::name('admin_role')->where('id', 2)->update([
                'name'        => '超级管理员',
                'permissions' => json_encode(['user', 'product', 'classification', 'server', 'order', 'ticket', 'announcement', 'pay', 'aff', 'set', 'admin_manager', 'sq', 'transaction', 'transferrecord', 'op_log']),
                'description' => '除支付配置外所有功能',
            ]);
        }
    } catch (\Exception $e) {
        // 忽略表已存在等异常
    }
}

/**
 * 根据已有 IP 批量补全用户地区字段（用于地域分布图首次统计）
 * 每页仅处理少量用户，避免外部 API 超时
 */
function refresh_user_regions($limit = 50) {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}user";
        $rows = \think\Db::name('user')
            ->where('last_login_region', 'in', ['', '未知', '本地'])
            ->where('last_login_ip', 'not in', ['', '0.0.0.0', '127.0.0.1', 'localhost', 'unknown'])
            ->field('id,last_login_ip')
            ->limit($limit)
            ->select();
        if (empty($rows)) {
            return 0;
        }
        foreach ($rows as $row) {
            $region = get_ip_region($row['last_login_ip']);
            if ($region && !in_array($region, ['', '未知', '本地'])) {
                \think\Db::name('user')->where('id', $row['id'])->update([
                    'last_login_region' => $region,
                ]);
            }
        }
        return count($rows);
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * 确保实名认证记录表存在
 */
function ensure_realname_record_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}realname_record` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL COMMENT '用户ID',
          `realname` varchar(100) NOT NULL DEFAULT '' COMMENT '真实姓名',
          `idcard` varchar(20) NOT NULL DEFAULT '' COMMENT '身份证号',
          `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0未认证 1已通过 2已驳回 3待审核',
          `apply_time` int(11) NOT NULL DEFAULT 0 COMMENT '申请时间',
          `review_time` int(11) NOT NULL DEFAULT 0 COMMENT '审核时间',
          `reviewer_id` int(11) NOT NULL DEFAULT 0 COMMENT '审核人ID',
          `reviewer_name` varchar(50) NOT NULL DEFAULT '' COMMENT '审核人账号',
          `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '审核备注',
          PRIMARY KEY (`id`),
          KEY `idx_user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        \think\Db::execute($sql);
    } catch (\Exception $e) {
        // 忽略表已存在等异常
    }
}

/**
 * 确保卡密表存在
 */
function ensure_cdkey_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}cdkey` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `cdkey` varchar(64) NOT NULL DEFAULT '' COMMENT '卡密',
          `type` varchar(10) NOT NULL DEFAULT 'balance' COMMENT '类型: balance=余额充值, host=购买主机',
          `money` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '余额充值金额',
          `cartid` int(11) NOT NULL DEFAULT '0' COMMENT '关联产品ID(host类型)',
          `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0未使用 1已使用 2已停用/回收',
          `created_at` int(11) NOT NULL DEFAULT '0' COMMENT '创建时间',
          `used_at` int(11) NOT NULL DEFAULT '0' COMMENT '使用时间',
          `used_userid` int(11) NOT NULL DEFAULT '0' COMMENT '使用用户ID',
          `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
          `repeatable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0不可重复使用 1可重复使用',
          `restrict_type` varchar(20) NOT NULL DEFAULT 'all' COMMENT '限制类型: all=通用, single=单用户, multi=多用户, once_per_user=全站每人一次',
          `restrict_users` text COMMENT '限制用户ID列表(逗号分隔, single/multi时生效)',
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_cdkey` (`cdkey`),
          KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        \think\Db::execute($sql);
        // 兼容旧表：添加新字段
        $columns = \think\Db::query("SHOW COLUMNS FROM `{$prefix}cdkey`");
        $colNames = array_column($columns, 'Field');
        if (!in_array('repeatable', $colNames)) {
            \think\Db::execute("ALTER TABLE `{$prefix}cdkey` ADD COLUMN `repeatable` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0不可重复使用 1可重复使用'");
        }
        if (!in_array('restrict_type', $colNames)) {
            \think\Db::execute("ALTER TABLE `{$prefix}cdkey` ADD COLUMN `restrict_type` varchar(20) NOT NULL DEFAULT 'all' COMMENT '限制类型: all=通用, single=单用户, multi=多用户, once_per_user=全站每人一次'");
        } else {
            // 兼容旧表：扩展 restrict_type 长度以支持 once_per_user
            $colInfo = \think\Db::query("SHOW COLUMNS FROM `{$prefix}cdkey` LIKE 'restrict_type'");
            if (!empty($colInfo) && isset($colInfo[0]['Type'])) {
                $colType = $colInfo[0]['Type'];
                if (stripos($colType, 'varchar(10)') !== false) {
                    \think\Db::execute("ALTER TABLE `{$prefix}cdkey` MODIFY COLUMN `restrict_type` varchar(20) NOT NULL DEFAULT 'all' COMMENT '限制类型: all=通用, single=单用户, multi=多用户, once_per_user=全站每人一次'");
                }
            }
        }
        if (!in_array('restrict_users', $colNames)) {
            \think\Db::execute("ALTER TABLE `{$prefix}cdkey` ADD COLUMN `restrict_users` text COMMENT '限制用户ID列表(逗号分隔)'");
        }
    } catch (\Exception $e) {
        // 忽略表已存在等异常
    }
}

function ensure_cdkey_usage_log_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}cdkey_usage_log` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `cdkey` varchar(64) NOT NULL COMMENT '卡密',
          `userid` int(11) NOT NULL COMMENT '使用用户ID',
          `used_at` int(11) NOT NULL COMMENT '使用时间',
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_cdkey_user` (`cdkey`, `userid`),
          KEY `idx_cdkey` (`cdkey`),
          KEY `idx_userid` (`userid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        \think\Db::execute($sql);
    } catch (\Exception $e) {
        // 忽略表已存在等异常
    }
}

function ensure_announcements_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $sql = "CREATE TABLE IF NOT EXISTS `{$prefix}announcements` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `title` varchar(255) NOT NULL DEFAULT '' COMMENT '公告标题',
          `content` text COMMENT '公告内容',
          `notice_type` varchar(10) NOT NULL DEFAULT 'silent' COMMENT '提醒方式: force=强制弹窗, silent=静默红点',
          `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '0隐藏 1显示',
          `send_email` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0不发送邮件 1发送邮件',
          `email_sent` tinyint(1) NOT NULL DEFAULT '0' COMMENT '邮件是否已发送',
          `created_at` int(11) NOT NULL DEFAULT '0' COMMENT '发布时间',
          `updated_at` int(11) NOT NULL DEFAULT '0' COMMENT '更新时间',
          PRIMARY KEY (`id`),
          KEY `idx_status` (`status`),
          KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        \think\Db::execute($sql);
        $sql2 = "CREATE TABLE IF NOT EXISTS `{$prefix}announcement_reads` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `announcement_id` int(11) NOT NULL DEFAULT '0' COMMENT '公告ID',
          `user_id` int(11) NOT NULL DEFAULT '0' COMMENT '用户ID',
          `read_at` int(11) NOT NULL DEFAULT '0' COMMENT '阅读时间',
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_user_ann` (`user_id`, `announcement_id`),
          KEY `idx_ann` (`announcement_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        \think\Db::execute($sql2);
    } catch (\Exception $e) {
        // 忽略表已存在等异常
    }
}

/**
 * 确保购物车表存在（供前台购物车和后台统计共用）
 */
function ensure_shopping_cart_table() {
    try {
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
        \think\Db::execute($sql);
    } catch (\Exception $e) {
        // 忽略表已存在等异常
    }
}

/**
 * 确保 web 表有 bg_image / bg_blur / bg_gradient / bg_images / bg_switch_interval 字段
 */
function ensure_web_bg_column() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}web";
        $columns = \think\Db::query("SHOW COLUMNS FROM `{$table}`");
        $columnNames = array_column($columns, 'Field');
        if (!in_array('bg_image', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `bg_image` varchar(500) DEFAULT '' COMMENT '全局背景图'");
        }
        if (!in_array('bg_blur', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `bg_blur` int(2) DEFAULT '3' COMMENT '背景图模糊程度(0-10)'");
        }
        if (!in_array('bg_gradient', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `bg_gradient` varchar(50) DEFAULT 'default' COMMENT '预设渐变色'");
        }
        if (!in_array('bg_images', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `bg_images` text COMMENT '轮播背景图URL列表（逗号分隔）'");
        }
        if (!in_array('bg_switch_interval', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `bg_switch_interval` int(6) DEFAULT '0' COMMENT '背景图轮播间隔(秒，0=不轮播)'");
        }
        if (!in_array('glass_enabled', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `glass_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用液态玻璃主题:0关闭 1开启'");
        }
        if (!in_array('glass_opacity', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `glass_opacity` int(3) DEFAULT '72' COMMENT '液态玻璃透明度(30-100)'");
        }
        if (!in_array('bg_type', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `bg_type` varchar(20) DEFAULT 'image' COMMENT '背景类型：image/video/gif'");
        }
        if (!in_array('bg_video_loop', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `bg_video_loop` tinyint(1) NOT NULL DEFAULT '1' COMMENT '视频背景是否循环播放'");
        }
        if (!in_array('bg_video_muted', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `bg_video_muted` tinyint(1) NOT NULL DEFAULT '1' COMMENT '视频背景是否静音'");
        }
        if (!in_array('service_email', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `service_email` varchar(255) DEFAULT '' COMMENT '客服邮箱'");
        }
        if (!in_array('live2d_enabled', $columnNames)) {
            \think\Db::execute("ALTER TABLE `{$table}` ADD COLUMN `live2d_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Live2D看板娘:0关闭 1开启'");
        }
    } catch (\Exception $e) {
        // 忽略
    }
}

/**
 * 确保 host_transfer 表存在
 */
function ensure_host_transfer_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}host_transfer";
        $exists = \think\Db::query("SHOW TABLES LIKE '{$table}'");
        if (empty($exists)) {
            \think\Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL COMMENT '订单ID',
                `userid` int(11) NOT NULL COMMENT '转让方用户ID',
                `target_userid` int(11) NOT NULL DEFAULT '0' COMMENT '指定接收用户ID',
                `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '转让价格',
                `original_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '原购买价格',
                `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=待转让 1=已完成 2=已驳回 3=已取消',
                `buyer_userid` int(11) NOT NULL DEFAULT '0' COMMENT '购买方用户ID',
                `email_verified` tinyint(1) NOT NULL DEFAULT '0' COMMENT '邮箱验证状态',
                `contact_info` varchar(500) DEFAULT '' COMMENT '卖家联系方式',
                `reject_reason` varchar(500) DEFAULT '' COMMENT '驳回原因',
                `created_at` int(11) NOT NULL DEFAULT '0',
                `updated_at` int(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `idx_order_id` (`order_id`),
                KEY `idx_userid` (`userid`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (\Exception $e) {
        // 忽略
    }
}

// 确保转让消息表存在
function ensure_host_transfer_message_table() {
    try {
        $prefix = \think\Db::getConfig('prefix');
        $prefix = $prefix ?: '';
        $table = "{$prefix}host_transfer_message";
        $exists = \think\Db::query("SHOW TABLES LIKE '{$table}'");
        if (empty($exists)) {
            \think\Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `transfer_id` int(11) NOT NULL COMMENT '转让ID',
                `sender_id` int(11) NOT NULL COMMENT '发送方用户ID',
                `receiver_id` int(11) NOT NULL COMMENT '接收方用户ID',
                `content` text NOT NULL COMMENT '消息内容',
                `is_read` tinyint(1) NOT NULL DEFAULT '0' COMMENT '0=未读 1=已读',
                `created_at` int(11) NOT NULL DEFAULT '0',
                PRIMARY KEY (`id`),
                KEY `idx_transfer_id` (`transfer_id`),
                KEY `idx_sender_id` (`sender_id`),
                KEY `idx_receiver_id` (`receiver_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    } catch (\Exception $e) {
        // 忽略
    }
}

/**
 * 处理待开通订单（延迟自动开通 / 手动开通重试）
 * 可在 cron、用户中心、后台总览中调用，作为无 cron 时的兜底机制
 */
function process_pending_host_orders() {
    try {
        $time = time();
        $pendingOrders = \think\Db::name("order")
            ->where("state", "0")
            ->where("auto_create_at", ">", 0)
            ->where("auto_create_at", "<=", $time)
            ->select();
        if (empty($pendingOrders)) {
            return;
        }
        $cartIds = array_unique(array_filter(array_column($pendingOrders, 'cartid')));
        $carts = [];
        if (!empty($cartIds)) {
            $cartRows = \think\Db::name('cart')->where('id', 'in', $cartIds)->select();
            foreach ($cartRows as $row) { $carts[$row['id']] = $row; }
        }
        $serverIds = [];
        foreach ($carts as $c) { if (!empty($c['serverid'])) { $serverIds[] = $c['serverid']; } }
        $serverIds = array_unique($serverIds);
        $servers = [];
        if (!empty($serverIds)) {
            $serverRows = \think\Db::name('server')->where('id', 'in', $serverIds)->select();
            foreach ($serverRows as $row) { $servers[$row['id']] = $row; }
        }
        foreach ($pendingOrders as $order) {
            $cart = isset($carts[$order['cartid']]) ? $carts[$order['cartid']] : null;
            if (!$cart) { continue; }
            $server = isset($servers[$cart['serverid']]) ? $servers[$cart['serverid']] : null;
            if (!$server || $server['serverplugins'] == "") { continue; }
            // 如果订单账号密码为空，自动生成随机凭据
            $hostUser = $order["user"];
            $hostPass = $order["password"];
            if(empty($hostUser) || strlen($hostUser) < 3){
                $hostUser = 'user' . rand(10000, 99999);
            }
            if(empty($hostPass) || strlen($hostPass) < 6){
                $hostPass = substr(md5(uniqid(mt_rand(), true)), 0, 12);
            }
            if($hostUser != $order["user"] || $hostPass != $order["password"]){
                \think\Db::name('order')->where('id', $order["id"])->update([
                    "user" => $hostUser,
                    "password" => $hostPass,
                ]);
                $order["user"] = $hostUser;
                $order["password"] = $hostPass;
            }
            $pluginFile = PATH . "plugins/host/" . $server["serverplugins"] . "/" . $server["serverplugins"] . ".php";
            if (!file_exists($pluginFile)) { continue; }
            include_once $pluginFile;
            $function = $server["serverplugins"] . "_CreateAccount";
            if (!function_exists($function)) { continue; }
            $times = intval($order["ztime"]) - intval($order["atime"]);
            if ($times < 0) { $times = 0; }
            $cycleTime = 1;
            if ($cart["cycle"] == "month") $cycleTime = 2592000;
            elseif ($cart["cycle"] == "season") $cycleTime = 7879680;
            elseif ($cart["cycle"] == "year") $cycleTime = 31536000;
            elseif ($cart["cycle"] == "day") $cycleTime = 86400;
            elseif ($cart["cycle"] == "unrestricted") $cycleTime = 3153600000;
            $buyTime = ($cycleTime > 0) ? intval($times / $cycleTime) : 1;
            if ($buyTime < 1) { $buyTime = 1; }
            $result = @$function($server, ["user" => $order["user"], "password" => $order["password"], "time" => $buyTime], $cart, $times, $order["id"]);
            if (!is_array($result) || !isset($result["code"]) || $result["code"] != "1") {
                // 开通失败，5分钟后重试
                \think\Db::name("order")->where("id", $order["id"])->update(["auto_create_at" => $time + 300]);
            }
        }
    } catch (\Exception $e) {
        // 忽略异常，避免影响页面正常加载
    }
}

/**
 * HTTP GET 请求（简单封装）
 */
function http_get($url, $timeout = 3) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        return $err ? false : $res;
    } else {
        $ctx = stream_context_create([
            'http' => ['timeout' => $timeout, 'user_agent' => 'Mozilla/5.0'],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        return @file_get_contents($url, false, $ctx);
    }
}

/**
 * 获取客户端真实 IP（兼容 CDN / 反向代理）
 * 优先读取 X-Forwarded-For、X-Real-IP、CF-Connecting-IP 等头部
 * @return string
 */
function get_client_ip() {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $h) {
        if (!empty($_SERVER[$h])) {
            $ip = $_SERVER[$h];
            if ($h === 'HTTP_X_FORWARDED_FOR') {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

/**
 * 根据 IP 获取所在地区（省份）
 * 多源 fallback：ip-api.com → pconline → taobao
 * @param string $ip IP 地址
 * @return string 中文省份名或“未知/本地”
 */
function get_ip_region($ip = '') {
    if (empty($ip)) {
        $ip = function_exists('get_client_ip') ? get_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0');
    }
    // 本地/内网 IP
    if (in_array($ip, ['127.0.0.1', 'localhost', '::1']) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return '本地';
    }

    $cnProvinces = ['北京','天津','上海','重庆','河北','山西','辽宁','吉林','黑龙江','江苏','浙江','安徽','福建','江西','山东','河南','湖北','湖南','广东','海南','四川','贵州','云南','陕西','甘肃','青海','台湾','内蒙古','广西','西藏','宁夏','新疆','香港','澳门'];

    $extractProvince = function($str) use ($cnProvinces) {
        foreach ($cnProvinces as $p) {
            if (mb_strpos($str, $p) !== false) return normalize_province_name($p);
        }
        return '';
    };

    // 源1: ip-api.com (HTTPS)
    $res1 = http_get('https://ip-api.com/json/' . urlencode($ip) . '?lang=zh-CN&fields=status,regionName', 3);
    if ($res1) {
        $json = json_decode($res1, true);
        if (!empty($json['status']) && $json['status'] === 'success' && !empty($json['regionName'])) {
            $p = $extractProvince($json['regionName']);
            return $p ?: normalize_province_name($json['regionName']);
        }
    }

    // 源2: pconline（国内稳定，GBK 编码）
    $res2 = http_get('https://whois.pconline.com.cn/ipJson.jsp?ip=' . urlencode($ip) . '&json=true', 3);
    if ($res2) {
        $res2 = @iconv('GBK', 'UTF-8//TRANSLIT', $res2);
        if ($res2) {
            $json2 = json_decode($res2, true);
            if (!empty($json2['pro'])) {
                $p = $extractProvince($json2['pro']);
                return $p ?: normalize_province_name($json2['pro']);
            }
        }
    }

    return '未知';
}

/**
 * 将常见短省份名标准化为 ECharts 中国地图使用的全称
 * @param string $name 省份短名（如“广东”）
 * @return string 全称（如“广东省”），无法识别则原样返回
 */
function normalize_province_name($name) {
    $map = [
        '北京' => '北京市', '天津' => '天津市', '上海' => '上海市', '重庆' => '重庆市',
        '河北' => '河北省', '山西' => '山西省', '辽宁' => '辽宁省', '吉林' => '吉林省',
        '黑龙江' => '黑龙江省', '江苏' => '江苏省', '浙江' => '浙江省', '安徽' => '安徽省',
        '福建' => '福建省', '江西' => '江西省', '山东' => '山东省', '河南' => '河南省',
        '湖北' => '湖北省', '湖南' => '湖南省', '广东' => '广东省', '海南' => '海南省',
        '四川' => '四川省', '贵州' => '贵州省', '云南' => '云南省', '陕西' => '陕西省',
        '甘肃' => '甘肃省', '青海' => '青海省', '台湾' => '台湾省',
        '内蒙古' => '内蒙古自治区', '广西' => '广西壮族自治区', '西藏' => '西藏自治区',
        '宁夏' => '宁夏回族自治区', '新疆' => '新疆维吾尔自治区',
        '香港' => '香港特别行政区', '澳门' => '澳门特别行政区',
    ];
    return isset($map[$name]) ? $map[$name] : $name;
}

/**
 * 获取后台待处理工单列表（state 1/2）
 * @param int $limit 返回条数
 * @return array
 */
function admin_pending_tickets($limit = 5) {
    try {
        $rows = \think\Db::name('ticket')
            ->where('state', 'in', ['1', '2'])
            ->order('id desc')
            ->limit(intval($limit))
            ->select();
        $users = [];
        if (!empty($rows)) {
            $userIds = array_unique(array_filter(array_column($rows, 'userid')));
            if (!empty($userIds)) {
                foreach (\think\Db::name('user')->where('id', 'in', $userIds)->column('name', 'id') as $uid => $uname) {
                    $users[$uid] = $uname;
                }
            }
        }
        foreach ($rows as &$row) {
            $row['username'] = isset($users[$row['userid']]) ? $users[$row['userid']] : ('用户#' . $row['userid']);
        }
        return $rows;
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * 获取待审核实名认证列表（后台通知用）
 * @param int $limit 返回条数
 * @return array
 */
function admin_pending_realname($limit = 5) {
    try {
        $rows = \think\Db::name('user')
            ->where('realname_status', '3')
            ->order('id desc')
            ->limit(intval($limit))
            ->field('id, name, realname, realname_status')
            ->select();
        return $rows ?: [];
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * 实名认证二要素核验（API 模式）
 * 对接花迹数据（huajidata.com）身份证二要素验证接口
 * 接口URL: https://api.huajidata.com/id_name/check
 * 签名方式: md5(appid + timestamp + appkey)
 * @param string $name 真实姓名
 * @param string $idcard 身份证号
 * @return array ['code'=>1|-1, 'msg'=>'', 'api_result'=>?]
 */
function realname_api_verify($name, $idcard, $mobile = '') {
    $logDir = defined('LOG_PATH') ? LOG_PATH : (PATH . '/runtime/log/');
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    try {
        @file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " realname_api_verify start name={$name}\n", FILE_APPEND);
        $web = web_config();
        $apiType = isset($web['realname_api_type']) ? $web['realname_api_type'] : '1';
        @file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " apiType={$apiType}\n", FILE_APPEND);
        if ($apiType == '2') {
            $secretId = isset($web['realname_secret_id']) ? $web['realname_secret_id'] : '';
            $secretKey = isset($web['realname_secret_key']) ? $web['realname_secret_key'] : '';
            @file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " call cloudmarket secretId len=" . strlen($secretId) . " secretKey len=" . strlen($secretKey) . "\n", FILE_APPEND);
            return cloudmarket_realname_verify($name, $idcard, $secretId, $secretKey);
        }
        if ($apiType == '3') {
            $secretId = isset($web['realname_secret_id']) ? $web['realname_secret_id'] : '';
            $secretKey = isset($web['realname_secret_key']) ? $web['realname_secret_key'] : '';
            @file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " call phone3element secretId len=" . strlen($secretId) . " secretKey len=" . strlen($secretKey) . " mobile={$mobile}\n", FILE_APPEND);
            return phone3element_verify($name, $idcard, $mobile, $secretId, $secretKey);
        }
        $appid = isset($web['realname_api_appid']) ? $web['realname_api_appid'] : '';
        $appkey = isset($web['realname_api_appkey']) ? $web['realname_api_appkey'] : '';
        return realname_api_verify_with_key($name, $idcard, $appid, $appkey);
    } catch (\Throwable $e) {
        @file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " realname_api_verify error: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        return ['code' => -1, 'msg' => '实名认证调用异常：' . $e->getMessage()];
    }
}

function realname_api_verify_with_key($name, $idcard, $appid, $appkey) {
    if (empty($name) || empty($idcard)) {
        return ['code' => -1, 'msg' => '姓名和身份证号不能为空'];
    }
    if (empty($appid) || empty($appkey)) {
        return ['code' => -1, 'msg' => '实名认证API未配置，请联系管理员'];
    }
    // 与花迹数据官方 PHP 示例保持一致：timestamp = time() + '000'
    $timestamp = time() . '000';
    $signStr = $appid . $timestamp . $appkey;
    $sign = md5($signStr);
    $url = 'https://api.huajidata.com/id_name/check';
    $bodyParams = [
        'appid' => $appid,
        'timestamp' => $timestamp,
        'sign' => $sign,
        'idcard' => $idcard,
        'name' => $name,
    ];
    $postData = http_build_query($bodyParams);

    $res = false;
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($res === false || $err) {
            return ['code' => -1, 'msg' => 'API 接口请求失败：' . ($err ?: '网络错误')];
        }
    } else {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($postData),
                'content' => $postData,
                'timeout' => 60,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $res = @file_get_contents($url, false, $ctx);
        if ($res === false) {
            return ['code' => -1, 'msg' => 'API 接口请求失败：网络不可达'];
        }
    }
    $data = json_decode($res, true);
    if (!$data || !isset($data['code'])) {
        return ['code' => -1, 'msg' => 'API 返回数据异常'];
    }
    if ($data['code'] != 200) {
        $errMsg = isset($data['msg']) ? $data['msg'] : '未知错误';
        // 余额不足
        if ($data['code'] == 1003) {
            return ['code' => -1, 'msg' => '实名认证服务余额不足，请联系管理员充值'];
        }
        // 账号/签名错误（花迹数据返回 code=400，msg 含"错误码:4000"）
        if ($data['code'] == 400 || stripos($errMsg, '4000') !== false) {
            return ['code' => -1, 'msg' => '实名认证 API 账号或密钥错误（错误码：4000），请管理员检查后台 AppID / AppKey 是否正确'];
        }
        // 账号停用
        if ($data['code'] == 1002) {
            return ['code' => -1, 'msg' => '实名认证 API 账号已停用，请联系服务商'];
        }
        // 无接口权限
        if ($data['code'] == 1004) {
            return ['code' => -1, 'msg' => '实名认证 API 接口权限未开通，请联系服务商'];
        }
        // 接口已停用
        if ($data['code'] == 1005) {
            return ['code' => -1, 'msg' => '实名认证 API 接口已停用，请联系服务商'];
        }
        return ['code' => -1, 'msg' => '实名认证失败：' . $errMsg];
    }
    $result = isset($data['data']['result']) ? intval($data['data']['result']) : 0;
    if ($result === 1) {
        return ['code' => 1, 'msg' => '实名认证通过'];
    }
    if ($result === 2) {
        return ['code' => -1, 'msg' => '实名认证不通过：姓名与身份证号不匹配'];
    }
    if ($result === 3) {
        return ['code' => -1, 'msg' => '实名认证不通过：身份证信息库无记录'];
    }
    return ['code' => -1, 'msg' => '实名认证失败：' . (isset($data['data']['message']) ? $data['data']['message'] : '未知结果')];
}

function cloudmarket_realname_verify($name, $idcard, $secretId, $secretKey) {
    $logDir = defined('LOG_PATH') ? LOG_PATH : (PATH . '/runtime/log/');
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " cloudmarket start name={$name} idcard={$idcard}\n", FILE_APPEND);
    if (empty($name) || empty($idcard)) {
        return ['code' => -1, 'msg' => '姓名和身份证号不能为空'];
    }
    if (empty($secretId) || empty($secretKey)) {
        return ['code' => -1, 'msg' => '云市场实名认证API未配置，请联系管理员'];
    }
    if (!function_exists('hash_hmac')) {
        return ['code' => -1, 'msg' => '服务器 PHP 未启用 hash 扩展，无法调用云市场 API'];
    }
    if (!function_exists('curl_init')) {
        return ['code' => -1, 'msg' => '服务器未启用 cURL 扩展，无法调用云市场 API'];
    }
    try {
        $datetime = gmdate('D, d M Y H:i:s T');
        $signStr = sprintf("x-date: %s", $datetime);
        $sign = base64_encode(hash_hmac('sha1', $signStr, $secretKey, true));
        $auth = sprintf('{"id": "%s", "x-date": "%s" , "signature": "%s"}', $secretId, $datetime, $sign);

        $method = 'POST';
        $requestId = strtolower(md5(uniqid(mt_rand(), true)));
        $headers = array(
            'Authorization' => $auth,
            'request-id' => $requestId,
            'X-Requested-With' => 'XMLHttpRequest',
        );
        $queryParams = array();
        $bodyParams = array(
            'idcard' => $idcard,
            'name' => $name,
        );
        $sendData = http_build_query($bodyParams);
        $url = 'https://ap-shanghai.cloudmarket-apigw.com/service-hvx9h497/id_name/verify';
        if (count($queryParams) > 0) {
            $url .= '?' . http_build_query($queryParams);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if (in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $sendData);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($v, $k) {
            return $k . ': ' . $v;
        }, array_values($headers), array_keys($headers)));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $res = curl_exec($ch);
        $err = '';
        if (curl_errno($ch)) {
            $err = curl_error($ch);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        @file_put_contents($logDir . 'realname_cloudmarket.log', date('Y-m-d H:i:s') . "\nURL: {$url}\nrequest-id: {$requestId}\nAuth: {$auth}\nBody: {$sendData}\nHTTP: {$httpCode}\nError: {$err}\nResponse: " . ($res !== false ? $res : '(empty)') . "\n--------------------\n", FILE_APPEND);

        if ($err) {
            return ['code' => -1, 'msg' => 'API 接口请求失败：' . $err];
        }
        if ($res === false || $res === '') {
            return ['code' => -1, 'msg' => 'API 接口请求失败：网络错误（HTTP ' . $httpCode . '）'];
        }
        $data = json_decode($res, true);
        if (!$data || !isset($data['code'])) {
            $raw = $res ? substr($res, 0, 500) : '(empty)';
            return ['code' => -1, 'msg' => 'API 返回数据异常（HTTP ' . $httpCode . '），原始响应：' . $raw];
        }
        if ($data['code'] != 200) {
            $errMsg = isset($data['msg']) && $data['msg'] !== '' ? $data['msg'] : '未知错误';
            if ($data['code'] == 400) {
                return ['code' => -1, 'msg' => '实名认证请求参数错误：' . $errMsg];
            }
            if ($data['code'] == 500) {
                return ['code' => -1, 'msg' => '实名认证服务系统异常，请联系服务商'];
            }
            if ($data['code'] == 1000) {
                return ['code' => -1, 'msg' => '实名认证服务异常：' . $errMsg];
            }
            if ($data['code'] == 9999) {
                return ['code' => -1, 'msg' => '实名认证核验中心异常，请稍后重试'];
            }
            return ['code' => -1, 'msg' => '实名认证失败：' . $errMsg];
        }
        $result = isset($data['data']['result']) ? intval($data['data']['result']) : 0;
        $message = isset($data['data']['message']) ? $data['data']['message'] : '';
        if ($result === 1) {
            return ['code' => 1, 'msg' => '实名认证通过'];
        }
        if ($result === 2) {
            return ['code' => -1, 'msg' => '实名认证不通过：姓名与身份证号不匹配'];
        }
        if ($result === 3) {
            return ['code' => -1, 'msg' => '实名认证不通过：身份证信息库无记录'];
        }
        $failMsg = $message ?: '未知结果';
        return ['code' => -1, 'msg' => '实名认证失败：' . $failMsg];
    } catch (\Throwable $e) {
        @file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " cloudmarket throwable: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        return ['code' => -1, 'msg' => '实名认证调用异常：' . $e->getMessage()];
    }
}

/**
 * 手机三要素核验（腾讯云市场 API）
 * 接口: https://ap-beijing.cloudmarket-apigw.com/service-4epp7bin/phone3element
 * @param string $name 真实姓名
 * @param string $idcard 身份证号
 * @param string $mobile 手机号码
 * @param string $secretId 云市场 SecretId
 * @param string $secretKey 云市场 SecretKey
 * @return array ['code'=>1|-1, 'msg'=>'']
 */
function phone3element_verify($name, $idcard, $mobile, $secretId, $secretKey) {
    $logDir = defined('LOG_PATH') ? LOG_PATH : (PATH . '/runtime/log/');
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    @file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " phone3element start name={$name} mobile={$mobile}\n", FILE_APPEND);
    if (empty($name) || empty($idcard) || empty($mobile)) {
        return ['code' => -1, 'msg' => '姓名、身份证号和手机号不能为空'];
    }
    if (empty($secretId) || empty($secretKey)) {
        return ['code' => -1, 'msg' => '云市场 API 未配置，请联系管理员'];
    }
    if (!function_exists('hash_hmac')) {
        return ['code' => -1, 'msg' => '服务器 PHP 未启用 hash 扩展'];
    }
    if (!function_exists('curl_init')) {
        return ['code' => -1, 'msg' => '服务器未启用 cURL 扩展'];
    }
    try {
        $datetime = gmdate('D, d M Y H:i:s T');
        $signStr = sprintf("x-date: %s", $datetime);
        $sign = base64_encode(hash_hmac('sha1', $signStr, $secretKey, true));
        $auth = sprintf('{"id": "%s", "x-date": "%s" , "signature": "%s"}', $secretId, $datetime, $sign);

        $requestId = strtolower(md5(uniqid(mt_rand(), true)));
        $headers = array(
            'Authorization' => $auth,
            'request-id' => $requestId,
            'X-Requested-With' => 'XMLHttpRequest',
            'Content-Type' => 'application/x-www-form-urlencoded',
        );
        $bodyParams = array(
            'idCard' => $idcard,
            'mobile' => $mobile,
            'realName' => $name,
        );
        $sendData = http_build_query($bodyParams);
        $url = 'https://ap-beijing.cloudmarket-apigw.com/service-4epp7bin/phone3element';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sendData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($v, $k) {
            return $k . ': ' . $v;
        }, array_values($headers), array_keys($headers)));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $res = curl_exec($ch);
        $err = '';
        if (curl_errno($ch)) {
            $err = curl_error($ch);
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        @file_put_contents($logDir . 'realname_phone3e.log', date('Y-m-d H:i:s') . "\nURL: {$url}\nrequest-id: {$requestId}\nAuth: {$auth}\nBody: {$sendData}\nHTTP: {$httpCode}\nError: {$err}\nResponse: " . ($res !== false ? $res : '(empty)') . "\n--------------------\n", FILE_APPEND);

        if ($err) {
            return ['code' => -1, 'msg' => 'API 请求失败：' . $err];
        }
        if ($res === false || $res === '') {
            return ['code' => -1, 'msg' => 'API 请求失败：网络错误（HTTP ' . $httpCode . '）'];
        }
        $data = json_decode($res, true);
        if (!$data) {
            $raw = $res ? substr($res, 0, 500) : '(empty)';
            return ['code' => -1, 'msg' => 'API 返回数据异常，原始响应：' . $raw];
        }
        if (!isset($data['error_code'])) {
            return ['code' => -1, 'msg' => 'API 返回格式异常：' . (isset($data['reason']) ? $data['reason'] : json_encode($data))];
        }
        if ($data['error_code'] != 0) {
            $reason = isset($data['reason']) ? $data['reason'] : '未知错误';
            return ['code' => -1, 'msg' => '手机三要素核验失败：' . $reason . ' (error_code=' . $data['error_code'] . ')'];
        }
        $result = isset($data['result']['VerificationResult']) ? $data['result']['VerificationResult'] : '';
        if ($result === '1') {
            return ['code' => 1, 'msg' => '实名认证通过'];
        }
        if ($result === '-1') {
            return ['code' => -1, 'msg' => '实名认证不通过：手机号、姓名、身份证号不匹配'];
        }
        if ($result === '0') {
            return ['code' => -1, 'msg' => '实名认证不通过：运营商系统中无此手机号记录'];
        }
        return ['code' => -1, 'msg' => '实名认证失败：未知结果'];
    } catch (\Throwable $e) {
        @file_put_contents($logDir . 'realname_debug.log', date('Y-m-d H:i:s') . " phone3element throwable: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        return ['code' => -1, 'msg' => '实名认证调用异常：' . $e->getMessage()];
    }
}

/**
 * 检测当前登录用户是否通过实名认证，未通过时返回限制提示
 * @param string $type 限制类型：pay(充值) / buy(购买主机) / ticket(提交工单) / renew(续费)
 * @return array ['code'=>1] 表示通过，['code'=>-2, 'msg'=>'...'] 表示未实名被限制
 */
function check_realname_limit($type = '') {
    $web = web_config();
    $mode = isset($web['realname_mode']) ? $web['realname_mode'] : '0';
    // 未开启实名认证时不限制
    if ($mode == '0') {
        return ['code' => 1];
    }
    // 检查后台是否对该功能开启限制
    $key = 'realname_limit_' . $type;
    if (!isset($web[$key]) || $web[$key] != '1') {
        return ['code' => 1];
    }
    $userId = session('userid');
    if (!$userId) {
        return ['code' => -1, 'msg' => '请先登录'];
    }
    $user = \think\Db::name('user')->where('id', $userId)->find();
    if (!$user) {
        return ['code' => -1, 'msg' => '用户不存在'];
    }
    // 已认证通过
    if (isset($user['realname_status']) && $user['realname_status'] == '1') {
        return ['code' => 1];
    }
    $typeText = [
        'pay' => '充值',
        'buy' => '购买主机',
        'ticket' => '提交工单',
        'renew' => '续费主机',
    ];
    $text = isset($typeText[$type]) ? $typeText[$type] : '此操作';
    // 待审核与未认证统一使用 -2，便于前端弹出实名认证提示框
    if (isset($user['realname_status']) && $user['realname_status'] == '3') {
        return ['code' => -2, 'msg' => '实名认证正在审核中，暂时无法进行' . $text];
    }
    return ['code' => -2, 'msg' => '请先完成实名认证后再进行' . $text];
}

/**
 * Sanitize email body content
 */
function sanitize_email_body($body) {
    // Allow only safe HTML tags
    $allowed = '<br><br/><p><b><strong><i><em><span><div><a><hr><hr/><ul><ol><li><table><tr><td><th><thead><tbody><h1><h2><h3><h4><h5><h6><img>';
    return strip_tags($body, $allowed);
}

/**
 * 检测是否为临时/一次性邮箱（10分钟邮箱等）
 * @param string $email 邮箱地址
 * @return bool true=是临时邮箱
 */
function is_disposable_email($email) {
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    if (!$domain) return false;
    $disposableDomains = [
        '0-mail.com', '0815.ru', '0wnd.net', '0wnd.org', '10minutemail.com',
        '10minutemail.info', '10minutemail.net', '10minutemail.org', '20minutemail.com',
        '20minutemail.it', '30minutemail.com', '33mail.com', '3d-painting.com',
        '4warding.com', '4warding.net', '4warding.org', '60minutemail.com',
        '675hosting.com', '675hosting.net', '675hosting.org', '6url.com',
        '75hosting.com', '75hosting.net', '75hosting.org', '7tags.com',
        '9ox.net', 'a-bc.net', 'abyssmail.com', 'afrobacon.com',
        'ajaxapp.net', 'amilegit.com', 'amiri.net', 'amiriindustries.com',
        'anonbox.net', 'anonymbox.com', 'antichef.com', 'antichef.net',
        'antireg.ru', 'antispam.de', 'antispammail.de', 'armyspy.com',
        'artman-conception.com', 'azmeil.tk', 'baxomale.ht.cx', 'beddly.com',
        'bigstring.com', 'binkmail.com', 'bio-muesli.info', 'bobmail.info',
        'bodhi.lawlita.com', 'bofthew.com', 'bootybay.de', 'boun.cr',
        'bouncr.com', 'breakthru.com', 'brefmail.com', 'bsnow.net',
        'bugmenever.com', 'bumpymail.com', 'bund.us', 'burstmail.info',
        'buymoreplays.com', 'byom.de', 'c2.hu', 'card.zp.ua',
        'casualdx.com', 'cek.pm', 'chammy.info', 'cheatmail.de',
        'chogmail.com', 'choicemail1.com', 'clixser.com', 'clrmail.com',
        'cmail.com', 'cmail.net', 'cmail.org', 'coldemail.info',
        'cool.fr.nf', 'courriel.fr.nf', 'courrieltemporaire.com', 'crapmail.org',
        'cust.in', 'cuvox.de', 'd3p.dk', 'dacoolest.com',
        'dandikmail.com', 'dayrep.com', 'dbunker.com', 'dcemail.com',
        'deadaddress.com', 'deadspam.com', 'delikkt.de', 'despam.it',
        'devnullmail.com', 'dfgh.net', 'digitalsanctuary.com', 'dingbone.com',
        'discard.email', 'discardmail.com', 'discardmail.de', 'disposableaddress.com',
        'disposableemailaddresses.com', 'disposableinbox.com', 'dispose.it', 'dispostable.com',
        'dmarc.ro', 'dodgeit.com', 'dodgit.com', 'dodgit.org',
        'donemail.ru', 'dontreg.com', 'dontsendmespam.de', 'dotmsg.com',
        'drdrb.com', 'drdrb.net', 'dropmail.me', 'dt.com',
        'duam.net', 'dudmail.com', 'dump-email.info', 'dumpandjunk.com',
        'dumpmail.de', 'dumpyemail.com', 'e-mail.com', 'e-mail.org',
        'e4ward.com', 'easytrashmail.com', 'einmalmail.de', 'einrot.com',
        'eintagsmail.de', 'email60.com', 'emailfake.com', 'emailgo.de',
        'emailias.com', 'emailigo.de', 'emaillime.com', 'emailmiser.com',
        'emailna.co', 'emailproxsy.com', 'emails.ga', 'emailsensei.com',
        'emailtemporanea.com', 'emailtemporanea.net', 'emailtemporar.ro', 'emailtemporario.com.br',
        'emailthe.net', 'emailtmp.com', 'emailto.de', 'emailwarden.com',
        'emailx.at.hm', 'emailxfer.com', 'emz.net', 'enterto.com',
        'ephemail.net', 'ero-tube.org', 'etranquil.com', 'etranquil.net',
        'etranquil.org', 'evopo.com', 'explodemail.com', 'express.net.ua',
        'eyepaste.com', 'fakeinbox.com', 'fakeinformation.com', 'fakemail.fr',
        'fakemailz.com', 'fammix.com', 'fastacura.com', 'fastchevy.com',
        'fastchrysler.com', 'fasternet.biz', 'fastkawasaki.com', 'fastmazda.com',
        'fastmitsubishi.com', 'fastnissan.com', 'fastsubaru.com', 'fastsuzuki.com',
        'fasttoyota.com', 'fastyamaha.com', 'fatflap.com', 'fdfdsfds.com',
        'fightallspam.com', 'fiifke.de', 'filzmail.com', 'fivemail.de',
        'fixmail.tk', 'fizmail.com', 'fleckens.hu', 'flemail.ru',
        'flyspam.com', 'footard.com', 'forgetmail.com', 'fr33mail.info',
        'frapmail.com', 'free-email-addresses.info', 'freemail.ms', 'freundin.ru',
        'friendlymail.co.uk', 'front14.org', 'fuckingduh.com', 'fudgerub.com',
        'fux0ringduh.com', 'fw2.me', 'fw6m0bd.com', 'fyii.de',
        'garliclife.com', 'gehensiemirnichtaufdenkeks.de', 'gelitik.in', 'get-mail.info',
        'get1mail.com', 'get2mail.fr', 'getairmail.com', 'getmails.eu',
        'getonemail.com', 'getonemail.net', 'ghosttexter.de', 'giantmail.de',
        'girlsundertheinfluence.com', 'gishpuppy.com', 'gmial.com', 'goemailgo.com',
        'gorillaswithdirtyarmpits.com', 'gotmail.com', 'gotmail.net', 'gotmail.org',
        'gotti.otherinbox.com', 'gowikibooks.com', 'gowikicampus.com', 'gowikicars.com',
        'gowikifilms.com', 'gowikigames.com', 'gowikimusic.com', 'gowikinetwork.com',
        'gowikitravel.com', 'gowikitv.com', 'grandmamail.com', 'grandmasmail.com',
        'great-host.in', 'greensloth.com', 'grr.la', 'gsrv.co.uk',
        'guerrillamail.biz', 'guerrillamail.com', 'guerrillamail.de', 'guerrillamail.info',
        'guerrillamail.net', 'guerrillamail.org', 'guerrillamailblock.com', 'gustr.com',
        'h.mintemail.com', 'h8s.org', 'hacccc.com', 'haltospam.com',
        'harakirimail.com', 'hartbot.de', 'hatespam.org', 'herp.in',
        'hidemail.de', 'hidzz.com', 'hmamail.com', 'hochsitze.com',
        'hopemail.biz', 'hotpop.com', 'hulapla.de', 'iaoss.com',
        'ieatspam.eu', 'ieatspam.info', 'ieh-mail.de', 'ihateyoualot.info',
        'iheartspam.org', 'imails.info', 'imgof.com', 'imstations.com',
        'inbax.tk', 'inbox.si', 'incognitomail.com', 'incognitomail.net',
        'incognitomail.org', 'insorg-mail.info', 'instant-mail.de', 'ip6.li',
        'irish2me.com', 'iwi.net', 'jetable.com', 'jetable.fr.nf',
        'jetable.net', 'jetable.org', 'jnxjn.com', 'jourrapide.com',
        'jsrsolutions.com', 'junk1e.com', 'kasmail.com', 'kaspop.com',
        'keepmymail.com', 'killmail.com', 'killmail.net', 'kimsdisk.com',
        'kingsq.ga', 'kir.ch.tc', 'klassmaster.com', 'klassmaster.net',
        'klzlk.com', 'koszmail.pl', 'kulturbetrieb.info', 'kurzepost.de',
        'l33r.eu', 'lackmail.net', 'lackmail.ru', 'lags.us',
        'lawlita.com', 'lazyinbox.com', 'letthemeatspam.com', 'lhsdv.com',
        'lifebyfood.com', 'link2mail.net', 'litedrop.com', 'loadby.us',
        'login-email.ml', 'lol.ovpn.to', 'lookugly.com', 'lopl.co.cc',
        'lortemail.dk', 'lovemeet.com', 'lr78.com', 'lroid.com',
        'lukop.dk', 'm21.cc', 'm4ilweb.info', 'maboard.com',
        'mail-filter.com', 'mail-temporaire.fr', 'mail.by', 'mail.mezimages.net',
        'mail.zp.ua', 'mail114.net', 'mail1a.de', 'mail21.cc',
        'mail2rss.org', 'mail333.com', 'mail4trash.com', 'mailbidon.com',
        'mailbiscuit.com', 'mailcatch.com', 'mailde.de', 'mailde.info',
        'maildrop.cc', 'maildx.com', 'maileater.com', 'mailed.ro',
        'maileimer.de', 'mailexpire.com', 'mailf5.com', 'mailfa.tk',
        'mailfall.com', 'mailforspam.com', 'mailfree.ga', 'mailfree.gq',
        'mailfree.ml', 'mailfs.com', 'mailguard.me', 'mailgutter.com',
        'mailhazard.com', 'mailhazard.us', 'mailhz.me', 'mailimate.com',
        'mailin8r.com', 'mailinater.com', 'mailinator.com', 'mailinator.net',
        'mailinator.org', 'mailinator2.com', 'mailincubator.com', 'mailismagic.com',
        'mailita.tk', 'mailjunk.com', 'mailmate.com', 'mailme.ir',
        'mailme.lv', 'mailmetrash.com', 'mailmoat.com', 'mailms.com',
        'mailnator.com', 'mailnesia.com', 'mailnull.com', 'mailorg.org',
        'mailpick.biz', 'mailproxsy.com', 'mailquack.com', 'mailrock.biz',
        'mailscrap.com', 'mailseal.de', 'mailshell.com', 'mailshiv.com',
        'mailsiphon.com', 'mailslapping.com', 'mailslite.com', 'mailtemp.info',
        'mailtome.de', 'mailtothis.com', 'mailtrash.net', 'mailtv.net',
        'mailtv.tv', 'mailzi.ru', 'mailzilla.com', 'mailzilla.org',
        'makemetheking.com', 'manifestgenerator.com', 'manybrain.com', 'mbx.cc',
        'mciek.com', 'mega.zik.dj', 'meinspamschutz.de', 'meltmail.com',
        'messagebeamer.de', 'mezimages.net', 'mfsa.ru', 'mierdamail.com',
        'migmail.net', 'migmail.pl', 'migumail.com', 'mintemail.com',
        'mjukglass.nu', 'moakt.com', 'mobi.web.id', 'mobileninja.co.uk',
        'moburl.com', 'mohmal.com', 'moncourrier.fr.nf', 'monemail.fr.nf',
        'monmail.fr.nf', 'monumentmail.com', 'mor19.uu.gl', 'msa.minsmail.com',
        'mt2009.com', 'mt2014.com', 'mx0.wwwnew.eu', 'my10minutemail.com',
        'mycard.net.ua', 'mycleaninbox.net', 'myemailboxy.com', 'mymail-in.net',
        'mymailoasis.com', 'mynetstore.de', 'mypacks.net', 'mypartyclip.de',
        'myphantomemail.com', 'mysamp.de', 'myspaceinc.com', 'myspaceinc.net',
        'myspaceinc.org', 'myspacepimpedup.com', 'myspamless.com', 'mytemp.email',
        'mytempemail.com', 'mytempmail.com', 'n1nja.org', 'n8.gs',
        'nepwk.com', 'nervmich.net', 'nervtmich.net', 'netmails.com',
        'netmails.net', 'netzidiot.de', 'neverbox.com', 'nice-4u.com',
        'nincsmail.com', 'nincsmail.hu', 'nneko.com', 'no-spam.ws',
        'noblepioneer.com', 'nobulk.com', 'noclickemail.com', 'nogmailspam.info',
        'nomail.pw', 'nomail2me.com', 'nomorespamemails.com', 'nonspam.eu',
        'nonspammer.de', 'noref.in', 'nospam.ze.tc', 'nospam4.us',
        'nospamfor.us', 'nospamthanks.info', 'notmailinator.com', 'nowmymail.com',
        'nurfuerspam.de', 'nus.edu.sg', 'nwldx.com', 'objectmail.com',
        'obobbo.com', 'odaymail.com', 'odnorazovoe.ru', 'one-time.email',
        'oneoffemail.com', 'oneoffmail.com', 'onewaymail.com', 'onlatedotcom.info',
        'online.ms', 'oopi.org', 'opayq.com', 'ordinaryamerican.net',
        'otherinbox.com', 'ourklips.com', 'outlawspam.com', 'ovpn.to',
        'owlpic.com', 'pancakemail.com', 'paplease.com', 'pcusers.otherinbox.com',
        'pepbot.com', 'pfui.ru', 'pimpedupmyspace.com', 'pjjkp.com',
        'plexolan.de', 'poczta.onet.pl', 'politikerclub.de', 'pooae.com',
        'pookmail.com', 'privacy.net', 'privy-mail.com', 'privymail.de',
        'proxymail.eu', 'prtnx.com', 'punkass.com', 'putthisinyourspamdatabase.com',
        'pwrby.com', 'qasti.com', 'quickinbox.com', 'quickmail.nl',
        'rcpt.at', 'reallymymail.com', 'realtyalerts.ca', 'recode.me',
        'recursor.net', 'regbypass.com', 'regbypass.comsafe-mail.net', 'rejectmail.com',
        'rklips.com', 'rmqkr.net', 'royal.net', 'rppkn.com',
        'rtrtr.com', 's0ny.net', 'safe-mail.net', 'safersignup.de',
        'safetymail.info', 'safetypost.de', 'sandelf.de', 'saynotospams.com',
        'schafmail.de', 'schrott-email.de', 'secretemail.de', 'secure-mail.biz',
        'senseless-entertainment.com', 'services391.com', 'sharklasers.com', 'shieldemail.com',
        'shiftmail.com', 'shitmail.me', 'shitmail.org', 'shitware.nl',
        'shmeriously.com', 'shortmail.net', 'shotmail.ru', 'showslow.de',
        'sibmail.com', 'sinnlos-mail.de', 'siteposter.net', 'skeefmail.com',
        'slapsfromlastnight.com', 'slaskpost.se', 'slipry.net', 'slopsbox.com',
        'slushmail.com', 'smaakt.naar.gravel', 'smashmail.de', 'smellfear.com',
        'snakemail.com', 'sneakemail.com', 'sneakmail.de', 'snkmail.com',
        'sofimail.com', 'sofort-mail.de', 'softpls.asia', 'sogetthis.com',
        'soodonims.com', 'spam.la', 'spam.su', 'spam4.me',
        'spamail.de', 'spamarrest.com', 'spamavert.com', 'spambob.com',
        'spambob.net', 'spambob.org', 'spambog.com', 'spambog.de',
        'spambog.net', 'spambog.ru', 'spambox.info', 'spambox.irishspringrealty.com',
        'spambox.us', 'spamcannon.com', 'spamcannon.net', 'spamcero.com',
        'spamcon.org', 'spamcorptastic.com', 'spamcowboy.com', 'spamcowboy.net',
        'spamcowboy.org', 'spamday.com', 'spamex.com', 'spamfighter.cf',
        'spamfighter.ga', 'spamfighter.gq', 'spamfighter.ml', 'spamfighter.tk',
        'spamfree.eu', 'spamfree24.com', 'spamfree24.de', 'spamfree24.eu',
        'spamfree24.info', 'spamfree24.net', 'spamfree24.org', 'spamgoose.xyz',
        'spamgourmet.com', 'spamgourmet.net', 'spamgourmet.org', 'spamherelots.com',
        'spamhole.com', 'spamify.com', 'spaminator.de', 'spamkill.info',
        'spaml.com', 'spaml.de', 'spamlot.net', 'spammotel.com',
        'spamobox.com', 'spamoff.de', 'spamsalad.in', 'spamslicer.com',
        'spamspot.com', 'spamstack.net', 'spamthis.co.uk', 'spamthisplease.com',
        'spamtrail.com', 'spamtrap.ro', 'spamtroll.net', 'speed.1s.fr',
        'spikio.com', 'spoofmail.de', 'squizzy.de', 'ssoia.com',
        'startkeys.com', 'stinkefinger.net', 'stop-my-spam.com', 'stuffmail.de',
        'super-auswahl.de', 'supergreatmail.com', 'supermailer.jp', 'superrito.com',
        'superstachel.de', 'suremail.info', 'svk.jp', 'sweetxxx.de',
        't.odour.fr', 'talkinator.com', 'tapchicuoihoi.com', 'teewars.org',
        'teleworm.com', 'teleworm.us', 'temp-mail.com', 'temp-mail.de',
        'temp-mail.org', 'temp-mail.ru', 'temp.emeraldwebmail.com', 'temp15mail.com',
        'tempail.com', 'tempalias.com', 'tempe-mail.com', 'tempemail.biz',
        'tempemail.co.za', 'tempemail.com', 'tempemail.net', 'tempinbox.co.uk',
        'tempinbox.com', 'tempmail.co', 'tempmail.de', 'tempmail.eu',
        'tempmail.it', 'tempmail.org', 'tempmail.us', 'tempmail.ws',
        'tempmail2.com', 'tempmaildemo.com', 'tempmailer.com', 'tempmailer.de',
        'tempomail.fr', 'temporarily.de', 'temporarioemail.com.br', 'temporaryemail.net',
        'temporaryemail.us', 'temporaryforwarding.com', 'temporaryinbox.com', 'temporarymailaddress.com',
        'tempr.email', 'tempsky.com', 'tempthe.net', 'tempymail.com',
        'thanksnospam.info', 'thankyou2010.com', 'thc.st', 'thelimestones.com',
        'thisisnotmyrealemail.com', 'thismail.net', 'throwawayemailaddress.com', 'tilien.com',
        'tittbit.in', 'tmail.ws', 'tmailinator.com', 'toiea.com',
        'toomail.biz', 'topranklist.de', 'tradermail.info', 'trash-amil.com',
        'trash-mail.at', 'trash-mail.com', 'trash-mail.de', 'trash2009.com',
        'trashemail.de', 'trashmail.at', 'trashmail.com', 'trashmail.de',
        'trashmail.me', 'trashmail.net', 'trashmail.org', 'trashmail.ws',
        'trashmailer.com', 'trashymail.com', 'trashymail.net', 'trbvm.com',
        'trialmail.de', 'trillianpro.com', 'tryalert.com', 'turual.com',
        'twinmail.de', 'tyldd.com', 'uggsrock.com', 'umail.net',
        'upliftnow.com', 'uplipht.com', 'uroid.com', 'us.af',
        'venompen.com', 'veryrealemail.com', 'vidchart.com', 'viditag.com',
        'viewcastmedia.com', 'viewcastmedia.net', 'viewcastmedia.org', 'viralplays.com',
        'vomoto.com', 'vpn.st', 'vps30.com', 'vsimcard.com',
        'vubby.com', 'wasteland.rfc822.org', 'webemail.me', 'webm4il.info',
        'webuser.in', 'wee.my', 'weg-werf-email.de', 'wegwerf-email-addressen.de',
        'wegwerf-email-adressen.de', 'wegwerf-email.at', 'wegwerf-email.de', 'wegwerf-email.net',
        'wegwerf-emails.de', 'wegwerfadresse.de', 'wegwerfmail.com', 'wegwerfmail.de',
        'wegwerfmail.info', 'wegwerfmail.net', 'wegwerfmail.org', 'wetrainbayarea.com',
        'wetrainbayarea.org', 'wh4f.org', 'whatiaas.com', 'whatpaas.com',
        'whatsaas.com', 'whopy.com', 'whtjddn.33mail.com', 'whyspam.me',
        'wilemail.com', 'willhackforfood.biz', 'willselfdestruct.com', 'winemaven.info',
        'wronghead.com', 'wuzup.net', 'wuzupmail.net', 'x.ip6.li',
        'xagloo.com', 'xemaps.com', 'xents.com', 'xmaily.com',
        'xoxy.net', 'yep.it', 'yogamaven.com', 'yopmail.com',
        'yopmail.fr', 'yopmail.net', 'yopmail.org', 'yopmail.pp.ua',
        'yourtube.ml', 'ypmail.webarnak.fr.eu.org', 'yuurok.com', 'z1p.biz',
        'za.com', 'zehnminuten.de', 'zehnminutenmail.de', 'zippymail.info',
        'zoaxe.com', 'zoemail.com', 'zoemail.net', 'zoemail.org',
        'zomg.info',
    ];
    return in_array($domain, $disposableDomains);
}

/**
 * 记录访客访问日志
 * 仅记录一次（基于 session 标记），避免频繁写入
 */
function log_visitor() {
	if (!empty($_SESSION['visitor_logged'])) {
		return;
	}
	try {
		$tableName = \think\Db::name('visitor_log')->getTable();
		\think\Db::execute("CREATE TABLE IF NOT EXISTS `{$tableName}` (
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
		$now = time();
		$ip = function_exists('get_client_ip') ? get_client_ip() : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
		$url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
		$ua = mb_substr($ua, 0, 500);
		\think\Db::name('visitor_log')->insert([
			'ip'         => $ip,
			'url'        => mb_substr($url, 0, 500),
			'referer'    => mb_substr($referer, 0, 500),
			'user_agent' => $ua,
			'visit_time' => $now,
			'date'       => date('Y-m-d', $now),
			'hour'       => (int)date('H', $now),
		]);
		$_SESSION['visitor_logged'] = true;
	} catch (\Exception $e) {
		// 记录失败不影响正常访问
	}
}

/**
 * 确保管理操作日志表存在
 */
function ensure_admin_op_log_table() {
	try {
		$prefix = \think\Db::getConfig('prefix');
		$prefix = $prefix ?: '';
		$table = "{$prefix}admin_op_log";
		$exists = \think\Db::query("SHOW TABLES LIKE '{$table}'");
		if (empty($exists)) {
			\think\Db::execute("CREATE TABLE IF NOT EXISTS `{$table}` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`admin_id` int(11) NOT NULL DEFAULT 0 COMMENT '操作管理员ID',
				`admin_name` varchar(50) NOT NULL DEFAULT '' COMMENT '操作管理员用户名',
				`action` varchar(50) NOT NULL DEFAULT '' COMMENT '操作类型',
				`target` varchar(200) DEFAULT '' COMMENT '操作目标描述',
				`ip` varchar(50) DEFAULT '' COMMENT '操作IP',
				`detail` text COMMENT '操作详情JSON',
				`create_time` int(11) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_admin_id` (`admin_id`),
				KEY `idx_action` (`action`),
				KEY `idx_create_time` (`create_time`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
	} catch (\Exception $e) {
		// 忽略
	}
}

/**
 * 记录管理员操作日志
 * @param string $action 操作类型
 * @param string $target 操作目标描述
 * @param array|string $detail 操作详情
 */
function admin_op_log($action, $target = '', $detail = '') {
	try {
		$adminId = session('adminid') ?: 0;
		$adminName = '';
		$roleId = 0;
		$roleName = '';
		if ($adminId) {
			$admin = \think\Db::name('admin')->where('id', $adminId)->field('user,role_id')->find();
			$adminName = $admin ? $admin['user'] : '';
			$roleId = $admin ? $admin['role_id'] : 0;
			if ($roleId) {
				$role = \think\Db::name('admin_role')->where('id', $roleId)->field('name')->find();
				$roleName = $role ? $role['name'] : '';
			}
		}
		// 自动捕获详细信息
		$autoDetail = [
			'role'       => $roleName ?: ($adminId ? '站长' : ''),
			'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '',
			'url'        => isset($_SERVER['REQUEST_URI']) ? mb_substr($_SERVER['REQUEST_URI'], 0, 255) : '',
			'method'     => isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '',
			'referer'    => isset($_SERVER['HTTP_REFERER']) ? mb_substr($_SERVER['HTTP_REFERER'], 0, 255) : '',
			'time'       => date('Y-m-d H:i:s'),
		];
		if (is_array($detail)) {
			$detail = array_merge($autoDetail, $detail);
		} elseif (!empty($detail)) {
			$detail = array_merge($autoDetail, ['extra' => $detail]);
		} else {
			$detail = $autoDetail;
		}
		$detailJson = json_encode($detail, JSON_UNESCAPED_UNICODE);
		\think\Db::name('admin_op_log')->insert([
			'admin_id'    => $adminId,
			'admin_name'  => $adminName,
			'action'      => $action,
			'target'      => mb_substr($target, 0, 200),
			'ip'          => function_exists('get_client_ip') ? get_client_ip() : '',
			'detail'      => $detailJson,
			'create_time' => time(),
		]);
	} catch (\Exception $e) {
		// 日志记录失败不影响正常操作
	}
}