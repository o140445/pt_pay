<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class TelegramConfig extends Command
{
    protected function configure()
    {
        $this->setName('telegram:config')
            ->setDescription('Run Telegram Bot using Long Polling');
    }

    protected function execute(Input $input, Output $output)
    {
        // 设置机器人回调地址
        $botToken = config('site.bot_token');
        $webhookUrl = config('site.webhook_url');
        $setWebhookUrl = "https://api.telegram.org/bot{$botToken}/setWebhook?url={$webhookUrl}";
        $ch = curl_init($setWebhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        echo $response;
    }


}