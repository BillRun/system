<?php

/**
 * @package         Billing
 * @copyright       Copyright (C) 2012-2013 BillRun Technologies Ltd. All rights reserved.
 * @license         GNU Affero General Public License Version 3; see LICENSE.txt
 */

/**
 * Billrun digital signature
 *
 * @package  Billing
 * @since    5.16
 */
class Billrun_Signer_JsignpdfSigner extends Billrun_Signer_SignerAbstract
{
    static public $type = 'jsignpdf';
    
    public $config;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->configure();
    }
    public function sign()
    {
        // check if file exists
        if (!file_exists($this->path)) {
            throw new Exception('File not found');
        }
        
        // build execution command
        $this->exec();
    }

    /**
     * @throws Exception
     */
    public function configure()
    {
        Billrun_Factory::config()->addConfig(APPLICATION_PATH . '/conf/PdfSign/signer.ini');
        $this->config = Billrun_Factory::config()->getConfigValue('signer.' . self::$type, []);
        if (empty($this->config)) {
            throw new Exception('No config for pdf signer');
        }
    }

    public function buildCommand()
    {
        $keystoreFile = $this->config['keystore_file'];
        $keystorePassword = $this->config['keystore_password'];
        $execPath = $this->config['exec_path'];

        // get dir of $this->path
        $outDir = dirname($this->path);
        
        $params = [
            '--crl' => '',
            '--disable-modify-content' => '',
            '--keystore-type' => 'PKCS12',
            '--certification-level' => 'CERTIFIED_NO_CHANGES_ALLOWED',
            '--keystore-file' => escapeshellarg($keystoreFile),
            '--keystore-password' => escapeshellarg($keystorePassword),
            '--out-directory' => escapeshellarg($outDir),
            '--l2-text' => escapeshellarg($this->config['l2_text']) ?? '""',
            '--l4-text' => escapeshellarg($this->config['l4_text']) ?? '""',
        ];
        
        if (isset($this->config['image'])) {
            $imageConfig = $this->config['image'];
            $params['--visible-signature'] = '';
            $availableKeys = array(
                '--bg-scale', '--bg-path', '-urx', '-ury', '-llx', '-lly'
            );
            foreach ($availableKeys as $k) {
                $cleanKey = ltrim($k, '-');
                if (!isset($imageConfig[$cleanKey])) {
                    continue;
                }
                $params[$k] = $imageConfig[$cleanKey];
            }
        }
        
        $paramsStr = join(' ', array_map(function ($key, $value) {
            return $value ? $key . ' ' . $value : $key;
        }, array_keys($params), $params));
        
        return $execPath . " " . $this->path . " " . $paramsStr;
    }

    public function exec()
    {
        try {
            $command = $this->buildCommand();
            $output = null;
            $return = null;
            exec($command, $output, $return);
            switch ($return) {
                case 0:
                    // success
                    // replace original file with signed file
                    $originalDir = dirname($this->path);
                    $originalFile = basename($this->path, '.pdf');
                    $signedFile = $originalDir . '/' . $originalFile . '_signed.pdf';
                    if (file_exists($signedFile)) {
                        rename($signedFile, $this->path);
                    }
                    break;
                case 1:
                    throw new Exception('command line is in a wrong format');
                case 2:
                    throw new Exception('no operation requested - e.g. no file for signing provided');
                case 3:
                    throw new Exception('signing of some - but not all - files failed');
                case 4:
                    throw new Exception('signing of all files failed');
                default:
                    throw new Exception('unknown error');
            }
        } catch (Exception $e) {
            throw new Exception('Error while signing pdf file');
        }
    }
}
