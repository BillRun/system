/* 
 * Version 5.8 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */


db.createCollection('prepaidgroups');
db.prepaidgroups.ensureIndex({ 'name':1, 'from': 1, 'to': 1 }, { unique: false, background: true });
db.prepaidgroups.ensureIndex({ 'name':1, 'to': 1 }, { unique: false, sparse: true, background: true });
db.prepaidgroups.ensureIndex({ 'description': 1}, { unique: false, background: true });
