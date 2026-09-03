<?php

namespace Pankaj\MHFSafeguard\XF\Service\Thread;

use Pankaj\MHFSafeguard\XF\Service\Post\PreparerService;

class ReplierService extends XFCP_ReplierService
{
    public function checkForSpam()
    {
        parent::checkForSpam();

        if ($this->post->message_state === 'visible')
        {
            /** @var PreparerService $preparer */
            $preparer = $this->postPreparer;
            $preparer->runMhfSafeguardScan();
        }
    }
}
