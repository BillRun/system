/* 
 * Version 5.7 Idempotent DB migration script goes here.
 * Please try to avoid using migration script and instead make special treatment in the code!
 */

// BRCD-865 - extend postpaid balances period
db.balances.update({},{"$set":{"period":"default","start_period":"default"}}, {multi:1});
