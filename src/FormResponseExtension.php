<?php

namespace Symbiote\UdfObjects;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\UserForms\Model\UserDefinedForm;

class FormResponseExtension extends DataExtension
{
    private static $has_one = [
        'SubmissionList' => FormSubmissionList::class,
        'FromForm' => UserDefinedForm::class
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->dataFieldByName('SubmissionListID')->setDisabled(true);
        $fields->dataFieldByName('FromFormID')->setDisabled(true);
    }
}
