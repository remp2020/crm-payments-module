<?php

namespace Crm\PaymentsModule\Tests;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;

class TestOutput implements OutputInterface
{
    public function write($messages, $newline = false, $type = self::OUTPUT_NORMAL)
    {
//        echo $messages;
    }

    public function writeln($messages, $type = self::OUTPUT_NORMAL)
    {
//        echo $this->write($messages) . "\n";
    }
    public function setVerbosity($level)
    {
    }

    public function getVerbosity()
    {
    }

    public function setDecorated($decorated)
    {
    }

    public function isDecorated()
    {
    }

    public function setFormatter(OutputFormatterInterface $formatter)
    {
    }

    public function getFormatter()
    {
    }

    public function isQuiet()
    {
        return false;
    }

    public function isVerbose()
    {
        return true;
    }

    public function isVeryVerbose()
    {
        return false;
    }

    public function isDebug()
    {
        return false;
    }
}
