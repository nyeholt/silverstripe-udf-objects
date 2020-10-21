# Silverstripe User defined forms objects

Provides a way to map the information from a user defined form submission
to a custom data object type. 


## Composer Install

```
composer require nyeholt/silverstripe-udf-objects:~1.0
```

## Requirements

* SilverStripe 4.1+
* MultiValueField 5.0+

## Documentation

The module adds a "Submissions" section to the CMS. In here, 
create a new Form Submission list, which is where submissions are stored. 
First create a submission list, then navigate in the site tree
to find the user defined form you want to submit to this list; a 
new dropdown is available on the Submissions tab. 

Once this association is created, you can update the form submission list
to specify the data object being saved into, and then choose the field 
mapping. 

Next, apply the `FormResponseExtension` to the data object
being used to capture submissions. 

Note that if you have a multivaluefield on the target object, you can 
choose multiple fields to be mapped to it; each source field name
will appear as a key in that target field. 
