<?php
namespace Helper;

/**
 * Jobs worker control for tests.
 *
 * The test environment has no running worker process (--worker), so an API that
 * hands its work over to the jobs queue (e.g. api/adjustinvoice) is refused with
 * "Workers are disabled", and once accepted - its job waits in the queue forever.
 * This helper turns the worker on/off and executes the queued jobs on demand:
 *
 *   $I->enableWorker();
 *   $I->sendAdjustInvoice([...]);   // the API pushes a Cycle_Account job
 *   $I->runWorkerJobs();            // the job is executed here, synchronously
 *   $I->disableWorker();
 */
class Worker extends \Codeception\Module
{
    /**
     * Turn the worker on: worker.enabled=true as a config revision, so the web
     * server (the API requests) sees it as well as the test process.
     * The jobs queue is emptied first, so runWorkerJobs() executes only the jobs
     * pushed from now on.
     */
    public function enableWorker()
    {
        $this->clearWorkerJobs();
        $this->setWorkerEnabled(true);
    }

    /**
     * Turn the worker off (the system default) and drop the jobs that were not
     * executed, so nothing leaks to the next test.
     */
    public function disableWorker()
    {
        $this->setWorkerEnabled(false);
        $this->clearWorkerJobs();
    }

    /**
     * Execute the queued jobs synchronously inside the test process - the same
     * pull + execute the worker performs (Action/Worker.php), just not forked.
     * Jobs pushed by an executed job (child jobs) are executed as well.
     * An exception thrown by a job is not caught - it fails the test.
     *
     * @param array $options
     *   limit        - max number of jobs to execute (default 100, guards against an endless queue)
     *   generate_pdf - when given, the jobs run with this value as billrun.generate_pdf.
     *                  a cycle job does not get the cycle options of the test (runCycle) -
     *                  it takes generate_pdf from the config, so pass 0 to skip the invoice PDF
     * @return int the number of executed jobs
     */
    public function runWorkerJobs(array $options = [])
    {
        $limit = $options['limit'] ?? 100;
        $config = \Billrun_Factory::config();
        $overrideGeneratePdf = isset($options['generate_pdf']);
        if ($overrideGeneratePdf) {
            // in-memory only - the jobs are executed inside the test process
            $originalGeneratePdf = $config->getConfigValue('billrun.generate_pdf');
            $config->setConfigValue('billrun.generate_pdf', (bool) $options['generate_pdf']);
        }
        $jobsManager = \Billrun_Jobsmanager::getInstance();
        $executedJobs = 0;
        try {
            while ($executedJobs < $limit && ($job = $jobsManager->pull()) !== false) {
                $job->execute(['config' => (array) $job->config]);
                $executedJobs++;
            }
        } finally {
            if ($overrideGeneratePdf) {
                $config->setConfigValue('billrun.generate_pdf', $originalGeneratePdf);
            }
        }
        return $executedJobs;
    }

    protected function setWorkerEnabled($enabled)
    {
        $this->getModule('\Helper\TestHelper')->overrideConfigValue('worker.enabled', $enabled);
    }

    protected function clearWorkerJobs()
    {
        \Billrun_Factory::db()->getCollection('jobs_messages')->remove(['_id' => ['$exists' => true]]);
    }
}
