<?php
namespace app\install\controller;

use think\Controller;
use think\Db;
use think\Request;

class Index extends Controller
{
    // 安装锁文件路径
    private $lockFile;

    public function _initialize()
    {
        $this->lockFile = PATH . 'install.lock';
        // 如果已安装，跳转到首页
        if (file_exists($this->lockFile)) {
            $this->redirect('/');
        }
    }

    // 安装首页 - 许可协议
    public function index()
    {
        return $this->fetch('/default/index');
    }

    // 第2步 - 环境检测
    public function step2()
    {
        // 检测环境
        $env = [];
        $env['php'] = PHP_VERSION;
        $env['php_ok'] = version_compare(PHP_VERSION, '7.2.0', '>=');
        $env['pdo'] = extension_loaded('pdo_mysql');
        $env['mysqli'] = extension_loaded('mysqli');
        $env['curl'] = extension_loaded('curl');
        $env['gd'] = extension_loaded('gd');
        $env['openssl'] = extension_loaded('openssl');

        // 目录权限检测
        $dirs = [
            PATH . 'app/database.php',
            PATH . 'runtime',
            PATH . 'public/static',
        ];
        $dirPerm = [];
        foreach ($dirs as $dir) {
            $dirPerm[$dir] = is_writable($dir);
        }

        $allOk = $env['php_ok'] && ($env['pdo'] || $env['mysqli']) && !in_array(false, $dirPerm);

        return $this->fetch('/default/step2', [
            'env' => $env,
            'dirPerm' => $dirPerm,
            'allOk' => $allOk,
        ]);
    }

    // 第3步 - 数据库配置
    public function step3()
    {
        if (Request::instance()->isPost()) {
            $hostname = input('hostname', '127.0.0.1');
            $hostport = input('hostport', '3306');
            $database = input('database', '');
            $username = input('username', '');
            $password = input('password', '');
            $prefix = input('prefix', 'dd_');
            $keepData = input('keep_data', '0') == '1';
            $skipDb = input('skip_db', '0') == '1';

            if ($database == '' || $username == '') {
                return ['code' => -1, 'msg' => '数据库名和用户名不能为空!'];
            }

            // 测试数据库连接
            try {
                $dsn = "mysql:host={$hostname};port={$hostport};charset=utf8";
                $pdo = new \PDO($dsn, $username, $password);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                // 检查数据库是否存在，不存在则创建
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` DEFAULT CHARACTER SET utf8");
                $pdo->exec("USE `{$database}`");
            } catch (\PDOException $e) {
                return ['code' => -1, 'msg' => '数据库连接失败: ' . $e->getMessage()];
            }

            // 写入数据库配置文件
            $configContent = <<<'PHP'
<?php

return [
    // 数据库类型
    'type'            => 'mysql',
    // 服务器地址
    'hostname'        => '{hostname}',
    // 数据库名
    'database'        => '{database}',
    // 用户名
    'username'        => '{username}',
    // 密码
    'password'        => '{password}',
    // 端口
    'hostport'        => '{hostport}',
    // 连接dsn
    'dsn'             => '',
    // 数据库连接参数
    'params'          => [],
    // 数据库编码默认采用utf8
    'charset'         => 'utf8',
    // 数据库表前缀
    'prefix'          => '{prefix}',
    // 数据库调试模式
    'debug'           => false,
    // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
    'deploy'          => 0,
    // 数据库读写是否分离 主从式有效
    'rw_separate'     => false,
    // 读写分离后 主服务器数量
    'master_num'      => 1,
    // 指定从服务器序号
    'slave_no'        => '',
    // 自动读取主库数据
    'read_master'     => false,
    // 是否严格检查字段是否存在
    'fields_strict'   => true,
    // 数据集返回类型
    'resultset_type'  => 'array',
    // 自动写入时间戳字段
    'auto_timestamp'  => false,
    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',
    // 是否需要进行SQL性能分析
    'sql_explain'     => false,
];
PHP;

            $configContent = str_replace(
                ['{hostname}', '{database}', '{username}', '{password}', '{hostport}', '{prefix}'],
                [$hostname, $database, $username, $password, $hostport, $prefix],
                $configContent
            );

            $result = file_put_contents(PATH . 'app/database.php', $configContent);
            if ($result === false) {
                return ['code' => -1, 'msg' => '数据库配置文件写入失败，请检查目录权限!'];
            }

            // 跳过数据库表创建（仅写入配置文件）
            if ($skipDb) {
                return ['code' => 1, 'msg' => '数据库配置已保存，跳过表创建!'];
            }

            // 创建数据表
            try {
                $this->createTables($pdo, $prefix, $keepData);
            } catch (\Exception $e) {
                return ['code' => -1, 'msg' => '创建数据表失败: ' . $e->getMessage()];
            }

            return ['code' => 1, 'msg' => '数据库配置成功!'];
        }

        $skipDb = input('skip', '0') == '1';
        return $this->fetch('/default/step3', ['skipDb' => $skipDb]);
    }

    // 第4步 - 管理员设置
    public function step4()
    {
        if (Request::instance()->isPost()) {
            $adminUser = input('admin_user', '');
            $adminPassword = input('admin_password', '');
            $adminName = input('admin_name', '站长');
            $adminQQ = input('admin_qq', '');
            $adminMail = input('admin_mail', '');
            $siteName = input('site_name', '我的主机站');

            if ($adminUser == '' || $adminPassword == '') {
                return ['code' => -1, 'msg' => '管理员账号和密码不能为空!'];
            }

            try {
                // 重新加载数据库配置（step3已写入database.php）
                $dbConfig = include PATH . 'app/database.php';
                \think\Config::set('database', $dbConfig);
                // 清除已有的数据库连接实例，确保使用新配置
                \think\Db::clear();

                $prefix = $dbConfig['prefix'];

                // 如果跳过了数据库安装或表不存在，先确保核心表存在
                $this->ensureCoreTables($prefix);

                // 确保管理员角色存在
                $roleExists = Db::name('admin_role')->where('id', 1)->find();
                if (!$roleExists) {
                    Db::name('admin_role')->insert([
                        'id'          => 1,
                        'name'        => '超级管理员',
                        'permissions' => json_encode(['all']),
                        'description' => '拥有所有权限',
                        'created_at'  => time(),
                    ]);
                }

                // 确保默认管理员记录存在
                $adminExists = Db::name('admin')->where('id', 1)->find();
                if (!$adminExists) {
                    Db::name('admin')->insert([
                        'id'         => 1,
                        'name'       => $adminName,
                        'user'       => $adminUser,
                        'password'   => password_hash($adminPassword, PASSWORD_DEFAULT),
                        'qq'         => $adminQQ,
                        'mail'       => $adminMail,
                        'is_super'   => 1,
                        'role_id'    => 1,
                        'status'     => 1,
                        'created_at' => time(),
                    ]);
                } else {
                    // 更新管理员账号（设为站长）
                    Db::name('admin')->where('id', 1)->update([
                        'name' => $adminName,
                        'user' => $adminUser,
                        'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
                        'qq' => $adminQQ,
                        'mail' => $adminMail,
                        'is_super' => 1,
                        'role_id' => 1,
                        'status' => 1,
                        'created_at' => time(),
                    ]);
                }

                // 确保默认网站配置存在
                $webExists = Db::name('web')->where('id', 1)->find();
                if (!$webExists) {
                    Db::name('web')->insert([
                        'id'          => 1,
                        'name'        => $siteName,
                        'description' => $siteName . ',提供快速、稳定、优质的虚拟主机服务！',
                        'keywords'    => $siteName . ',虚拟主机,主机销售',
                        'favicon'     => '/favicon.ico',
                        'template'    => 'default',
                        'admintemplate' => 'default',
                        'wh'          => '0',
                        'global_datacenters' => '[]',
                        'templateset' => '[]',
                    ]);
                } else {
                    // 更新网站名称
                    Db::name('web')->where('id', 1)->update([
                        'name' => $siteName,
                        'description' => $siteName . ',提供快速、稳定、优质的虚拟主机服务！',
                        'keywords' => $siteName . ',虚拟主机,主机销售',
                    ]);
                }

                // 添加固定授权密钥记录
                $domain = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
                $ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
                if (empty($ip) || $ip == '::1') {
                    $ip = '127.0.0.1';
                }
                $authKey = 'LHXYYDS';
                $sqExists = Db::name('sq')->where('domain', $domain)->where('ip', $ip)->find();
                if (!$sqExists) {
                    Db::name('sq')->insert([
                        'domain' => $domain,
                        'qq'     => $authKey,
                        'ip'     => $ip,
                        'time'   => time(),
                    ]);
                }

                // 生成安装锁文件
                file_put_contents($this->lockFile, date('Y-m-d H:i:s'));

                return ['code' => 1, 'msg' => '安装完成!'];
            } catch (\Exception $e) {
                return ['code' => -1, 'msg' => '管理员设置失败: ' . $e->getMessage()];
            }
        }

        return $this->fetch('/default/step4');
    }

    // 安装完成
    public function done()
    {
        if (!file_exists($this->lockFile)) {
            $this->redirect('/install');
        }
        return $this->fetch('/default/done');
    }

    // 创建数据表
    private function createTables($pdo, $prefix, $keepData = false)
    {
        $sql = $this->getInstallSql($prefix);
        $pdo->exec("SET NAMES utf8");
        // PDO::exec 一次只能执行一条SQL，需要逐条执行
        $statements = array_filter(array_map('trim', explode(";\n", $sql)));
        foreach ($statements as $stmt) {
            if ($stmt === '') {
                continue;
            }
            // 保留数据模式跳过 DROP TABLE 语句
            if ($keepData && stripos($stmt, 'DROP TABLE') === 0) {
                continue;
            }
            $pdo->exec($stmt);
        }
    }

    // 确保核心表存在（用于跳过数据库安装或表缺失时兜底）
    private function ensureCoreTables($prefix)
    {
        $coreSql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$prefix}admin_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '角色名称',
  `permissions` text COMMENT '权限JSON',
  `description` varchar(255) DEFAULT '' COMMENT '角色描述',
  `created_at` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `qq` varchar(100) NOT NULL,
  `user` varchar(100) NOT NULL,
  `password` varchar(288) NOT NULL,
  `role_id` int(11) DEFAULT 0,
  `is_super` tinyint(1) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` int(11) DEFAULT 0,
  `mail` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}web` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text,
  `keywords` text,
  `favicon` text,
  `template` varchar(288) NOT NULL DEFAULT 'default',
  `admintemplate` varchar(288) NOT NULL DEFAULT 'default',
  `wh` varchar(10) NOT NULL DEFAULT '0',
  `whxx` text,
  `email` varchar(100) DEFAULT '0',
  `emailchar` varchar(500) NOT NULL DEFAULT '',
  `emailsecure` varchar(100) NOT NULL DEFAULT '',
  `emailport` varchar(500) NOT NULL DEFAULT '',
  `emailhost` varchar(500) NOT NULL DEFAULT '',
  `emailname` varchar(500) NOT NULL DEFAULT '',
  `emailpass` varchar(500) NOT NULL DEFAULT '',
  `emailauth` varchar(500) NOT NULL DEFAULT '',
  `affdiscount` varchar(100) NOT NULL DEFAULT '',
  `affwithdrawal` varchar(100) NOT NULL DEFAULT '',
  `cronzz` varchar(100) NOT NULL DEFAULT '',
  `cronsc` varchar(100) NOT NULL DEFAULT '',
  `paycron` varchar(100) NOT NULL DEFAULT '',
  `tickcron` varchar(100) NOT NULL DEFAULT '',
  `zcyxyz` varchar(100) NOT NULL DEFAULT '0',
  `yxdl` varchar(288) NOT NULL DEFAULT '0',
  `logo` text,
  `logo_icon` varchar(255) NOT NULL DEFAULT 'fas fa-server',
  `icp` varchar(255) NOT NULL DEFAULT '',
  `business_license` varchar(255) NOT NULL DEFAULT '',
  `police_beian` varchar(255) NOT NULL DEFAULT '',
  `telecom_license` varchar(255) NOT NULL DEFAULT '',
  `qq_group` varchar(255) NOT NULL DEFAULT '' COMMENT 'QQ官方群号或链接',
  `global_datacenters` text,
  `popup_notice` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用弹窗公告',
  `popup_title` varchar(255) NOT NULL DEFAULT '' COMMENT '弹窗公告标题',
  `popup_content` text COMMENT '弹窗公告 HTML 内容',
  `templateset` text,
  `host_auto_create` varchar(10) DEFAULT '0' COMMENT '0=manual 1=auto',
  `host_auto_create_delay` varchar(50) DEFAULT '0' COMMENT 'minutes',
  `bg_image` varchar(500) DEFAULT '' COMMENT '全局背景图URL',
  `bg_type` varchar(20) DEFAULT 'image' COMMENT '背景类型：image/video/gif',
  `bg_video_loop` tinyint(1) NOT NULL DEFAULT '1' COMMENT '视频背景是否循环播放',
  `bg_video_muted` tinyint(1) NOT NULL DEFAULT '1' COMMENT '视频背景是否静音',
  `bg_blur` int(2) DEFAULT '3' COMMENT '背景图模糊程度(0-10)',
  `bg_gradient` varchar(50) DEFAULT 'default' COMMENT '预设渐变色',
  `bg_images` text COMMENT '轮播背景图URL列表（逗号分隔）',
  `bg_switch_interval` int(6) DEFAULT '0' COMMENT '背景图轮播间隔(秒，0=不轮播)',
  `glass_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用液态玻璃主题:0关闭 1开启',
  `glass_opacity` int(3) DEFAULT '72' COMMENT '液态玻璃透明度(30-100)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}sq` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain` text NOT NULL,
  `qq` varchar(288) NOT NULL,
  `ip` varchar(288) NOT NULL,
  `time` varchar(288) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `user` varchar(200) NOT NULL,
  `password` varchar(255) NOT NULL,
  `money` varchar(200) NOT NULL DEFAULT '0',
  `mail` varchar(300) NOT NULL,
  `qq` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `aff` varchar(100) NOT NULL,
  `affmoney` varchar(100) NOT NULL DEFAULT '0',
  `upperid` varchar(100) NOT NULL,
  `time` varchar(200) NOT NULL,
  `state` varchar(10) NOT NULL DEFAULT '1',
  `ban_time` int(11) NOT NULL DEFAULT 0 COMMENT '封禁到期时间戳，0=未封禁',
  `ban_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '封禁原因',
  `last_login_time` int(11) NOT NULL DEFAULT 0 COMMENT '最后登录时间戳',
  `last_login_ip` varchar(255) NOT NULL DEFAULT '' COMMENT '最后登录IP',
  `last_login_region` varchar(50) NOT NULL DEFAULT '' COMMENT '最后登录地区（省份）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

        $statements = array_filter(array_map('trim', explode(";\n", $coreSql)));
        foreach ($statements as $stmt) {
            if ($stmt !== '') {
                Db::execute($stmt);
            }
        }
    }

    // 获取安装SQL
    private function getInstallSql($prefix)
    {
        return <<<SQL
DROP TABLE IF EXISTS `{$prefix}admin`;

CREATE TABLE IF NOT EXISTS `{$prefix}admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `qq` varchar(100) NOT NULL,
  `user` varchar(100) NOT NULL,
  `password` varchar(288) NOT NULL,
  `role_id` int(11) DEFAULT 0,
  `is_super` tinyint(1) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `created_at` int(11) DEFAULT 0,
  `mail` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `{$prefix}admin` (`id`, `name`, `qq`, `user`, `password`, `role_id`, `is_super`, `status`, `created_at`, `mail`) VALUES
(1, '管理员', '', 'admin', '', 1, 1, 1, UNIX_TIMESTAMP(), '');

DROP TABLE IF EXISTS `{$prefix}admin_role`;

CREATE TABLE IF NOT EXISTS `{$prefix}admin_role` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL COMMENT '角色名称',
  `permissions` text COMMENT '权限JSON',
  `description` varchar(255) DEFAULT '' COMMENT '角色描述',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `{$prefix}admin_role` (`id`, `name`, `permissions`, `description`) VALUES
(1, '超级管理员', '["all"]', '拥有所有权限');

CREATE TABLE IF NOT EXISTS `{$prefix}affsymoney` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `information` text NOT NULL,
  `money` varchar(100) NOT NULL,
  `userid` varchar(100) NOT NULL,
  `time` varchar(50) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}afftxjl` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `information` text NOT NULL,
  `money` varchar(100) NOT NULL,
  `state` varchar(10) NOT NULL,
  `userid` varchar(100) NOT NULL,
  `time` varchar(50) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}announcement` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` text NOT NULL,
  `information` text NOT NULL,
  `time` varchar(288) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}cart` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product` varchar(200) NOT NULL,
  `name` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `money` varchar(200) NOT NULL DEFAULT '0',
  `cycle` varchar(100) NOT NULL,
  `firstmo` varchar(288) NOT NULL DEFAULT '0',
  `serverid` varchar(200) NOT NULL,
  `upgrade` varchar(10) NOT NULL DEFAULT '0',
  `upgrades` text,
  `buy` varchar(10) NOT NULL DEFAULT '0',
  `hide` varchar(10) NOT NULL DEFAULT '0',
  `sort` int(11) NOT NULL DEFAULT '0',
  `renew` varchar(100) NOT NULL DEFAULT '0',
  `limits` varchar(288) NOT NULL DEFAULT '0',
  `inventory` varchar(100) NOT NULL DEFAULT '0',
  `data1` text, `data2` text, `data3` text, `data4` text, `data5` text,
  `data6` text, `data7` text, `data8` text, `data9` text, `data10` text,
  `data11` text, `data12` text, `data13` text, `data14` text, `data15` text,
  `data16` text, `data17` text, `data18` text, `data19` text, `data20` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}order` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ordernumber` varchar(32) NOT NULL DEFAULT '' COMMENT '订单号',
  `user` varchar(300) DEFAULT NULL,
  `password` varchar(320) DEFAULT NULL,
  `userid` varchar(100) NOT NULL,
  `cartid` varchar(100) NOT NULL,
  `atime` varchar(300) NOT NULL,
  `ztime` varchar(300) NOT NULL,
  `state` varchar(200) NOT NULL,
  `auto_create_at` varchar(50) DEFAULT '0' COMMENT '自动开通时间戳',
  `data1` text, `data2` text, `data3` text, `data4` text, `data5` text,
  `data6` text, `data7` text, `data8` text, `data9` text, `data10` text,
  `data11` text, `data12` text, `data13` text, `data14` text, `data15` text,
  `data16` text, `data17` text, `data18` text, `data19` text, `data20` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}pay` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(288) NOT NULL,
  `ordernumber` varchar(288) NOT NULL,
  `pay` varchar(288) NOT NULL,
  `money` varchar(288) NOT NULL,
  `userid` varchar(100) NOT NULL,
  `time` varchar(288) NOT NULL,
  `state` varchar(288) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}pays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(288) NOT NULL,
  `plugins` varchar(288) NOT NULL,
  `state` varchar(10) NOT NULL DEFAULT '0',
  `data` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}host_transfer` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL COMMENT '订单ID',
  `userid` int(11) NOT NULL COMMENT '转让方用户ID',
  `target_userid` int(11) NOT NULL DEFAULT '0' COMMENT '指定接收用户ID(0=公开)',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}host_transfer_message` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}product` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `introduce` text NOT NULL,
  `hide` varchar(10) NOT NULL DEFAULT '0',
  `sort` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}server` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `host` varchar(100) NOT NULL,
  `ip` varchar(200) NOT NULL,
  `security` text NOT NULL,
  `port` varchar(200) NOT NULL,
  `ssl` varchar(200) NOT NULL DEFAULT '0',
  `user` varchar(300) NOT NULL,
  `password` varchar(300) NOT NULL,
  `serverplugins` varchar(288) NOT NULL,
  `data1` text, `data2` text, `data3` text, `data4` text, `data5` text,
  `data6` text, `data7` text, `data8` text, `data9` text, `data10` text,
  `data11` text, `data12` text, `data13` text, `data14` text, `data15` text,
  `data16` text, `data17` text, `data18` text, `data19` text, `data20` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}sq` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `domain` text NOT NULL,
  `qq` varchar(288) NOT NULL,
  `ip` varchar(288) NOT NULL,
  `time` varchar(288) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}ticket` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(288) NOT NULL,
  `content` mediumtext NOT NULL,
  `userid` varchar(100) NOT NULL,
  `time` varchar(100) NOT NULL,
  `state` varchar(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}transaction` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` varchar(288) NOT NULL,
  `content` text NOT NULL,
  `time` varchar(288) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `{$prefix}transferrecord` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` varchar(100) NOT NULL,
  `record` mediumtext NOT NULL,
  `time` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `{$prefix}user`;

CREATE TABLE IF NOT EXISTS `{$prefix}user` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `user` varchar(200) NOT NULL,
  `password` varchar(255) NOT NULL,
  `money` varchar(200) NOT NULL DEFAULT '0',
  `mail` varchar(300) NOT NULL,
  `qq` varchar(100) NOT NULL,
  `address` text NOT NULL,
  `aff` varchar(100) NOT NULL,
  `affmoney` varchar(100) NOT NULL DEFAULT '0',
  `upperid` varchar(100) NOT NULL,
  `time` varchar(200) NOT NULL,
  `state` varchar(10) NOT NULL DEFAULT '1',
  `ban_time` int(11) NOT NULL DEFAULT 0 COMMENT '封禁到期时间戳，0=未封禁',
  `ban_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '封禁原因',
  `last_login_time` int(11) NOT NULL DEFAULT 0 COMMENT '最后登录时间戳',
  `last_login_ip` varchar(255) NOT NULL DEFAULT '' COMMENT '最后登录IP',
  `last_login_region` varchar(50) NOT NULL DEFAULT '' COMMENT '最后登录地区（省份）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `{$prefix}web`;

CREATE TABLE IF NOT EXISTS `{$prefix}web` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `description` text,
  `keywords` text,
  `favicon` text,
  `template` varchar(288) NOT NULL DEFAULT 'default',
  `admintemplate` varchar(288) NOT NULL DEFAULT 'default',
  `wh` varchar(10) NOT NULL DEFAULT '0',
  `whxx` text,
  `email` varchar(100) DEFAULT '0',
  `emailchar` varchar(500) NOT NULL DEFAULT '',
  `emailsecure` varchar(100) NOT NULL DEFAULT '',
  `emailport` varchar(500) NOT NULL DEFAULT '',
  `emailhost` varchar(500) NOT NULL DEFAULT '',
  `emailname` varchar(500) NOT NULL DEFAULT '',
  `emailpass` varchar(500) NOT NULL DEFAULT '',
  `emailauth` varchar(500) NOT NULL DEFAULT '',
  `affdiscount` varchar(100) NOT NULL DEFAULT '',
  `affwithdrawal` varchar(100) NOT NULL DEFAULT '',
  `cronzz` varchar(100) NOT NULL DEFAULT '',
  `cronsc` varchar(100) NOT NULL DEFAULT '',
  `paycron` varchar(100) NOT NULL DEFAULT '',
  `tickcron` varchar(100) NOT NULL DEFAULT '',
  `zcyxyz` varchar(100) NOT NULL DEFAULT '0',
  `yxdl` varchar(288) NOT NULL DEFAULT '0',
  `logo` text,
  `logo_icon` varchar(255) NOT NULL DEFAULT 'fas fa-server',
  `icp` varchar(255) NOT NULL DEFAULT '',
  `business_license` varchar(255) NOT NULL DEFAULT '',
  `police_beian` varchar(255) NOT NULL DEFAULT '',
  `telecom_license` varchar(255) NOT NULL DEFAULT '',
  `qq_group` varchar(255) NOT NULL DEFAULT '' COMMENT 'QQ官方群号或链接',
  `global_datacenters` text,
  `popup_notice` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用弹窗公告',
  `popup_title` varchar(255) NOT NULL DEFAULT '' COMMENT '弹窗公告标题',
  `popup_content` text COMMENT '弹窗公告 HTML 内容',
  `templateset` text,
  `host_auto_create` varchar(10) DEFAULT '0' COMMENT '0=manual 1=auto',
  `host_auto_create_delay` varchar(50) DEFAULT '0' COMMENT 'minutes',
  `bg_image` varchar(500) DEFAULT '' COMMENT '全局背景图URL',
  `bg_type` varchar(20) DEFAULT 'image' COMMENT '背景类型：image/video/gif',
  `bg_video_loop` tinyint(1) NOT NULL DEFAULT '1' COMMENT '视频背景是否循环播放',
  `bg_video_muted` tinyint(1) NOT NULL DEFAULT '1' COMMENT '视频背景是否静音',
  `bg_blur` int(2) DEFAULT '3' COMMENT '背景图模糊程度(0-10)',
  `bg_gradient` varchar(50) DEFAULT 'default' COMMENT '预设渐变色',
  `bg_images` text COMMENT '轮播背景图URL列表（逗号分隔）',
  `bg_switch_interval` int(6) DEFAULT '0' COMMENT '背景图轮播间隔(秒，0=不轮播)',
  `glass_enabled` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用液态玻璃主题:0关闭 1开启',
  `glass_opacity` int(3) DEFAULT '72' COMMENT '液态玻璃透明度(30-100)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `{$prefix}web` (`id`, `name`, `description`, `keywords`, `favicon`, `template`, `admintemplate`, `global_datacenters`, `templateset`) VALUES
(1, '我的主机站', '', '', '/favicon.ico', 'default', 'default', '[]', '[]');

CREATE TABLE IF NOT EXISTS `{$prefix}violation` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `rsthemes_displays` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `active` tinyint(1) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `rsthemes_displays` (`id`, `name`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Default', 1, NOW(), NOW());
SQL;
    }
}
