/* 
 * Version 56 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treament in the code!
 */

db.queue.ensureIndex({'urt': 1 , 'type': 1}, { unique: false , sparse: true, background: true });