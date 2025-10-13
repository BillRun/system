/* 
 * split_billrun migration script.
 */

// =============================== Helper functions ============================

// Perform specific migrations only once
// Important note: runOnce is guaranteed to run some migration code once per task code only if the whole migration script completes without errors.
function runOnce(lastConfig, taskCode, callback) {
    if (typeof lastConfig.past_migration_tasks === 'undefined') {
        lastConfig['past_migration_tasks'] = [];
    }
    taskCode = taskCode.toUpperCase();
    if (!lastConfig.past_migration_tasks.includes(taskCode)) {
        if (new RegExp(/.*-\d+$/).test(taskCode)) {
            print("running task " + taskCode);
            callback();
            lastConfig.past_migration_tasks.push(taskCode);
        } else {
            print('Illegal task code ' + taskCode);
        }
    } else {
        //        print('task ' + taskCode + ' already applied in this environment');
    }
    return lastConfig;
}

// =============================================================================
var lastConfig = db.config.find().sort({ _id: -1 }).limit(1).pretty().next();
delete lastConfig['_id'];
// =============================================================================

//BRCD-4969: Split billrun docment into billrun_subs and billrun_grouping collections
runOnce(lastConfig, 'BRCD-4969-1', function () {
    db.billrun.find({ "subs": { "$exists": true, "$ne": null } }).forEach(function (doc) {
        var allSubscribersToSave = [];
        var allGroupItemsToSave = [];

        if (doc.subs && Array.isArray(doc.subs) && doc.subs.length > 0) {
            doc.subs.forEach(function (subscriber) {
                if (subscriber.totals && subscriber.totals.grouping && Array.isArray(subscriber.totals.grouping)) {
                    subscriber.totals.grouping.forEach(function (groupItem) {
                        groupItem.sid = subscriber.sid;
                        groupItem.billrun_key = doc.billrun_key;
                        groupItem.aid = doc.aid;
                        allGroupItemsToSave.push(groupItem);
                    });
                    delete subscriber.totals.grouping;
                }
            });
            allSubscribersToSave = doc.subs;
        }

        try {
            if (allSubscribersToSave.length > 0) {
                db.billrun_subs.insertMany(allSubscribersToSave);
                print(`Inserted ${allSubscribersToSave.length} subscribers for billrun _id: ${doc._id}`);
            }

            if (allGroupItemsToSave.length > 0) {
                db.billrun_grouping.insertMany(allGroupItemsToSave);
                print(`Inserted ${allGroupItemsToSave.length} grouping items for billrun _id: ${doc._id}`);
            }

            var updateResult = db.billrun.updateOne(
                { "_id": doc._id },
                { "$unset": { "subs": "" } }
            );

            if (updateResult.modifiedCount === 1) {
                print(`Successfully removed 'subs' array from billrun _id: ${doc._id}`);
            } else {
                print(`Warning: 'subs' array was not removed for billrun _id: ${doc._id}. It might have been empty.`);
            }

        } catch (e) {
            print(`An error occurred for document _id: ${doc._id}. Error: ${e}`);
        }
        print("----------------------------------------------------");
    });
});


db.config.insertOne(lastConfig);
