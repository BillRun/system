// Example: mongo <DB Name> scripts/tools/close_overlapping_accounts_revisions.js

function findDifferentFields(docs) {
    if (!docs.length) return [];

    // Get all unique field names from all documents
    var allFields = new Set();
    docs.forEach(function(doc) {
        Object.keys(doc).forEach(function(field) {
            allFields.add(field);
        });
    });

    // Convert to an array
    allFields = Array.from(allFields);

    // Go over each field and compare the values of each documents
    var differingFields = allFields.filter(function(field) {
        var values = {};
        docs.forEach(function(doc) {
            var value = (doc[field] !== undefined ? JSON.stringify(doc[field]) : "null");
            values[value] = true; //Create an item for each unique value of that field
        });
        return Object.keys(values).length > 1; // If more than one unique value, field is different
    });

    return {aid: docs[0].aid, fields: differingFields, latest_doc_id: docs[0]._id}; //index 0 will be the most recent
}

function adjustOverlapping(doc) {
    var now = ISODate();
    db.subscribers.updateMany({aid: doc.aid, type: "account", to: {$gt: ISODate()}, _id: {$ne: doc.latest_doc_id}}, {$set: {to: now, manual_update: "BRCD-4813"}})
    print("updated overlapping revisions of aid " + doc.aid + " with a new to: " + now);
}

var relevantAids = db.subscribers.aggregate(
    [
        {
            $match: {
                type: "account",
                to: {$gt: ISODate()}
            }
        },
        {
            $group: {
                _id: "$aid",
                count: {$sum: 1}
            }
        },
        {
            $match: {
                count: {$gt: 1}
            }
        }
    ]
).toArray();

relevantAids.forEach(function(account) {
    print("working on aid: " + account._id);
    var docs = db.subscribers.find({aid: account._id, type: "account", to: {$gt: ISODate()}}).sort({"from": -1}).toArray();

    res = findDifferentFields(docs)

    // if (!res.fields.every(elem => ["_id", "from", "to", "in_collection_from", "in_collection", "account_name"].includes(elem))) {
    //     //possible filter option
    // }

    adjustOverlapping(res)
})