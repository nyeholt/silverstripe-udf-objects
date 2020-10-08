<?php

namespace Symbiote\UdfObjects;

use SilverStripe\ORM\DataExtension;

class FormResponseExtension extends DataExtension
{
    private static $has_one = [
        'SubmissionList' => FormSubmissionList::class
    ];
}
