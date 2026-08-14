<?php
namespace app\index\controller;
use think\Controller;
use think\Db;
use think\Request;

class Oauth extends Controller {
    public function _initialize() {
        $this->web = web_config();
    }

    /**
     * 获取正确的回调地址：优先使用数据库配置，自动校验并以 /oauth/callback 结尾补充
     */
    private function getCallback() {
        $callback = isset($this->web['oauth_callback']) ? trim($this->web['oauth_callback']) : '';
        // 如果配置为空或不以 /oauth/callback 结尾，从当前请求自动构造
        if (empty($callback) || strpos($callback, '/oauth/callback') === false) {
            $callback = request()->domain() . '/oauth/callback';
        }
        return $callback;
    }

    /**
     * 请求 mapay 聚合登录接口（SSL 证书校验失败时兜底重试一次）
     */
    private function apiGet($url) {
        $result = http_get($url);
        if ($result !== false) {
            return $result;
        }
        // 兜底：服务器 CA 配置异常导致证书校验失败时，关闭校验重试
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $res = curl_exec($ch);
            $err = curl_error($ch);
            curl_close($ch);
            if ($err === '' && $res !== false) {
                return $res;
            }
        }
        return false;
    }

    /**
     * 聚合登录入口
     * @param string $type 登录类型：qq
     */
    public function login($type) {
        if ($type !== 'qq') {
            $this->error('不支持的登录类型');
        }
        if (!$this->web['oauth_enabled']) {
            $this->error('聚合登录功能未启用');
        }
        $appid = $this->web['oauth_appid'];
        $appkey = $this->web['oauth_appkey'];
        $callback = $this->getCallback();
        if (empty($appid) || empty($appkey)) {
            $this->error('聚合登录参数未配置完整，请联系管理员');
        }
        $url = "https://login.mapay.cn/connect.php?act=login&appid={$appid}&appkey={$appkey}&type={$type}&redirect_uri=" . urlencode($callback);
        $result = $this->apiGet($url);
        $data = json_decode($result, true);
        if ($data && isset($data['code']) && $data['code'] == 0 && !empty($data['url'])) {
            $this->redirect($data['url']);
        } else {
            $msg = isset($data['msg']) ? $data['msg'] : '获取登录地址失败';
            $this->error('获取登录地址失败：' . $msg);
        }
    }

    /**
     * 聚合登录回调（统一处理登录和绑定回调）
     */
    public function callback() {
        if (!$this->web['oauth_enabled']) {
            $this->error('聚合登录功能未启用');
        }
        $type = input('type');
        $code = input('code');
        if ($type !== 'qq') {
            $this->error('不支持的登录类型');
        }
        if (empty($code)) {
            $this->error('回调参数缺失');
        }
        $appid = $this->web['oauth_appid'];
        $appkey = $this->web['oauth_appkey'];
        $url = "https://login.mapay.cn/connect.php?act=callback&appid={$appid}&appkey={$appkey}&type={$type}&code={$code}";
        $result = $this->apiGet($url);
        $data = json_decode($result, true);
        if (!$data || !isset($data['code']) || $data['code'] != 0 || empty($data['social_uid'])) {
            $msg = isset($data['msg']) ? $data['msg'] : '回调验证失败';
            $this->error('登录失败：' . $msg);
        }
        $social_uid = $data['social_uid'];
        $oauthField = 'oauth_' . $type;

        // 判断是否为已登录用户绑定 OAuth（兼容 session 丢失场景，同时读取 cookie 兜底）
        $bindUserId = session('oauth_bind_userid') ?: cookie('oauth_bind_userid') ?: input('bind_userid');
        if ($bindUserId) {
            // 已登录用户绑定QQ
            session('oauth_bind_userid', null);
            cookie('oauth_bind_userid', null);
            $existBind = Db::name('user')->where($oauthField, $social_uid)->where('id', '<>', $bindUserId)->find();
            if ($existBind) {
                $this->error('该QQ账号已被其他用户绑定');
            }
            $updateResult = Db::name('user')->where('id', $bindUserId)->update([$oauthField => $social_uid]);
            if ($updateResult === false) {
                $this->error('绑定失败，数据库更新异常，请稍后重试');
            }
            $this->redirect('/user/information');
        }

        // 未登录用户：查找已绑定的用户进行登录
        $user = Db::name('user')->where($oauthField, $social_uid)->find();
        if ($user) {
            if ($user['state'] == '0') {
                $this->error('账户已被冻结，禁止登录');
            }
            session_regenerate_id(true);
            session('userid', $user['id']);
            $loginIp = function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            try {
                Db::name('user')->where('id', $user['id'])->update([
                    'last_login_time' => time(),
                    'last_login_ip' => $loginIp,
                    'last_login_region' => get_ip_region($loginIp)
                ]);
            } catch (\Exception $e) {}
            $this->redirect('/user/index');
        }

        // 未绑定，存储 OAuth 数据到 session，跳转注册/绑定页面
        session('oauth_data', [
            'type' => $type,
            'social_uid' => $social_uid,
            'nickname' => isset($data['nickname']) ? $data['nickname'] : '',
            'avatar' => isset($data['faceimg']) ? $data['faceimg'] : '',
        ]);
        $this->redirect('/oauth/bind');
    }

    /**
     * 新用户绑定页面（OAuth 登录后设置密码）
     */
    public function bind() {
        $oauthData = session('oauth_data');
        if (!$oauthData) {
            $this->redirect('/login');
        }
        if (Request::instance()->isPost()) {
            $username = trim(input('username'));
            $password = input('password');
            $password2 = input('password2');
            if (empty($username) || empty($password) || empty($password2)) {
                return json(['code' => -1, 'msg' => '请填写完整信息']);
            }
            if ($password != $password2) {
                return json(['code' => -1, 'msg' => '两次密码不一致']);
            }
            if (strlen($password) < 6) {
                return json(['code' => -1, 'msg' => '密码长度不能少于6位']);
            }
            // 检查用户名是否已存在
            $existUser = Db::name('user')->where('user', $username)->find();
            if ($existUser) {
                return json(['code' => -1, 'msg' => '该用户名已被使用，请更换']);
            }
            $oauthField = 'oauth_' . $oauthData['type'];
            $newUserId = Db::name('user')->insertGetId([
                'user' => $username,
                'name' => $oauthData['nickname'] ?: $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'money' => '0.00',
                'aff' => random(8),
                'state' => '1',
                'regtime' => date('Y-m-d H:i:s'),
                'regip' => function_exists('get_client_ip') ? get_client_ip() : ($_SERVER['REMOTE_ADDR'] ?? 'unknown'),
                $oauthField => $oauthData['social_uid'],
            ]);
            session('oauth_data', null);
            session_regenerate_id(true);
            session('userid', $newUserId);
            return json(['code' => 1, 'msg' => '绑定成功，正在跳转...', 'url' => '/user/index']);
        }
        $this->assign([
            'webname' => $this->web['name'],
            'web' => $this->web,
            'oauthData' => $oauthData,
        ]);
        return $this->fetch('/' . $this->web['template'] . '/oauth/bind');
    }

    /**
     * 已登录用户绑定 OAuth（从用户中心触发）
     * 跳转到QQ登录页，回调时通过 callback() 中的 session('oauth_bind_userid') 判断
     */
    public function userBind($type) {
        $userid = session('userid');
        if (!$userid) {
            $this->error('请先登录');
        }
        if ($type !== 'qq') {
            $this->error('不支持的绑定类型');
        }
        if (!$this->web['oauth_enabled']) {
            $this->error('聚合登录功能未启用');
        }
        $appid = $this->web['oauth_appid'];
        $appkey = $this->web['oauth_appkey'];
        $callback = $this->getCallback();
        if (empty($appid) || empty($appkey)) {
            $this->error('聚合登录参数未配置完整，请联系管理员');
        }
        // Mapay 回调时不会透传 redirect_uri 中的额外参数，因此同时使用 session + cookie 标记绑定用户
        $url = "https://login.mapay.cn/connect.php?act=login&appid={$appid}&appkey={$appkey}&type={$type}&redirect_uri=" . urlencode($callback);
        $result = $this->apiGet($url);
        $data = json_decode($result, true);
        if ($data && isset($data['code']) && $data['code'] == 0 && !empty($data['url'])) {
            session('oauth_bind_userid', $userid);
            cookie('oauth_bind_userid', $userid, 300);
            $this->redirect($data['url']);
        } else {
            $msg = isset($data['msg']) ? $data['msg'] : '获取绑定地址失败';
            $this->error('获取绑定地址失败：' . $msg);
        }
    }

    /**
     * 解绑 OAuth
     */
    public function unbind($type) {
        $userid = session('userid');
        if (!$userid) {
            return json(['code' => -1, 'msg' => '请先登录']);
        }
        if ($type !== 'qq') {
            return json(['code' => -1, 'msg' => '不支持的绑定类型']);
        }
        $oauthField = 'oauth_' . $type;
        Db::name('user')->where('id', $userid)->update([$oauthField => '']);
        return json(['code' => 1, 'msg' => '解绑成功']);
    }
}