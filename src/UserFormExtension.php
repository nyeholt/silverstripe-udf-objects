<?php

namespace Symbiote\UdfObjects;

use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

class UserFormExtension extends DataExtension
{
    private static $has_one = [
        'SubmissionList' => FormSubmissionList::class,
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $lists = FormSubmissionList::get();
        $targetLists = $lists->map();
        if (count($targetLists)) {
            $listDropdown = DropdownField::create('SubmissionListID', 'Create items from submission in this list', $targetLists);
            $listDropdown->setEmptyString("");
            $fields->addFieldToTab('Root.Submissions', $listDropdown);
        }

    }
}
