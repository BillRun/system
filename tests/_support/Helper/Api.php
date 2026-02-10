<?php
namespace Helper;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

use Codeception\Configuration;
use Codeception\Exception\ModuleConfigException;
use Codeception\Exception\ModuleException;
use Codeception\TestInterface;

class Api extends \Codeception\Module
{
    protected $config = [
        'mongoRestoreEnable' => false,
        // test|suite
        'mongoRestoreRunBefore' => 'test',
        'mongoRestoreOnlyTests' => [],
        'mongoRestoreBinary' => 'mongorestore',
        // When false, helper will fail explicitly if mongorestore binary is unavailable.
        'mongoRestoreFallbackToPhp' => true,
        'mongoRestoreDir' => null,
        'mongoRestoreUri' => null,
        'mongoRestoreDb' => null,
        'mongoRestoreGzip' => true,
        'mongoRestoreDrop' => false,
        'mongoRestoreCollections' => [],
    ];

    protected $mongoRestoreDone = false;

    public function _beforeSuite($settings = [])
    {
        if (!$this->config['mongoRestoreEnable']) {
            return;
        }
        if (($this->config['mongoRestoreRunBefore'] ?? 'test') !== 'suite') {
            return;
        }

        $this->debugSection('MongoRestore', 'Trigger: _beforeSuite');
        $this->runMongoRestore();
    }

    public function _before(TestInterface $test)
    {
        if (!$this->config['mongoRestoreEnable']) {
            return;
        }
        if (($this->config['mongoRestoreRunBefore'] ?? 'test') !== 'test') {
            return;
        }
        if (!$this->matchesTestFilter($test)) {
            return;
        }

        $this->debugSection('MongoRestore', 'Trigger: _before (per test)');
        $this->runMongoRestore();
    }

    protected function matchesTestFilter(TestInterface $test)
    {
        $filters = $this->config['mongoRestoreOnlyTests'] ?? [];
        if (empty($filters)) {
            return true;
        }

        $metadata = $test->getMetadata();
        $filename = (string) $metadata->getFilename();
        $name = (string) $metadata->getName();

        foreach ((array) $filters as $token) {
            if (!is_string($token) || $token === '') {
                continue;
            }
            if (strpos($filename, $token) !== false || strpos($name, $token) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function runMongoRestore()
    {
        if ($this->mongoRestoreDone) {
            return;
        }

        $restoreDir = $this->resolvePath((string) ($this->config['mongoRestoreDir'] ?? ''));
        if ($restoreDir === '' || !is_dir($restoreDir)) {
            throw new ModuleConfigException(
                __CLASS__,
                sprintf('mongoRestoreDir is missing or not found: %s', $this->config['mongoRestoreDir'] ?? '')
            );
        }

        $uri = trim((string) ($this->config['mongoRestoreUri'] ?? ''));
        if ($uri === '') {
            $uri = (string) $this->getModule('MongoDb')->_getConfig('dsn');
        }
        if ($uri === '') {
            throw new ModuleConfigException(__CLASS__, 'mongoRestoreUri (or MongoDb dsn) is required.');
        }

        $db = trim((string) ($this->config['mongoRestoreDb'] ?? ''));
        if ($db === '') {
            $db = $this->extractDbNameFromUri($uri);
        }
        if ($db === '') {
            throw new ModuleConfigException(__CLASS__, 'mongoRestoreDb could not be resolved.');
        }

        $commandParts = [
            escapeshellarg((string) ($this->config['mongoRestoreBinary'] ?? 'mongorestore')),
            '--uri ' . escapeshellarg($uri),
            '--db ' . escapeshellarg($db),
            '--dir ' . escapeshellarg($restoreDir),
        ];

        if (!empty($this->config['mongoRestoreGzip'])) {
            $commandParts[] = '--gzip';
        }
        if (!empty($this->config['mongoRestoreDrop'])) {
            $commandParts[] = '--drop';
        }

        $collections = $this->config['mongoRestoreCollections'] ?? [];
        $collections = $this->normalizeCollections($collections, $restoreDir, $db);

        $binary = (string) ($this->config['mongoRestoreBinary'] ?? 'mongorestore');
        if ($this->hasBinary($binary)) {
            foreach ($collections as $collection) {
                $commandParts[] = '--nsInclude ' . escapeshellarg($db . '.' . $collection);
            }

            $command = implode(' ', $commandParts) . ' 2>&1';
            $this->debugSection('MongoRestore', $command);

            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            if ($exitCode !== 0) {
                $tail = implode(PHP_EOL, array_slice($output, -40));
                throw new ModuleException(__CLASS__, "mongorestore failed (exit $exitCode)\n$tail");
            }

            $this->debugSection('MongoRestore', 'Restore completed successfully via mongorestore.');
        } else {
            if (empty($this->config['mongoRestoreFallbackToPhp'])) {
                throw new ModuleException(
                    __CLASS__,
                    sprintf(
                        'mongorestore binary "%s" not found/executable and fallback is disabled.',
                        $binary
                    )
                );
            }
            $this->debugSection('MongoRestore', 'mongorestore not found, falling back to PHP BSON restore.');
            $this->runPhpBsonRestore($restoreDir, $db, $collections);
        }

        $this->mongoRestoreDone = true;
        $this->debugSection('MongoRestore', 'Restore completed successfully.');
    }

    protected function hasBinary($binary)
    {
        $binary = trim((string) $binary);
        if ($binary === '') {
            return false;
        }
        if (strpos($binary, '/') !== false) {
            return is_executable($binary);
        }

        $cmd = 'command -v ' . escapeshellarg($binary) . ' >/dev/null 2>&1';
        $output = [];
        $exitCode = 0;
        exec($cmd, $output, $exitCode);
        return $exitCode === 0;
    }

    protected function normalizeCollections($collections, $restoreDir, $db)
    {
        $normalized = [];
        foreach ((array) $collections as $collection) {
            if (is_string($collection) && $collection !== '') {
                $normalized[] = $collection;
            }
        }

        if (!empty($normalized)) {
            return array_values(array_unique($normalized));
        }

        $patterns = [
            $restoreDir . DIRECTORY_SEPARATOR . '*.bson',
            $restoreDir . DIRECTORY_SEPARATOR . '*.bson.gz',
            $restoreDir . DIRECTORY_SEPARATOR . $db . DIRECTORY_SEPARATOR . '*.bson',
            $restoreDir . DIRECTORY_SEPARATOR . $db . DIRECTORY_SEPARATOR . '*.bson.gz',
        ];

        foreach ($patterns as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                $name = basename($file);
                $name = preg_replace('/\.bson(\.gz)?$/', '', $name);
                if (is_string($name) && $name !== '') {
                    $normalized[] = $name;
                }
            }
        }

        return array_values(array_unique($normalized));
    }

    protected function runPhpBsonRestore($restoreDir, $db, array $collections)
    {
        if (!function_exists('\MongoDB\BSON\toPHP')) {
            $binary = (string) ($this->config['mongoRestoreBinary'] ?? 'mongorestore');
            throw new ModuleException(
                __CLASS__,
                sprintf(
                    'Cannot restore fixtures: "%s" is unavailable and PHP BSON functions are missing. Install mongodb-database-tools or ext-mongodb.',
                    $binary
                )
            );
        }

        /** @var \Codeception\Module\MongoDb $mongoModule */
        $mongoModule = $this->getModule('MongoDb');
        $mongoModule->useDatabase($db);
        $dbh = $mongoModule->driver->getDbh();
        $isLegacy = $mongoModule->driver->isLegacy();

        foreach ($collections as $collectionName) {
            $bsonFile = $this->findBsonFile($restoreDir, $db, $collectionName);
            if ($bsonFile === null) {
                $this->debugSection('MongoRestore', sprintf('Skip "%s": BSON file not found.', $collectionName));
                continue;
            }

            $documents = $this->readBsonDocuments($bsonFile);
            $collection = $dbh->selectCollection($collectionName);
            $restored = 0;

            foreach ($documents as $document) {
                if (!is_array($document)) {
                    continue;
                }

                if (isset($document['_id'])) {
                    $filter = ['_id' => $document['_id']];
                    if ($isLegacy) {
                        $collection->update($filter, ['$set' => $document], ['upsert' => true]);
                    } else {
                        $collection->updateOne($filter, ['$set' => $document], ['upsert' => true]);
                    }
                } else {
                    if ($isLegacy) {
                        $collection->insert($document);
                    } else {
                        $collection->insertOne($document);
                    }
                }
                $restored++;
            }

            $this->debugSection(
                'MongoRestore',
                sprintf('Restored %d docs into "%s" from "%s".', $restored, $collectionName, basename($bsonFile))
            );
        }
    }

    protected function findBsonFile($restoreDir, $db, $collection)
    {
        $candidates = [
            $restoreDir . DIRECTORY_SEPARATOR . $collection . '.bson.gz',
            $restoreDir . DIRECTORY_SEPARATOR . $collection . '.bson',
            $restoreDir . DIRECTORY_SEPARATOR . $db . DIRECTORY_SEPARATOR . $collection . '.bson.gz',
            $restoreDir . DIRECTORY_SEPARATOR . $db . DIRECTORY_SEPARATOR . $collection . '.bson',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function readBsonDocuments($filePath)
    {
        $documents = [];
        $isGz = substr($filePath, -3) === '.gz';
        $typeMap = ['root' => 'array', 'document' => 'array', 'array' => 'array'];

        if ($isGz) {
            $handle = gzopen($filePath, 'rb');
            if (!$handle) {
                throw new ModuleException(__CLASS__, 'Unable to open gzip BSON file: ' . $filePath);
            }
            while (!gzeof($handle)) {
                $lengthBytes = gzread($handle, 4);
                if ($lengthBytes === '' || strlen($lengthBytes) === 0) {
                    break;
                }
                if (strlen($lengthBytes) !== 4) {
                    throw new ModuleException(__CLASS__, 'Corrupted BSON length header in: ' . $filePath);
                }
                $docLength = unpack('Vlen', $lengthBytes)['len'] ?? 0;
                if ($docLength < 5) {
                    throw new ModuleException(__CLASS__, 'Invalid BSON document length in: ' . $filePath);
                }
                $body = $this->gzReadExact($handle, $docLength - 4, $filePath);
                $raw = $lengthBytes . $body;
                $documents[] = \MongoDB\BSON\toPHP($raw, $typeMap);
            }
            gzclose($handle);
            return $documents;
        }

        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            throw new ModuleException(__CLASS__, 'Unable to open BSON file: ' . $filePath);
        }
        while (!feof($handle)) {
            $lengthBytes = fread($handle, 4);
            if ($lengthBytes === '' || strlen($lengthBytes) === 0) {
                break;
            }
            if (strlen($lengthBytes) !== 4) {
                throw new ModuleException(__CLASS__, 'Corrupted BSON length header in: ' . $filePath);
            }
            $docLength = unpack('Vlen', $lengthBytes)['len'] ?? 0;
            if ($docLength < 5) {
                throw new ModuleException(__CLASS__, 'Invalid BSON document length in: ' . $filePath);
            }
            $body = $this->fReadExact($handle, $docLength - 4, $filePath);
            $raw = $lengthBytes . $body;
            $documents[] = \MongoDB\BSON\toPHP($raw, $typeMap);
        }
        fclose($handle);

        return $documents;
    }

    protected function gzReadExact($handle, $length, $filePath)
    {
        $data = '';
        while (strlen($data) < $length && !gzeof($handle)) {
            $chunk = gzread($handle, $length - strlen($data));
            if ($chunk === false) {
                break;
            }
            $data .= $chunk;
        }
        if (strlen($data) !== $length) {
            throw new ModuleException(__CLASS__, 'Unexpected EOF while reading BSON from: ' . $filePath);
        }
        return $data;
    }

    protected function fReadExact($handle, $length, $filePath)
    {
        $data = '';
        while (strlen($data) < $length && !feof($handle)) {
            $chunk = fread($handle, $length - strlen($data));
            if ($chunk === false) {
                break;
            }
            $data .= $chunk;
        }
        if (strlen($data) !== $length) {
            throw new ModuleException(__CLASS__, 'Unexpected EOF while reading BSON from: ' . $filePath);
        }
        return $data;
    }

    protected function extractDbNameFromUri($uri)
    {
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return '';
        }
        $db = trim($path, '/');
        if ($db === '') {
            return '';
        }
        $qPos = strpos($db, '?');
        if ($qPos !== false) {
            $db = substr($db, 0, $qPos);
        }
        return $db;
    }

    protected function resolvePath($path)
    {
        if ($path === '') {
            return '';
        }
        if ($path[0] === '/') {
            return $path;
        }
        return rtrim(Configuration::projectDir(), '/\\') . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    public static function cleanDB() {

        $subs = \Billrun_Factory::db()->subscribersCollection();
        $subs->remove(['_id'=>['$exists' => true]]);
        $lines = \Billrun_Factory::db()->linesCollection();
        $lines->remove(['_id'=>['$exists' => true]]);
        $queue = \Billrun_Factory::db()->queueCollection();
        $queue->remove(['_id'=>['$exists' => true]]);
        $services = \Billrun_Factory::db()->servicesCollection();
        $services->remove(['_id'=>['$exists' => true]]);
        $plans = \Billrun_Factory::db()->plansCollection();
        $plans->remove(['_id'=>['$exists' => true]]);
        $discounts = \Billrun_Factory::db()->discountsCollection();
        $discounts->remove(['_id'=>['$exists' => true]]);
        $billruns =\Billrun_Factory::db()->billrunCollection();
        $billruns->remove(['_id'=>['$exists' => true]]);
        $billing_cycleCollection = \Billrun_Factory::db()->billing_cycleCollection();
        $billing_cycleCollection->remove(['_id'=>['$exists' => true]]);
        $rates = \Billrun_Factory::db()->ratesCollection();
        $rates->remove(['_id'=>['$exists' => true]]);
    }

}
