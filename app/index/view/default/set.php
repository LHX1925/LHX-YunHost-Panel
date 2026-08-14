<?php
$data=[

//编辑框
["name"=>"网站背景",'title'=>'网站背景', 'type'=>'input',"prompt"=>"网站背景图地址 ","value"=>"/static/assets/images/bj.png"],

["name"=>"网站标题",'title'=>'网站标题', 'type'=>'input',"prompt"=>"网站首页标题","value"=>"只提供快速、稳定、优质的虚拟主机服务!"],

["name"=>"公告开关",'title'=>'是否开启公告', 'type'=>'select',"prompt"=>"公告开关","value"=>"关","option"=>["开","关"]],

["name"=>"网站公告",'title'=>'网站公告', 'type'=>'textarea',"prompt"=>"公告内容,支持HTML","value"=>''],


["name"=>"侧边栏",'title'=>'侧边栏', 'type'=>'textarea',"prompt"=>"侧边栏","value"=>'<li class="nav-main-item"><a class="nav-main-link" href=""><i class="nav-main-link-icon fas fa-paper-plane"></i><span class="nav-main-link-name">测试</span></a></li>'],


["name"=>"网站备案号",'title'=>'网站备案号', 'type'=>'input',"prompt"=>"网站备案号 ","value"=>""],

/**
//选择框
["name"=>"测试",'title'=>'测试', 'type'=>'select',"prompt"=>"测试","value"=>"是","option"=>["是","否"]],
**/
];
return $data;