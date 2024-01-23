# Setup

## Prerequisites

- python 3.9 or higher
- linux / os x / windows

## Create virtualenv

Navigate to folder with tests and run

```bash
python3 -m venv venv
````

## Activate venv

```bash
 source venv/bin/activate
 ```

## Install dependencies
```bash
 pip3 install -e .
````

Make sure to use pip3, or set up pip with Python 3.

## Set system variables

Could be placed to .env file

| Variable name | Value                    |
|---------------|--------------------------|
| USERNAME      | Login for BillRun app    |
| PASSWORD      | Password for BillRun app |
| ENV           | http://localhost:8074    |

# Run

1. Activate venv from the repo folder, if it was deactivated
```bash
source venv/bin/activate
````
2. Run tests
```bash
pytest -m smoke
````

## Config for TestRail

* Settings file template config:

```ini
    [API]
    url = https://yoururl.testrail.net/
    email = user@email.com
    password = <api_key>

    [TESTRUN]
    assignedto_id = 1
    project_id = 1
    suite_id = 1
    plan_id = 4
    custom_git_tag = 'This is an example custom_git_tag'
    description = 'This is an example description'
    # run_id = 131 if run is already existing with same tests scope

    [TESTCASE]
    custom_comment = 'This is a custom comment'
```

Or

* Set command line options (see below)

Usage
-----

Basically, the following command will create a testrun in TestRail, add all marked tests to run.
Once the all tests are finished they will be updated in TestRail:

```bash
    py.test --testrail --tr-config=<settings file>.cfg
```

### All available options

| option                         | description                                                                                                                                        |
|--------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------|
| --testrail                     | Create and update testruns with TestRail                                                                                                           |
| --tr-config                    | Path to the config file containing information about the TestRail server (defaults to testrail.cfg)                                                |
| --tr-url                       | TestRail address you use to access TestRail with your web browser (config file: url in API section)                                                |
| --tr-email                     | Email for the account on the TestRail server (config file: email in API section)                                                                   |
| --tr-password                  | Password for the account on the TestRail server (config file: password in API section)                                                             |
| --tr-testrun-assignedto-id     | ID of the user assigned to the test run (config file:assignedto_id in TESTRUN section)                                                             |
| --tr-testrun-project-id        | ID of the project the test run is in (config file: project_id in TESTRUN section)                                                                  |
| --tr-testrun-suite-id          | ID of the test suite containing the test cases (config file: suite_id in TESTRUN section)                                                          |
| --tr-testrun-suite-include-all | Include all test cases in specified test suite when creating test run (config file: include_all in TESTRUN section)                                |
| --tr-testrun-name              | Name given to testrun, that appears in TestRail (config file: name in TESTRUN section)                                                             |
| --tr-testrun-description       | Description given to testrun, that appears in TestRail (config file: description in TESTRUN section)                                               |
| --tr-testrun-run-id            | Identifier of testrun, that appears in TestRail. If provided, option "--tr-testrun-name" will be ignored                                           |
| --tr-plan-id                   | Identifier of testplan, that appears in TestRail (config file: plan_id in TESTRUN section) If provided, option "--tr-testrun-name" will be ignored |
| --tr-version                   | Indicate a version in Test Case result.                                                                                                            |
| --tr-no-ssl-cert-check         | Do not check for valid SSL certificate on TestRail host                                                                                            |
| --tr-close-on-complete         | Close a test plan or test run on completion.                                                                                                       |
| --tr-dont-publish-blocked      | Do not publish results of "blocked" testcases in TestRail                                                                                          |
| --tr-skip-missing              | Skip test cases that are not present in testrun                                                                                                    |
| --tr-milestone-id              | Identifier of milestone to be assigned to run                                                                                                      |
| --tc-custom-comment            | Custom comment, to be appended to default comment for test case (config file: custom_comment in TESTCASE section)                                  |

## Allure report

1. Install allure to your machine
   `brew install allure` for macOS
2. Run tests
3. Run allure server 
```bash
allure serve allure-result
```

