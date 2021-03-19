<?php

namespace Symbiote\UdfObjects;

use DNADesign\ElementalUserForms\Model\ElementForm;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig_RecordEditor;
use SilverStripe\Forms\GridField\GridFieldEditButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\ListboxField;
use SilverStripe\Forms\Tab;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Group;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\UserForms\Model\EditableFormField\EditableFieldGroup;
use SilverStripe\UserForms\Model\EditableFormField\EditableFieldGroupEnd;
use SilverStripe\UserForms\Model\EditableFormField\EditableFormStep;
use SilverStripe\UserForms\Model\Submission\SubmittedForm;
use SilverStripe\UserForms\Model\UserDefinedForm;
use Symbiote\AdvancedWorkflow\DataObjects\WorkflowDefinition;
use Symbiote\AdvancedWorkflow\Extensions\WorkflowApplicable;
use Symbiote\AdvancedWorkflow\Services\WorkflowService;
use Symbiote\MultiValueField\Fields\KeyValueField;
use Symbiote\MultiValueField\ORM\FieldType\MultiValueField;
use Symbiote\QueuedJobs\Services\QueuedJobService;

class FormSubmissionList extends DataObject
{
    private static $table_name = 'FormSubmissionList';

    private static $db = [
        'Title'     => 'Varchar(128)',
        'TargetClass' => 'Varchar(255)',
        'PropertyMap' => 'MultiValueField',
        'RemoveFormSubmissions' => 'Boolean',
    ];

    private static $many_many = [
        'ViewGroups' => Group::class,
        'EditorGroups' => Group::class,
        'AdminGroups' => Group::class,
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
        $this->beforeUpdateCMSFields(function (FieldList $fields) {
            $types = [];
            $dataClasses = ClassInfo::subclassesFor(DataObject::class);
            foreach ($dataClasses as $type => $label) {
                if (DataObject::has_extension($type, FormResponseExtension::class)) {
                    $types[$label] = substr($label, strrpos($label, "\\") + 1);
                }
            }

            $fields->replaceField('TargetClass', DropdownField::create('TargetClass', 'Create items of this type', $types));
            $fields->removeByName('PropertyMap');

            $fields->removeByName('ViewGroups');
            $fields->removeByName('EditorGroups');
            $fields->removeByName('AdminGroups');

            if ($this->ID && $this->TargetClass) {
                $mapping = $this->PropertyMap->getValues();

                $fields->addFieldToTab('Root', Tab::create('Configuration'));
                $configFields = [];
                $configFields[] = $fields->dataFieldByName('Title');
                $configFields[] = $fields->dataFieldByName('TargetClass');
                $configFields[] = $fields->dataFieldByName('RemoveFormSubmissions');

                $fields->removeFieldsFromTab('Root.Main', ['Title', 'TargetClass', 'PropertyMap', 'RemoveFormSubmissions']);

                $groups = Group::get()->map()->toArray();
                $configFields[] = ListboxField::create('ViewGroups', "Viewer groups", $groups);
                $configFields[] = ListboxField::create('EditorGroups', "Editor groups", $groups);
                $configFields[] = ListboxField::create('AdminGroups', "Admin groups", $groups);

                $fields->addFieldsToTab('Root.Configuration', $configFields);

                $inst = singleton($this->TargetClass);
                $dbFields = [];
                if ($inst) {
                    $dbFields = array_keys(Config::inst()->get($this->TargetClass, 'db'));

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

                    $exportFields = $this->buildExportColumns($mapping, $inst);
                    $exportButton->setExportColumns($exportFields);
                    $conf->addComponent(
                        $exportButton
                    );
                    $grid = GridField::create('Submissions', 'Submissions', $items->sort('ID', "DESC"), $conf);

                    $gridDataFields = $this->buildDataFields($mapping, $inst);
                    if (count($gridDataFields)) {
                        $grid->addDataFields($gridDataFields);
                    }

                    // for workflow support!
                    $components = $grid->getReadonlyComponents();
                    $components[] = GridFieldEditButton::class;
                    $grid->setReadonlyComponents($components);

                    $fields->addFieldToTab('Root.Main', $grid);
                }

                $fields->removeByName('AdditionalWorkflowDefinitions');
                if (class_exists(WorkflowDefinition::class) && DataObject::has_extension($this->TargetClass, WorkflowApplicable::class)) {
                    $fields->addFieldsToTab('Root.Workflow', [
                        DropdownField::create('WorkflowDefinitionID', 'Workflow to apply', WorkflowDefinition::get()->map())
                            ->setEmptyString('Select a workflow')
                    ]);
                }
            }

            if (!$this->canConfigure()) {
                $fields->removeByName('Configuration');
                $fields->removeByName('Workflow');
            }
        });

        return parent::getCMSFields();
    }

    /**
     * Takes all the properties from a custom mapping of data
     * and makes them available in the CSV export
     */
    public function buildExportColumns($mapping, $instance)
    {
        $fields = [];
        if (count($mapping)) {
            foreach ($mapping as $formField => $propertyName) {
                // hardcoded to "Properties" because that's what's on the form submission extension
                if ($instance->obj($propertyName) instanceof MultiValueField) {
                    $fields[$formField] = $formField;
                } else {
                    $fields[$propertyName] = $propertyName;
                }
            }
        }
        if (method_exists($this->TargetClass, 'updateExportColumns')) {
            $this->TargetClass::updateExportColumns($fields);
        }
        return $fields;
    }

    /**
     * Creates the actual closures used by the gridfield to extract the
     * property values
     */
    protected function buildDataFields($mapping, $instance)
    {
        $fields = [];

        if (!count($mapping)) {
            return $fields;
        }

        $buildField = function ($fieldProp) {
            return function ($item) use ($fieldProp) {
                list($fieldName, $propertyName) = explode('.', $fieldProp);
                if ($item->$fieldName) {
                    $props = $item->$fieldName->getValues();
                    if ($props) {
                        return isset($props[$propertyName]) ? $props[$propertyName] : '';
                    }
                }
            };
        };
        foreach ($mapping as $formField => $propertyName) {
            // see if it's a multivaluefield
            if ($instance->obj($propertyName) instanceof MultiValueField) {

                $fields[$formField] = $buildField($propertyName . '.' . $formField);
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
        ])->toArray();

        if (class_exists(ElementForm::class)) {
            $elements = ElementForm::get()->filter([
                'SubmissionListID' => $this->ID,
            ])->toArray();

            $forms = array_merge($forms, $elements);
        }


        $names = [];
        foreach ($forms as $form) {
            foreach ($form->Fields() as $field) {
                if (
                    $field instanceof EditableFormStep ||
                    $field instanceof EditableFieldGroup ||
                    $field instanceof EditableFieldGroupEnd
                ) {
                    continue;
                }
                if (!$field->Title) {
                    continue;
                }
                $names[$field->Title] = $field->Title;
            }
        }

        return $names;
    }

    public function addFormSubmission(SubmittedForm $submission, $fromForm)
    {
        $mapping = $this->PropertyMap->getValues();

        $submittedFields = $submission->Values();

        $toCreate = $this->TargetClass;

        if ($mapping && $submittedFields && $toCreate) {
            $obj = $toCreate::create();

            foreach ($submittedFields as $submittedField) {
                if (isset($mapping[$submittedField->Title])) {
                    $fname = $mapping[$submittedField->Title];

                    // let's check what the type of the given field is;
                    // if it's a multivaluefield, we're storing as part
                    // of an array value
                    $field = $obj->obj($fname);
                    if ($field instanceof MultiValueField) {
                        $vals = $obj->$fname->getValues();
                        if (!$vals) {
                            $vals = [];
                        }
                        $vals[$submittedField->Title] = $submittedField->Value;
                        $obj->$fname = $vals;
                    } else {
                        $obj->$fname = $submittedField->Value;
                    }
                }
            }

            $obj->SubmissionListID = $this->ID;
            $obj->SubmittedFormID = $submission->ID;
            if ($fromForm instanceof ElementForm) {
                $obj->FromElementID = $fromForm->ID;
            } else {
                $obj->FromFormID = $fromForm->ID;
            }
            $obj->write();

            // now remove if needed
            if ($this->RemoveFormSubmissions) {
                // via queued-job so the data is available for UserFormExtension::UdfDataValue()
                $job = new DeleteSubmissionJob([ 'ID' => $submission->ID ]);
                QueuedJobService::singleton()->queueJob($job, strtotime('now'));
            }

            if ($this->WorkflowDefinitionID) {
                Injector::inst()->get(WorkflowService::class)->startWorkflow($obj, $this->WorkflowDefinitionID);
            }
        }
    }

    /**
     * From a workflow perspective, we're acrchived
     */
    public function isArchived()
    {
        return true;
    }

    public function canView($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        $can = parent::canView($member);
        if (!$can && $member && $member->ID) {
            if ($member->inGroups($this->ViewGroups())) {
                $can = true;
            }
        }
        if (!$can) {
            return $this->canEdit($member);
        }
        return $can;
    }

    public function canEdit($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        $can = parent::canEdit($member);
        if (!$can && $member && $member->ID) {
            if ($member->inGroups($this->EditorGroups())) {
                return true;
            }
        }
        return $can;
    }

    public function canConfigure($member = null)
    {
        if (!$member) {
            $member = Security::getCurrentUser();
        }
        if ($member && $member->ID) {
            // if admin or in admin group
            if (Permission::checkMember($member, 'ADMIN') || $member->inGroups($this->AdminGroups())) {
                return true;
            }
        }
        return false;
    }
}
