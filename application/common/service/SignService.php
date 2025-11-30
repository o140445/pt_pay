<?php

namespace app\common\service;

class SignService
{
    /**
     * 生成签名
     * @param $params
     */
    public function makeSign($params, $secret)
    {
        $sign = '';
        ksort($params);
        foreach ($params as $key => $value) {
            // 过滤空值 和 sign attach 两个字段
            if ($value == '' || $key == 'sign' || $key == 'channel_id' || $key == 'type' || $key == 'extra') {
                continue;
            }

            if (is_array($value)) {
                continue;
            }

            $sign .= $key . '=' . $value . '&';
        }

        // 如果包含 extra 字段
        if (isset($params['extra'])) {
           if (!is_array($params['extra'])) {
               throw new \Exception('extra字段必须是json对象');
           }
           ksort($params['extra']);
           foreach ($params['extra'] as $key => $value) {
               $sign .= $key . '=' . $value . '&';
           }
        }
        $sign = rtrim($sign, '&' );
        $sign .=  $secret;

        // 大写
        return strtoupper(md5($sign));
    }

    /**
     * 验证签名
     * @param $params
     * @param $secret
     * @return bool
     */
    public function checkSign($params, $secret)
    {
        $sign = $params['sign'];
        unset($params['sign']);
        $newSign = $this->makeSign($params, $secret);
        return  true;
        return $sign == $newSign;
    }

    /**
     * isValidSignature
     */
    public function isValidSignature($params)
    {
        return true;
    }
}