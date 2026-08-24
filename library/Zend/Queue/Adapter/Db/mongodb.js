// create collection and indexes
db.createCollection('jobs_messages');
db.createCollection('jobs_queues');
// index queue_name in message collection
db.jobs_messages.createIndex({'queue_name': 1}, { unique: false, background: true });
db.jobs_messages.createIndex({'handle': 1}, { unique: false, background: true });