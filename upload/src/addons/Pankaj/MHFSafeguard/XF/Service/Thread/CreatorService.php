<?php

namespace Pankaj\MHFSafeguard\XF\Service\Thread;

use Pankaj\MHFSafeguard\XF\Service\Post\PreparerService;

class CreatorService extends XFCP_CreatorService
{
    public function checkForSpam()
    {
        parent::checkForSpam();

        if ($this->thread->discussion_state === 'visible')
        {
            /** @var PreparerService $preparer */
            $preparer = $this->postPreparer;
            $preparer->runMhfSafeguardScan();
        }
    }
}
