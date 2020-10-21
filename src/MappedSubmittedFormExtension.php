<?php

namespace Symbiote\UdfObjects;

use DNADesign\ElementalUserForms\Model\ElementForm;
use SilverStripe\Core\Extension;
use SilverStripe\UserForms\Model\UserDefinedForm;

class MappedSubmittedFormExtension extends Extension
{
    public function updateAfterProcess()
    {
        $form = $this->owner->Parent();

        if ($form && $form->SubmissionListID) {
            $list = $form->SubmissionList();
            if ($list && $list->ID) {
                $list->addFormSubmission($this->owner, $form);
            }
        }
    }
}
