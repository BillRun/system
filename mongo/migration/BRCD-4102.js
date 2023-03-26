var cancelBills = db.bills.find({cancel:{$exists:1}});
var bulkUpdate = [];
var maxWriteBatchSize =db.runCommand(
    {
      hello: 1
    }
 )['maxWriteBatchSize'];
print("Starts to update " + cancelBills.toArray().length + " bills!")
for (var i=0; i<cancelBills.toArray().length; i++) {
    var update = { "updateOne" : {
        "filter" : {"_id" : cancelBills[i]['_id']},
        "update" :  {"$set" : {"urt" : cancelBills[i]['_id'].getTimestamp()}}
    }};
    bulkUpdate.push(update);
    if (i!=0 && i%maxWriteBatchSize==0) {
        db.bills.bulkWrite(bulkUpdate);
        print("Updated " + maxWriteBatchSize + " cancellation bills, continue..")
        bulkUpdate = []
    }
}
db.bills.bulkWrite(bulkUpdate);
print("Updated total of " + i + " bills!")