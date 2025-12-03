<?php

namespace app\api\controller\v1;

use app\common\service\BotService;
use Telegram\Bot\Api;
use think\Config;
use think\Log;
use think\Request;

class Telegram
{

    public function _initialize()
    {

        //修改日志路径
        Log::init([
            'type'  => 'File',
            'path'  => LOG_PATH . 'pay/',
            'level' => ['error', 'info'],
        ]);
    }
    public function webhook(Request $request)
    {
        $telegram = new Api(Config::get('site.bot_token'));

        try {
            $update = $telegram->getWebhookUpdate();

            // 有时 getWebhookUpdate() 返回 null 或 Collection，需兼容
            if (!$update) {
                Log::warning('Telegram webhook update is empty or invalid');
                return 'ok';
            }

            // 处理普通消息
            if (!method_exists($update, 'getMessage')) {
                Log::warning('Telegram webhook update has no getMessage method');
                return 'ok';
            }

            $message = $update->getMessage();
            if (!$message) {
                Log::info('Telegram webhook: no message in update');
                return 'ok';
            }

            $chat   = $message->getChat();
            $chatId = $chat->getId();
            $text   = $message->getText();
            $msgId  = $message->getMessageId();

            if (!is_string($text) || trim($text) === '') {
                return 'ok';
            }

            // ===== 指令判断 =====
            $text = trim($text);

            // /help
            if (str_starts_with($text, '/help')) {
                $this->handleHelp($telegram, $chatId, $msgId);
                return 'ok';
            }

            // /bind
            if (str_starts_with($text, '/b') || str_starts_with($text, '/绑定')) {
                $this->handleBind($telegram, $chatId, $text, $msgId);
                return 'ok';
            }

            // /unbind
            if (str_starts_with($text, '/u') || str_starts_with($text, '/解绑')) {
                $this->handleUnbind($telegram, $chatId, $msgId);
                return 'ok';
            }

            // /balance
            if (str_starts_with($text, '/b') || str_starts_with($text, '/余额')) {
                $this->handleBalance($telegram, $chatId, $msgId);
                return 'ok';
            }

            // /in /代收
            if (str_starts_with($text, '/c') || str_starts_with($text, '/代收')) {
                $this->handleInOrder($telegram, $chatId, $text, $msgId);
                return 'ok';
            }

            // /out /代付
            if (str_starts_with($text, '/p') || str_starts_with($text, '/代付')) {
                $this->handleOutOrder($telegram, $chatId, $text, $msgId);
                return 'ok';
            }

            // /voucher /凭证
            if (str_starts_with($text, '/tr') || str_starts_with($text, '/凭证')) {
                $this->handleVoucher($telegram, $chatId, $text, $msgId);
                return 'ok';
            }


            // /id
            if (str_starts_with($text, '/id')) {
                $telegram->sendMessage([
                    'chat_id' => $chatId,
                    'text'    => "您的聊天ID是: {$chatId}，请妥善保存"
                ]);
                return 'ok';
            }

        } catch (\Throwable $e) {
            // 捕获所有异常（包括类型错误）
            Log::error('Telegram Bot Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
        }

        return 'ok';
    }


    // 处理 /help 指令
    protected function handleHelp($telegram, $chatId, $msgId)
    {
        $helpText = "欢迎使用支付通知机器人！\n\n" .
            "可用指令：\n" .
            "/p /代付 商户订单号\n" .
            "/c /代收 商户订单号\n" .
            "/b /余额\n" .
            "/tr /凭证 商户订单号\n";

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => $helpText,
            'reply_to_message_id' => $msgId
        ]);
    }

    // handleBind
    protected function handleBind($telegram, $chatId, $text, $msgId)
    {
        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "请提供商户单号，例如: /绑定 商户号",
                'reply_to_message_id' => $msgId
            ]);
            return;
        }

        $mch_id = trim($parts[1]);

        try {
            // 这里可以添加绑定逻辑，例如将 chatId 和 orderNo 存储到数据库
            $botService = new BotService();
            $botService->bindBotToMerchant($chatId, $mch_id);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "绑定成功",
                'reply_to_message_id' => $msgId
            ]);
        }catch (\Exception $e){
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "绑定失败: " . $e->getMessage(),
                'reply_to_message_id' => $msgId
            ]);
            return;
        }
    }

    // 解绑
    protected function handleUnbind($telegram, $chatId, $msgId)
    {

        try {
            $botService = new BotService();
            $botService->unbindBotFromMerchant($chatId);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "解绑成功",
                'reply_to_message_id' => $msgId
            ]);
        }catch (\Exception $e){
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "解绑失败: " . $e->getMessage(),
                'reply_to_message_id' => $msgId
            ]);
            return;
        }
    }

    // 查询余额
    protected function handleBalance($telegram, $chatId, $msgId)
    {
        try {
            $botService = new BotService();
            // 这里假设有一个方法可以获取商户余额
            $balance = $botService->getBalance($chatId);

            // return [
            //            'balance' => $wallet->balance,
            //            'blocked_balance' => $wallet->blocked_balance,
            //        ];
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "余额查询成功:\n可用余额: {$balance['balance']}\n冻结余额: {$balance['blocked_balance']}",
                'reply_to_message_id' => $msgId
            ]);
        }catch (\Exception $e){
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text'    => "查询余额失败: " . $e->getMessage(),
                'reply_to_message_id' => $msgId
            ]);
            return;
        }
    }

    // 查询代收订单
    protected function handleInOrder($telegram, $chatId, $text, $msgId)
    {
        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "请提供商户订单号，例如: /代收 商户订单号",
                'reply_to_message_id' => $msgId
            ]);
            return;
        }

        $merchant_order_no = trim($parts[1]);
        try {
            $botService = new BotService();
            $orderInfo = $botService->getInOrder($chatId, $merchant_order_no);

            $orderText = "代收订单查询结果:\n" .
                "订单号: {$orderInfo['order_no']}\n" .
                "商户订单号: {$orderInfo['merchant_order_no']}\n" .
                "金额: {$orderInfo['amount']}\n" .
                "状态: {$orderInfo['status']}\n";

            if ($orderInfo['error_msg']) {
                $orderText .= "错误信息: {$orderInfo['error_msg']}\n";
            }

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $orderText,
                'reply_to_message_id' => $msgId
            ]);
        } catch (\Exception $e) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "查询代收订单失败: " . $e->getMessage(),
                'reply_to_message_id' => $msgId
            ]);
            return;
        }

    }

    // 查询代付订单
    protected function handleOutOrder($telegram, $chatId, $text, $msgId)
    {
        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "请提供商户订单号，例如: /代付 商户订单号",
                'reply_to_message_id' => $msgId
            ]);
            return;
        }

        $merchant_order_no = trim($parts[1]);
        try {
            $botService = new BotService();
            $orderInfo = $botService->getOutOrder($chatId, $merchant_order_no);
            $orderText = "代付订单查询结果:\n" .
                "订单号: {$orderInfo['order_no']}\n" .
                "商户订单号: {$orderInfo['merchant_order_no']}\n" .
                "金额: {$orderInfo['amount']}\n" .
                "状态: {$orderInfo['status']}\n";
            if ($orderInfo['error_msg']) {
                $orderText .= "错误信息: {$orderInfo['error_msg']}\n";
            }

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => $orderText,
                'reply_to_message_id' => $msgId
            ]);
        } catch (\Exception $e) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "查询代付订单失败: " . $e->getMessage(),
                'reply_to_message_id' => $msgId
            ]);
            return;
        }
    }

    // 查询凭证
    protected function handleVoucher($telegram, $chatId, $text, $msgId)
    {
        $parts = explode(' ', $text);
        if (count($parts) < 2) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "请提供商户订单号，例如: /凭证 商户订单号",
                'reply_to_message_id' => $msgId
            ]);
            return;
        }

        $merchant_order_no = trim($parts[1]);
        try {
            $botService = new BotService();
            $voucherUrl = $botService->getVoucherUrl($chatId, $merchant_order_no);
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "获取凭证成功:\n" . $voucherUrl['url'],
                'reply_to_message_id' => $msgId
            ]);
        } catch (\Exception $e) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => "查询凭证失败: " . $e->getMessage(),
                'reply_to_message_id' => $msgId
            ]);
            return;
        }

    }

}