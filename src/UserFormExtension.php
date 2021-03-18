<?php

namespace Symbiote\UdfObjects;

use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;

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

    public function UdfObject()
    {
        static $sub_obj = null;

        if (!$sub_obj) {
            /** @var ElementForm */
            $owner = $this->getOwner();

            if ($owner->SubmissionListID) {
                /** @var FormSubmissionList */
                $list = $owner->SubmissionList();
                /** @var DataObject */
                $class = ClassInfo::class_name($list->TargetClass);
                // need SubmittedForm id from request
                if ($request = Controller::curr()->getRequest()) {
                    // Session has userform ID?
                    $session = $request->getSession()->getAll();
                    $key = 'userformssubmission'.$owner->ID;
                    if (key_exists($key, $session)) {
                        // get SubmittedForm
                        if ($submission = SubmittedForm::get_by_id($session[$key])) {
                            // is DataObject?
                            if (ClassInfo::exists($class) && in_array(DataObject::class, ClassInfo::ancestry($class))) {
                                if ($obj = $class::get()->filter('SubmittedFormID', $submission->ID)->first()) {
                                    $sub_obj = $obj;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $sub_obj;
    }

    public function UdfDataArray()
    {
        static $sub_data_cache = [];

        if (!$sub_data_cache) {
            /** @var ElementForm */
            $owner = $this->getOwner();

            if ($obj = $owner->UdfObject()) {
                // cache fields
                $sub_data_cache = $obj->toMap();
                // add mapped properties
                if ($obj->Properties) {
                    $props = $obj->Properties->getValue();
                    if ($props && count($props)) {
                        $sub_data_cache = array_replace_recursive($sub_data_cache, $props);
                    }
                }
            }
        }

        return $sub_data_cache;
    }

    public function UdfDataValue($name = null)
    {
        if (!$name) {
            return null;
        }

        /** @var ElementForm */
        $owner = $this->getOwner();

        // get field from Udf obj if able
        $data = $owner->UdfDataArray();
        if (key_exists($name, $data)) {
            return $data[$name];
        }

        return null;
    }

    public function UdfMethod($name = null, ...$args)
    {
        if (!$name) {
            return null;
        }

        /** @var ElementForm */
        $owner = $this->getOwner();

        // get field from Udf obj if able
        if ($obj = $owner->UdfObject()) {
            if (method_exists($obj, $name)) {
                return $obj->$name(...$args);
            }
            $altName = 'get' . $name;
            if (method_exists($obj, $altName)) {
                return $obj->$altName(...$args);
            }
        }

        return null;
    }
}
