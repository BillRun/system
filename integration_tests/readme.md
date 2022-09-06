# Setup

## Prerequisites

- python 3.7 or higher
- linux / os x / windows

## Create virtualenv

Navigate to folder with tests and run

`python3 -m venv venv`

## Activate venv

`source venv/bin/activate`

## Install dependencies

`pip3 install -e .`

Make sure to use pip3, or set up pip with Python 3.

## Set system variables

Could be placed to .env file

| Variable name | Value                    |
|---------------|--------------------------|
| USERNAME      | Login for BillRun app    |
| PASSWORD      | Password for BillRun app |
| ENV           | localhost:8074           |

# Run

1. Activate venv from the repo folder, if it was deactivated
`source venv/bin/activate`
2. Run tests
`pytest -m smoke`