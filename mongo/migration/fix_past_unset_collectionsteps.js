

var accounts = db.collection_steps.aggregate({$match: {"returned_value.success": {$ne: true}, trigger_date: {$lt: ISODate()}}}, {$group: {_id: "$extra_params.aid", count: {$sum: 1}}}, {$match: {count: {$gte: 1}}});

function DaysToSkip(type) {

    switch (type) {
        case "HTTP step 1":
            return 1;
            break;
        case "HTTP step 2":
            return 5;
            break;
        case "HTTP step 3":
            return 10;
            break;
        case "HTTP step 4":
            return 20;
            break;
        case "HTTP step 5":
            return 30;
            break;
        case "HTTP step 6":
            return 50;
            break;
    }
}

function setAlert(stepId, cur_date, type, stepcode) {

    var data = new Date(cur_date.getTime() + type * 24 * 60 * 60000);
    print("set step " + stepcode + " to " + data);

    db.collection_steps.update({"_id": stepId}, {$set: {"trigger_date": data}});

}
accounts.forEach(function (acc) {
    
    var acc_aid = acc["_id"];
    print ("setting steps for account: " +acc_aid);
    var first = true;

    var currnet_acc = db.collection_steps.find({"returned_value.success": {$ne: true}, "extra_params.aid": {$in: [acc_aid]}}).sort({step_code: 1});

    currnet_acc.forEach(function (step) {
        newDate = ISODate();
        var prevDate = new Date(newDate.getTime() + 1 * 24 * 60 * 60000);
        var nextSkip;
        var stepcode = step["step_code"];
        var id = step["_id"];
        if (first === true) {
            nextSkip = 1;
            prevDate = new Date(newDate.getTime());
            first = false;
        } else {
            nextSkip = DaysToSkip(stepcode);
        }
        setAlert(id, prevDate, nextSkip, stepcode);
    });
});

