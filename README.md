# BillRun
http://www.billrun.net
About
======
BillRun is an open-source billing and anti-fraud system utilized by telecom companies for managing customer usage effectively. It offers sophisticated tools to receive, process, rate, charge, and monitor various types of usage data, including telecom CDRs and pre-paid cards, all handled in real-time. The system supports different output formats such as customer invoices, wholesale reports, system monitoring, alerts, and anti-fraud event triggering. Built with fail-over safety and high-availability support, BillRun ensures reliability even in large volumes and sizes.

The system is developed by BillRun Technologies Ltd., a company committed to supporting open-source initiatives across various fields and fostering innovation.

Features
======
- Real-Time Usage Handling: Receive, process, rate, charge, and monitor customer usage in real-time.
- Versatile Output: Generate customer invoices, wholesale reports, system monitoring, alerts, and anti-fraud event triggers.
- Fail-Over Safety: Built-in fail-over safety mechanisms for enhanced reliability.
- High-Availability Support: Fully supports high-availability volumes and sizes.
- Flexible Architecture: Integration of YAF PHP Framework for high performance and Zend Framework toolbox for customization.
- Open-Source Customization: Modify according to specific needs and requirements, with support from third-party providers.

Installation
======
Install BillRun by configuring:


### Docker Configuration

To start the Billrun application for testing purposes using Docker and Docker Compose:

1. Start the Docker Compose stack:

```bash
docker-compose -f docker-compose-php74.yml up
```

2. Create the log file to overcome permission issues and crashes:

```bash
DEBUG_LOG_DIR=../../logs/container
mkdir ${DEBUG_LOG_DIR} -p
touch ${DEBUG_LOG_DIR}/debug.log && chmod 666 ${DEBUG_LOG_DIR}/debug.log
```

3. Build the Docker image:

```bash
docker-compose -f docker-compose-php74.yml build
```

4. Stop the Docker Compose stack and delete Docker-created volumes:

```bash
docker-compose -f docker-compose-php74.yml down -v
```

### Stripe PHP Bindings

To use the Stripe PHP bindings:

1. Install via Composer:

```bash
composer require stripe/stripe-php
```

2. Use Composer's autoload:

```php
require_once('vendor/autoload.php');

Ensure the following PHP extensions are enabled: curl, json, and mbstring. For more detailed documentation and usage examples, refer to the Stripe API documentation (https://stripe.com/docs/api)

Contribute
======
- Issue Tracker: [github.com/BillRun/system/issues](https://github.com/BillRun/system/pulls)
- Source Code: [github.com/BillRun/system](https://github.com/BillRun/system)

Support
======
If you encounter any issues or have questions, feel free to reach out:

- Email : info@billrun.com
- Phone Number : + 1 (917) 728-1607

License
======
This project is licensed under the BSD license.

