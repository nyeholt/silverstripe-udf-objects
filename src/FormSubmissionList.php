<?php

namespace Symbiote\UdfObjects;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;
use SilverStripe\ORM\DataObject;
use Symbiote\MultiValueField\Fields\KeyValueField;

class FormSubmissionList extends DataObject
{
    private static $db = [
        'TargetClass' => 'Varchar(255)',
        'PropertyMap' => 'MultiValueField',
    ];

    public function onBeforeWrite() {
        parent::onBeforeWrite();

        $props = $this->PropertyMap->getValues();
        if (!$props) {
            $props = [];
        }
        if (!isset($props['ListID'])) {
            $props['ListID'] = '';
        }
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->replaceField('TargetClass', DropdownField::create('TargetClass', 'Create items of this type', ClassInfo::allClasses()));

        $fields->removeByName('PropertyMap');
        if ($this->ID && $this->TargetClass) {
            $inst = singleton($this->TargetClass);
            $dbFields = [];
            if ($inst) {
                $dbFields = array_keys($this->getSchema()->databaseFields($this->TargetClass));
                $dbFields = array_combine($dbFields, $dbFields);
            }
            $mappingField = KeyValueField::create('PropertyMap', 'Map fields from the submission to a property', null, $dbFields);
            // $mappingField->setRightTitle("");
            $fields->insertAfter('TargetClass', $mappingField);
        }


        return $fields;
    }
}
