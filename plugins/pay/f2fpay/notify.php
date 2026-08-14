<?php
use think\Db;
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
$alipayPublicKey = $ppay["2"]["value"];; //支付宝公钥
 
$aliPay = new NotifyService($alipayPublicKey);
//验证签名
$result = $aliPay->rsaCheck($_POST);
if($result===true && $_POST['trade_status'] == 'TRADE_SUCCESS'){
 
 $payamount = $_POST['total_amount']; //支付金额
$out_trade_no = $_POST['out_trade_no']; //订单号
$db=Db::name('pay')->where([
"ordernumber"=>$out_trade_no,
])->find();
if($db){
if($db["state"]=="1"){
	$msg="该订单已充值成功,禁止重复提交!";
}else{
	// 使用条件更新防止并发重复充值（原子操作）
	$affected = Db::name('pay')->where([
		"ordernumber"=>$out_trade_no,
		"state"=>"2",  // 仅更新未支付订单
	])->update([
		"state"=>"1",
		"time"=>time(),
	]);
	if ($affected > 0) {
		$user=Db::name('user')->where('id',$db["userid"])->find();
		$db1=Db::name('user')->where('id',$db["userid"])->update([
			"money"=>round($user["money"]+$db["money"],2),
			"total_recharge"=>round(floatval($user["total_recharge"] ?? 0) + $db["money"], 2),
		]);
		if (function_exists('update_user_membership')) {
			update_user_membership($db["userid"]);
		}
		$msg="充值成功";
	} else {
		$msg="该订单已处理,禁止重复提交!";
	}
}
}else{
	$msg="未找到该订单";
}
    



	exit($msg);
}
echo 'error';exit();
 
 
//NotifyService类
class NotifyService
{
    //支付宝公钥
    protected $alipayPublicKey;
    protected $charset;
 
    public function __construct($alipayPublicKey)
    {
        $this->charset = 'utf8';
        $this->alipayPublicKey=$alipayPublicKey;
    }
 
    /**
     *  验证签名
     **/
    public function rsaCheck($params): ?bool
    {
        $sign = $params['sign'];
        $signType = $params['sign_type'];
        unset($params['sign_type']);
        unset($params['sign']);
        return $this->verify($this->getSignContent($params), $sign, $signType);
    }
 
    function verify($data, $sign, $signType = 'RSA') {
        $pubKey= $this->alipayPublicKey;
        $res = "-----BEGIN PUBLIC KEY-----\n" .
            wordwrap($pubKey, 64, "\n", true) .
            "\n-----END PUBLIC KEY-----";
        ($res) or die('支付宝RSA公钥错误。请检查公钥文件格式是否正确');
 
        //调用openssl内置方法验签，返回bool值
        if ("RSA2" == $signType) {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res, version_compare(PHP_VERSION,'5.4.0', '<') ? SHA256 : OPENSSL_ALGO_SHA256);
        } else {
            $result = (bool)openssl_verify($data, base64_decode($sign), $res);
        }
//        if(!$this->checkEmpty($this->alipayPublicKey)) {
//            //释放资源
//            openssl_free_key($res);
//        }
        return $result;
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
    function characet($data, $targetCharset): string
    {
        if (!empty($data)) {
            $fileType = $this->charset;
            if (strcasecmp($fileType, $targetCharset) != 0) {
                $data = mb_convert_encoding($data, $targetCharset, $fileType);
                //$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
            }
        }
        return $data;
    }
}
