<?php

namespace Crm\PaymentsModule\Tests;

use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TestOutput implements OutputInterface
{
    private $decorated;

    private $formatter;

    private $level;

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
        $this->level = $level;
    }

    public function getVerbosity()
    {
        return $this->level;
    }

    public function setDecorated($decorated)
    {
        $this->decorated = $decorated;
    }

    public function isDecorated()
    {
        return $this->decorated;
    }

    public function setFormatter(OutputFormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    public function getFormatter()
    {
        return $this->formatter;
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
