<?php

namespace Symbiote\UdfObjects;

use SilverStripe\ORM\DataExtension;
use SilverStripe\UserForms\Model\UserDefinedForm;

class FormResponseExtension extends DataExtension
{
    private static $has_one = [
        'SubmissionList' => FormSubmissionList::class,
        'FromForm' => UserDefinedForm::class
    ];
}
