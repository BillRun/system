/* 
 * Version 56 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treament in the code!
 */

// BRCD-552
db.events.ensureIndex({'creation_time': 1 }, { unique: false , sparse: true, background: true });