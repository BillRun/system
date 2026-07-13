# BillRun Configuration Variables Reference

This article documents all configuration variables read from the application configuration via:

```php
Billrun_Factory::config()->getConfigValue('<key>', <default>)
```

It is generated from a static scan of every `getConfigValue()` call site under `application/` and `library/Billrun/`. Sections are sorted alphabetically; keys within each section are sorted alphabetically. Where multiple call sites disagree on a default, the most common one is listed; complex literals are shown as `(computed)` or `(array)`.

**Notation:**

- Default `null` means no default was supplied in code (the key must be set in configuration).
- Bracketed key segments like `{$collection}` or `{$action}` are placeholders interpolated from runtime values.

## admindb

| Key | Default | Description |
|-----|---------|-------------|
| `admindb` | `null` | MongoDB connection options for the admin database; falls back to main db when empty. |


## api

| Key | Default | Description |
|-----|---------|-------------|
| `api.actions` | `(array)` | Map of available API actions registered with the API controller. |
| `api.api2.allowed` | `0` | Whether unauthenticated access to the Api2 controller is permitted. |
| `api.bill.collection_debt.threshold` | `null` | Minimum debt threshold used when querying accounts in collection. |
| `api.cards.query.page` | `0` | Default page number for the cards query API when none provided. |
| `api.cards.query.size` | `10000` | Default page size for the cards query API when none provided. |
| `api.config.aggregate` | `[]` | Aggregate API settings including permitted pipelines and collections. |
| `api.config.aggregate.timeout` | `60000` | Timeout in milliseconds for aggregate API cursor queries. |
| `api.config.find` | `[]` | Find API settings including page size limits and permitted collections. |
| `api.config.find.timeout` | `60000` | Timeout in milliseconds for find API cursor queries. |
| `api.export.max_export_lines` | `100000` | Maximum number of lines exported via the v3 export API. |
| `api.healthcheck.auth_required` | `1` | Whether the API healthcheck endpoint requires authentication. |
| `api.log.db.base` | `1000` | Sampling base used to decide which API calls to persist to the log DB. |
| `api.log.db.enable` | `0` | Sampling fraction controlling how many API calls are logged to the database. |
| `api.maintain` | `0` | Maintenance mode flag that causes healthcheck to report failure. |
| `api.outputMethod` | `(array)` | Per-action output formatter mapping for API responses. |
| `api.realtime2.allowed` | `0` | Whether unauthenticated access to the Realtime2 controller is permitted. |

## application

| Key | Default | Description |
|-----|---------|-------------|
| `application.directory` | `APPLICATION_PATH "/application"` | Filesystem path to the application root, used for views and assets. |

## array_query

| Key | Default | Description |
|-----|---------|-------------|
| `array_query.expressions_mapping` | `[]` | Custom expression operators mapping used when evaluating in-memory array queries. |

## async

| Key | Default | Description |
|-----|---------|-------------|
| `async.fork.disabled` | `0` | Disables process forking so async tasks run synchronously. |

## auth

| Key | Default | Description |
|-----|---------|-------------|
| `auth.protocols` | `[]` | List of allowed authentication protocols exposed via the auth options endpoint. |

## backup

| Key | Default | Description |
|-----|---------|-------------|
| `backup.default_backup_path` | `FALSE` | Default filesystem path used to back up processed input files. |

## balance

| Key | Default | Description |
|-----|---------|-------------|
| `balance` | `array()` | Default settings array merged into Billrun_Balance instances. |
| `balance.minCost` | `-0.1` | Minimum balance cost threshold used when locating usable balances. |
| `balance.minCost.data` | `(computed)` | Minimum data balance cost threshold for detecting valid data balance during slowness. |
| `balance.minUsage` | `-3` | Minimum balance usage volume threshold used when locating usable balances. |
| `balance.minUsage.data` | `(computed)` | Minimum data balance usage threshold for detecting valid data balance during slowness. |

## balances

| Key | Default | Description |
|-----|---------|-------------|
| `balances.accounts.limit` | `50000` | Page size for batching balance lookups across accounts during data-usage queries. |
| `balances.filter_fields` | `null` | Field names allowed in the balance update query filter. |
| `balances.operation` | `null` | Map from operation name to operation class for balance updates. |
| `balances.operation.default` | `null` | Default operation name used when balance update does not specify one. |
| `balances.sid_level` | `false` | Whether balances are tracked per subscriber rather than per account. |
| `balances.update_fields` | `null` | Field names allowed to be set in the balance update record. |
| `balances.updaters` | `null` | Map from filter name to balance updater action class. |
| `balances.updaters.no_upsert` | `array()` | Filter names that should not create a new balance record when none exists. |

## billapi

| Key | Default | Description |
|-----|---------|-------------|
| `billapi.error_base` | `10400` | Base error code offset used by the billapi module for error responses. |
| `billapi.{$collection}.export.mapper` | `[]` | Field mapping used when exporting records of a collection via billapi. |
| `billapi.{$collection}.import.mapper` | `[]` | Field mapping used when importing records of a collection via billapi. |
| `billapi.{$collection}.{$action}` | `[]` | Per-collection per-action settings for billapi operations. |
| `billapi.{$entityName}.duplicate_check` | `[]` | Fields used to detect duplicate revisions of an entity. |
| `billapi.{$module}.{$action}` | `[]` | Per-module per-action settings used by portal actions helper. |
| `billapi.{$this->getCollectionName()}.duplicate_check` | `[]` | Unique fields used to build duplicate-check query on imports. |

## billrun

| Key | Default | Description |
|-----|---------|-------------|
| `billrun` | `(array)` | Base settings array passed to the Billrun_Billrun instance constructor. |
| `billrun.breakdowns` | `(array)` | List of invoice breakdown keys used by the PDF generator to render detail sections. |
| `billrun.changepassword.url` | `"%s://%s/#/Changepassword/%s?sig=%s&t=%s&u=%s"` | URL template used to build the password reset link sent to users. |
| `billrun.charge_not_before` | `'+0 seconds'` | Default relative offset for calculating the earliest charge date when no rule matches. |
| `billrun.charging_day` | `1` | Day of month on which the billing cycle charging runs. |
| `billrun.compute.suggestions.rate_recalculations.enabled` | `false` | Enables generation of rate retroactive recalculation suggestions. |
| `billrun.compute.suggestions.rate_recalculations.grouping.fields` | `[]` | Extra grouping fields used when aggregating lines for rate recalculation suggestions. |
| `billrun.compute.suggestions.rate_recalculations.intervals` | `[0, 15, 30, 45]` | Minute intervals used for splitting time windows in rate recalculation suggestions. |
| `billrun.core.foreign_fields.subscribers.time_fields_mapping` | `(computed)` | Maps subscriber time-related fields to comparison offsets for foreign-data lookups. |
| `billrun.defaultCountryPrefix` | `972` | Default country dialing prefix used for MSISDN normalization. |
| `billrun.due_date` | `[]` | Rule set defining how invoice due dates are calculated based on anchor fields and conditions. |
| `billrun.due_date_interval` | `"+13 days"` | Fallback relative interval used to compute invoice due date when no due-date rule matches. |
| `billrun.email_after_confirmation` | `false` | Whether to send the invoice-ready email after invoice confirmation. |
| `billrun.failed_invoices_path` | `'/tmp'` | Filesystem directory where invoice data is dumped if it exceeds the inline subscriber limit. |
| `billrun.fake_password` | `'password'` | Placeholder string returned in place of stored secrets in API responses. |
| `billrun.filter_fields` | `(array)` | Extra line fields retrieved from the lines collection during cycle aggregation. |
| `billrun.flats.generate_zero_refunds` | `true` | Whether to emit refund records for upfront plan charges that evaluate to zero. |
| `billrun.generate_pdf` | `null` | Flag controlling whether cycle jobs produce PDF invoice files. |
| `billrun.group.id.taxes.enable` | `true` | Whether tax keys are included in invoice line grouping identifiers. |
| `billrun.grouping` | `[]` | Invoice grouping definitions with named groups, conditions and aggregation fields. |
| `billrun.grouping.enabled` | `true` | Master switch enabling invoice line grouping aggregation. |
| `billrun.grouping.fields` | `[]` | Extra fields used as grouping keys when aggregating subscriber invoice lines. |
| `billrun.grouping.max_fields` | `[]` | Fields aggregated using the max operator in invoice grouping. |
| `billrun.grouping.min_fields` | `[]` | Fields aggregated using the min operator in invoice grouping. |
| `billrun.grouping.sum_fields` | `[]` | Fields aggregated using the sum operator in invoice grouping. |
| `billrun.ignore_no_plans_invoices` | `true` | Skips invoicing for accounts whose subscribers have no active plan charges. |
| `billrun.immediate_invoice.min_backdate` | `[]` | Rule defining the earliest allowed backdating timestamp for immediate one-time invoices. |
| `billrun.immediate_invoice.uf` | `[]` | Whitelist of user-defined fields accepted on immediate invoice requests. |
| `billrun.installments.prepone_on_termination` | `false` | Whether to bring future installment charges forward when a subscriber terminates. |
| `billrun.invoice.aggregate.account.added_data` | `[]` | Aggregation pipeline appending additional computed fields to account invoices. |
| `billrun.invoice.aggregate.account.final_data` | `[]` | Aggregation pipeline producing the final configurable data on account invoices. |
| `billrun.invoice.aggregate.pipelines` | `(computed)` | Aggregation pipelines applied when building the subscriber invoice breakdown. |
| `billrun.invoice.aggregate.subscriber.final_data` | `[]` | Aggregation pipeline producing the final breakdown data on subscriber invoices. |
| `billrun.invoicing_date` | `"first day of this month"` | Relative date expression that yields the invoice date for each billing cycle. |
| `billrun.invoicing_day` | `null` | Day of month for invoice generation when multi-day cycle mode is in use. |
| `billrun.linesLimit` | `10000` | Maximum number of lines fetched per query batch during cycle aggregation. |
| `billrun.max_batch_insert_limit` | `500000` | Maximum number of documents inserted into the lines collection in one batch. |
| `billrun.max_subscribers_to_aggregate` | `500` | Threshold above which subscriber usage aggregation is disabled to save memory. |
| `billrun.max_subscribers_to_keep_lines` | `50` | Threshold above which raw lines are dropped from memory for large accounts. |
| `billrun.multi_day_cycle` | `false` | Enables multi-day billing cycle mode supporting per-account invoicing days. |
| `billrun.passthrough_data` | `(array)` | Map of account attribute fields copied through into the billrun document. |
| `billrun.past_balance` | `[]` | Configuration for including a past account balance offset on the current invoice. |
| `billrun.return_url` | `null` | Default return URL used by payment gateway redirect flows. |
| `billrun.runnble_functions` | `(array)` | Whitelist of PHP functions allowed when merging arrays via configurable rule operators. |
| `billrun.save_subs` | `true` | Whether to persist the subscribers array inside the saved invoice document. |
| `billrun.save_to_file_subs_limit` | `10000` | Subscriber count above which oversized invoices are saved to a file instead of MongoDB. |
| `billrun.separate_cross_cycle_charges` | `true` | Whether upfront plan charges spanning multiple cycles are split per-cycle. |
| `billrun.subscriber.sub_revision_fields` | `['services','plans']` | Subscriber dated fields used to derive sub-revision cut dates. |
| `billrun.subscriber.sub_revision_fields_to_copy` | `(array)` | Subscriber fields copied from the parent revision onto each derived sub-revision. |
| `billrun.timezone` | `null` | Tenant timezone used when evaluating charge-day and cycle dates. |

## bills

| Key | Default | Description |
|-----|---------|-------------|
| `bills.switch_links` | `false` | Enables rewiring of payment links when newer invoices were paid before older pending ones. |
| `bills.uf` | `[]` | Whitelist of user-defined fields accepted on bill and payment records. |

## cache

| Key | Default | Description |
|-----|---------|-------------|
| `cache` | `(array)` | Cache backend configuration arguments passed to the cache factory. |

## calcCpu

| Key | Default | Description |
|-----|---------|-------------|
| `calcCpu.dont_auto_generate_invoice` | `FALSE` | Disables automatic invoice/PDF generation after each account aggregation. |
| `calcCpu.forkXmlGeneration` | `0` | Enables forking a child process per account for invoice XML/PDF generation. |
| `calcCpu.forkXmlLimit` | `2` | Maximum concurrent forked child processes used for XML/PDF generation. |
| `calcCpu.remove_duplicates` | `1` | Removes incoming CDR rows whose stamps already exist in lines or archive. |
| `calcCpu.reuse.addedFields` | `(array)` | Extra field names copied from the previous matched line when reusing fields. |
| `calcCpu.reuse.ignoreFields` | `(array)` | Field names excluded when reusing fields from a previous matching line. |
| `calcCpu.reuse.ignoreRecordTypes` | `(array)` | Record types that bypass the field-reuse mechanism for repeat events. |

## CG

| Key | Default | Description |
|-----|---------|-------------|
| `CG.conf.amount` | `100` | Amount sent in CreditGuard recurring debit verification requests. |

## chains

| Key | Default | Description |
|-----|---------|-------------|
| `chains` | `(array)` | List of dispatcher chain plugin identifiers to load at bootstrap. |

## changepassword

| Key | Default | Description |
|-----|---------|-------------|
| `changepassword.email.link_expire` | `'24 hours'` | Lifetime of password reset email links before expiration. |
| `changepassword.email.logo` | `"https://billrun.com/images/logocloudbillrunf1.png"` | Logo URL embedded in the password reset email. |

## charge

| Key | Default | Description |
|-----|---------|-------------|
| `charge.not_before` | `[]` | Rules computing the earliest charge date based on invoice type and anchor field. |

## cli

| Key | Default | Description |
|-----|---------|-------------|
| `cli.actions` | `(array)` | Map of available CLI actions exposed by the CLI controller. |
| `cli.clearcall.urtRange` | `null` | Time range (start/end) used when scanning open calls during cleanup. |

## cliForceUser

| Key | Default | Description |
|-----|---------|-------------|
| `cliForceUser` | `''` | OS user required to execute CLI commands; empty disables the check. |

## collection

| Key | Default | Description |
|-----|---------|-------------|
| `collection.processes` | `[]` | Collection processes defining conditions and steps for accounts in collection. |
| `collection.settings` | `[]` | General collection notifier settings merged into each notifier task. |
| `collection.settings.authentication` | `[]` | Remote authentication parameters used by HTTP collection notifiers. |
| `collection.settings.customers.exempted_from_collection` | `[]` | Account IDs excluded from collection processing. |
| `collection.settings.customers.subject_to_collection` | `[]` | Account IDs explicitly included in collection processing. |
| `collection.settings.min_debt` | `'10'` | Minimum debt amount required for an account to enter collection. |
| `collection.settings.rejection_required.conditions.customers` | `[]` | Conditions identifying customers that require rejection before collection. |
| `collection.settings.run_on_days` | `[]` | Days of the week on which collection steps may be triggered. |
| `collection.settings.run_on_holidays` | `false` | Whether collection steps are allowed to run on holidays. |
| `collection.settings.run_on_hours` | `[]` | Allowed hours of the day for triggering collection steps. |
| `collection.settings.step_ttl_type` | `'days'` | Time unit (days/hours) for collection step time-to-live. |
| `collection.settings.step_ttl_value` | `90` | Time-to-live value beyond which pending collection steps are skipped. |

## collection_steps

| Key | Default | Description |
|-----|---------|-------------|
| `collection_steps` | `(array)` | Configuration of debt collection steps and their settings. |

## config

| Key | Default | Description |
|-----|---------|-------------|
| `config.permissions` | `null` | Per-category permission level overrides for the settings API. |

## config_cache

| Key | Default | Description |
|-----|---------|-------------|
| `config_cache.enabled` | `false` | Enables caching of the merged DB configuration. |
| `config_cache.ttl` | `600` | TTL in seconds for the cached configuration. |

## configuration

| Key | Default | Description |
|-----|---------|-------------|
| `configuration.realtime.mandatory_fields` | `[]` | Required fields validated when saving realtime configuration. |

## constants

| Key | Default | Description |
|-----|---------|-------------|
| `constants.ggsn_file_read_ahead_length` | `null` | Number of bytes read ahead from the GGSN file stream per iteration. |
| `constants.ggsn_header_length` | `null` | Byte length of the GGSN file header block. |
| `constants.ggsn_max_chunklength_length` | `null` | Maximum byte length used when detecting GGSN record chunk size. |
| `constants.ggsn_record_padding` | `null` | Extra byte padding added when advancing past a parsed GGSN ASN record. |
| `constants.handle_multiple_volume` | `TRUE` | Whether the GGSN parser splits records containing multiple volume entries. |

## create_tenant

| Key | Default | Description |
|-----|---------|-------------|
| `create_tenant.db_permissions` | `'readWrite'` | MongoDB role granted to the new tenant database user. |
| `create_tenant.ini.base_config` | `'base.ini'` | Filename of the base ini template used when generating a tenant ini file. |
| `create_tenant.remotes.white_list` | `array()` | Allowed remote IP addresses permitted to invoke create-tenant requests. |

## credit

| Key | Default | Description |
|-----|---------|-------------|
| `credit.failed_credits_file` | `APPLICATION_PATH "failed_credits.json"` | Path of the file where failed credit rows are appended. |
| `credit.fields` | `array()` | Field definitions used to validate incoming credit/refund rows. |

## creditguard

| Key | Default | Description |
|-----|---------|-------------|
| `creditguard` | `(array)` | CreditGuard payment gateway settings including rejection codes and card expiration handling. |

## cron

| Key | Default | Description |
|-----|---------|-------------|
| `cron.log.mail_recipients` | `[]` | Email addresses notified with cron job log output. |
| `cron.log.sms_recipients` | `[]` | Phone numbers notified by SMS with cron job log output. |

## customer

| Key | Default | Description |
|-----|---------|-------------|
| `customer.aggregator.cache.clear_on_start` | `false` | Clears subscriber and account caches when starting a new cycle run. |
| `customer.aggregator.cache.gad.enabled` | `false` | Enables external caching for account (Get Account Details) lookups. |
| `customer.aggregator.cache.gad.prefix` | `''` | Cache key prefix used for cached account lookups. |
| `customer.aggregator.cache.gad.ttl` | `600` | TTL in seconds for cached account lookup results. |
| `customer.aggregator.cache.gba_to_gsd.enabled` | `false` | Reuses account balances result as subscriber details cache source. |
| `customer.aggregator.cache.gsd.enabled` | `false` | Enables external caching for subscriber (Get Subscriber Details) lookups. |
| `customer.aggregator.cache.gsd.prefix` | `''` | Cache key prefix used for cached subscriber lookups. |
| `customer.aggregator.cache.gsd.ttl` | `600` | TTL in seconds for cached subscriber lookup results. |
| `customer.aggregator.charge_included_service` | `TRUE` | Whether plan-included services should still be charged during the cycle. |
| `customer.aggregator.config_enrichment` | `(array)` | Mapping that enriches aggregator config with passthrough subscriber/account fields. |
| `customer.aggregator.options` | `[]` | Extra options attached to customer aggregation result sets. |
| `customer.aggregator.passthrough_data` | `(array)` | Subscriber/account fields preserved as-is through aggregation for discounts. |
| `customer.aggregator.plan_identification_fields` | `[]` | Extra fields used to detect a plan change during plan history aggregation. |
| `customer.aggregator.revision_identification_fields` | `[]` | Extra fields used to group account revisions when aggregating cycle data. |
| `customer.aggregator.service_identification_fields` | `[]` | Extra fields used to identify and merge subscriber services in aggregation. |
| `customer.aggregator.should_fork` | `TRUE` | Whether the cycle action forks a child process per aggregation page. |
| `customer.aggregator.size` | `100` | Number of accounts processed per cycle aggregation page. |
| `customer.aggregator.subscriber.activation_minimum_resolution` | `1` | Minimum time resolution in seconds for subscriber plan activation detection. |
| `customer.aggregator.zero_pages_limit` | `2` | Number of empty pages tolerated before considering the cycle complete. |
| `customer.aggregator.{$type}.passthrough_data` | `[]` | Type-specific subscriber/account passthrough fields appended to the global list. |
| `customer.calculator` | `(array)` | Extra options passed to the customer queue calculator. |
| `customer.calculator.row_enrichment` | `(array)` | Extra subscriber fields the customer calculator may update on a line. |

## customeronetime

| Key | Default | Description |
|-----|---------|-------------|
| `customeronetime.aggregate.invalid_account_functions` | `(array)` | Account passthrough functions disallowed during one-time aggregation. |
| `customeronetime.aggregate.invalid_fields` | `['services']` | Account fields excluded from one-time aggregation passthrough. |
| `customeronetime.aggregator.cache.gba_to_gsd.enabled` | `false` | Whether to cache get-billable-accounts results for get-subscriber-data during cycle. |

## customerPricing

| Key | Default | Description |
|-----|---------|-------------|
| `customerPricing.calculator.serviceFetchSortField` | `'to'` | Service date field used to sort services when fetched during pricing. |
| `customerPricing.calculator.typesWithoutBalance` | `(array)` | Line types priced without consulting balances (e.g. credit, flat, service). |

## cycle

| Key | Default | Description |
|-----|---------|-------------|
| `cycle.allow_premature_run` | `0` | Allow running cycle before its end date (used in non-prod or for any invoicing day). |
| `cycle.processes.interval` | `60` | Sleep interval in seconds between cycle processing iterations. |

## db

| Key | Default | Description |
|-----|---------|-------------|
| `db` | `(array)` | Main database connection options used to instantiate the DB connection. |
| `db.collections` | `(array)` | Mapping of logical collection names to physical Mongo collections. |
| `db.long_queries_timeout` | `10800000` | Cursor timeout in milliseconds for long-running queries such as exports. |
| `db.timeout` | `-1` | Default write concern timeout in milliseconds for DB operations. |

## discounts

| Key | Default | Description |
|-----|---------|-------------|
| `discounts.always_prorated` | `false` | Forces all discounts to be prorated regardless of their individual configuration. |
| `discounts.rounding_rules.inherit_rounding` | `true` | Whether discount lines inherit rounding rules from the eligible subject line. |
| `discounts.service.section_types` | `(array)` | Mapping of invoice section types eligible for subscriber service discounts. |
| `discounts.usage.section_types` | `(array)` | Mapping of invoice section types eligible for usage-based discounts. |
| `discounts.usage.usage_types` | `(computed)` | Usage types eligible for usage discounts; defaults to all configured file types. |

## email_templates

| Key | Default | Description |
|-----|---------|-------------|
| `email_templates.invoice_ready.placeholders` | `[]` | Custom placeholders available when rendering invoice-ready emails. |
| `email_templates.invoice_ready.send_pdf` | `true` | Whether to attach the invoice PDF to invoice-ready emails. |

## encryption

App-level 2-way encryption of database fields, backing the billapi `encrypted` field type (BRCD-4649). Values are encrypted at rest with deterministic, authenticated AES-256-CTR and decrypted on fetch; the deterministic scheme also allows exact-match queries on encrypted fields. Intended for moderately sensitive PII, not cardholder data.

| Key | Default | Description |
|-----|---------|-------------|
| `encryption.key` | `null` | Master key for field encryption, read as the fallback when no environment key is set. Accepts a 64-char hex string, a base64 string decoding to 32 bytes, or a raw 32-byte string (any other value is SHA-256-derived to 32 bytes). Generated automatically on tenant creation and by the BRCD-4649 migration when absent. Storing the key here places it next to the data it protects; prefer the environment variables below in production. |

### Environment variables

These take precedence over `encryption.key` and keep the key out of the database (the stronger posture for at-rest encryption). When either is set, no key is written to the DB config. Resolution order: `BR_ENCRYPTION_KEY_FILE`, then `BR_ENCRYPTION_KEY`, then `encryption.key`.

| Variable | Default | Description |
|----------|---------|-------------|
| `BR_ENCRYPTION_KEY_FILE` | (unset) | Path to a file holding the field-encryption key (contents are trimmed). Highest precedence; preferred for production so the key is not exposed in the process environment. |
| `BR_ENCRYPTION_KEY` | (unset) | The field-encryption key supplied directly as an environment value. Used when `BR_ENCRYPTION_KEY_FILE` is not set. |

**Key rotation:** the key must be stable. Decryption fails open - if the key changes (rotation) without re-encrypting stored data, reads silently return the raw ciphertext (`enc:v1:...`) with only a WARN log, rather than erroring. Rotating the key therefore requires a one-time migration that decrypts every encrypted field with the old key and re-encrypts it with the new one before the old key is removed.

## environment

| Key | Default | Description |
|-----|---------|-------------|
| `environment` | `"prod"` | Current environment name; used to determine production mode. |

## esb

| Key | Default | Description |
|-----|---------|-------------|
| `esb.queue_config` | `[]` | Default Stomp/ESB message broker connection settings (host, port, user, pass). |

## events

| Key | Default | Description |
|-----|---------|-------------|
| `events` | `[]` | Master events configuration loaded by the events manager singleton. |
| `events.settings` | `[]` | Base notifier settings merged with per-notifier overrides. |
| `events.settings.email.global_addresses` | `[]` | Global email recipients added to outgoing event notifications. |
| `events.settings.notify.notify_orphan_time` | `'1 hour'` | Age threshold after which a stalled event notification is reclaimed. |

## export

| Key | Default | Description |
|-----|---------|-------------|
| `export.orphan_wait_time` | `'6 hours'` | Wait period before re-exporting lines whose previous export run was abandoned. |

## export_generators

| Key | Default | Description |
|-----|---------|-------------|
| `export_generators` | `null` | List of configured export generators with their settings. |

## external_parsers_config

| Key | Default | Description |
|-----|---------|-------------|
| `external_parsers_config.ggsn` | `null` | Path to the external INI configuration file for the GGSN parser. |
| `external_parsers_config.tap3` | `null` | Path to the external INI configuration file for the TAP3 parser. |

## file_types

| Key | Default | Description |
|-----|---------|-------------|
| `file_types` | `array()` | Definitions of input file types with parsers, fields and processors. |

## GC

| Key | Default | Description |
|-----|---------|-------------|
| `GC.conf.amount` | `1` | Default Go Credit transaction amount for the payment authorization request. |

## generate

| Key | Default | Description |
|-----|---------|-------------|
| `generate.loadBalanced` | `0` | Enables load-balanced distribution of generator output across nodes. |

## import

| Key | Default | Description |
|-----|---------|-------------|
| `import.max_rows_to_import` | `1000000` | Maximum number of rows allowed in a single import file. |

## invoice_export

| Key | Default | Description |
|-----|---------|-------------|
| `invoice_export.aid_with_detailed_invoices` | `array()` | Account IDs that always receive detailed (itemized) invoices. |
| `invoice_export.datetime_format` | `'d/m/Y H:i:s'` | Date/time format used when rendering invoice line timestamps. |
| `invoice_export.export` | `'files/invoices/'` | Path where exported invoice PDFs are stored for email delivery. |
| `invoice_export.invoice_display_options` | `null` | Display options controlling monthly invoice rendering. |
| `invoice_export.senders` | `[]` | List of remote server configurations for sending exported invoices. |

## lines

| Key | Default | Description |
|-----|---------|-------------|
| `lines.credit.fields` | `array()` | Custom user fields available on credit (uf) lines. |
| `lines.fields` | `array()` | Custom field definitions on usage lines, including foreign entity references. |
| `lines.reference_fields` | `[]` | Line fields tracked for full-calculation reference timestamps. |

## log

| Key | Default | Description |
|-----|---------|-------------|
| `log` | `(array)` | Logger configuration including writers and their parameters. |
| `log.email.writerParams.to` | `null` | Global recipient list for log/notification emails. |
| `log.sms.writerParams.to` | `null` | Global recipient list for log/notification SMS messages. |

## mailer

| Key | Default | Description |
|-----|---------|-------------|
| `mailer.from.address` | `'no-reply@bill.run'` | Default sender email address for outgoing mail. |
| `mailer.from.name` | `'BillRun'` | Default sender display name for outgoing mail. |
| `mailer.transport` | `null` | Zend mail transport configuration (type, host, etc.). |

## notifications_settings

| Key | Default | Description |
|-----|---------|-------------|
| `notifications_settings` | `array()` | Standalone monitoring/notification config for input processors. |

## oauth2

| Key | Default | Description |
|-----|---------|-------------|
| `oauth2` | `array()` | OAuth2 server configuration parameters passed to the OAuth2 server constructor. |

## onetimeinvoice

| Key | Default | Description |
|-----|---------|-------------|
| `onetimeinvoice.invoice_type_config` | `(array)` | Per-subtype configuration (e.g. starting invoice id) for one-time invoices. |

## payment

| Key | Default | Description |
|-----|---------|-------------|
| `payment.fail_page` | `null` | Default cancel URL used for Stripe Checkout sessions when payment fails. |

## payment_gateways

| Key | Default | Description |
|-----|---------|-------------|
| `payment_gateways` | `[]` | List of configured payment gateway integrations and their parameters. |

## PaymentGateways

| Key | Default | Description |
|-----|---------|-------------|
| `PaymentGateways.images` | `(array)` | Map of payment gateway names to logo image URLs. |
| `PaymentGateways.instance.separator` | `'#'` | Delimiter used between gateway name and instance name in identifiers. |
| `PaymentGateways.ok_page` | `"%s://%s/paymentgateways/OkPage?name=%s"` | URL template used to build the success page after gateway redirect. |
| `PaymentGateways.orphan_check_time` | `'2 days'` | Time interval after which pending payments are re-checked for orphan status. |
| `PaymentGateways.payment_method` | `"automatic"` | Fallback payment method label when an invoice has no payment method set. |
| `PaymentGateways.potential` | `(array)` | List of payment gateway instance names allowed for use in the tenant. |
| `PaymentGateways.success_url` | `""` | Default tenant return URL when no specific return URL is provided. |

## payments

| Key | Default | Description |
|-----|---------|-------------|
| `payments.offline.sources` | `null` | Additional allowed source identifiers for offline payments (merged with POS, web). |
| `payments.offline.uf` | `[]` | User-defined fields persisted on offline payment records. |

## plans

| Key | Default | Description |
|-----|---------|-------------|
| `plans.connection_type_default` | `"postpaid"` | Default connection type used for balance queries when none is provided. |
| `plans.lineFields` | `[]` | Plan fields copied onto generated lines. |
| `plans.plan_charge_fields_to_copy.fields` | `(array)` | Plan charge fields copied onto generated charge line entries. |

## plays

| Key | Default | Description |
|-----|---------|-------------|
| `plays` | `[]` | List of plays defined in the system used for product segmentation. |

## plugins

| Key | Default | Description |
|-----|---------|-------------|
| `plugins` | `(array)` | List of plugin definitions to register with the BillRun dispatcher. |

## pricing

| Key | Default | Description |
|-----|---------|-------------|
| `pricing` | `(array)` | Fallback pricing/currency conversion rate definitions. |
| `pricing.currency` | `'USD'` | Default currency code used for invoices and payment gateway charges. |
| `pricing.max_delta_months` | `2` | Maximum months gap before switching from active billrun to runtime billrun. |
| `pricing.months_limit` | `3` | Historical month range considered for pricing, tax and account activeness checks. |

## property_types

| Key | Default | Description |
|-----|---------|-------------|
| `property_types` | `[]` | Available property type definitions used by the units utility. |

## queue

| Key | Default | Description |
|-----|---------|-------------|
| `queue.advancedProperties` | `(array)` | Extra row fields copied into queue records (imsi, msisdn, etc.). |
| `queue.calculator.orphan_wait_time` | `"6 hours"` | Wait period before reclaiming queue rows orphaned by another calculator. |
| `queue.calculators` | `(array)` | Ordered list of calculators that queue lines must pass through. |
| `queue.max_size` | `999999999` | Maximum number of rows allowed in the processing queue. |

## rate

| Key | Default | Description |
|-----|---------|-------------|
| `rate.default_mcc` | `425` | Fallback mobile country code applied when a line has no location MCC. |
| `rate.minLengthTrimPrefixes` | `10` | Minimum number length required before trim prefixes are applied during rating. |
| `rate.trimPrefixes` | `[]` | List of dial prefixes stripped from numbers before rate lookup. |

## rates

| Key | Default | Description |
|-----|---------|-------------|
| `rates.getVolumeByRate.epsilon` | `0.000001` | Convergence threshold for the binary search that finds usage volume by price. |
| `rates.getVolumeByRate.limitLoop` | `50` | Maximum iterations for the binary search that finds usage volume by price. |
| `rates.prepaid_granted.$usageType.cost` | `5` | Fallback prepaid granted cost per usage type when not defined on the rate. |

## read_only_db_pref

| Key | Default | Description |
|-----|---------|-------------|
| `read_only_db_pref` | `null` | MongoDB read preference used for non-critical, read-only queries. |

## realtime

| Key | Default | Description |
|-----|---------|-------------|
| `realtime.granted_code` | `[]` | Map of realtime granted return codes used to build Diameter result codes. |
| `realtime.granted_code.failed_calculator.rate` | `null` | Return code assigned when the rate calculator fails or rate is blocked. |
| `realtime.granted_code.no_available_balances` | `null` | Return code assigned when no prepaid balance is available for the subscriber. |
| `realtime.granted_code.ok` | `0` | Return code that represents a successful realtime authorization. |

## realtime_error_base

| Key | Default | Description |
|-----|---------|-------------|
| `realtime_error_base` | `null` | Base error code offset added when building realtime responder error codes. |

## realtimeevent

| Key | Default | Description |
|-----|---------|-------------|
| `realtimeevent.announcement.call_to_blocked_number` | `null` | Announcement code returned when calling a blocked or unrated number. |
| `realtimeevent.announcement.default_language` | `null` | Default language code for call announcements when subscriber language is unknown. |
| `realtimeevent.announcement.insufficient_credit` | `null` | Announcement code returned when the subscriber has insufficient balance. |
| `realtimeevent.announcement.subscriber_not_found` | `null` | Announcement code returned when the subscriber cannot be identified. |
| `realtimeevent.callReservationTime.default` | `180` | Default call reservation time (seconds, x10 for response) until next check with BillRun. |
| `realtimeevent.callTypes` | `['call', 'video_call']` | Map of call_type input values to internal usage type (e.g. call, video_call). |
| `realtimeevent.clearCause.black_list_number` | `null` | Clear cause code returned when destination number is blacklisted. |
| `realtimeevent.clearCause.inactive_account` | `null` | Clear cause code returned when the subscriber account is inactive. |
| `realtimeevent.clearCause.invalid_called_number` | `null` | Clear cause code returned when the called number has no matching rate. |
| `realtimeevent.clearCause.no_balance` | `null` | Clear cause code returned when subscriber has no available balance. |
| `realtimeevent.data.freeOfChargeRatingGroups` | `[]` | List of Diameter rating groups granted as free-of-charge. |
| `realtimeevent.data.freeOfChargeRatingGroupsDefaultUsagev` | `0` | Granted usage volume applied to free-of-charge rating groups. |
| `realtimeevent.data.maxCurrency` | `[]` | Default max currency cap (cost/period) used when plan does not override it. |
| `realtimeevent.data.maxCurrency.cost` | `null` | Default max currency cost cap exposed to the admin UI. |
| `realtimeevent.data.maxCurrency.period` | `null` | Default max currency period (e.g. monthly) exposed to the admin UI. |
| `realtimeevent.data.quotaDefaultValue` | `10485760` | Default granted data volume (bytes) for subscribers in data slowness mode. |
| `realtimeevent.data.quotaHoldingTime` | `0` | Quota holding time (seconds) returned in the MSCC response. |
| `realtimeevent.data.requestType` | `[]` | Map of data request_type descriptions to numeric request codes. |
| `realtimeevent.data.requestType.FINAL_REQUEST` | `null` | Numeric request_type code identifying a Diameter FINAL_REQUEST. |
| `realtimeevent.data.returnCode.DIAMETER_CREDIT_LIMIT_REACHED` | `-1` | Diameter result code returned when the credit limit has been reached. |
| `realtimeevent.data.returnCode.DIAMETER_END_USER_SERVICE_DENIED` | `-1` | Diameter result code returned when the end-user service is denied. |
| `realtimeevent.data.returnCode.DIAMETER_SUCCESS` | `-1` | Diameter result code returned on a successful authorization. |
| `realtimeevent.data.returnCode.DIAMETER_USER_UNKNOWN` | `-1` | Diameter result code returned when the subscriber is unknown. |
| `realtimeevent.data.slowness` | `[]` | Data slowness configuration including provisioning command and request settings. |
| `realtimeevent.data.slowness.bandwidth_cap` | `[]` | SOC-keyed map of bandwidth cap definitions (speed and SOC code). |
| `realtimeevent.data.validityTime` | `0` | Default validity time (seconds) for granted data quotas in MSCC responses. |
| `realtimeevent.defaultValue` | `0` | Fallback usage volume returned when no per-request-type default is configured. |
| `realtimeevent.incomingCallUsageTypes` | `[]` | Usage types treated as incoming calls (used to pick calling vs called number). |
| `realtimeevent.notification.sms.sendRequestForkUrl` | `''` | URL used to dispatch SMS notification requests asynchronously via fork. |
| `realtimeevent.notifications.dontSendNotification` | `[]` | Balance source references for which notifications should be suppressed. |
| `realtimeevent.requestType` | `[]` | Map of realtime request_type descriptions to numeric request codes. |
| `realtimeevent.requestType.FINAL_REQUEST` | `null` | Numeric request_type code identifying a realtime FINAL_REQUEST. |
| `realtimeevent.requestType.POSTPAY_CHARGE_REQUEST` | `"4"` | Numeric request_type code identifying a postpay charge request. |
| `realtimeevent.responseData.basic` | `[]` | Base list of response fields included in every realtime response. |
| `realtimeevent.responseData.call.$this->responseApiName` | `[]` | Per-API extra response fields for call responders. |
| `realtimeevent.responseData.call.basic` | `[]` | Base response fields included in every call realtime response. |
| `realtimeevent.responseData.data.$this->responseApiName` | `[]` | Per-API extra response fields for data responders. |
| `realtimeevent.responseData.data.basic` | `[]` | Base response fields included in every data realtime response. |
| `realtimeevent.responseData.mms.$this->responseApiName` | `[]` | Per-API extra response fields for MMS responders. |
| `realtimeevent.responseData.mms.basic` | `[]` | Base response fields included in every MMS realtime response. |
| `realtimeevent.responseData.service.$this->responseApiName` | `[]` | Per-API extra response fields for service responders. |
| `realtimeevent.responseData.service.basic` | `[]` | Base response fields included in every service realtime response. |
| `realtimeevent.responseData.sms.$this->responseApiName` | `[]` | Per-API extra response fields for SMS responders. |
| `realtimeevent.responseData.sms.basic` | `[]` | Base response fields included in every SMS realtime response. |
| `realtimeevent.returnCode.call_allowed` | `null` | Return code value returned when a call is authorized. |
| `realtimeevent.returnCode.call_not_allowed` | `null` | Return code value returned when a call is rejected. |

## registration_date

| Key | Default | Description |
|-----|---------|-------------|
| `registration_date` | `null` | Tenant registration date used to bound the oldest billing cycle key. |

## resetlines

| Key | Default | Description |
|-----|---------|-------------|
| `resetlines.archived_lines.batch_size` | `100000` | Batch size when iterating archived/unify lines during rebalance. |
| `resetlines.avoid_repeating_reset` | `false` | Whether to skip rebalance work already covered by another queue entry. |
| `resetlines.failed_sids_file` | `APPLICATION_PATH "/files/failed_resetlines.json"` | File path where SIDs that failed to reset are logged. |
| `resetlines.limit` | `10` | Maximum number of rebalance queue records processed per run. |
| `resetlines.lines.size` | `100000` | Batch size for processing line stamps during account rebalance. |
| `resetlines.lines.update_size` | `'10000'` | Batch size when updating lines with reset data. |
| `resetlines.offset` | `'1 hour'` | Minimum age of rebalance queue records before they are processed. |
| `resetlines.process_time_offset` | `'15 minutes'` | Time offset applied when calculating the rebalance processing window. |
| `resetlines.queue.insert_size` | `'10000'` | Batch size when reinserting queue records during rebalance. |
| `resetlines.queue.removal_size` | `'10000'` | Batch size when removing queue records during rebalance. |
| `resetlines.stamps.size` | `10000` | Batch size for processing stamps during rebalance recovery. |
| `resetlines.stamps_store_in_db.limit` | `100000` | Maximum number of stamps that can be stored on a single rebalance queue document. |
| `resetlines.updated_aids.size` | `10` | Batch size of account IDs processed per rebalance iteration. |

## response

| Key | Default | Description |
|-----|---------|-------------|
| `response.backup` | `null` | Default backup directory used by responders when no override is supplied. |
| `response.export.path` | `APPLICATION_PATH "/export/"` | Local filesystem path where response files are exported. |
| `response.workspace` | `APPLICATION_PATH "/workspace/"` | Default workspace directory used by responders to stage files. |

## saveversion

| Key | Default | Description |
|-----|---------|-------------|
| `saveversion.delimiter` | `"***"` | Delimiter used when serializing entity revisions to disk. |
| `saveversion.export_base_url` | `""` | Base path appended to APPLICATION_PATH for storing entity version exports. |

## Seclibgateway

| Key | Default | Description |
|-----|---------|-------------|
| `Seclibgateway.check_is_numeric` | `false` | Whether SFTP file listings filter out folders with non-numeric names. |

## send_file

| Key | Default | Description |
|-----|---------|-------------|
| `send_file.orphan_time` | `'6 hours'` | Time window after which unsent files are considered orphans for retry. |

## service

| Key | Default | Description |
|-----|---------|-------------|
| `service.failed_credits_file` | `APPLICATION_PATH "failed_credits.json"` | File path for logging failed service credit rows. |

## services

| Key | Default | Description |
|-----|---------|-------------|
| `services.create_fields` | `[]` | List of fields accepted when creating a service via the action manager. |
| `services.fields` | `null` | Field definitions (name, mandatory, type) used for service create/update validation. |
| `services.query_fields` | `[]` | Whitelist of fields allowed when querying services. |

## session

| Key | Default | Description |
|-----|---------|-------------|
| `session.timeout` | `3600` | HTTP session lifetime in seconds. |

## session_id_field

| Key | Default | Description |
|-----|---------|-------------|
| `session_id_field` | `(array)` | Per-line-type session ID field name used to correlate prepaid/realtime sessions. |

## shared_folder

| Key | Default | Description |
|-----|---------|-------------|
| `shared_folder` | `'shared'` | Base directory name under the application path for tenant shared files. |

## shared_secret

| Key | Default | Description |
|-----|---------|-------------|
| `shared_secret` | `null` | List of shared HMAC secrets used to sign and validate API requests. |
| `shared_secret.key` | `null` | Shared secret key used to sign external pay page redirect data. |

## signer

| Key | Default | Description |
|-----|---------|-------------|
| `signer.use` | `'none'` | Signer implementation used to digitally sign generated PDFs. |

## smser

| Key | Default | Description |
|-----|---------|-------------|
| `smser` | `[]` | Default options used to instantiate the SMS sender (Billrun_Sms). |

## subscriber

| Key | Default | Description |
|-----|---------|-------------|
| `subscriber` | `(array)` | General subscriber instance defaults applied when constructing subscriber objects. |
| `subscriber.connection_type_default` | `"postpaid"` | Connection type assigned to new balances when the charging plan does not specify one. |

## subscribers

| Key | Default | Description |
|-----|---------|-------------|
| `subscribers.account` | `array()` | Settings used to instantiate the account (customer) loader. |
| `subscribers.account.cache_gba_to_gsd.enabled` | `false` | Caches getBillableAccounts results for reuse by getSubscribers calls. |
| `subscribers.account.cache_gba_to_gsd.query_fields` | `(computed)` | Identifier field tuples used when caching billable revisions for subscriber/account lookups. |
| `subscribers.account.external.time_format` | `'Y-m-d H:i:s.u'` | Time format sent to the external account API in account queries. |
| `subscribers.account.external_authentication` | `(computed)` | HTTP authentication settings for the external account API. |
| `subscribers.account.external_cache_enabled` | `false` | Enables local caching of external account API responses. |
| `subscribers.account.external_cache_ttl` | `300` | Time-to-live (seconds) for cached external account responses. |
| `subscribers.account.external_url` | `''` | Remote URL of the external account (CRM) service. |
| `subscribers.account.fields` | `array()` | Custom account field definitions (validation, search, display). |
| `subscribers.account.gad_limit` | `false` | Batch size limit for getAccountsData (GAD) calls when paying or collecting. |
| `subscribers.account.timeout` | `(computed)` | HTTP timeout (seconds) for external account API requests. |
| `subscribers.account.type` | `'db'` | Account source backend: db or external (remote API). |
| `subscribers.billable.compatiblity.use_datetime_for_same_day_cycle` | `true` | Uses full datetime format for same-day billing cycle requests for CRM compatibility. |
| `subscribers.billable.external_authentication` | `(computed)` | HTTP authentication settings for the external billable accounts API. |
| `subscribers.billable.single_day_cycle_format` | `'Y-m-d H:i:s'` | Date format sent to the billable API for single-day cycles. |
| `subscribers.billable.timeout` | `(computed)` | HTTP timeout (seconds) for external billable API requests. |
| `subscribers.billable.url` | `''` | Remote URL of the external billable accounts API. |
| `subscribers.external_authentication` | `[]` | Default HTTP authentication used by external subscriber/account/billable APIs. |
| `subscribers.fields` | `array()` | Custom field definitions shared across subscriber and account types. |
| `subscribers.query_fields` | `null` | Field mapping describing which fields are set in subscriber query records. |
| `subscribers.subscriber` | `array()` | Settings used to instantiate the subscriber loader. |
| `subscribers.subscriber.external_authentication` | `(computed)` | HTTP authentication settings for the external subscriber API. |
| `subscribers.subscriber.external_cache_enabled` | `false` | Enables local caching of external subscriber API responses. |
| `subscribers.subscriber.external_cache_ttl` | `300` | Time-to-live (seconds) for cached external subscriber responses. |
| `subscribers.subscriber.external_url` | `''` | Remote URL of the external subscriber service. |
| `subscribers.subscriber.fields` | `array()` | Custom subscriber field definitions (validation, search, display). |
| `subscribers.subscriber.timeout` | `(computed)` | HTTP timeout (seconds) for external subscriber API requests. |
| `subscribers.subscriber.type` | `'db'` | Subscriber source backend: db or external (remote API). |
| `subscribers.timeout` | `600` | Default HTTP timeout (seconds) for any external subscribers API call. |
| `subscribers.types` | `array('account', 'subscriber')` | Allowed subscriber entity types accepted by subscriber action APIs. |

## system

| Key | Default | Description |
|-----|---------|-------------|
| `system.closed_cycle_changes` | `false` | Whether entity changes are allowed during closed billing cycles. |

## tap3

| Key | Default | Description |
|-----|---------|-------------|
| `tap3.config_path` | `null` | Path to the TAP3 INI configuration file used by the parser. |
| `tap3.processor.local_code` | `null` | Local area/place code used to identify local called numbers in TAP3 records. |

## tariff

| Key | Default | Description |
|-----|---------|-------------|
| `tariff` | `array()` | Settings used to instantiate the tariff service. |

## taxation

| Key | Default | Description |
|-----|---------|-------------|
| `taxation` | `(array)` | Root taxation configuration including providers, mappings, and tax-type settings. |
| `taxation.CSI.apply_optional_charges` | `FALSE` | Whether optional (non-pass-through) CSI taxes are added to invoice totals. |
| `taxation.default.key` | `''` | Key of the default tax rate applied when no mapping matches. |
| `taxation.mapping` | `[]` | Filter rules mapping line attributes to tax entities. |
| `taxation.tax_type` | `usage` | Tax engine type identifier (e.g. CSI) selecting the active taxation backend. |
| `taxation.vat` | `0.18` | Fallback VAT rate used when no time-bound VAT entry is defined. |
| `taxation.vat_label` | `'VAT'` | Label displayed for the VAT line item on invoices. |

## teldas

| Key | Default | Description |
|-----|---------|-------------|
| `teldas.access_token.cache_lifetime` | `43200` | Cache lifetime (seconds) for the Teldas API access token. |
| `teldas.initialize.allow_mistake_error` | `0.0001` | Tolerance ratio for missing INA-number records during system initialization. |
| `teldas.initialize.ina_numbers_history.limit` | `"-1 month"` | How far back to retrieve INA-number history during initial sync. |
| `teldas.is_system_initialize` | `false` | Flag indicating whether the Teldas system has completed its initial sync. |
| `teldas.keep_system_up_to_date.missing_period` | `3600` | Time gap (seconds) above which a full missing-revisions resync is triggered. |
| `teldas.last_update_time` | `null` | Timestamp of the last successful Teldas synchronization. |
| `teldas.non_working_days.exclude` | `array()` | Non-working day descriptions to skip when importing Teldas holidays. |

## TemplateTokens

| Key | Default | Description |
|-----|---------|-------------|
| `TemplateTokens` | `null` | Toggle/config map enabling per-class token defaults when constructing template tokens. |

## templateTokens

| Key | Default | Description |
|-----|---------|-------------|
| `templateTokens.enabled` | `[]` | List of enabled template token categories whose replacer classes are loaded. |

## tenant

| Key | Default | Description |
|-----|---------|-------------|
| `tenant.address` | `''` | Company postal address used on invoices and emails. |
| `tenant.email` | `''` | Company email address used as sender and in customer-facing messages. |
| `tenant.name` | `''` | Company name used in invoices, emails, and as sender display name. |
| `tenant.phone` | `''` | Company phone number shown on invoices and communications. |
| `tenant.website` | `''` | Company website URL shown on invoices and communications. |

## unify

| Key | Default | Description |
|-----|---------|-------------|
| `unify` | `(array)` | Global unification configuration used for prepaid input processors. |
| `unify.unification_fields` | `[]` | Base unification field mapping applied to records when merging duplicates. |

## updateValueEqualOldValueMaxRetries

| Key | Default | Description |
|-----|---------|-------------|
| `updateValueEqualOldValueMaxRetries` | `8` | Maximum retries when the optimistic value-equals-old-value update conflicts. |

## usage_types

| Key | Default | Description |
|-----|---------|-------------|
| `usage_types` | `[]` | List of allowed usage type definitions configured for the tenant. |

## usaget

| Key | Default | Description |
|-----|---------|-------------|
| `usaget.unit` | `(array)` | Mapping of usage type to its measurement unit. |

## wkpdf

| Key | Default | Description |
|-----|---------|-------------|
| `wkpdf.exec` | `'wkhtmltopdf'` | Path/name of the wkhtmltopdf executable used to render invoice PDFs. |
| `wkpdf.export` | `(computed)` | Filesystem path where generated invoice PDFs are stored. |

## worker

| Key | Default | Description |
|-----|---------|-------------|
| `worker.concurrent_limit` | `4` | Maximum number of concurrent async worker jobs. |
| `worker.cron.enabled` | `null` | Enable cron-bounded worker iteration loop. |
| `worker.cron.timeout` | `55` | Maximum runtime in seconds for a cron-bounded worker iteration. |
| `worker.enabled` | `false` | Whether the background worker is enabled to process the job queue. |
| `worker.iteration` | `2000000` | Microseconds to sleep between worker job-poll iterations when idle. |
| `worker.job_timeout` | `900` | Per-job execution timeout in seconds. |
| `worker.job_types` | `(array)` | Job type names exposed by the queue API. |
| `worker.resetConfigIteration` | `900` | Interval in seconds between worker reloads of DB configuration. |
| `worker.resetWorkerOnConfigSave` | `0` | Whether to stop the worker when configuration is saved (requires supervisor restart). |

## $collection

| Key | Default | Description |
|-----|---------|-------------|
| `{$collection}.fields` | `[]` | Per-collection field definitions used by the Billapi import and export actions. |

