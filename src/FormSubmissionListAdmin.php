<?php

namespace Symbiote\UdfObjects;

use SilverStripe\Admin\ModelAdmin;

class FormSubmissionListAdmin extends ModelAdmin
{
    private static $menu_title = 'Submissions';
    private static $url_segment = 'formsubmissionlists';
    private static $managed_models = [
        FormSubmissionList::class,
    ];

    protected function init()
    {
        parent::init();
        $this->showImportForm = false;
    }
}
