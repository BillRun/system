<?php
namespace Helper;

class LogChecker extends \Codeception\Module
{
    protected $logPath = './logs/container/debug.log';
    protected $startOffset = 0;
    protected $cachedContent = null;

    public function _initialize()
    {
        if (!file_exists($this->logPath)) {
            throw new \Exception("Log file not found: " . $this->logPath);
        }

        // Default offset: end of current log
        $this->startOffset = filesize($this->logPath);
        $this->cachedContent = null;
    }

    public function clearLogFile()
    {
        clearstatcache(true, $this->logPath);
        $this->startOffset = filesize($this->logPath);
        $this->cachedContent = null;
    }

    protected function getNewLogContent(): string
    {
        if ($this->cachedContent !== null) {
            return $this->cachedContent;
        }

        clearstatcache(true, $this->logPath);
        $currentSize = filesize($this->logPath);

        if ($currentSize < $this->startOffset) {
            // Log rotation or truncation
            $this->startOffset = 0;
        }

        $fp = fopen($this->logPath, 'rb');
        if ($fp === false) {
            throw new \Exception("Cannot open log file for reading: {$this->logPath}");
        }

        fseek($fp, $this->startOffset);
        $this->cachedContent = stream_get_contents($fp) ?: '';
        fclose($fp);

        return $this->cachedContent;
    }

    public function seeInLogFile($message)
    {
        $content = $this->getNewLogContent();

        if (!str_contains($content, $message)) {
            $this->fail("Failed to find '$message' in new logs.");
        }
    }

    public function dontSeeInLogFile($message)
    {
        $content = $this->getNewLogContent();

        if (str_contains($content, $message)) {
            $this->fail("Found unexpected message '$message' in new logs.");
        }
    }

    public function grabLastLogEntry(): ?string
    {
        $content = $this->getNewLogContent();

        if (trim($content) === '') {
            return null;
        }

        $pos = strrpos($content, PHP_EOL);
        if ($pos === false) {
            return trim($content);
        }

        return trim(substr($content, $pos));
    }
}