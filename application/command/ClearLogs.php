<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\Output;

class ClearLogs extends Command
{
    protected function configure()
    {
        $this->setName('clear:logs')
            ->setDescription('Clear logs');
    }

    protected function execute(Input $input, Output $output)
    {
        // 日志目录
        $logDir = LOG_PATH; // 修改为你的日志目录

        // 保留多少天的日志
        $daysToKeep = 5; // 修改为你需要保留的天数

        // 获取日志文件列表
        $files = $this->getFiles($logDir);

        // 当前时间
        $now = time();

        foreach ($files as $file) {
            // 获取文件的修改时间
            $fileTime = filemtime($file);

            // 判断是否超过保留天数
            if (($now - $fileTime) > $daysToKeep * 24 * 60 * 60) {
                unlink($file);
            }
        }

        $output->writeln("Old logs cleared successfully.");
    }

    /**
     * 获取目录下的所有文件
     * @param $dir
     * @return array
     */
    private function getFiles($dir)
    {
        $files = [];
        $handle = opendir($dir);
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $curPath = $dir . DIRECTORY_SEPARATOR . $file;
                if (is_dir($curPath)) {
                    $files = array_merge($files, $this->getFiles($curPath));
                } else {
                    $files[] = $curPath;
                }
            }
        }
        closedir($handle);
        return $files;
    }
}