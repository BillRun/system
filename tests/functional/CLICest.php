<?php
Use Codeception\Module\Cli;

class CLICest
{
    public function cliSanity(CLI $I)
    {
        $I->runShellCommand('php public/index.php');
        $I->seeInShellOutput('Running under');
    }

    public function cliIllegalCommand(CLI $I)
    {
        $I->runShellCommand('php public/index.php -receive');
        $I->seeShellOutputMatches('/Option.*not recognized/');
    }

    public function cliActionExists(CLI $I)
    {
        $I->runShellCommand('php public/index.php --receive');
        $I->seeInShellOutput('Receive');
    }

    public function cliNonExistentAction(CLI $I)
    {
        $I->runShellCommand('php public/index.php --receive2');
        $I->dontSeeInShellOutput('Receive');
    }

}
