<?php

namespace Symbiote\UdfObjects;

use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

class DeleteSubmissionJob extends AbstractQueuedJob
{
    public function __construct($params = [])
    {
        if (isset($params['ID'])) {
            $this->id = $params['ID'];
        }
    }

    public function getTitle()
    {
        return "Delete Submission Job";
    }

    public function getSignature()
    {
        return DeleteSubmissionJob::class . '#' . $this->id;
    }

    public function setup()
    {
        if (!$this->id) {
            throw new \Exception('Must supply submission ID.');
        }
        $this->totalSteps = 1;
        $this->addMessage("Prepping to delete submission {$this->id}.");
    }

    public function process()
    {
        $submission = SubmittedForm::get_by_id($this->id);
        if ($submission && $submission->ID) {
            $this->currentStep = 1;
            $this->addMessage("Successfully deleted submission #{$this->id}.");
        } else {
            $this->addMessage("Failed to delete submission #{$this->id}.");
        }
        $this->isComplete = true;
        $this->addMessage("Complete.");
    }
}
