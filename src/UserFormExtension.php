<?php

namespace Symbiote\UdfObjects;

use SilverStripe\ORM\DataExtension;

class UserFormExtension extends DataExtension
{
    private static $has_one = [
        'SubmissionList' => FormSubmissionList::class,
    ];
}
