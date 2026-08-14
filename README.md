# LHX-YunHost-Panel · 虚拟云主机销售管理系统

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
  <img src="https://img.shields.io/badge/PHP-7.2--8.0+-777BB4?logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/MySQL-5.6+-4479A1?logo=mysql" alt="MySQL">
  <img src="https://img.shields.io/badge/ThinkPHP-5.0-brightgreen" alt="ThinkPHP">
  <img src="https://img.shields.io/badge/Web_Server-Apache|Nginx|LiteSpeed-blue" alt="Web Server">
</p>

<p align="center">
  一款基于 <b>ThinkPHP 5</b> 的虚拟主机自动销售与管理系统，支持多面板对接、在线支付、会员体系、工单系统、Live2D AI 看板娘等丰富功能。
</p>

---

## 目录

- [项目简介](#项目简介)
- [核心功能](#核心功能)
  - [前台展示](#前台展示)
  - [用户中心](#用户中心)
  - [管理后台](#管理后台)
  - [插件系统](#插件系统)
  - [特色功能](#特色功能)
- [技术栈](#技术栈)
- [环境要求](#环境要求)
- [快速部署](#快速部署)
- [目录结构](#目录结构)
- [配置说明](#配置说明)
- [更新升级](#更新升级)
- [常见问题](#常见问题)
- [安全建议](#安全建议)
- [相关文档](#相关文档)
- [声明](#声明)

---

## 项目简介

**LHX-YunHost-Panel** 是一套完整的虚拟主机在线销售解决方案，帮助您快速搭建自己的 IDC 业务网站。系统覆盖从商品展示、在线下单、自动开通到售后管理的全流程，支持对接 EasyPanel、梦奈宝塔等主流主机面板，实现主机产品的自动开通、暂停、续费、删除等操作。

### 适用场景

- 虚拟主机 / 云服务器销售商
- IDC 代理商
- 个人站长售卖主机产品

---

## 核心功能

### 前台展示

| 功能 | 说明 |
|------|------|
| 产品展示 | 分类展示主机套餐，支持自定义规格与价格 |
| 购物车 | 批量选购、一键结算，支持多产品同时下单 |
| 公告系统 | 发布站点公告与维护通知，首页弹窗提示 |
| 排行榜 | 用户消费排行，支持切换日/月/总榜 |
| 帮助中心 | 常见问题与使用教程 |
| 聚合登录 | 支持 QQ 等第三方 OAuth 一键登录/绑定 |
| 玻璃态主题 | 全站毛玻璃 UI 风格，支持背景图/轮播/视频 |
| Live2D 看板娘 | 右下角 AI 看板娘，支持聊天互动，后台可自定义人设 |

### 用户中心

| 功能模块 | 说明 |
|----------|------|
| 控制台 | 概览主机数量、余额、订单状态，快捷操作入口 |
| 主机管理 | 查看/续费/升降级/重置密码/登录面板 |
| 购物车 | 商品选购、结算支付 |
| 在线充值 | 支持支付宝、微信支付，充值记录可查 |
| 订单管理 | 查看所有订单状态与详情 |
| 实名认证 | 对接第三方实名认证 API，支持按次收费 |
| 卡密兑换 | 余额卡密 / 主机卡密，一键兑换 |
| 积分签到 | 每日签到获取积分，积分商城兑换商品 |
| 会员等级 | 多级会员体系，不同等级享不同折扣 |
| 工单系统 | 提交售后工单，管理员回复处理 |
| 邮箱绑定 | 绑定邮箱用于找回密码、接收通知 |
| 推荐返利 | 专属推广链接，按比例返佣 |
| 交易记录 | 收支明细，含余额变动记录 |
| 违规记录 | 查看违规警告与处罚记录 |
| 主机转让 | 支持主机在线转让给其他用户，含消息沟通 |

### 管理后台

| 功能模块 | 说明 |
|----------|------|
| 系统设置 | 网站名称、描述、Logo、备案号、公告等全局配置 |
| 主题设置 | 配色方案（冰蓝/粉蓝）、透明度、模糊程度自定义 |
| 背景设置 | 上传背景图、轮播背景图、GIF/视频背景，支持模糊度调节 |
| AI 设置 | 对接 DeepSeek API，自定义 Live2D 看板娘人设 |
| 用户管理 | 用户列表、详情、封禁/解封、余额调整 |
| 产品管理 | 主机产品分类、规格、价格、服务器绑定 |
| 服务器管理 | 对接主机面板（EasyPanel/梦奈宝塔），接口配置 |
| 订单管理 | 查看/处理/删除订单，手动开通主机 |
| 支付方式 | 配置支付接口（易支付/支付宝当面付） |
| 卡密管理 | 生成余额卡密与主机卡密，支持批量导出 |
| 工单处理 | 回复与关闭用户工单 |
| 公告管理 | 发布/编辑/删除站点公告 |
| 实名审核 | 审核用户实名认证申请 |
| 推广管理 | 查看推广记录与提成结算 |
| 会员等级 | 自定义等级折扣与升级条件 |
| 积分商城 | 管理积分兑换商品 |
| 违规管理 | 添加/编辑/删除违规记录 |
| 主机管理 | 批量操作主机（暂停/解除/删除） |
| IP 封禁 | IP 黑名单管理 |
| 管理员管理 | 多管理员角色与权限分配（RBAC） |
| 登录日志 | 管理员登录记录 |
| 操作日志 | 管理员操作审计 |
| 访客统计 | 网站流量统计，含中国地图可视化 |
| 邮件审核 | 审核用户绑定邮箱 |

### 插件系统

| 类型 | 已支持 | 说明 |
|------|--------|------|
| 主机面板 | EasyPanel（康乐面板） | 自动开通/暂停/续费/删除主机 |
| 主机面板 | 梦奈宝塔（MNBT） | 对接梦奈宝塔接口 |
| 支付接口 | 易支付（ePay） | 聚合支付，支持支付宝/微信 |
| 支付接口 | 支付宝当面付 | 支付宝官方扫码支付 |

### 特色功能

| 功能 | 说明 |
|------|------|
| 玻璃态主题 | 全站毛玻璃 UI，透明度/模糊度/配色方案后台可调 |
| 背景图轮播 | 支持多张背景图轮播切换，GIF 动态背景 |
| Live2D AI 看板娘 | 舒芙蕾看板娘，对话型 AI 助手，后台可自定义人设与模型 |
| 聚合登录 | 第三方 OAuth 一键登录，支持 Mapay QQ 登录 |
| 滑块验证码 | 安全防刷验证码 |
| 主机转让市场 | 用户间主机在线转让，含消息沟通功能 |
| 自动开通 | 支持延迟自动开通，时间可自定义 |
| 重装保留数据 | 安装程序支持保留数据库数据 |

---

## 技术栈

| 层级 | 技术 |
|------|------|
| 后端框架 | ThinkPHP 5.0.24 |
| 数据库 | MySQL 5.6+ |
| 前端 | HTML5 + CSS3 + JavaScript + jQuery |
| UI 框架 | OneUI + Bootstrap + 自定义玻璃态主题 |
| AI 模型 | DeepSeek（OpenAI 兼容 API） |
| 邮件 | PHPMailer |
| Live2D | Cubism 4 SDK |

---

## 环境要求

- **PHP** 7.2 - 8.0+（推荐 7.4 / 8.0）
- **MySQL** 5.6 或更高版本
- **Web 服务器** Apache / Nginx / LiteSpeed
- **PHP 扩展**：`pdo_mysql`、`curl`、`gd`、`openssl`、`fileinfo`

---

## 快速部署

### 1. 上传源码

将项目所有文件上传到服务器，确保 Web 服务器**运行目录**指向 `public/` 文件夹。

> 如果不设置运行目录，将暴露 ThinkPHP 核心代码，存在严重安全风险！

#### 宝塔面板设置

网站 → 设置 → 网站目录 → 运行目录：选择 `/public` → 保存

#### Apache 配置

```apache
DocumentRoot "/path/to/project/public"
<Directory "/path/to/project/public">
    AllowOverride All
    Require all granted
</Directory>
```

#### Nginx 配置

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/project/public;
    index index.php index.html;

    location / {
        if (!-e $request_filename) {
            rewrite ^(.*)$ /index.php?s=$1 last;
            break;
        }
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 2. 配置伪静态

#### Apache

确保 `public/.htaccess` 文件存在：

```apache
<IfModule mod_rewrite.c>
  Options +FollowSymlinks -Multiviews
  RewriteEngine On
  RewriteCond %{REQUEST_FILENAME} !-d
  RewriteCond %{REQUEST_FILENAME} !-f
  RewriteRule ^(.*)$ index.php/$1 [QSA,PT,L]
</IfModule>
```

### 3. 运行安装向导

首次访问网站时，系统会自动跳转到安装向导页面：

1. **许可协议** — 阅读并同意
2. **环境检测** — 自动检测 PHP 版本、扩展、目录权限
3. **数据库配置** — 填写 MySQL 连接信息（数据库不存在则自动创建）
4. **管理员设置** — 设置网站名称、管理员账号和密码

安装完成后生成 `install.lock` 文件，防止重复安装。

> 如需重新安装，删除 `install.lock` 文件即可。

### 4. 访问系统

- **前台首页**：`http://yourdomain.com`
- **管理后台**：`http://yourdomain.com/admin`

---

## 目录结构

```
project/
├── app/                    # 应用目录
│   ├── admin/              # 后台管理模块
│   │   ├── controller/     # 后台控制器
│   │   └── view/           # 后台视图模板
│   ├── index/              # 前台模块
│   │   ├── controller/     # 前台控制器
│   │   └── view/           # 前台视图模板
│   ├── install/            # 安装向导模块
│   ├── common.php          # 公共函数
│   ├── common_security.php # 安全配置
│   ├── config.php          # 应用配置
│   ├── database.php        # 数据库配置
│   └── route.php           # 路由配置
├── extend/                 # 扩展目录
│   ├── PHPMailer/          # 邮件发送组件
│   └── pay/                # 支付组件
├── frame/                  # ThinkPHP 框架核心
├── plugins/                # 插件目录
│   ├── host/               # 主机面板插件
│   └── pay/                # 支付接口插件
├── public/                 # 网站根目录（Web 服务器指向此处）
│   ├── static/             # 静态资源（CSS/JS/图片/字体）
│   └── index.php           # 入口文件
├── runtime/                # 运行时目录（缓存/日志）
├── docs/                   # 文档
├── install.lock            # 安装锁文件
└── README.md               # 本文件
```

---

## 配置说明

### 数据库配置

安装完成后，数据库配置保存在 `app/database.php`：

```php
return [
    'type'     => 'mysql',
    'hostname' => '127.0.0.1',
    'database' => 'your_database',
    'username' => 'your_username',
    'password' => 'your_password',
    'hostport' => '3306',
    'prefix'   => 'sale_',
];
```

### 应用配置

关键配置在 `app/config.php`：

| 配置项 | 说明 |
|--------|------|
| `app_debug` | 调试模式，生产环境请设为 `false` |
| `app_trace` | 页面 Trace 调试，生产环境请设为 `false` |
| `default_return_type` | 默认输出类型（json/html） |

### 对接主机面板

1. 进入后台 → 服务器管理 → 添加服务器
2. 选择面板类型（EasyPanel / 梦奈宝塔）
3. 填写面板地址、API 密钥等参数
4. 在产品管理中，将产品绑定到对应服务器

详细教程：[梦奈宝塔对接教程](docs/梦奈宝塔对接教程.md) | [EasyPanel 对接教程](docs/EasyPanel对接教程.md)

### 对接 AI 看板娘

1. 注册 [DeepSeek](https://platform.deepseek.com/) 获取 API Key
2. 后台 → 设置 → Live2D/看板娘 → AI 聊天设置
3. 填入 API Key，选择模型
4. 可选：自定义 AI 人设（性格、语气、身份设定）

### 对接支付接口

后台 → 支付方式 → 选择支付插件 → 填写商户 ID/密钥等信息

---

## 更新升级

1. **备份数据**：导出数据库，备份整个网站目录
2. **下载新版**：从仓库下载最新版本
3. **覆盖文件**：上传新版覆盖旧文件，**不要覆盖**：
   - `app/database.php`
   - `app/config.php`
   - `runtime/` 目录
   - `install.lock`
4. **执行升级脚本**：如有 `update.sql`，在数据库中执行
5. **清理缓存**：删除 `runtime/` 目录下的缓存文件
6. **验证**：访问前台和后台确认功能正常

---

## 常见问题

| 问题 | 解决方法 |
|------|----------|
| 页面空白或 500 错误 | 检查 PHP 版本，确认 `runtime/` 目录有写入权限 |
| 样式丢失 | 确认 Web 服务器运行目录指向 `public/` |
| 数据库连接失败 | 检查 `app/database.php` 配置，确认 MySQL 服务运行中 |
| 安装向导无法访问 | 配置伪静态规则，检查目录权限 |
| 后台登录后跳转 | 清除浏览器缓存，确认 Session 配置正确 |
| QQ 登录无效 | 确认 `common_security.php` 中 SameSite 为 Lax |

---

## 安全建议

1. 生产环境关闭调试模式：`app/config.php` → `app_debug` → `false`
2. 确保 `public/` 外的文件无法通过 Web 直接访问
3. 定期备份数据库和重要文件
4. 安装完成后删除 `app/install/` 目录
5. 首次登录后立即修改管理员密码
6. 使用 HTTPS 加密传输

---

## 相关文档

- [宝塔面板安装教程](btpanel_install/宝塔面板安装教程.md)
- [梦奈宝塔对接教程](docs/梦奈宝塔对接教程.md)
- [EasyPanel 对接教程](docs/EasyPanel对接教程.md)

---

## 声明

本项目部分代码来源于 SIB-HOST（sib.cc 思博系统），在此对原作者表示感谢。本项目仅供学习交流使用，请勿用于非法用途。
