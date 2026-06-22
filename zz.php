<?php
// ==================== 彩虹易支付通用SDK（修复act参数干扰签名BUG） ====================
class EpayCore
{
	private $pid;
	private $key;
	private $submit_url;
	private $mapi_url;
	private $api_url;
	private $sign_type = 'MD5';

	function __construct($config){
		$this->pid = $config['pid'];
		$this->key = $config['key'];
		$this->submit_url = rtrim($config['apiurl'], '/').'/submit.php';
		$this->mapi_url = rtrim($config['apiurl'], '/').'/mapi.php';
		$this->api_url = rtrim($config['apiurl'], '/').'/api.php';
	}

	// 自动跳转支付表单（页面支付）
	public function pagePay($param_tmp, $button='正在跳转'){
		$param = $this->buildRequestParam($param_tmp);
		$html = '<form id="dopay" action="'.$this->submit_url.'" method="post">';
		foreach ($param as $k=>$v) {
			$html.= '<input type="hidden" name="'.$k.'" value="'.htmlspecialchars($v).'"/>';
		}
		$html .= '<input type="submit" value="'.$button.'"></form><script>document.getElementById("dopay").submit();</script>';
		return $html;
	}

	// 获取支付链接
	public function getPayLink($param_tmp){
		$param = $this->buildRequestParam($param_tmp);
		$url = $this->submit_url.'?'.http_build_query($param);
		return $url;
	}

	// API获取支付JSON
	public function apiPay($param_tmp){
		$param = $this->buildRequestParam($param_tmp);
		$response = $this->getHttpResponse($this->mapi_url, http_build_query($param));
		$arr = json_decode($response, true);
		return $arr;
	}

	// 异步回调签名校验
	public function verifyNotify(){
		$params = array_merge($_GET,$_POST);
		$sign = $this->getSign($params);
		return $sign === $params['sign'];
	}

	// 同步返回页签名校验
	public function verifyReturn(){
		$params = $_GET;
		$sign = $this->getSign($params);
		return $sign === $params['sign'];
	}

	// 判断订单是否支付成功
	public function orderStatus($trade_no){
		$result = $this->queryOrder($trade_no);
		return $result['status']==1 ? true : false;
	}

	// 订单查询接口
	public function queryOrder($trade_no){
		$url = $this->api_url.'?act=order&pid=' . $this->pid . '&key=' . $this->key . '&trade_no=' . $trade_no;
		$response = $this->getHttpResponse($url);
		$arr = json_decode($response, true);
		return $arr;
	}

	// 退款接口
	public function refund($trade_no, $money){
		$url = $this->api_url.'?act=refund';
		$post = 'pid=' . $this->pid . '&key=' . $this->key . '&trade_no=' . $trade_no . '&money=' . $money;
		$response = $this->getHttpResponse($url, $post);
		$arr = json_decode($response, true);
		return $arr;
	}

	// 组装支付参数+生成签名
	private function buildRequestParam($param){
		$mysign = $this->getSign($param);
		$param['sign'] = $mysign;
		$param['sign_type'] = $this->sign_type;
		return $param;
	}

	// MD5标准签名算法【核心修复：过滤act等自定义页面参数】
	private function getSign($param){
		ksort($param);
		reset($param);
		$signstr = '';
		// 黑名单：排除页面自定义参数、签名相关参数
		$skipKeys = ["sign","sign_type","act"];
		foreach($param as $k => $v){
			if(!in_array($k,$skipKeys) && $v!==''){
				$signstr .= $k.'='.$v.'&';
			}
		}
		$signstr = rtrim($signstr, '&');
		$signstr .= $this->key;
		$sign = md5($signstr);
		return $sign;
	}

	// CURL请求封装
	private function getHttpResponse($url, $post = false, $timeout = 10){
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$httpheader[] = "Accept: */*";
		$httpheader[] = "Accept-Language: zh-CN,zh;q=0.8";
		$httpheader[] = "Connection: close";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $httpheader);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if($post){
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		}
		$response = curl_exec($ch);
		curl_close($ch);
		return $response;
	}
}

// ==================== 商户支付配置 ====================
$epay_config = array(
    'apiurl' => 'https://pay.whx1.top/',
    'pid'    => 1000,
    'key'    => 't9n2ZT48Js9VTJj3Tvjq9jgnJ2Xu2V2j'
);
$epay = new EpayCore($epay_config);

$act = isset($_GET['act']) ? $_GET['act'] : 'index';

// ==================== 1、赞助首页 act=index ====================
if($act == 'index'){
    $order_no = date('YmdHis') . mt_rand(1000,9999);
    $client_ip = $_SERVER['REMOTE_ADDR'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>站点赞助</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
.box-pay{border:2px solid #eee;border-radius:10px;padding:16px;cursor:pointer}
.box-pay.active{border-color:#007aff;background:#f0f7ff}
.money-btn{min-width:86px;margin:5px}
</style>
</head>
<body class="bg-light">
<div class="container py-5" style="max-width:680px;">
<div class="card shadow-lg">
<div class="card-header bg-primary text-white text-center">
<h4>☕ 赞助支持本站</h4>
<p class="mb-0 small">您的赞助是持续维护更新的动力</p>
</div>
<div class="card-body p-4">
<form action="zz.php?act=pay" method="POST">
<input type="hidden" name="act" value="pay">
<input type="hidden" name="out_trade_no" value="<?php echo htmlspecialchars($order_no); ?>">
<input type="hidden" name="clientip" value="<?php echo htmlspecialchars($client_ip); ?>">

<div class="mb-4">
<h5>1. 选择支付渠道</h5>
<div class="row g-3">
<div class="col-6">
<div class="box-pay text-center" data-type="alipay">
<input type="radio" name="type" value="alipay" hidden checked>
<h5 class="text-primary">支付宝</h5>
</div>
</div>
<div class="col-6">
<div class="box-pay text-center" data-type="wxpay">
<input type="radio" name="type" value="wxpay" hidden>
<h5 class="text-success">微信支付</h5>
</div>
</div>
</div>
</div>

<div class="mb-4">
<h5>2. 选择赞助金额</h5>
<div class="d-flex flex-wrap">
<button type="button" class="btn btn-outline-primary money-btn" data-val="5">¥5</button>
<button type="button" class="btn btn-outline-primary money-btn" data-val="10">¥10</button>
<button type="button" class="btn btn-outline-primary money-btn" data-val="20">¥20</button>
<button type="button" class="btn btn-outline-primary money-btn" data-val="50">¥50</button>
<button type="button" class="btn btn-outline-primary money-btn" data-val="100">¥100</button>
</div>
<div class="mt-3 input-group">
<span class="input-group-text">¥</span>
<input step="0.01" min="0.01" class="form-control" name="money" placeholder="自定义金额" required>
</div>
<small class="text-muted">最低0.01元，金额保留两位小数</small>
</div>

<button type="submit" class="w-100 btn btn-lg btn-primary mt-3">立即前往支付</button>
</form>
</div>
</div>
</div>

<script>
var payBox = document.querySelectorAll('.box-pay');
for(var i=0;i<payBox.length;i++){
    payBox[i].onclick = function(){
        for(var j=0;j<payBox.length;j++) payBox[j].classList.remove('active');
        this.classList.add('active');
        this.querySelector('input').checked = true;
    }
}
document.querySelector('.box-pay').classList.add('active');

var moneyInput = document.querySelector('input[name="money"]');
var moneyBtns = document.querySelectorAll('.money-btn');
for(var i=0;i<moneyBtns.length;i++){
    moneyBtns[i].onclick = function(){
        moneyInput.value = this.dataset.val;
    }
}
</script>
</body>
</html>
<?php
}

// ==================== 2、提交支付 act=pay ====================
elseif($act == 'pay'){
    $post = $_POST;
    $pay_param = array(
        'pid'         => $epay_config['pid'],
        'type'        => $post['type'],
        'out_trade_no'=> $post['out_trade_no'],
        'notify_url'  => 'https://femboy.zhminyu.cn/zz.php?act=notify',
        'return_url'  => 'https://femboy.zhminyu.cn/zz.php?act=return',
        'name'        => 'product',
        'money'       => sprintf("%.2f", $post['money']),
        'clientip'    => $post['clientip']
    );
    echo $epay->pagePay($pay_param, '正在跳转至支付页面...');
    exit;
}

// ==================== 3、异步回调通知 act=notify ====================
elseif($act == 'notify'){
    if(!$epay->verifyNotify()){
        exit('sign error');
    }
    exit('success');
}

// ==================== 4、同步返回结果页 act=return ====================
elseif($act == 'return'){
    $pay_success = false;
    if($epay->verifyReturn() && isset($_GET['trade_status']) && $_GET['trade_status'] === 'TRADE_SUCCESS'){
        $pay_success = true;
    }
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<title>支付结果</title>
</head>
<body class="bg-light d-flex align-items-center vh-100">
<div class="container text-center">
<?php if($pay_success): ?>
<div class="card mx-auto shadow" style="max-width:420px;">
<div class="card-body py-5">
<h2 class="text-success">✅ 赞助成功</h2>
<p class="text-muted mt-3">感谢您的支持！</p>
<a href="zz.php?act=index" class="btn btn-primary mt-3">返回赞助页面</a>
</div>
</div>
<?php else: ?>
<div class="card mx-auto shadow" style="max-width:420px;">
<div class="card-body py-5">
<h2 class="text-danger">❌ 支付未完成</h2>
<p class="text-muted mt-3">未校验到有效支付订单</p>
<?php
// 测试完成后删除这段调试输出
echo '<div class="alert alert-warning mt-3 text-start">';
echo 'GET参数：<pre>';print_r($_GET);echo '</pre>';
echo '</div>';
?>
<a href="zz.php?act=index" class="btn btn-primary mt-3">重新赞助</a>
</div>
</div>
<?php endif; ?>
</div>
</body>
</html>
<?php
}
?>