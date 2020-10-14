<?php

namespace Symbiote\UdfObjects;

use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\UserForms\Model\UserDefinedForm;

/**
 * Add this to data objects that should receive a user defined form
 * submission.
 */
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

    /**
     * The list we're attached to is the source of any
     * workflow definitions
     */
    public function workflowParent()
    {
        return $this->owner->SubmissionList();
    }
}
