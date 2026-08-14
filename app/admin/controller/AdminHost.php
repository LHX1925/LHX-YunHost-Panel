<?php
namespace app\admin\controller;
use think\Controller;
use think\Db;
use think\Request;

class AdminHost extends Controller
{
    protected $hasFullAccess = false;

    public function _initialize() {
        if(!session("adminid")) {
            $this->redirect(url('admin/login/index'));
        }
        $this->user = Db::name('admin')->where('id', session("adminid"))->find();
        $this->web = web_config();

        // 权限判断
        $this->hasFullAccess = ($this->user['is_super'] == 1 || $this->user['role_id'] == 1);
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
        if ($this->hasFullAccess && in_array('all', $adminPermissions)) {
            $adminPermissions = ['all', 'user', 'product', 'classification', 'server', 'order', 'ticket', 'announcement', 'pay', 'pays', 'aff', 'set', 'admin_manager', 'sq', 'transaction', 'transferrecord', 'op_log'];
        }

        $this->assign([
            'webname'  => $this->web['name'],
            'user'     => $this->user,
            'adminPermissions' => $adminPermissions,
            'templateset' => file_exists(PATH . "/app/index/view/" . $this->web["template"] . "/set.php") ? "1" : "0",
            'csrf_token'=> csrf_token(),
        ]);
    }

    protected function checkPermission($permission) {
        if ($this->hasFullAccess) return true;
        try {
            $role = Db::name('admin_role')->where('id', $this->user['role_id'])->find();
            if (!$role) return false;
            $permissions = json_decode($role['permissions'], true);
            return in_array($permission, $permissions) || in_array('all', $permissions);
        } catch (\Exception $e) {
            return false;
        }
    }

    // 主机列表
    public function index() {
        if (!$this->checkPermission('server') && !$this->hasFullAccess) {
            $this->error('您没有权限访问此页面', '/admin/index');
        }

        $search = input('search', '');
        $stateFilter = input('state', '');

        $query = Db::name('order')->alias('o')
            ->join('cart c', 'o.cartid = c.id', 'LEFT')
            ->join('server s', 'c.serverid = s.id', 'LEFT')
            ->join('user u', 'o.userid = u.id', 'LEFT')
            ->field('o.*, c.name as product_name, s.name as server_name, s.serverplugins, u.user as user_name')
            ->order('o.id desc');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('o.user', 'like', '%'.$search.'%')
                  ->whereOr('o.id', 'like', '%'.$search.'%')
                  ->whereOr('o.userid', 'like', '%'.$search.'%')
                  ->whereOr('c.name', 'like', '%'.$search.'%')
                  ->whereOr('u.user', 'like', '%'.$search.'%');
            });
        }

        if ($stateFilter !== '') {
            $query->where('o.state', intval($stateFilter));
        }

        $data = $query->paginate(15, false, ['query' => request()->param()]);

        return $this->fetch('/'.$this->web["admintemplate"].'/host_manager', [
            'hosts' => $data,
            'search' => $search,
            'stateFilter' => $stateFilter,
        ]);
    }

    // 操作单个主机
    public function operate() {
        if (!$this->checkPermission('server') && !$this->hasFullAccess) {
            return json(['code' => -1, 'msg' => '无权限']);
        }
        if (!Request::instance()->isPost()) {
            return json(['code' => -1, 'msg' => '非法请求']);
        }
        if (!csrf_verify(input('__token__'))) {
            return json(['code' => -1, 'msg' => '安全验证失败，请刷新页面重试']);
        }
        $id = input('id', 0);
        $act = input('act', '');
        if (!$id || !in_array($act, ['stop', 'stopoff', 'delete'])) {
            return json(['code' => -1, 'msg' => '参数错误']);
        }

        $order = Db::name('order')->where('id', $id)->find();
        if (!$order) {
            return json(['code' => -1, 'msg' => '订单不存在']);
        }

        if ($act == 'delete') {
            Db::name('order')->where('id', $id)->delete();
            admin_op_log('host_delete', '删除主机：' . $order['user'], ['order_id' => $id]);
            return json(['code' => 1, 'msg' => '删除成功']);
        }

        // 暂停/解除暂停需要调用插件
        $cart = Db::name('cart')->where('id', $order['cartid'])->find();
        $server = Db::name('server')->where('id', $cart['serverid'])->find();

        if ($act == 'stop') {
            if ($order['state'] == '3') {
                return json(['code' => -1, 'msg' => '产品已终止，禁止修改此状态']);
            }
            if ($order['state'] == '2') {
                return json(['code' => -1, 'msg' => '产品已暂停']);
            }
            $pluginFile = PATH . "plugins/host/" . $server["serverplugins"] . "/" . $server["serverplugins"] . ".php";
            if (file_exists($pluginFile)) {
                include_once $pluginFile;
                $function = $server["serverplugins"] . "_SuspendAccount";
                if (function_exists($function)) {
                    @$function($server, $order, $cart);
                }
            }
            Db::name('order')->where('id', $id)->update(['state' => '2']);
            admin_op_log('host_suspend', '暂停主机：' . $order['user'], ['order_id' => $id]);
            return json(['code' => 1, 'msg' => '暂停成功']);
        }

        if ($act == 'stopoff') {
            if ($order['state'] == '3') {
                return json(['code' => -1, 'msg' => '产品已终止，禁止修改此状态']);
            }
            if ($order['state'] == '1') {
                return json(['code' => -1, 'msg' => '产品已是正常运行状态']);
            }
            $pluginFile = PATH . "plugins/host/" . $server["serverplugins"] . "/" . $server["serverplugins"] . ".php";
            if (file_exists($pluginFile)) {
                include_once $pluginFile;
                $function = $server["serverplugins"] . "_UnsuspendAccount";
                if (function_exists($function)) {
                    @$function($server, $order, $cart);
                }
            }
            Db::name('order')->where('id', $id)->update(['state' => '1']);
            admin_op_log('host_unsuspend', '开启主机：' . $order['user'], ['order_id' => $id]);
            return json(['code' => 1, 'msg' => '解除暂停成功']);
        }

        return json(['code' => -1, 'msg' => '未知操作']);
    }

    // 一键暂停所有主机
    public function suspendAll() {
        if (!$this->checkPermission('server') && !$this->hasFullAccess) {
            return json(['code' => -1, 'msg' => '无权限']);
        }
        if (!Request::instance()->isPost()) {
            return json(['code' => -1, 'msg' => '非法请求']);
        }
        if (!csrf_verify(input('__token__'))) {
            return json(['code' => -1, 'msg' => '安全验证失败，请刷新页面重试']);
        }
        $orders = Db::name('order')->where('state', '1')->select();
        if (empty($orders)) {
            return json(['code' => 1, 'msg' => '没有需要暂停的主机']);
        }

        $success = 0;
        $fail = 0;
        foreach ($orders as $order) {
            try {
                $cart = Db::name('cart')->where('id', $order['cartid'])->find();
                $server = $cart ? Db::name('server')->where('id', $cart['serverid'])->find() : null;
                if ($server && !empty($server['serverplugins'])) {
                    $pluginFile = PATH . "plugins/host/" . $server["serverplugins"] . "/" . $server["serverplugins"] . ".php";
                    if (file_exists($pluginFile)) {
                        include_once $pluginFile;
                        $function = $server["serverplugins"] . "_SuspendAccount";
                        if (function_exists($function)) {
                            @$function($server, $order, $cart);
                        }
                    }
                }
                Db::name('order')->where('id', $order['id'])->update(['state' => '2']);
                $success++;
            } catch (\Exception $e) {
                $fail++;
            }
        }
        admin_op_log('host_suspend_all', '一键暂停所有主机', ['success' => $success, 'fail' => $fail]);
        return json(['code' => 1, 'msg' => "成功暂停 {$success} 个主机" . ($fail > 0 ? "，失败 {$fail} 个" : "")]);
    }

    // 一键开启所有主机
    public function unsuspendAll() {
        if (!$this->checkPermission('server') && !$this->hasFullAccess) {
            return json(['code' => -1, 'msg' => '无权限']);
        }
        if (!Request::instance()->isPost()) {
            return json(['code' => -1, 'msg' => '非法请求']);
        }
        if (!csrf_verify(input('__token__'))) {
            return json(['code' => -1, 'msg' => '安全验证失败，请刷新页面重试']);
        }
        $orders = Db::name('order')->where('state', '2')->select();
        if (empty($orders)) {
            return json(['code' => 1, 'msg' => '没有需要开启的主机']);
        }

        $success = 0;
        $fail = 0;
        foreach ($orders as $order) {
            try {
                $cart = Db::name('cart')->where('id', $order['cartid'])->find();
                $server = $cart ? Db::name('server')->where('id', $cart['serverid'])->find() : null;
                if ($server && !empty($server['serverplugins'])) {
                    $pluginFile = PATH . "plugins/host/" . $server["serverplugins"] . "/" . $server["serverplugins"] . ".php";
                    if (file_exists($pluginFile)) {
                        include_once $pluginFile;
                        $function = $server["serverplugins"] . "_UnsuspendAccount";
                        if (function_exists($function)) {
                            @$function($server, $order, $cart);
                        }
                    }
                }
                Db::name('order')->where('id', $order['id'])->update(['state' => '1']);
                $success++;
            } catch (\Exception $e) {
                $fail++;
            }
        }
        admin_op_log('host_unsuspend_all', '一键开启所有主机', ['success' => $success, 'fail' => $fail]);
        return json(['code' => 1, 'msg' => "成功开启 {$success} 个主机" . ($fail > 0 ? "，失败 {$fail} 个" : "")]);
    }

    // 一键删除所有主机
    public function deleteAll() {
        if (!$this->checkPermission('server') && !$this->hasFullAccess) {
            return json(['code' => -1, 'msg' => '无权限']);
        }
        if (!Request::instance()->isPost()) {
            return json(['code' => -1, 'msg' => '非法请求']);
        }
        if (!csrf_verify(input('__token__'))) {
            return json(['code' => -1, 'msg' => '安全验证失败，请刷新页面重试']);
        }
        $count = Db::name('order')->count();
        if ($count == 0) {
            return json(['code' => 1, 'msg' => '没有需要删除的主机']);
        }
        $result = Db::name('order')->where('1=1')->delete();
        admin_op_log('host_delete_all', '一键删除所有主机', ['count' => $result]);
        return json(['code' => 1, 'msg' => "成功删除 {$result} 个主机"]);
    }
}