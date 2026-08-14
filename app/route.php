<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\Route;
//定义404
Route::miss('index/index/null');

return [
//安装向导
"/install"=>"install/index/index",
"/install/index"=>"install/index/index",
"/install/index/step2"=>"install/index/step2",
"/install/index/step3"=>"install/index/step3",
"/install/index/step4"=>"install/index/step4",
"/install/index/done"=>"install/index/done",

//前台
"/"=>"index/index/index",
"/index"=>"index/index/index",
"/login"=>"index/index/login",
"/register"=>"index/index/register",
"/cart/[:id]"=>"index/index/cart",
"/user"=>"index/user/index",
"/user/index"=>"index/user/index",
"/user/password"=>"index/user/password",
"/user/information"=>"index/user/information",
"/user/logout"=>"index/user/logout",
"/product/[:id]"=>"index/index/product",
"/user/pay"=>"index/user/pay",
"/user/order/[:id]"=>"index/user/order",
"/user/return/:id"=>"index/user/return",
"/index/notify/:id"=>"index/index/notify",
"/announcement/[:id]"=>"index/index/announcement",
"/announcements"=>"index/index/announcement",
"/user/payrecord"=>"index/user/payrecord",
"/user/realname"=>"index/user/realname",
"/user/cdkey"=>"index/user/cdkey",
"/user/announcements"=>"index/user/announcements",
"/user/announcementRead"=>"index/user/announcementRead",
"/verify_email"=>"index/index/verifyEmail",
"/user/cart"=>"index/user/cart",
"/user/cartAdd"=>"index/user/cartAdd",
"/user/cartCheckout"=>"index/user/cartCheckout",
"/user/cartSettle"=>"index/user/cartSettle",
"/user/cartDel"=>"index/user/cartDel",
"/user/cartPay/:payid"=>"index/user/cartPay",
"/cron"=>"index/index/cron",
"/pwreset"=>"index/index/pwreset",
"/help"=>"index/index/help",
"/user/submitticket"=>"index/user/submitticket",
"/user/supportticket/[:id]"=>"index/user/supportticket",
"/user/mail"=>"index/user/mail",
"/user/transfer"=>"index/user/transfer",
"/user/transferMarket"=>"index/user/transferMarket",
"/user/transferHost"=>"index/user/transferHost",
"/user/transferBuy"=>"index/user/transferBuy",
"/user/transferContact"=>"index/user/transferContact",
"/user/transferCancel"=>"index/user/transferCancel",
"/user/transferDetail"=>"index/user/transferDetail",
"/user/transferSendCode"=>"index/user/transferSendCode",
"/user/transferSendMsg"=>"index/user/transferSendMsg",
"/user/transferGetMsgs"=>"index/user/transferGetMsgs",
"/user/transferUnreadCount"=>"index/user/transferUnreadCount",
"/user/qqGroupVerify"=>"index/user/qqGroupVerify",
"/user/transaction"=>"index/user/transaction",
"/user/aff"=>"index/user/aff",
"/user/transferrecord"=>"index/user/transferrecord",
"/aff/:upper"=>"index/index/aff",
"/sq"=>"index/sq/sq",


//后台
"/admin"=>"admin/index/index",
"/admin/login"=>"admin/login/index",
"/admin/index"=>"admin/index/index",
"/admin/info"=>"admin/index/info",
"/admin/password"=>"admin/index/password",
"/admin/logout"=>"admin/index/logout",
"/admin/set"=>"admin/index/set",
"/admin/user/[:id]/[:orderid]"=>"admin/index/user",
"/admin/ticket/[:id]"=>"admin/index/ticket",
"/admin/classification/[:id]"=>"admin/index/classification",
"/admin/server/[:id]"=>"admin/index/server",
"/admin/product/[:id]"=>"admin/index/product",
"/admin/announcement/[:id]"=>"admin/index/announcements",
"/admin/aff"=>"admin/index/aff",
"/admin/affsy"=>"admin/index/affsy",
"/admin/pay"=>"admin/index/pay",
"/admin/transferrecord"=>"admin/index/transferrecord",
"/admin/transferHostRecord"=>"admin/index/transferHostRecord",
"/admin/transaction"=>"admin/index/transaction",
"/admin/templateset"=>"admin/index/templateset",
"/admin/order/[:id]"=>"admin/index/order",
"/admin/realnameReview"=>"admin/index/realnameReview",
"/admin/test_realname_api"=>"admin/index/testRealnameApi",
"/admin/pays/[:id]"=>"admin/index/pays",
"/admin/sq/[:id]"=>"admin/index/sq",
"/admin/cdkey"=>"admin/index/cdkey",
"/admin/announcements/[:id]"=>"admin/index/announcements",
"/admin/loginLog"=>"admin/index/loginLog",
"/admin/opLog"=>"admin/index/opLog",
"/admin/violation/[:id]"=>"admin/index/violation",
"/admin/emailAudit"=>"index/index/emailAudit",
"/admin/bg_upload"=>"admin/index/bg_upload",
"/admin/bg_multi_upload"=>"admin/index/bg_multi_upload",
"/admin/bg_reset"=>"admin/index/bg_reset",
"/admin/admin_manager"=>"admin/admin_manager/index",
"/admin/admin_manager/add"=>"admin/admin_manager/add",
"/admin/admin_manager/edit/[:id]"=>"admin/admin_manager/edit",
"/admin/admin_manager/delete"=>"admin/admin_manager/delete",
"/admin/admin_manager/roles"=>"admin/admin_manager/roles",
"/admin/admin_manager/add_role"=>"admin/admin_manager/addRole",
"/admin/admin_manager/edit_role/[:id]"=>"admin/admin_manager/editRole",
"/admin/admin_manager/delete_role"=>"admin/admin_manager/deleteRole",

// 后台主机管理
"/admin/admin_host"=>"admin/admin_host/index",
"/admin/admin_host/operate"=>"admin/admin_host/operate",
"/admin/admin_host/suspendAll"=>"admin/admin_host/suspendAll",
"/admin/admin_host/unsuspendAll"=>"admin/admin_host/unsuspendAll",
"/admin/admin_host/deleteAll"=>"admin/admin_host/deleteAll",

// 后台IP封禁管理
"/admin/ip_ban"=>"admin/ip_ban/index",
"/admin/ip_ban/unban"=>"admin/ip_ban/unban",
"/admin/ip_ban/ban"=>"admin/ip_ban/ban",

// 后台地图数据接口
"/admin/chinamapjson"=>"admin/index/chinaMapJson",
"/admin/ajaxmapdata"=>"admin/index/ajaxMapData",

//积分签到和积分商城
"/user/checkin"=>"index/user/checkin",
"/user/pointsShop"=>"index/user/pointsShop",
"/user/pointsExchange"=>"index/user/pointsExchange",

//后台积分商城和会员等级
"/admin/index/pointsProducts/[:id]"=>"admin/index/pointsProducts",
"/admin/index/membershipLevels/[:id]"=>"admin/index/membershipLevels",

//后台访客统计
"/admin/visitorStats"=>"admin/index/visitorStats",

//滑块验证码
"/captcha/generate"=>"index/captcha/generate",
"/captcha/verify"=>"index/captcha/verify",
// 兼容原版滑动验证码路由
"/index/slide_captcha/create"=>"index/captcha/generate",
"/index/slide_captcha/verify"=>"index/captcha/verify",

//排行榜
"/rankings"=>"index/index/rankings",

//Live2D AI聊天
"/live2d/chat"=>"index/live2d/chat",
//Live2D 手机端纹理压缩（服务器端降采样并缓存）
"/live2d/texture"=>"index/live2d/texture",

//聚合登录
"/oauth/login/:type"=>"index/oauth/login",
"/oauth/callback"=>"index/oauth/callback",
"/oauth/bind"=>"index/oauth/bind",
"/oauth/userBind/:type"=>"index/oauth/userBind",
"/oauth/unbind/:type"=>"index/oauth/unbind",
];
