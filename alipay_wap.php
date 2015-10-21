<?php

/**
 * ECSHOP 支付宝WAP插件
 * 2015-09-27 更新最新的支付宝wap接口
 */

if (!defined('IN_ECS'))
{
    die('Hacking attempt');
}

$payment_lang = dirname(__FILE__) . '/alipay_wap/languages/zh_cn/alipay_wap.php';

if (file_exists($payment_lang))
{
    global $_LANG;

    include_once($payment_lang);
}

/* 模块的基本信息 */
if (isset($set_modules) && $set_modules == TRUE)
{
    $i = isset($modules) ? count($modules) : 0;

    /* 代码 */
    $modules[$i]['code']    = basename(__FILE__, '.php');

    /* 描述对应的语言项 */
    $modules[$i]['desc']    = 'alipay_wap_desc';

    /* 是否支持货到付款 */
    $modules[$i]['is_cod']  = '0';

    /* 是否支持在线支付 */
    $modules[$i]['is_online']  = '1';

    /* 作者 */
    $modules[$i]['author']  = 'jimmy';

    /* 网址 */
    $modules[$i]['website'] = 'http://x3d.cnblogs.com';

    /* 版本号 */
    $modules[$i]['version'] = '1.3';

    /* 配置信息 共用?? */
    $modules[$i]['config']  = array(
        array('name' => 'alipay_account',           'type' => 'text',   'value' => ''),
        array('name' => 'alipay_key',               'type' => 'text',   'value' => ''),
        array('name' => 'alipay_partner',           'type' => 'text',   'value' => '')
    );

    return;
}

/**
 * 类
 */
class alipay_wap
{

    /**
     * 构造函数
     *
     * @access  public
     * @param
     *
     * @return void
     */
    function __construct()
    {
        
    }

    /**
     * 生成支付代码
     * @param   array   $order      订单信息
     * @param   array   $payment    支付方式信息
     */
    function get_code($order, $payment)
    {
        
        //服务器异步通知页面路径
		$notify_url = return_url(basename(__FILE__, '.php'));
		//需http://格式的完整路径，不允许加?id=123这类自定义参数
        //log_write($notify_url, 'alipay_wap');

		//页面跳转同步通知页面路径
		$call_back_url = return_url(basename(__FILE__, '.php'));
		//需http://格式的完整路径，不允许加?id=123这类自定义参数
        
        $alipay_conf = $this->getAlipayConf($payment);
		
        //基本参数
        $parameter = array(
            'service' => 'alipay.wap.create.direct.pay.by.user',
            //合作身份者id，以2088开头的16位纯数字
            'partner' => $alipay_conf['partner'],
            '_input_charset' => $alipay_conf['input_charset'],
            'sign_type' => $alipay_conf['sign_type'],
            'notify_url'	=> $notify_url,
            'return_url'	=> $call_back_url,
            //业务参数
            'seller_id' => $alipay_conf['partner'],
            'payment_type'	=> '1',
            'out_trade_no'	=> $order['order_sn'] . $order['log_id'],
            'subject'	=> $order['order_sn'],
            'total_fee'	=> $order['order_amount'],
            //'show_url'	=> $show_url, //TODO
            //'body'	=> '',
            'it_b_pay'	=> '1d',
            //'extern_token'	=> $extern_token,
        );
        
        //建立请求
        require_once(dirname(__FILE__)."/alipay_wap/lib/alipay_submit.class.php");
        $alipaySubmit = new AlipaySubmit($alipay_conf);
        $html_text = $alipaySubmit->buildRequestForm($parameter, "get", "确认");
		return $html_text;
    }

    /**
     * 响应操作
     */
    function respond()
    {
        if (!empty($_POST))
        {
            foreach($_POST as $key => $data)
            {
                $_GET[$key] = $data;
            }
        }
		
		//log_write($_GET, 'alipay_wap');
        $payment  = get_payment('alipay_wap');
        
        $order_sn = str_replace($_GET['subject'], '', $_GET['out_trade_no']);
        $order_sn = trim($order_sn);

        /* 检查数字签名是否正确 */
        ksort($_GET);
        reset($_GET);
        
        $alipay_conf = $this->getAlipayConf($payment);
		
		require_once(dirname(__FILE__)."/alipay_wap/lib/alipay_notify.class.php");
		
		//计算得出通知验证结果
		$alipayNotify = new AlipayNotify($alipay_conf);
		$verify_result = $alipayNotify->verifyReturn();

		if(!$verify_result) {//验证不成功
			return false;
		}
        

        /* 检查支付的金额是否相符 */
        if (!check_money($order_sn, $_GET['total_fee'])) {
            return false;
        }

        if ($_GET['trade_status'] == 'TRADE_FINISHED') {
            /* 改变订单状态 */
            order_paid($out_trade_no);
            return true;
        } else if ($_GET['trade_status'] == 'TRADE_SUCCESS') {
            /* 改变订单状态 */
            order_paid($out_trade_no, 2);

            return true;
        } else {
            return false;
        }
        
    }
    
    /**
     * 构造要传给lib的配置参数
     * @param array $payment
     * @return array
     */
    protected function getAlipayConf($payment) {
        return array(
            'partner' => trim($payment['alipay_partner']),
            'seller_id' => trim($payment['alipay_partner']),
            'key' => trim($payment['alipay_key']),
            'transport'  => 'http',
            'cacert' => getcwd().'\\alipay_wap\\cacert.pem',
            'input_charset' => strtolower('utf-8'),
            'sign_type' => strtoupper('md5')
        );
    }
}
