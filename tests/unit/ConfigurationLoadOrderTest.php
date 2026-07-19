<?php

class ConfigurationLoadOrderTest extends \Codeception\Test\Unit
{
    private $hostIniPath;
    private $hostIniBackup = null;

    protected function _before()
    {
        array_map('unlink', glob(sys_get_temp_dir() . '/brcd4542_*'));
        $this->hostIniPath = APPLICATION_PATH . '/conf/' . Billrun_Util::getHostName() . '.ini';
        if (file_exists($this->hostIniPath) && strpos(file_get_contents($this->hostIniPath), 'brcd4542') === false) {
            $this->hostIniBackup = file_get_contents($this->hostIniPath);
        }
    }

    protected function _after()
    {
        array_map('unlink', glob(sys_get_temp_dir() . '/brcd4542_*'));
        if ($this->hostIniBackup === null) {
            @unlink($this->hostIniPath);
        } else {
            file_put_contents($this->hostIniPath, $this->hostIniBackup);
        }
    }

    public function testConfigurationIncludeLoadOrder()
    {
        $baseJson = $this->tempJson(['brcd4542_base_only' => 'from_base_include', 'brcd4542_marker' => 'from_base_include']);
        $hostJson = $this->tempJson(['brcd4542_marker' => 'from_host_include']);
        file_put_contents(
            $this->hostIniPath,
            "configuration.include[] = \"{$hostJson}\"\n"
            . "configuration.brcd4542_host = \"from_host\"\n"
        );

        $base = new Yaf_Config_Simple(['configuration' => ['include' => [$baseJson]]]);
        $ref = new ReflectionClass('Billrun_Config');
        $instance = $ref->newInstanceWithoutConstructor();
        $ctor = $ref->getConstructor();
        $ctor->setAccessible(true);
        $ctor->invoke($instance, $base);
        $configProp = $ref->getProperty('config');
        $configProp->setAccessible(true);
        $conf = $configProp->getValue($instance)->toArray()['configuration'];

        // the host ini itself is loaded
        $this->assertEquals('from_host', $conf['brcd4542_host']);
        // the host ini's include list does not override the base one - both files load
        $this->assertEquals('from_base_include', $conf['brcd4542_base_only']);
        // includes load in declaration order, so on conflicting keys the host include wins
        $this->assertEquals('from_host_include', $conf['brcd4542_marker']);
    }

    private function tempJson(array $values)
    {
        $path = tempnam(sys_get_temp_dir(), 'brcd4542_') . '.json';
        file_put_contents($path, json_encode(['configuration' => $values]));
        return $path;
    }
}
