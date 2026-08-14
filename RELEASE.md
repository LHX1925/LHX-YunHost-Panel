# 发布声明

## 项目名称

**LHX-YunHost-Panel** — 虚拟云主机销售管理系统

## 版本

v1.0.0

## 发布日期

2026 年 8 月

## 项目简介

LHX-YunHost-Panel 是一套基于 ThinkPHP 5 开发的虚拟主机在线销售与管理系统，覆盖商品展示、在线下单、支付结算、自动开通、售后管理全流程，支持对接 EasyPanel、梦奈宝塔等主流主机面板，助力快速搭建 IDC 业务网站。

## 核心特性

- 现代化玻璃态 UI，毛玻璃风格，支持自定义透明度/模糊度/配色
- 背景图、轮播图、GIF、视频等多种背景模式
- Live2D AI 看板娘，基于 DeepSeek API，支持自定义人设
- 购物车 + 多产品批量下单
- 支持易支付、支付宝当面付等多种支付方式
- 主机自动开通，支持延迟开通与手动开通
- 会员等级体系 + 积分签到 + 积分商城
- 推荐返利系统
- 主机在线转让市场
- 工单系统 + 实名认证 + 卡密兑换
- 聚合登录（QQ 一键登录）
- 滑块验证码
- 访客流量统计（含中国地图可视化）
- 管理员 RBAC 权限体系
- 登录日志 / 操作日志审计

## 技术栈

| 层级 | 技术 |
|------|------|
| 后端 | PHP 7.2+ / ThinkPHP 5.0 |
| 数据库 | MySQL 5.6+ |
| 前端 | HTML5 + CSS3 + jQuery + Bootstrap |
| AI | DeepSeek Chat API |
| Live2D | Cubism 4 SDK |

## 环境要求

- PHP 7.2 - 8.0+
- MySQL 5.6+
- Apache / Nginx / LiteSpeed
- PHP 扩展：pdo_mysql、curl、gd、openssl

## 快速开始

1. 将 Web 服务器运行目录指向 `public/`
2. 配置伪静态（Apache .htaccess 或 Nginx rewrite）
3. 访问网站自动进入安装向导
4. 按提示完成数据库配置与管理员设置

详细部署说明请参阅 [README.md](README.md)。

## 开源协议

本项目基于 **MIT License** 开源。

## 免责声明

1. 本项目仅供学习交流使用，请勿用于任何违法违规用途。
2. 使用本项目产生的一切后果由使用者自行承担，开发者不承担任何责任。
3. 本项目部分代码来源于 SIB-HOST（sib.cc 思博系统），在此对原作者表示感谢。
4. 项目中涉及的第三方服务（如 DeepSeek、Mapay、EasyPanel 等）的商标归各自所有者所有，本项目仅提供对接接口。

## 联系与反馈

- GitHub 仓库：https://github.com/LHX1925/LHX-YunHost-Panel
- 如有问题或建议，请在 GitHub Issues 中提交反馈。

## 致谢

感谢以下开源项目和服务：

- [ThinkPHP](http://thinkphp.cn) — PHP 开发框架
- [SIB-HOST](https://sib.cc) — 原始项目基础
- [DeepSeek](https://platform.deepseek.com) — AI 对话模型
- [PHPMailer](https://github.com/PHPMailer/PHPMailer) — 邮件发送组件
- [OneUI](https://pixelcave.com/oneui) — 后台 UI 框架
- [Live2D Cubism SDK](https://www.live2d.com) — 看板娘渲染引擎