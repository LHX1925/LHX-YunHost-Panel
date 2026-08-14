<?php
use think\Db;
use think\Request;
$user=Db::name("user")->where("id",session("userid"))->find();
$file=file_exists(PATH."/plugins/pay/".$data1["plugins"]."/set.php");
if($file){
if($data1["data"]){
$ppay=json_decode($data1["data"],true);
}else{
exit("<title>出错啦!</title>没有参数文件!");
}
}else{
exit("<title>出错啦!</title>没有参数文件!");
}
$out_trade_no = date('YmdHis').rand(000,999);

$db=Db::name("pay")->insertGetId([
"name"=>"余额充值",
"ordernumber"=>$out_trade_no,
"pay"=>$data1["id"],
"money"=>input("money"),
"userid"=>$user["id"],
"time"=>time(),
"state"=>"2",
]);
 





//支付配置
$appid = $ppay["0"]["value"];       //APPID
$rsaPrivateKey= $ppay["1"]["value"];  //私钥
 
//默认配置
$notifyUrl = Request::instance()->domain() . Request::instance()->root() ."/index/notify/".$data1["id"]."/";     //付款成功后的异步回调地址
$outTradeNo = $out_trade_no;     //商品订单号
 
//取商品名称和金额
$payAmount = input("money");          //付款金额，单位:元
$orderName = "账号ID:".$user["id"].",余额充值";          //订单标题
 
//发起支付
$aliPay = new IndexService();
$aliPay->setAppid($appid);
$aliPay->setNotifyUrl($notifyUrl);
$aliPay->setRsaPrivateKey($rsaPrivateKey);
$aliPay->setTotalFee($payAmount);
$aliPay->setOutTradeNo($outTradeNo);
$aliPay->setOrderName($orderName);
$result = $aliPay->doPay();
$result = $result['alipay_trade_precreate_response'];
if($result['code']=='10000'){
    $url = 'https://api.pwmqr.com/qrcode/create/?url='.$result['qr_code'];
    exit("
<!DOCTYPE html>
<html lang='en'>
<style>
body{
    width: 100%;
    height: 100vh;
    background: #ffffff;
}
.header__content{
    padding-top: 37px;
}
.header__logo{
    color: #2d8cf0;
    background: 0 0;
    background-color: rgba(0, 0, 0, 0);
    background-color: transparent;
    outline: 0;
    transition: color .2s ease;
    text-decoration: none;
}
.header__logo img {
    width: 30px;
}
.header__logo span {
    font-size: 18px;
    color: #4375ff;
    font-weight: 700;
    margin-left: 5px;
}
.header__order{
    display: inline-block;
    width: 219px;
    line-height: 35px;
    font-weight: 700;
    font-size: 14px;
    border-radius: 19px;
    text-align: center;
    position: relative;
    -webkit-box-shadow: 0 5px 6px 0 rgba(73,105,230,.22);
    box-shadow: 0 5px 6px 0 rgba(73,105,230,.22);
    background: -webkit-gradient(linear,left bottom,left top,from(#2a62ff),to(#4e7dff));
    background: linear-gradient(0deg,#2a62ff,#4e7dff);
    color: #fff;
}
.header__order:hover{
    color: #fff;
    text-decoration: none;
}

.section .container{
    margin: 18px auto 0;
    padding-top:38px;
    background: #fff;
    -webkit-box-shadow: 0 3px 3px 0 hsla(0,0%,92.5%,.44);
    box-shadow: 0 3px 3px 0 hsla(0,0%,92.5%,.44);
    border-radius: 12px;
    text-align: center;
    padding-bottom: 38px;
}
.section .con{
    margin: 3px auto 0;
    padding-top:10px;
    background: #fff;
    -webkit-box-shadow: 0 3px 3px 0 hsla(0,0%,92.5%,.44);
    box-shadow: 0 3px 3px 0 hsla(0,0%,92.5%,.44);
    border-radius: 12px;
    text-align: center;
    padding-bottom: 38px;
}
.time span{
    font-size: 18px;
    color: #545454;
    font-weight: 700;
    display: inline-block;
    vertical-align: middle;
}
.time span p {
    color: #3259ff;
    display: inline-block;
}
.order {
    width: 340px;
    margin: 15px auto 21px;
    background: #fbfbfb;
    border-radius: 6px;
    line-height: 42px;
    text-align: left;
}
.order span:first-child {
    color: #999;
    font-size: 15px;
    margin-left: 14px;
}
.order span:nth-child(2) {
    color: #3259ff;
    font-size: 13px;
    float: right;
    margin-right: 21px;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    cursor: pointer;
}
.goods_name {
    font-weight: 500;
    font-size: 12px;
    color: #999;
    border-bottom: 1px solid #f5f5f5;
    padding-bottom: 20px;
}
.goods_name span:nth-child(2) {
    margin-left: 14px;
}
.yuanmoney {
    width: 340px;
    margin: 15px auto 21px;
    background: #fbfbfb;
    border-radius: 6px;
    line-height: 42px;
    text-align: left;
}
.yuanmoney span:nth-child(2) {
     color: #3259ff;
    font-size: 13px;
    float: right;
    margin-right: 21px;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    cursor: pointer;
}
.pay_type {
    width: 100%;
    text-align: center;
    margin-top: 10px;
}
.pay_type img{
    display: inline-block;
    vertical-align: middle;
    width: 23px;
}
.pay_type span {
    font-weight: 700;
    font-size: 14px;
    color: #545454;
    margin-left: 3px;
    display: inline-block;
    vertical-align: middle;
}
.code_cs,.code {
    height: 208px;
    background: #fbfbfb;
    position: relative;
    width: 208px;
    margin-top: 10px;
    margin-left: -104px;
    left: 50%;
}
.code_cs {
    height: 208px;
    background: #ffffff;
}
.code_cs img {
    position: absolute;
    width: 49px;
    left: 50%;
    margin-left: -25px;
    top: 50%;
    margin-top: -25px;
    padding: 10px;
}
.code {
                border: 5px solid #d8d1d1;
                border-radius: 5px;
                position: relative;
                width: 208px;
                margin-top: 10px;
                margin-left: -104px;
                left: 50%;
                display: block;
                padding: 3px;
            }
.price {
    color: #386cfa;
    width: 100%;
    text-align: center;
    top: 65px;
}
.price span:first-child {
    font-size: 28px;
}
.price span {
    font-weight: 700;
}
.price span:nth-child(2) {
    font-size: 17px;
}
.price span {
    font-weight: 700;
}
.shanxinzha {
    margin-top: 32px;
    width: 100%;
    text-align: center;
}
.shanxinzha img{
    display: inline-block;
    vertical-align: middle;
    width: 26px;
    -webkit-animation: xuanzhuan .8s linear infinite;
}
@-webkit-keyframes xuanzhuan {
    0% {
        -webkit-transform:rotate(0deg)
    }
    25% {
        -webkit-transform:rotate(90deg)
    }
    50% {
        -webkit-transform:rotate(180deg)
    }
    75% {
        -webkit-transform:rotate(270deg)
    }
    to {
        -webkit-transform:rotate(1turn)
    }
}
.shanxinzha span {
    color: #999;
    font-size: 14px;
    font-weight: 400;
    margin-left: 5px;
}
.shanxinzha span p {
    display: inline-block;
    color: #386cfa;
}
.section--last{
    margin-bottom: 20px;
}
</style>
    <head>
     
        <meta charset='utf-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1, shrink-to-fit=no'>
       <link rel='stylesheet' href='https://cdn.bootcdn.net/ajax/libs/twitter-bootstrap/5.3.1/css/bootstrap-grid.css'>
      
        <script src='//lib.baomitu.com/jquery/1.12.4/jquery.min.js'></script>
        <title>在线支付-支付宝</title>
        <style>
            body {
                width: 100%;
                height: 100vh;
                background: #f7f7f7;
             
            }
        </style>

   

        
<body>
   

<section class='section details__section section--first  section--last'>
                       
                    <div style='padding-bottom: 18px;padding-top: 15px;' class='container'>
                    <div class='row '>
                    <div class='col-12'>
                    <div style='text-align:center' class='mt-3'>
                   
            
                
                    <div class='row'>
                    <div class='col-12'>
                        
<h2>支付宝</h2>
                        <div class='order'>
<center>
                        <span>订单号：{$out_trade_no}</span></center>
                        </div>
                        
                        <div class='goods_name'>
                            <span>商品名称：账号ID:".$user["id"].",余额充值</span>              
                        </div>
                        
                        <div class='price mt-2'>
                            <span>".input("money")."</span>
                            <span>元</span> 
                        </div>
                        
                            <img id='show_qrcode' src='{$url}' class='code' /> 
                            <div id='qrcode'>
                           
                        </div><br/>

                            <div class='price mt-1'>
                            <span style='color:red'></span>  
                            <span style='color:red'>
                        手机端请点击下方直接跳转支付!<br>
                            </span>
                            
                            </div>
                            <a href='alipayqr://platformapi/startapp?saId=10000007&clientVersion=3.7.0.0718&qrcode=".$result['qr_code']."' class='header__order mt-2 weixinbtn'>启动支付宝</a>
                        
                        
                        <div class='shanxinzha'>
                        <span>请使用支付宝扫一扫</span>
                        <br><br>
                        <span style='padding-top:10px;color:red;font-weight:700;'></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>


             
</body>
</html>
<script type='text/javascript' src='https://www.layuicdn.com/layer/layer.js'></script>
   <script>
      var setOrder = setInterval(function() {
            $.ajax({
                type: 'post',
                url: '/user/return/".$data1["id"]."',
                data: {
                    no: '".$out_trade_no."',
                    t: Math.random()
                },
                dataType: 'json',
                success: function(data) {
                    if (data.code == '1') {
                        layer.msg('支付成功，正在跳转中...');
setTimeout(function(){
window.location.href='/user';
},1000);
clearInterval(setOrder);
                    }
                },
            });

        }, 1000);
    </script>
");
}
 
 
//IndexService类
class IndexService
{
    protected $appId;
    protected $notifyUrl;
    protected $charset;
    //私钥值
    protected $rsaPrivateKey;
    protected $totalFee;
    protected $outTradeNo;
    protected $orderName;
 
    public function __construct()
    {
        $this->charset = 'utf-8';
    }
 
    public function setAppid($appid)
    {
        $this->appId = $appid;
    }
 
    public function setNotifyUrl($notifyUrl)
    {
        $this->notifyUrl = $notifyUrl;
    }
 
    public function setRsaPrivateKey($saPrivateKey)
    {
        $this->rsaPrivateKey = $saPrivateKey;
    }
 
    public function setTotalFee($payAmount)
    {
        $this->totalFee = $payAmount;
    }
 
    public function setOutTradeNo($outTradeNo)
    {
        $this->outTradeNo = $outTradeNo;
    }
 
    public function setOrderName($orderName)
    {
        $this->orderName = $orderName;
    }
 
    /**
     * 发起订单
     * @return array
     */
    public function doPay(): array
    {
        //请求参数
        $requestConfigs = array(
            'out_trade_no'=>$this->outTradeNo,
            'total_amount'=>$this->totalFee, //单位 元
            'subject'=>$this->orderName,  //订单标题
            'timeout_express'=>'2h'       //该笔订单允许的最晚付款时间，逾期将关闭交易。取值范围：1m～15d。m-分钟，h-小时，d-天，1c-当天（1c-当天的情况下，无论交易何时创建，都在0点关闭）。 该参数数值不接受小数点， 如 1.5h，可转换为 90m。
        );
        $commonConfigs = array(
            //公共参数
            'app_id' => $this->appId,
            'method' => 'alipay.trade.precreate',             //接口名称
            'format' => 'JSON',
            'charset'=>$this->charset,
            'sign_type'=>'RSA2',
            'timestamp'=>date('Y-m-d H:i:s'),
            'version'=>'1.0',
            'notify_url' => $this->notifyUrl,
            'biz_content'=>json_encode($requestConfigs),
        );
        $commonConfigs["sign"] = $this->generateSign($commonConfigs, $commonConfigs['sign_type']);
        $result = $this->curlPost('https://openapi.alipay.com/gateway.do?charset='.$this->charset,$commonConfigs);
        return json_decode($result,true);
    }
    public function generateSign($params, $signType = "RSA"): ?string
    {
        return $this->sign($this->getSignContent($params), $signType);
    }
    protected function sign($data, $signType = "RSA") {
        $priKey=$this->rsaPrivateKey;
        $res = "-----BEGIN RSA PRIVATE KEY-----\n" .
            wordwrap($priKey, 64, "\n", true) .
            "\n-----END RSA PRIVATE KEY-----";
        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');
        if ("RSA2" == $signType) {
            openssl_sign($data, $sign, $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256); //OPENSSL_ALGO_SHA256是php5.4.8以上版本才支持
        } else {
            openssl_sign($data, $sign, $res);
        }
        $sign = base64_encode($sign);
        return $sign;
    }
    /**
     * 校验$value是否非空
     *  if not set ,return true;
     *    if is null , return true;
     **/
    protected function checkEmpty($value): bool
    {
        if (!isset($value))
            return true;
        if (trim($value) === "")
            return true;
        return false;
    }
    public function getSignContent($params): string
    {
        ksort($params);
        $stringToBeSigned = "";
        $i = 0;
        foreach ($params as $k => $v) {
            if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
                // 转换成目标字符集
                $v = $this->characet($v, $this->charset);
                if ($i == 0) {
                    $stringToBeSigned .= "$k" . "=" . "$v";
                } else {
                    $stringToBeSigned .= "&" . "$k" . "=" . "$v";
                }
                $i++;
            }
        }
        unset ($k, $v);
        return $stringToBeSigned;
    }
    /**
     * 转换字符集编码
     * @param $data
     * @param $targetCharset
     * @return string
     */
    function characet($data, $targetCharset) {
        if (!empty($data)) {
            $fileType = $this->charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }
    public function curlPost($url = '', $postData = '', $options = array())
    {
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
 
}