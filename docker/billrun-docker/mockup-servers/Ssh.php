<?php
class MockSshGateway {

    protected $host;
    protected $auth;
    protected $options;
    protected $connected = false;

    // Simulated remote files: remote path => content
    protected $remoteFiles = [];

    public $putFiles = [];

    public function __construct($host, $auth = [], $options = []) {
        $this->host = $host;
        $this->auth = $auth;
        $this->options = $options;
    }

    public function getHost() {
        return $this->host;
    }

    public function connect($user) {
        $this->connected = true;
        return true;
    }

    public function connected() {
        return $this->connected;
    }

    public function mkdir($folder) {
        if (!$this->connected) {
            throw new Exception("Not connected");
        }
        // Just simulate success
        return true;
    }

    public function put($localPath, $remotePath) {
        if (!file_exists($localPath)) {
            return false;
        }
        $this->putFiles[] = [$localPath => $remotePath];
        // Simulate upload by storing file content in remoteFiles
        $this->remoteFiles[$remotePath] = file_get_contents($localPath);
        return true;
    }

    public function putString($remotePath, $contents) {
        if (!$this->connected) {
            throw new Exception("Not connected");
        }
        $this->remoteFiles[$remotePath] = $contents;
        return true;
    }

    public function get($remotePath, $localPath) {
        if (!isset($this->remoteFiles[$remotePath])) {
            return false;
        }
        file_put_contents($localPath, $this->remoteFiles[$remotePath]);
        return true;
    }

    public function getString($remotePath) {
        return $this->remoteFiles[$remotePath] ?? false;
    }

    public function run($command) {
        if (!$this->connected) {
            throw new Exception("Not connected");
        }
        // Simple mock response - echo command
        return "Executed command: " . $command;
    }

    public function deleteFile($remotePath) {
        if (isset($this->remoteFiles[$remotePath])) {
            unset($this->remoteFiles[$remotePath]);
            return true;
        }
        return false;
    }

    public function renameFile($oldName, $newName) {
        if (isset($this->remoteFiles[$oldName])) {
            $this->remoteFiles[$newName] = $this->remoteFiles[$oldName];
            unset($this->remoteFiles[$oldName]);
            return true;
        }
        return false;
    }

    public function changeDir($newPath) {
        // For the mock, just return true, no actual directory management
        return true;
    }

    public function isFile($path) {
        return isset($this->remoteFiles[$path]);
    }

    public function disconnect() {
        $this->connected = false;
    }
}