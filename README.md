# REDCap CAT-MH External Module

This external module allows REDCap administrators to schedule CAT-MH interview invitations by email. Upon following the URL or hyperlink in the email invitations, the patient will be forwarded to a page where a test or series of tests (sequence) will be administered via the CAT-MH API. Once the interview is complete, the results will be stored in REDCap and can be viewed by a separate report page accessible from the project's Control Center. There are additional options to send reminder emails or send the patient's provider an email upon interview completion.

## Getting Started
The prerequisites for using this module are:
* A REDCap project to host the module
* The following fields must exist in instruments/forms in the project

	[record_id] (this module requires the record ID field to be named 'record_id')
	
	[catmh_email] (of type 'Text Box') (this is the patient's email address -- required to send them scheduled invitations and reminder invitations)

	[cat_mh_data] (of type 'Notes Box (Paragraph Text)') (it is recommended that this field have the @HIDDEN action tag)

	[subjectid] (of type 'Text Box') (it is recommended that this field have the @HIDDEN action tag)
* The following field is optional, but required to use the automatic provider email feature
	[catmh_provider_email] (of type 'Text Box') (this is the patient's provider's email address -- required to send them notification of interview completion and a link to the results)
	
#### Project Configuration
You can configure any number of CAT-MH interview sequences. Each sequence consists of a series of CAT-MH tests that make up a CAT-MH interview.
To do so, go to the "External Modules" page and click "Configure" for the CAT-MH module. You can select any number of tests for a sequence and select whether the interviewee should see the results at the end of their test.
You may enable/disable the provider email feature here.
You may also specify scheduled invitation and reminder email subject and body texts here. The module will replace`[interview-links]` and `[interview-urls]` with the actual patient-specific interview link/URLs at the time the emails are sent.

### Scheduling Interviews and Reminders
Interviews can be scheduled by interval or calendar.
1. Select a sequence that has been previously created/configured in the project's external module settings page.
2. Specify interval duration/frequency/delay or select a date and time on the calendar widget
3. Click 'Add to Schedule'. You will now see the scheduled sequence appear in the "Sequences" table.

![Scheduling Interface](/docs/scheduling.PNG)

You may select sequences in the table and delete them by clicking "Delete"

You may also enable reminder emails and specify how many days in succession, how far apart, and after how many days these emails are sent.

All emails sent (scheduled sequence, reminders, and provider emails) are logged to the project's log_event table which can be read by REDCap administrators via the Logging page available in the project sidebar.

![Emails logged](/docs/logging.PNG)

### Provider Email Note
Providers must have a REDCap account and be able to login before viewing patient results.

#### System Configuration (for REDCap Administrators)
After installing the external module, it must be configured on both then system level and the project level.
You must provide the module with both of the following CAT-MH API registration details:
* Application ID
* Organization ID

![System Configuration Details](/docs/systemLevel.PNG)