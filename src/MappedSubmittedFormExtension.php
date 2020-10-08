<?php

namespace Symbiote\UdfObjects;

use SilverStripe\Core\Extension;
use SilverStripe\UserForms\Model\UserDefinedForm;

class MappedSubmittedFormExtension extends Extension
{
    public function updateAfterProcess()
    {
        $formId = $this->owner->ParentID;

        $form = UserDefinedForm::get()->byID($formId);
    }
}
