<?php

namespace Pankaj\MHFSafeguard\XF\Service\Post;

class EditorService extends XFCP_EditorService
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
