# REDCap CAT-MH External Module

This external module allows a REDCap administrator to add a link to the end of any survey that redirects the survey participant (user) to a new page where a test or series of tests will be administered via the CAT-MH API. Once the interview is complete, the results will be stored in REDCap and can be viewed by a separate report page accessible from the project's Control Center.

## Getting Started

The prerequisites for using this module are:
* Instance of REDCap
* Project created
* Survey type instrument created in that project

Next, you should install the CAT-MH external module. For more info about external modules visit [REDCap External Modules](https://redcap.vanderbilt.edu/external_modules/manager/control_center.php).

### Configuration

After installing the external module, it must be configured on both then system level and the project level.

#### System Configuration:

You must provide the module with both of the following CAT-MH API registration details:
* Application ID
* Organization ID

![System Configuration Details](/images/systemLevel.PNG)

#### Project Configuration:

Each survey instrument in a project can redirect the participant to one set of tests (interview) upon completion. You can create multiple survey instruments for your project and configure the CAT-MH external module to issue different interviews for each instrument.

To create an interview for your survey instrument
* Enter the instrument full display name in the first project-level input
* Select a language
* Select 1 or more tests to include in the interview associated with this instrument.

![Project Configuration Details](/images/projectLevel.PNG)

After saving this configuration, survey participants should then see a message and a button at the end of the survey prompting them to continue to the CAT-MH interview.

![Survey Complete](/images/surveyComplete.PNG)

After a participant finishes a survey, they will see a button that will redirect them to the CAT-MH interview that is proctored via the CAT-MH API. When the participant finishes the interview, they are shown the results, which are also stored in the REDCap project. You can view the results by clicking the “CAT-MH Interview Results Report” link on the project sidebar.
