<?php
namespace app\admin\controller;
use think\Controller;
use think\Db;
use think\Request;

class AdminManager extends Controller
{
    public function _initialize() {
        if(!session("adminid")) {
            $this->redirect(url('admin/login/index'));
        }
        $this->user = Db::name('admin')->where('id', session("adminid"))->find();
		$this->web = web_config();

		// 确保 admin 表字段完整（role_id/is_super/status/created_at）
		ensure_admin_columns();

		// 确保 admin_role 表存在（防止直接访问 admin_manager 时表未创建）
		try {
			Db::name('admin_role')->count();
		} catch (\Exception $e) {
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
		}

		// 确保三个默认角色始终存在（兼容旧版升级）
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
		// 如果数据库中仍配置为旧版 layui 后台主题，强制使用已重构的 default 主题
		if($this->web["admintemplate"]=="layui"){
			$this->web["admintemplate"]="default";
		}

		// 计算当前管理员权限
        $this->isFounder = ($this->user['is_super'] == 1); // 站长（创始人）
        $this->hasFullAccess = ($this->isFounder || $this->user['role_id'] == 1); // 站长或超级管理员角色
        $adminPermissions = $this->hasFullAccess ? ['all'] : [];
        if (!$this->hasFullAccess && $this->user['role_id']) {
            try {
                $role = Db::name('admin_role')->where('id', $this->user['role_id'])->find();
                if ($role) {
                    $adminPermissions = json_decode($role['permissions'], true) ?: [];
                }
            } catch (\Exception $e) {
                // 查询异常时默认无额外权限，确保权限限制生效
                $adminPermissions = [];
            }
        }

        // 仅超级管理员或拥有 admin_manager 权限的角色可访问
        if (!$this->hasFullAccess && !in_array('admin_manager', $adminPermissions)) {
            $this->error('您没有权限访问此页面', '/admin/index');
        }
        
        $file = file_exists(PATH."/app/index/view/".$this->web["template"]."/set.php");
        $templateset = $file ? "1" : "0";
        
        $this->assign([
            'webname' => $this->web['name'],
            'user' => $this->user,
            'templateset' => $templateset,
            'adminPermissions' => $adminPermissions,
            'isFounder' => $this->isFounder,
        ]);
    }

    // List all admins
    public function index()
    {
        $admins = Db::name('admin')->order('id asc')->paginate(10);
        $roles = Db::name('admin_role')->select();
        $roleMap = [];
        foreach ($roles as $role) {
            $roleMap[$role['id']] = $role['name'];
        }
        
        return $this->fetch('/'.$this->web["admintemplate"]."/admin_list", [
            'admins'   => $admins,
            'roleMap'  => $roleMap,
            'isFounder'=> $this->isFounder,
        ]);
    }

    // Add new admin
    public function add()
    {
        if (Request::instance()->isPost()) {
            $array = ["code" => "-1", "msg" => ""];
            $user = input('user', '');
            $password = input('password', '');
            $name = input('name', '');
            $mail = input('mail', '');
            $qq = input('qq', '');
            $role_id = input('role_id', 0);
            
            if (empty($user) || empty($password) || empty($name)) {
                $array['code'] = '-1';
                $array['msg'] = '必填参数不可为空';
                return json($array);
            }
            
            // 非站长不可创建站长
            if (!$this->isFounder && in_array($role_id, [1, 2])) {
                $array['code'] = '-1';
                $array['msg'] = '仅站长可以创建站长级别的管理员';
                return json($array);
            }
            
            $exists = Db::name('admin')->where('user', $user)->find();
            if ($exists) {
                $array['code'] = '-1';
                $array['msg'] = '管理员账号已存在';
                return json($array);
            }
            
            $data = [
                'user' => $user,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'name' => $name,
                'mail' => $mail,
                'qq' => $qq,
                'role_id' => $role_id,
                'is_super' => 0,
                'status' => 1,
                'created_at' => time(),
            ];
            
            $id = Db::name('admin')->insertGetId($data);
            if ($id) {
                $array['code'] = '1';
                $array['msg'] = '添加成功';
                admin_op_log('admin_add', '添加管理员：' . $user, ['role_id' => $role_id, 'name' => $name]);
                return json($array);
            }
            $array['code'] = '-1';
            $array['msg'] = '添加失败';
            return json($array);
        }
        
        $roles = $this->isFounder
            ? Db::name('admin_role')->order('id asc')->select()
            : Db::name('admin_role')->where('id', 'not in', [1, 2])->order('id asc')->select();
        return $this->fetch('/'.$this->web["admintemplate"]."/admin_add", [
            'roles' => $roles,
            'isCurrentSuper' => $this->isFounder,
        ]);
    }

    // Edit admin
    public function edit($id = null)
    {
        if (!$id) {
            $this->redirect('/admin/admin_manager');
        }
        
        $admin = Db::name('admin')->where('id', $id)->find();
        if (!$admin) {
            $this->redirect('/admin/admin_manager');
        }
        
        // 当前操作者是否为站长
        $isCurrentSuper = $this->isFounder;
        
        if (Request::instance()->isPost()) {
            $array = ["code" => "-1", "msg" => ""];
            $user = input('user', '');
            $name = input('name', '');
            $mail = input('mail', '');
            $qq = input('qq', '');
            $password = input('password', '');
            $role_id = input('role_id', 0);
            $status = input('status', 1);
            
            if (empty($user) || empty($name)) {
                $array['code'] = '-1';
                $array['msg'] = '用户名和姓名不能为空';
                return json($array);
            }
            
            // 检查用户名是否已被其他管理员使用
            $exists = Db::name('admin')->where('user', $user)->where('id', '<>', $id)->find();
            if ($exists) {
                $array['code'] = '-1';
                $array['msg'] = '该用户名已被使用';
                return json($array);
            }
            
            // 非站长不可提升他人为站长
            if (!$this->isFounder && in_array($role_id, [1, 2])) {
                $array['code'] = '-1';
                $array['msg'] = '仅站长可以设置站长权限';
                return json($array);
            }
            
            // 禁止非超级管理员修改自己的角色（防止自我降权导致无法管理）
            if (!$this->hasFullAccess && $id == $this->user['id']) {
                $role_id = $this->user['role_id'];
            }

            // 根据角色自动设置 is_super：role_id=1 即为站长
            $is_super = ($role_id == 1) ? 1 : 0;

            $update = [
                'user' => $user,
                'name' => $name,
                'mail' => $mail,
                'qq' => $qq,
                'role_id' => $role_id,
                'status' => $status,
                'is_super' => $is_super,
            ];
            
            if (!empty($password)) {
                $update['password'] = password_hash($password, PASSWORD_DEFAULT);
            }
            
            $result = Db::name('admin')->where('id', $id)->update($update);
            if ($result !== false) {
                $array['code'] = '1';
                $array['msg'] = '修改成功';
                admin_op_log('admin_edit', '编辑管理员：' . $user, ['role_id' => $role_id, 'status' => $status, 'pwd_changed' => !empty($password)]);
                return json($array);
            }
            $array['code'] = '-1';
            $array['msg'] = '修改失败';
            return json($array);
        }
        
        // 站长可看到所有角色，其他人只能分配非站长/非超级管理员角色
        $roles = $this->isFounder
            ? Db::name('admin_role')->order('id asc')->select()
            : Db::name('admin_role')->where('id', 'not in', [1, 2])->order('id asc')->select();
        return $this->fetch('/'.$this->web["admintemplate"]."/admin_edit", [
            'admin' => $admin,
            'roles' => $roles,
            'isCurrentSuper' => $this->isFounder,
        ]);
    }

    // Delete admin
    public function delete()
    {
        if (Request::instance()->isPost()) {
            $array = ["code" => "-1", "msg" => ""];
            $id = input('id', 0);
            if (!$id) {
                $array['code'] = '-1';
                $array['msg'] = '参数错误';
                return json($array);
            }
            
            $admin = Db::name('admin')->where('id', $id)->find();
            if (!$admin) {
                $array['code'] = '-1';
                $array['msg'] = '管理员不存在';
                return json($array);
            }
            
            // 仅站长可以删除管理员，但站长不能删除自己
            if (!$this->isFounder) {
                $array['code'] = '-1';
                $array['msg'] = '仅站长可以删除管理员';
                return json($array);
            }
            if ($id == session('adminid')) {
                $array['code'] = '-1';
                $array['msg'] = '不能删除自己';
                return json($array);
            }
            
            $result = Db::name('admin')->where('id', $id)->delete();
            if ($result) {
                $array['code'] = '1';
                $array['msg'] = '删除成功';
                admin_op_log('admin_delete', '删除管理员：' . $admin['user'], ['id' => $id]);
                return json($array);
            }
            $array['code'] = '-1';
            $array['msg'] = '删除失败';
            return json($array);
        }
    }

    // List roles
    public function roles()
    {
        $roles = Db::name('admin_role')->select();
        return $this->fetch('/'.$this->web["admintemplate"]."/admin_roles", [
            'roles'    => $roles,
            'isFounder'=> $this->isFounder,
        ]);
    }

    // Add role
    public function addRole()
    {
        if (Request::instance()->isPost()) {
            $array = ["code" => "-1", "msg" => ""];
            $name = input('name', '');
            $description = input('description', '');
            $permissions = input('permissions/a', []);
            
            if (empty($name)) {
                $array['code'] = '-1';
                $array['msg'] = '角色名称不能为空';
                return json($array);
            }
            
            $data = [
                'name' => $name,
                'description' => $description,
                'permissions' => json_encode($permissions),
                'created_at' => time(),
            ];
            
            $id = Db::name('admin_role')->insertGetId($data);
            if ($id) {
                $array['code'] = '1';
                $array['msg'] = '添加成功';
                admin_op_log('role_add', '添加角色：' . $name, ['permissions' => $permissions]);
                return json($array);
            }
            $array['code'] = '-1';
            $array['msg'] = '添加失败';
            return json($array);
        }
        
        return $this->fetch('/'.$this->web["admintemplate"]."/admin_role_add");
    }

    // Edit role
    public function editRole($id = null)
    {
        if (!$id) {
            $this->redirect('/admin/admin_manager/roles');
        }
        
        $role = Db::name('admin_role')->where('id', $id)->find();
        if (!$role) {
            $this->redirect('/admin/admin_manager/roles');
        }
        
        // 仅站长可编辑系统默认角色
        $isCurrentSuper = ($this->user['is_super'] == 1 || $this->user['role_id'] == 1);
        if (in_array($id, [1, 2]) && !$isCurrentSuper) {
            $this->error('您没有权限编辑系统默认角色', '/admin/admin_manager/roles');
        }
        
        if (Request::instance()->isPost()) {
            $array = ["code" => "-1", "msg" => ""];
            $name = input('name', '');
            $description = input('description', '');
            $permissions = input('permissions/a', []);
            
            if (empty($name)) {
                $array['code'] = '-1';
                $array['msg'] = '角色名称不能为空';
                return json($array);
            }
            
            $data = [
                'name' => $name,
                'description' => $description,
                'permissions' => json_encode($permissions),
            ];
            
            $result = Db::name('admin_role')->where('id', $id)->update($data);
            if ($result !== false) {
                $array['code'] = '1';
                $array['msg'] = '修改成功';
                admin_op_log('role_edit', '编辑角色：' . $name, ['permissions' => $permissions]);
                return json($array);
            }
            $array['code'] = '-1';
            $array['msg'] = '修改失败';
            return json($array);
        }
        
        $role['permissions'] = json_decode($role['permissions'], true);
        return $this->fetch('/'.$this->web["admintemplate"]."/admin_role_edit", [
            'role' => $role,
        ]);
    }

    // Delete role
    public function deleteRole()
    {
        if (Request::instance()->isPost()) {
            $array = ["code" => "-1", "msg" => ""];
            $id = input('id', 0);
            if (in_array($id, [1, 2])) {
                $array['code'] = '-1';
                $array['msg'] = '不能删除系统默认角色';
                return json($array);
            }
            
            $role = Db::name('admin_role')->where('id', $id)->find();
            $roleName = $role ? $role['name'] : ('ID:' . $id);
            $result = Db::name('admin_role')->where('id', $id)->delete();
            if ($result) {
                $array['code'] = '1';
                $array['msg'] = '删除成功';
                admin_op_log('role_delete', '删除角色：' . $roleName, ['id' => $id]);
                return json($array);
            }
            $array['code'] = '-1';
            $array['msg'] = '删除失败';
            return json($array);
        }
    }
}