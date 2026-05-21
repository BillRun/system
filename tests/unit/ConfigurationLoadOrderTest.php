<?php

class ConfigurationLoadOrderTest extends \Codeception\Test\Unit
{
    /** @var \UnitTester */
    protected $tester;

    private $includePath;
    private $hostIniPath;
    private $hostIniExisted = false;
    private $hostIniBackup;

    protected function _before()
    {
        $this->includePath = tempnam(sys_get_temp_dir(), 'brcd4542_') . '.json';
        file_put_contents($this->includePath, json_encode([
            'configuration' => ['brcd4542_marker' => 'from_include'],
        ]));

        $hostname = Billrun_Util::getHostName();
        $this->hostIniPath = APPLICATION_PATH . '/conf/' . $hostname . '.ini';

        if (file_exists($this->hostIniPath)) {
            $this->hostIniExisted = true;
            $this->hostIniBackup = file_get_contents($this->hostIniPath);
        }
        file_put_contents(
            $this->hostIniPath,
            "configuration.include[] = \"{$this->includePath}\"\n"
            . "configuration.brcd4542_host = \"from_host\"\n"
        );
    }

    protected function _after()
    {
        @unlink($this->includePath);
        if ($this->hostIniExisted) {
            file_put_contents($this->hostIniPath, $this->hostIniBackup);
        } else {
            @unlink($this->hostIniPath);
        }
    }

    public function testIncludeDeclaredInHostIniIsLoaded()
    {
        $base = new Yaf_Config_Simple([
            'configuration' => ['brcd4542_base' => 'from_base'],
        ]);

        $ref = new ReflectionClass('Billrun_Config');
        $instance = $ref->newInstanceWithoutConstructor();
        $ctor = $ref->getConstructor();
        $ctor->setAccessible(true);
        $ctor->invoke($instance, $base);

        $configProp = $ref->getProperty('config');
        $configProp->setAccessible(true);
        $merged = $configProp->getValue($instance)->toArray();

        $this->assertEquals('from_include', $merged['configuration']['brcd4542_marker']);
        $this->assertEquals('from_host', $merged['configuration']['brcd4542_host']);
        $this->assertEquals('from_base', $merged['configuration']['brcd4542_base']);
    }
}
