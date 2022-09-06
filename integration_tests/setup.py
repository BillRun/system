from setuptools import setup, find_packages

INSTALL_REQUIRES = [
    'PyHamcrest',
    'pymongo',
    'pytest',
    'python-dotenv',
    'requests',
    'selene==2.0.0b7',
    'webdriver-manager',
    'faker',
    'addict',
    'pytz',
    'json_schema_matchers'
]

setup(
    name='BillRun Autotests',
    version='0.1',
    description='Tests for BillRun\'s applications',
    packages=find_packages(),
    install_requires=INSTALL_REQUIRES
)
