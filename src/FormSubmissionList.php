<?php

namespace Symbiote\UdfObjects;

use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldImportButton;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\UserForms\Model\EditableFormField\EditableFormStep;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\UserForms\Model\UserDefinedForm;
use Symbiote\MultiValueField\Fields\KeyValueField;

class FormSubmissionList extends DataObject
{
    private static $table_name = 'FormSubmissionList';

    private static $db = [
        'Title'     => 'Varchar(128)',
        'TargetClass' => 'Varchar(255)',
        'PropertyMap' => 'MultiValueField',
        'RemoveFormSubmissions' => 'Boolean',
    ];

    public function onBeforeWrite()
    {
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


        $types = [];
        $dataClasses = ClassInfo::subclassesFor(DataObject::class);
        foreach ($dataClasses as $type => $label) {
            if (DataObject::has_extension($type, FormResponseExtension::class)) {
                $types[$type] = substr($label, strrpos($label, "\\") + 1);
            }
        }

        $fields->replaceField('TargetClass', DropdownField::create('TargetClass', 'Create items of this type', $types));
        $fields->removeByName('PropertyMap');

        if ($this->ID && $this->TargetClass) {
            $mapping = $this->PropertyMap->getValues();

            $fields->addFieldToTab('Root', Tab::create('Configuration'));
            $configFields = [];
            $configFields[] = $fields->dataFieldByName('Title');
            $configFields[] = $fields->dataFieldByName('TargetClass');
            $configFields[] = $fields->dataFieldByName('RemoveFormSubmissions');

            $fields->removeFieldsFromTab('Root.Main', ['Title', 'TargetClass', 'PropertyMap', 'RemoveFormSubmissions']);

            $fields->addFieldsToTab('Root.Configuration', $configFields);

            $inst = singleton($this->TargetClass);
            $dbFields = [];
            if ($inst) {
                $dbFields = array_keys($this->getSchema()->databaseFields($this->TargetClass));
                $dbFields = array_combine($dbFields, $dbFields);
            }

            $formFields = $this->gatherFormFields();

            $mappingField = KeyValueField::create('PropertyMap', 'Map fields from the form to a property', $formFields, $dbFields);
            // $mappingField->setRightTitle("");
            $fields->insertAfter('TargetClass', $mappingField);

            $items = DataList::create($this->TargetClass)->filter([
                'SubmissionListID' => $this->ID,
            ]);

            if ($items && count($items)) {
                $conf = GridFieldConfig_RecordEditor::create();
                $conf->getComponentByType(GridFieldSortableHeader::class);
                $exportButton = new GridFieldExportButton('buttons-before-left');

                $exportFields = count($mapping) ? array_combine(array_values($mapping), array_values($mapping)) : singleton($this->TargetClass)->summaryFields();
                $exportButton->setExportColumns($exportFields);
                $conf->addComponent(
                    $exportButton
                );
                $grid = GridField::create('Submissions', 'Submissions', $items->sort('ID', "DESC"), $conf);
                $fields->addFieldToTab('Root.Main', $grid);
            }
        }


        return $fields;
    }

    /**
     * Collects all the form field names from the forms that submit to this
     * location
     */
    protected function gatherFormFields()
    {
        $forms = UserDefinedForm::get()->filter([
            'SubmissionListID' => $this->ID,
        ]);

        $names = [];
        foreach ($forms as $form) {
            foreach ($form->Fields() as $field) {
                if ($field instanceof EditableFormStep) {
                    continue;
                }
                $names[$field->Title] = $field->Title;
            }
        }

        return $names;
    }

    public function addFormSubmission(SubmittedForm $submission)
    {
        $mapping = $this->PropertyMap->getValues();

        $submittedFields = $submission->Values();

        $toCreate = $this->TargetClass;

        if ($mapping && $submittedFields && $toCreate) {
            $submissionFieldVals = [];
            foreach ($submittedFields as $submittedField) {
                if (isset($mapping[$submittedField->Title])) {
                    $fname = $mapping[$submittedField->Title];
                    $submissionFieldVals[$fname] = $submittedField->Value;
                }
            }

            $obj = $toCreate::create($submissionFieldVals);
            $obj->SubmissionListID = $this->ID;
            $obj->FromFormID = $submission->ParentID;
            $obj->write();

            // now remove if needed
            if ($this->RemoveFormSubmissions) {
                $submission->delete();
            }
        }
    }
}
