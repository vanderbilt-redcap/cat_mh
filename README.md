



# REDCap CAT-MH External Module

The CAT-MH module allows administrators to schedule CAT-MH interviews for patients. The interview invitations are delivered via email and scheduled via a user-friendly web interface.

These invitations are scheduled per participant on a rolling basis according to the patient's enrollment date.

The module includes a Results Report for reviewing test results and a patient Patient Dashboard to review interview status.

## Getting Started
The prerequisites for using this module are:
* A REDCap project to host the module

The following fields must exist in instruments/forms in the project

* [catmh_email] (of type 'Text Box') -- This is the patient's email address. It is required in order to send them scheduled invitations and reminder invitations. You may specify an alternative participant email field in the module configuration.

* [subjectid] (of type 'Text Box') -- It is recommended that this field have the @HIDDEN action tag.

* An enrollment date field -- This field can be named anything, and is selected in the External Modules configuration page.
	
The following field is optional, but required to use the automatic provider email feature:
* [catmh_provider_email] (of type 'Text Box') -- This is the patient's provider's email address. It is required to send them notification of interview completion and a link to the results.
	
#### Project Configuration
You can configure any number of CAT-MH sequences. Each sequence consists of a series of CAT-MH tests that make up a CAT-MH interview.

To do so, go to the "External Modules" page and click "Configure" for the CAT-MH module. You can select any number of tests for a sequence and select whether the interviewee should see the results at the end of their test.

**Interview Storage Field** (optional): select a field to store all interview data for the associated record (interview data is typically stored in the external module log which is not accessible via the API), this field will be updated hourly with the latest data for _all_ interviews associated with the record.

You may enable/disable the provider email feature here.
You may also specify scheduled invitation and reminder email subject and body texts here. The module will replace`[interview-links]` and `[interview-urls]` with the actual patient-specific interview link/URLs at the time the emails are sent.

You may also configure the module to automatically invite participants who complete a selected survey to continue to their first scheduled interview.

Finally, you can specify any number of 'filter fields'. These fields will prevent the module from sending invite/reminder emails to participants if the given field is empty for the patient's associated record.

##### Alternate Labels
You may also configure an alternate label for any sequence. This alternate label will be shown as the test name to the participant during the interview. 

Below is an image of an interview results page showing "Wellness Test" as the alternate label for the CAT-MH 'Depression' test type.
![Alternate Label Example](/docs/alternate_test_label_results.PNG)


### Scheduling Interviews and Reminders
To schedule invitations to be sent to participants, an Enrollment Date/Time field must first be selected via External Modules > Configure.

The module will send interview invitations and reminders based on the scheduled offset relative to this day. For example, if a participant record's [enrollment_date] field is 2020-11-23 and an administrator schedules an invite with offset 2, time of day 8:00 AM, then the module will send the participant an interview invitation on 2020-11-25 8:00:00 AM.

After an Enrollment Date/Time field is configured, interviews can be scheduled by interval or single occurrence.
1. Select a sequence that has been previously created/configured in the project's external module settings page.
2. Specify interval duration/frequency/delay or the offset and time of a single invitation.
3. Click 'Add to Schedule'. You will now see the scheduled sequence appear in the "Sequences" table.

![Scheduling Interface](/docs/scheduling.PNG)

You may select sequences in the table and delete them by clicking "Delete"

You may also enable reminder emails and specify how many days in succession, how far apart, and after how many days these emails are sent.

All emails sent (scheduled sequence, reminders, and provider emails) are logged to the project's Logging page available in the project sidebar.

![Emails logged](/docs/logging.PNG)

### Provider Email Note
Providers must have a REDCap account and be able to login before viewing patient results.

#### System Configuration (for REDCap Administrators)
After installing the external module, it must be configured at both the system level and the project level.

You must provide the module with both of the following CAT-MH API registration details at the system configuration level (available in the REDCap Control Center sidebar -> External Modules -> Configure):
* Application ID
* Organization ID

![System Configuration Details](/docs/systemLevel.PNG)

### K-CAT Paired Interviews

Version 2.1.0 of the CAT-MH module adds support for K-CAT test types. These are described by Adapative Testing Technologies:

"Our children’s version of the CAT-MH™ has been validated for youth ages 7 to 17. The K-CAT™ includes self-rated and parent/caregiver-rated modules for: depression, anxiety, mania, ADHD, conduct disorder, oppositional defiant disorder, substance use disorder (self-rated), and suicidality (self-rated)."

K-CAT interview sequences are configured similar to normal sequences. Due to the paired nature of the interviews, they can't be mixed in sequences with other test types. Project participants will receive an email with two links, one for a Parent interview and the other pointing to the Child interview.

### Interview Progress Meter
Version 2.2.0 adds a progress meter to the participant interview page.
![Interview progress meter](/docs/progress_meter.PNG)
The number of circle icons indicates how many tests are included in the interview*. Blue circles denote a test being taken currently, green denotes completed tests, and gray denote tests that have yet to be started as part of the interview.

*Note: Due to how the meter is implemented, the progress meter counts 'c/adhd' and 'a/adhd' to be a single test in the interview. This has to do with how the module detects which test a user is currently answering. The same is true for 'dep' and 'p-dep', 'anx' and 'p-anx', and 'm/hm' and 'p-m/hm' test sets.

## API

Interview response data may be fetched via an [EM Framework API endpoint](https://github.com/vanderbilt-redcap/external-module-framework-docs/blob/main/api.md) with the action "get-interview-data". The interview data returned will be identical to what is provided on the "CAT-MH Interview Data Export" page with the record's primary key (e.g. `record_id`) included.

Request query example:
``` bash
TOKEN="YOUR_PROJECT_API_TOKEN"
API_ENDPOINT="https://your_institution.edu/redcap_vX.Y.Z/api/"

curl -F "token=${TOKEN}" \
     -F "content=externalModule" \
     -F "prefix=cat_mh" \
     -F "action=get-interview-data" \
     -F "format=json" \
     -F "returnFormat=json" \
     "${API_ENDPOINT}"
```

Response example:
``` json
[
  {
    "<primary_key_field>": "<primary_key_value>",
    "interview_data": [
      { <interview_1_data> },
      ... ,
      { <interview_n_data> },
    ],
  },
  ...
]
```
