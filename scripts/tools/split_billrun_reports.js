db.billrun.find({ "subs": { "$exists": true, "$nin": [null, []] } }).forEach(function (doc) {
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
            db.billrun_subs.deleteMany({ "key": doc.billrun_key, "aid": doc.aid });
            db.billrun_subs.insertMany(allSubscribersToSave);
            print(`Inserted ${allSubscribersToSave.length} subscribers for billrun _id: ${doc._id}`);
        }

        if (allGroupItemsToSave.length > 0) {
            db.billrun_grouping.deleteMany({ "billrun_key": doc.billrun_key, "aid": doc.aid });
            db.billrun_grouping.insertMany(allGroupItemsToSave);
            print(`Inserted ${allGroupItemsToSave.length} grouping items for billrun _id: ${doc._id}`);
        }

    } catch (e) {
        print(`An error occurred for document _id: ${doc._id}. Error: ${e}`);
    }
    print("----------------------------------------------------");
});