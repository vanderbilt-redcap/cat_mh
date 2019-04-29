# REDCap CAT-MH External Module

This external module allows a REDCap administrators to send participants a link to a public survey. Upon providing their consent and finishing the public survey, the survey taker will be forwarded to a page where a test or series of tests will be administered via the CAT-MH API. Once the interview is complete, the results will be stored in REDCap and can be viewed by a separate report page accessible from the project's Control Center. Finally, there is an option to configure automatic interview invites for users who have provided an email address.

## Getting Started
The prerequisites for using this module are:
* A REDCap project to host the module
* Public survey instrument in said project that has the following fields:
	[participant_email] (of type 'Text Box') *optional
	[consent] (of type 'Yes - No')
	[cat_mh_data] (of type 'Notes Box (Paragraph Text)') (it is recommended that this field have the @HIDDEN action tag)
	[subjectid] (of type 'Text Box') (it is recommended that this field have the @HIDDEN action tag)

#### Project Configuration
You can configure any number of sequences. Each sequence consists of a series of CAT-MH tests that make up a CAT-MH interview.
On the project configuration modal, you can select any number of tests for a sequence and select whether the interviewee should see the results at the end of their test.
You may optionally set the email and periodicity settings. If these values are set, the module will automatically send an email to each participant who has provided a
`[participant_email]` value during their initial public survey.


#### System Configuration (for REDCap Administrators)
After installing the external module, it must be configured on both then system level and the project level.
You must provide the module with both of the following CAT-MH API registration details:
* Application ID
* Organization ID

![System Configuration Details](/images/systemLevel.PNG)